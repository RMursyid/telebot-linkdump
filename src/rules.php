<?php
$rules = [
    // Twitter/X: Matches any /status/NUMBER path
    [
        'pattern'     => '#(?:https?://)?(?:www\.)?(?:twitter\.com|x\.com|xcom|twittercom|fxtwitter\.com|fixupx\.com)/.+?/status/(\d+)#i',
        'replacement' => 'https://fxtwitter.com/i/status/$1'
    ],
    // Instagram: Converts /p/ or /reel/ to oginstagram, capturing img_index if present
    [
        'pattern' => '#(?:https?://)?(?:[a-z0-9-]+\.)?[a-z0-9]*stagram[a-z0-9]*\.com/(?:p|reel|reels)/([A-Za-z0-9_-]+)[^\s]*#i',
        'callback' => function ($url) {
            if (!preg_match('#/(?:p|reel|reels)/([A-Za-z0-9_-]+)#i', $url, $m)) {
                return null;
            }
            $code = $m[1];

            // Extract img_index parameter if present in query string
            if (preg_match('/[?&]img_index=(\d+)/i', $url, $im)) {
                return "https://www.oginstagram.com/p/{$code}/{$im[1]}";
            }

            return "https://www.oginstagram.com/p/{$code}/";
        }
    ],
    // Pixiv: Matches any /artworks/NUMBER path
    [
        'pattern'     => '#https?://(?:[a-z0-9-]+\.)?(?:pixiv\.net|phixiv\.net)/(?:[a-z]{2}/)?artworks/(\d+(?:/\d+)?)#i',
        'replacement' => 'https://phixiv.net/artworks/$1'
    ],
    // Reddit: Matches /r/subreddit/comments/post_id
    [
        'pattern'     => '#/r/([a-zA-Z0-9_]+)/comments/([a-zA-Z0-9]+)#i',
        'replacement' => 'https://vxreddit.com/r/$1/comments/$2'
    ],
    // TikTok -> Matches /@user/video/NUMBER
    [
        'pattern'     => '#(?:https?://)?(?:www\.)?tiktok\.com/@([a-zA-Z0-9_\.]+)/video/(\d+)#i',
        'replacement' => 'https://vxtiktok.com/@$1/video/$2'
    ],
];
