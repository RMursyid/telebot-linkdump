<?php
define('WEBHOOK_URL', 'https://yourdomain.com/bot.php');
$botToken        = "0123456789:ABCABCABCABCABCA_abcABCabcABCabcABC"; // YOUR_TELEGRAM_BOT_TOKEN_HERE
$apiUrl          = "https://api.telegram.org/bot" . $botToken;
$linkDumpTopicId = null; // General topic (no thread ID)
$debugTopicId    = 0; // Debug topic ID for instant debug message
