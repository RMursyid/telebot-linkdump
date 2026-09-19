<?php
// 1. Trap all output from the start so stray whitespace in includes can't break headers
ob_start();

// 2. Read incoming Telegram payload
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// 3. Load config and helper scripts
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/telegram.php';
$rules = require __DIR__ . '/rules.php';

// Verification Log
if (!empty($update)) {
    logMessage("Webhook received update ID: " . ($update['update_id'] ?? 'unknown'));
}

// 4. Wipe out any trapped whitespace or BOM output from the included files
ob_clean();

// 5. Send clean 200 OK headers to Telegram immediately
ignore_user_abort(true);
http_response_code(200);
header('Content-Type: text/html');
header('Content-Length: 0');
header('Connection: close');
header('X-Accel-Buffering: no');

// 6. Disconnect Telegram so it stops waiting
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 7. Process the message in the background
if (isset($update["message"])) {
    $message   = $update["message"];
    $chatId    = $message["chat"]["id"];
    $messageId = $message["message_id"];
    $rawText   = trim($message["text"] ?? '');
    $threadId  = $message["message_thread_id"] ?? null;

    // COMMAND: /ping
    $command = explode('@', explode(' ', $rawText)[0])[0];
    if ($command === '/ping') {
        $actionParams = ['chat_id' => $chatId, 'action' => 'typing'];
        if ($threadId !== null) $actionParams['message_thread_id'] = $threadId;
        telegramRequest($apiUrl . "/sendChatAction", $actionParams, $apiUrl, $chatId, $debugTopicId);

        $services = [
            'FxTwitter'   => 'https://fxtwitter.com',
            'phixiv'      => 'https://phixiv.net',
            'vxReddit'    => 'https://vxreddit.com',
            'ogInstagram' => 'https://oginstagram.com',
            'vxTikTok'    => 'https://vxtiktok.com'
        ];

        $report = "📡 **Link Fixer Health Status**\n\n";
        foreach ($services as $name => $url) {
            $status     = checkServiceStatus($url);
            $paddedName = str_pad($name, 12, ' ');
            $report    .= "• `$paddedName` {$status}\n";
        }

        $pingParams = [
            'chat_id'    => $chatId,
            'text'       => $report,
            'parse_mode' => 'Markdown'
        ];
        if ($threadId !== null) $pingParams['message_thread_id'] = $threadId;

        telegramRequest($apiUrl . "/sendMessage", $pingParams, $apiUrl, $chatId, $debugTopicId);
        exit();
    }

    // COMMAND: /reset
    if ($command === '/reset') {
        telegramRequest($apiUrl . "/deleteWebhook", ['drop_pending_updates' => true], $apiUrl, $chatId, $debugTopicId);
        telegramRequest($apiUrl . "/setWebhook", ['url' => WEBHOOK_URL], $apiUrl, $chatId, $debugTopicId);

        $infoResponse = telegramRequest($apiUrl . "/getWebhookInfo", [], $apiUrl, $chatId, $debugTopicId);
        $info         = $infoResponse['result'] ?? [];

        $report  = "⚡ **Webhook Reset Complete**\n\n";
        $report .= "📊 **Current Webhook Info:**\n";
        $report .= "• **URL:** `" . ($info['url'] ?? 'Not Set') . "`\n";
        $report .= "• **IP Address:** `" . ($info['ip_address'] ?? 'N/A') . "`\n";
        $report .= "• **Pending Updates:** `" . ($info['pending_update_count'] ?? 0) . "`\n";

        $resetParams = [
            'chat_id'    => $chatId,
            'text'       => $report,
            'parse_mode' => 'Markdown'
        ];
        if ($threadId !== null) $resetParams['message_thread_id'] = $threadId;

        telegramRequest($apiUrl . "/sendMessage", $resetParams, $apiUrl, $chatId, $debugTopicId);
        exit();
    }

    // Unwrap Embedded Direct Link (%2F encoded links)
    if (preg_match('/(https?%3A%2F%2F[^\s&"\'<>]+)/i', $rawText, $matches)) {
        $unwrappedUrl = urldecode($matches[1]);
        if (preg_match('/https?%3A%2F%2F/i', $unwrappedUrl)) {
            $unwrappedUrl = urldecode($unwrappedUrl);
        }
        $rawText = $unwrappedUrl;
    }

    // Normalize obfuscations
    $cleanedText = str_replace(
        ['(.)', '[.]', '{.}', '(/)', '[/]', '{/}', '*', '@', 'http s://', 'https s://'],
        ['.',   '.',   '.',   '/',   '/',   '/',   '.', '/', 'https://', 'https://'],
        $rawText
    );

    $cleanedText = preg_replace([
        '#\b[a-z]*https?://#i',                          // fix prefix
        '#/{3,}#',                                       // fix slashes
        '#\b([a-z0-9-]+)\s*\.\s*([a-z]{2,})\b#i'         // fix space
    ], [
        'https://',
        '//',
        '$1.$2'
    ], $cleanedText);

    // Process matching links against rules array
    $convertedLinks = [];
    foreach ($rules as $index => $rule) {
        if (empty($rule['pattern'])) {
            logMessage("Error: Rule index {$index} has no pattern defined.");
            continue;
        }

        $matchResult = @preg_match_all($rule['pattern'], $cleanedText, $matches);
        if ($matchResult === false) {
            logMessage("Regex syntax error in rule index {$index}: " . $rule['pattern']);
            continue;
        }

        if ($matchResult) {
            foreach ($matches[0] as $matchedUrl) {
                if (isset($rule['callback']) && is_callable($rule['callback'])) {
                    $res = $rule['callback']($matchedUrl);
                    if ($res) $convertedLinks[] = $res;
                } else {
                    $convertedLinks[] = preg_replace($rule['pattern'], $rule['replacement'], $matchedUrl);
                }
            }
        }
    }
    $convertedLinks = array_values(array_filter(array_unique($convertedLinks)));

    // Send converted links
    if (!empty($convertedLinks)) {
        // Send typing status
        $actionParams = ['chat_id' => $chatId, 'action' => 'typing'];
        if ($threadId !== null) $actionParams['message_thread_id'] = $threadId;
        telegramRequest($apiUrl . "/sendChatAction", $actionParams, $apiUrl, $chatId, $debugTopicId);

        foreach ($convertedLinks as $res) {
            if (is_array($res) && isset($res['type']) && $res['type'] === 'photo') {
                // Send native image via sendPhoto
                $sendParams = [
                    'chat_id' => $chatId,
                    'photo'   => $res['image'],
                    'caption' => $res['caption']
                ];
                if ($threadId !== null) $sendParams['message_thread_id'] = $threadId;

                $response = telegramRequest($apiUrl . "/sendPhoto", $sendParams, $apiUrl, $chatId, $debugTopicId);
            } else {
                // Send text link replacement via sendMessage
                $textOutput = is_array($res) ? ($res['caption'] ?? '') : $res;
                $sendParams = [
                    'chat_id'              => $chatId,
                    'text'                 => $textOutput,
                    'parse_mode'           => 'HTML',
                    'link_preview_options' => json_encode(['show_above_text' => true])
                ];
                if ($threadId !== null) $sendParams['message_thread_id'] = $threadId;

                $response = telegramRequest($apiUrl . "/sendMessage", $sendParams, $apiUrl, $chatId, $debugTopicId);
            }

            logMessage("Telegram API Response: " . json_encode($response));
        }

        // Delete original post in Link Dump topic
        if ($threadId == $linkDumpTopicId) {
            telegramRequest($apiUrl . "/deleteMessage", [
                'chat_id'    => $chatId,
                'message_id' => $messageId
            ], $apiUrl, $chatId, $debugTopicId);
        }
    } else {
        logMessage("No matching rules found for text: " . $rawText);
    }
}
