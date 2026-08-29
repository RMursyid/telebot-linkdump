<?php
function logError($response, $httpCode, $apiUrl = null, $chatId = null, $debugTopicId = null) {
    // 1. Log to local text file in the logs/ directory
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/bot.log', date('[Y-m-d H:i:s] ') . "API Error ({$httpCode}): " . $response . "\n", FILE_APPEND);

    // 2. Route error alert to Telegram Debug Topic
    if ($apiUrl && $chatId && $debugTopicId) {
        $errData = json_decode($response, true);
        $errDesc = $errData['description'] ?? 'Unknown Error';

        $debugParams = [
            'chat_id'           => $chatId,
            'message_thread_id' => $debugTopicId,
            'text'              => "⚠️ **Bot API Error ({$httpCode})**\n`{$errDesc}`",
            'parse_mode'        => 'Markdown'
        ];

        $chErr = curl_init($apiUrl . "/sendMessage");
        curl_setopt($chErr, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chErr, CURLOPT_POST, true);
        curl_setopt($chErr, CURLOPT_POSTFIELDS, $debugParams);
        curl_exec($chErr);
        curl_close($chErr);
    }
}
