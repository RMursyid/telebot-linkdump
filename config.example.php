<?php
$botToken        = "0123456789:ABCABCABCABCABCA_abcABCabcABCabcABC"; // YOUR_TELEGRAM_BOT_TOKEN_HERE
$apiUrl          = "https://api.telegram.org/bot" . $botToken;
define('WEBHOOK_URL', 'https://yourdomain.com/bot.php');
define('GELBOORU_USER_ID', 'YOUR_USER_ID_HERE');
define('GELBOORU_API_KEY', 'YOUR_API_KEY_HERE');
$linkDumpTopicId = null; // General topic (no thread ID)
$debugTopicId    = 0; // Debug topic ID for instant debug message
