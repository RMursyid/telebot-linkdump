<?php
$rules = [
    // Twitter/X: Matches any /status/NUMBER path
    [
        'pattern'     => '#/status/(\d+)#i',
        'replacement' => 'https://fxtwitter.com/i/status/$1'
    ],
    // Instagram: Matches any /p/CODE or /reel/CODE path (converts reels to /p/ and strips tracking)
    [
        'pattern'     => '/(?:https?:\/\/)?(?:[a-z0-9-]+\.)?[a-z0-9]*stagram[a-z0-9]*\.com\/(?:p|reel|reels)\/([A-Za-z0-9_-]+)\/?[^\s]*/i',
        'replacement' => 'https://www.oginstagram.com/p/$1/'
    ],
    // Pixiv: Matches any /artworks/NUMBER path
    [
        'pattern'     => '/https?:\/\/(?:[a-z0-9-]+\.)?(?:pixiv\.net|phixiv\.net)\/(?:[a-z]{2}\/)?artworks\/(\d+(?:\/\d+)?)(?:\?[^\s]*)?/i',
        'replacement' => 'https://phixiv.net/artworks/$1'
    ],
    // Reddit: Matches /r/subreddit/comments/post_id
    [
        'pattern'     => '#/r/([a-zA-Z0-9_]+)/comments/([a-zA-Z0-9]+)#i',
        'replacement' => 'https://vxreddit.com/r/$1/comments/$2'
    ],
    // TikTok -> vxtiktok
    [
        'pattern'     => '#(?:https?://)?(?:www\.)?tiktok\.com/@([a-zA-Z0-9_\.]+)/video/(\d+)#i',
        'replacement' => 'https://vxtiktok.com/@$1/video/$2'
    ],
];
