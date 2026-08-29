<?php
require_once __DIR__ . '/logger.php';

function telegramRequest($url, $params, $apiUrl = null, $chatId = null, $debugTopicId = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Max 5 seconds to connect
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);        // Max 10 seconds total execution
    $response = curl_exec($ch);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        logError($response, $httpCode, $apiUrl, $chatId, $debugTopicId);
    }
    
    curl_close($ch);
    return json_decode($response, true);
}

function checkServiceStatus($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY          => true, // HEAD request only (fast)
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 3,    // Max 3 sec limit
        CURLOPT_CONNECTTIMEOUT  => 3,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_USERAGENT       => 'Mozilla/5.0'
    ]);
    
    $startTime = microtime(true);
    curl_exec($ch);
    $latency  = round((microtime(true) - $startTime) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 400) ? "🟢 {$latency}ms" : "🔴 Offline ({$httpCode})";
}