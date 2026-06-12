<?php

function fetchReddit($subreddit) {

    $url = "https://www.reddit.com/r/$subreddit/new.json?limit=25";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            "User-Agent: Mozilla/5.0 (compatible; LeadBot/1.0)"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch);
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    return json_decode($response, true);
}

function timeAgo($utc) {
    $diff = time() - $utc;

    if ($diff < 3600) return floor($diff / 60) . " minutes ago";
    if ($diff < 86400) return floor($diff / 3600) . " hours ago";
    return floor($diff / 86400) . " days ago";
}

function isRelevant($title, $text) {

    $keywords = [
        "looking for",
        "hire",
        "hiring",
        "need developer",
        "full stack",
        "mvp",
        "build for me"
    ];

    $content = strtolower($title . " " . $text);

    foreach ($keywords as $k) {
        if (strpos($content, $k) !== false) return true;
    }

    return false;
}

function processSub($sub) {

    echo "\n========= r/$sub =========\n";

    $data = fetchReddit($sub);

    if (!$data) {
        echo "Failed to fetch data\n";
        return;
    }

    $posts = $data['data']['children'];

    foreach ($posts as $p) {

        $post = $p['data'];

        // last 24 hours filter
        if ((time() - $post['created_utc']) > 86400) continue;

        // relevance filter
        if (!isRelevant($post['title'], $post['selftext'])) continue;

        echo "Title: " . $post['title'] . "\n";
        echo "Time: " . timeAgo($post['created_utc']) . "\n";
        echo "Link: https://reddit.com" . $post['permalink'] . "\n";
        echo "-----------------------------\n";
    }
}

$subs = ["forhire", "startups", "Entrepreneur", "SaaS"];

foreach ($subs as $s) {
    processSub($s);
}