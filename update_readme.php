<?php

// 1. Configuration
$githubToken = getenv('BURIROR_GH_TOKEN'); // Set this in GitHub Secrets
$readmePath = __DIR__ . '/README.md';

// 2. Fetch recent releases (Public repos only)
function fetchReleases($token) {
    $query = '{
        viewer {
            repositories(first: 10, privacy: PUBLIC, orderBy: {field: UPDATED_AT, direction: DESC}) {
                nodes {
                    name
                    description
                    url
                    updatedAt
                }
            }
        }
    }';

    $ch = curl_init('https://api.github.com/graphql');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'User-Agent: BuriroDev-READMEScript'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    $repos = $data['data']['viewer']['repositories']['nodes'] ?? [];
    $output = [];
    foreach ($repos as $repo) {
        $output[] = "* [{$repo['name']}]({$repo['url']}) - Updated: " . date('Y-m-d', strtotime($repo['updatedAt']));
    }
    return implode("\n", $output) ?: "*No public repositories updated recently.*";
}

// 3. Fetch Dev.to blog posts
function fetchBlogPosts() {
    $feed = simplexml_load_file('https://dev.to/feed/burirodev');
    if (!$feed) return "*No blog posts fetched.*";

    $items = [];
    $count = 0;
    foreach ($feed->channel->item as $item) {
        if ($count++ >= 5) break;
        $title = (string) $item->title;
        $link = (string) $item->link;
        $pubDate = date('Y-m-d', strtotime((string) $item->pubDate));
        $items[] = "* [{$title}]({$link}) - {$pubDate}";
    }
    return implode("\n", $items) ?: "*No blog posts yet.*";
}

// 4. Fetch TILs (from a public `til` repo if you create one)
function fetchTILs() {
    $url = 'https://raw.githubusercontent.com/BuriroDev/burirodev-til/main/tils.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BuriroDev-READMEScript');
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return "*Failed to fetch TILs. Check the repo.*";

    $tils = json_decode($response, true);
    if (!is_array($tils) || count($tils) === 0) return "*No TILs recorded yet.*";

    // Sort by date (newest first)
    usort($tils, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Take latest 5
    $tils = array_slice($tils, 0, 5);
    $output = [];
    foreach ($tils as $til) {
        $output[] = "* [{$til['title']}]({$til['url']}) - {$til['date']}";
    }
    return implode("\n", $output);
}

// 5. Replace chunks in README
function replaceChunk($content, $marker, $chunk) {
    $pattern = "/<!-- {$marker} starts -->.*?<!-- {$marker} ends -->/s";
    $replacement = "<!-- {$marker} starts -->\n" . $chunk . "\n<!-- {$marker} ends -->";
    return preg_replace($pattern, $replacement, $content);
}

// 6. Execute
$readme = file_get_contents($readmePath);
if (!$readme) die("README.md not found!\n");

$readme = replaceChunk($readme, 'recent_releases', fetchReleases($githubToken));
$readme = replaceChunk($readme, 'blog', fetchBlogPosts());
$readme = replaceChunk($readme, 'til', fetchTILs());

file_put_contents($readmePath, $readme);

echo "✅ README updated successfully, BuriroDev.\n";
