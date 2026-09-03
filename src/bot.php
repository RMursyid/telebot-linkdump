<?php
// 1. Trap all output from the start so stray whitespace in includes can't break headers
ob_start();

// 2. Read incoming Telegram payload
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// 3. Load config and helper scripts
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/includes/logger.php';

// 4. Wipe out any trapped whitespace or BOM output from the included files
ob_clean();

// 5. Send clean 200 OK headers to Telegram immediately
ignore_user_abort(true);
http_response_code(200);
header('Content-Type: text/html');
header('Content-Length: 0');
header('Connection: close');
header('X-Accel-Buffering: no'); // Prevents LiteSpeed/Nginx proxies from holding the connection open

// 6. Disconnect Telegram so it stops waiting
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 7. Process the message in the background
if (isset($update["message"])) {
    $message = $update["message"];
    $chatId = $message["chat"]["id"];
    $messageId = $message["message_id"];
    $rawText = trim($message["text"] ?? '');
    $threadId = $message["message_thread_id"] ?? null;

    $sender = $message["from"]["first_name"] ?? 'Someone';
    if (isset($message["from"]["username"])) {
        $sender = "@" . $message["from"]["username"];
    }

    // Extract base command (strips @twxddbot and extra arguments)
    $command = explode(' ', $rawText)[0];
    $command = explode('@', $command)[0];
    // COMMAND: /ping (Health Check Link Fixers)
    if ($command === '/ping') {
        // Send "typing..." when processing ping
        $actionParams = [
            'chat_id' => $chatId,
            'action'  => 'typing'
        ];
        if ($threadId !== null) {
            $actionParams['message_thread_id'] = $threadId;
        }
        telegramRequest($apiUrl . "/sendChatAction", $actionParams, $apiUrl, $chatId, $debugTopicId);

        $services = [
            'FxTwitter'   => 'https://fxtwitter.com',
            'phixiv'      => 'https://phixiv.net',
            'vxReddit'    => 'https://vxreddit.com',
            'ogInstagram' => 'https://oginstagram.com',
            'vxTikTok'    => 'https://vxtiktok.com'
        ];

        $report = "ðŸ“¡ **Link Fixer Health Status**\n\n";
        foreach ($services as $name => $url) {
            $status  = checkServiceStatus($url);
            // Pad the name to 12 characters inside monospaced backticks
            $paddedName = str_pad($name, 12, ' ');
            $report    .= "â€¢ `$paddedName` {$status}\n";
        }

        telegramRequest($apiUrl . "/sendMessage", [
            'chat_id'           => $chatId,
            'message_thread_id' => $debugTopicId,
            'text'              => $report,
            'parse_mode'        => 'Markdown'
        ], $apiUrl, $chatId, $debugTopicId);
        exit();
    }

    // COMMAND: /reset (Flush Webhook Backlog & Fetch Webhook Info)
    if ($command === '/reset') {
        $webhookUrl = WEBHOOK_URL; // for a reset command on telegram bot incase theres an error

        // 1. Drop pending updates and re-bind webhook
        telegramRequest($apiUrl . "/deleteWebhook", ['drop_pending_updates' => true], $apiUrl, $chatId, $debugTopicId);
        telegramRequest($apiUrl . "/setWebhook", ['url' => $webhookUrl], $apiUrl, $chatId, $debugTopicId);

        // 2. Retrieve current webhook details from Telegram
        $infoResponse = telegramRequest($apiUrl . "/getWebhookInfo", [], $apiUrl, $chatId, $debugTopicId);
        $info = $infoResponse['result'] ?? [];

        $url           = $info['url'] ?? 'Not Set';
        $pendingCount  = $info['pending_update_count'] ?? 0;
        $ip            = $info['ip_address'] ?? 'N/A';
        $lastErrorMsg  = $info['last_error_message'] ?? 'None';
        $lastErrorDate = isset($info['last_error_date']) ? date('Y-m-d H:i:s T', $info['last_error_date']) : 'N/A';

        // 3. Build formatted status message
        $report  = "âš¡ **Webhook Reset Complete**\n\n";
        $report .= "ðŸ“Š **Current Webhook Info:**\n";
        $report .= "â€¢ **URL:** `{$url}`\n";
        $report .= "â€¢ **IP Address:** `{$ip}`\n";
        $report .= "â€¢ **Pending Updates:** `{$pendingCount}`\n";
        $report .= "â€¢ **Last Error Date:** `{$lastErrorDate}`\n";
        $report .= "â€¢ **Last Error Message:** `{$lastErrorMsg}`";

        telegramRequest($apiUrl . "/sendMessage", [
            'chat_id'           => $chatId,
            'message_thread_id' => $debugTopicId,
            'text'              => $report,
            'parse_mode'        => 'Markdown'
        ], $apiUrl, $chatId, $debugTopicId);
        exit();
    }

    // Unwrap Embedded Direct Link (%2F encoded links)[cite: 4]
    if (preg_match('/(https?%3A%2F%2F[^\s&"\'<>]+)/i', $rawText, $matches)) {
        $unwrappedUrl = urldecode($matches[1]);
        if (preg_match('/https?%3A%2F%2F/i', $unwrappedUrl)) {
            $unwrappedUrl = urldecode($unwrappedUrl);
        }
        $rawText = $unwrappedUrl;
    }

    // Normalize obfuscations[cite: 4]
    $cleanedText = str_replace(
        ['(.)', '[.]', '{.}', '(/)', '[/]', '{/}'],
        ['.',   '.',   '.',   '/',   '/',   '/'],
        $rawText
    );
    $cleanedText = preg_replace('#/{3,}#', '//', $cleanedText);

    // Process matching links against rules array
    $convertedLinks = [];
    foreach ($rules as $rule) {
        if (preg_match_all($rule['pattern'], $cleanedText, $matches)) {
            foreach ($matches[0] as $matchedUrl) {
                if (isset($rule['callback']) && is_callable($rule['callback'])) {
                    $convertedLinks[] = $rule['callback']($matchedUrl);
                } else {
                    $convertedLinks[] = preg_replace($rule['pattern'], $rule['replacement'], $matchedUrl);
                }
            }
        }
    }
    $convertedLinks = array_values(array_filter(array_unique($convertedLinks)));

    // Send converted links[cite: 4]
    if (!empty($convertedLinks)) {
        // Send typing status[cite: 4]
        $actionParams = [
            'chat_id' => $chatId,
            'action'  => 'typing'
        ];
        if ($threadId !== null) {
            $actionParams['message_thread_id'] = $threadId;
        }
        telegramRequest($apiUrl . "/sendChatAction", $actionParams, $apiUrl, $chatId, $debugTopicId);

        // Build and send link output[cite: 4]
        $linkList = implode("\n", $convertedLinks);
        $sendParams = [
            'chat_id'              => $chatId,
            'text'                 => $linkList,
            'link_preview_options' => json_encode([
                'show_above_text' => true
            ])
        ];
        if ($threadId !== null) {
            $sendParams['message_thread_id'] = $threadId;
        }
        telegramRequest($apiUrl . "/sendMessage", $sendParams, $apiUrl, $chatId, $debugTopicId);

        // Delete original post in Link Dump topic
        if ($threadId == $linkDumpTopicId) {
            telegramRequest($apiUrl . "/deleteMessage", [
                'chat_id'    => $chatId,
                'message_id' => $messageId
            ], $apiUrl, $chatId, $debugTopicId);
        }
    }
}
