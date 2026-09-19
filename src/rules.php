<?php
$rules = [
    // Twitter / X
    [
        'pattern'     => '#(?:https?://)?(?:www\.)?(?:twitter\.com|x\.com|xcom|twittercom|fxtwitter\.com|fixupx\.com)/.+?/status/(\d+)#i',
        'replacement' => 'https://fxtwitter.com/i/status/$1'
    ],

    // Instagram
    [
        'pattern'  => '#https?://(?:[a-z0-9-]+\.)?[a-z0-9]*stagram[a-z0-9]*\.com/(?:p|reel|reels)/([A-Za-z0-9_-]+)[^\s]*#i',
        'callback' => function ($url) {
            if (!preg_match('#/(?:p|reel|reels)/([A-Za-z0-9_-]+)#i', $url, $m)) {
                return null;
            }
            $code = $m[1];
            
            // If img_index exists, convert from 0-based to 1-based index (+1)
            if (preg_match('/[?&]img_index=(\d+)/i', $url, $im)) {
                $humanIndex = (int)$im[1] + 1;
                return "https://www.oginstagram.com/p/{$code}/{$humanIndex}";
            }
            
            // Return as-is without img_index path if parameter isn't present
            return "https://www.oginstagram.com/p/{$code}/";
        }
    ],

    // Pixiv
    [
        'pattern'  => '~(?:https?://)?(?:[a-z0-9-]+\.)?(?:pixiv\.net|phixiv\.net)/(?:(?:[a-z]{2}/)?artworks/|member_illust\.php\?[^?\s]*\billust_id=)(\d+)(?:/(\d+))?~i',
        'callback' => function ($url) {
            if (!preg_match('~(?:artworks/|illust_id=)(\d+)(?:/(\d+))?~i', $url, $m)) {
                return null;
            }
            $artId = $m[1];
            $page  = !empty($m[2]) ? $m[2] : '1';
            
            return "https://phixiv.net/artworks/{$artId}/{$page}";
        }
    ],

    // Gelbooru Simple Matcher
    [
        'pattern'  => '/gelbooru\.com\/index\.php\?[^#\s]*\bid=(\d+)/i',
        'callback' => function ($url) {
            if (!preg_match('/[?&]id=(\d+)/i', $url, $m)) {
                return null;
            }
            $postId = (int)$m[1];

            $queryParams = [
                'page'    => 'dapi',
                's'       => 'post',
                'q'       => 'index',
                'json'    => 1,
                'id'      => $postId,
                'api_key' => trim(GELBOORU_API_KEY),
                'user_id' => trim(GELBOORU_USER_ID)
            ];

            $apiUrl = "https://gelbooru.com/index.php?" . http_build_query($queryParams);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json, text/javascript, */*; q=0.01',
                    'Accept-Language: en-US,en;q=0.9',
                    'Referer: https://gelbooru.com/'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || $response === false) {
                logMessage("Gelbooru HTTP {$httpCode} Error: {$curlErr}");
                return null;
            }

            $data = json_decode($response, true);
            $post = $data['post'][0] ?? $data[0] ?? null;
            
            if (!empty($post['file_url'])) {
                return [
                    'type'     => 'photo',
                    'image'    => $post['file_url'],
                    'caption'  => 'https://gelbooru.com/index.php?page=post&s=view&id=' . $postId
                ];
            }
            return null;
        }
    ],

    // Reddit
    [
        'pattern'     => '#https?://(?:www\.)?reddit\.com/r/([a-zA-Z0-9_]+)/comments/([a-zA-Z0-9]+)#i',
        'replacement' => 'https://vxreddit.com/r/$1/comments/$2'
    ],

    // TikTok
    [
        'pattern'     => '#https?://(?:www\.)?tiktok\.com/@([a-zA-Z0-9_\.]+)/video/(\d+)#i',
        'replacement' => 'https://vxtiktok.com/@$1/video/$2'
    ]
];

return $rules;
