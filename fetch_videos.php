<?php
/**
 * Fetch Videos Script - Terminal Only
 * 
 * This script fetches videos from existing YouTube channels in the database
 * and adds them as new videos. It only runs from the command line.
 * 
 * Usage: php fetch_videos.php [--chan-id=ID] [--limit=N] [--dry-run]
 */

// CLI-only protection - return 404 if accessed via web server
if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
</head>
<body>
    <h1>404 - Page Not Found</h1>
    <p>The page you are looking for does not exist.</p>
</body>
</html>';
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/common.php';

// Parse command line arguments
$options = [
    'chan-id' => null,
    'limit' => 15,
    'dry-run' => false,
    'web' => false,
    'help' => false
];

foreach ($argv as $arg) {
    if (strpos($arg, '--chan-id=') === 0) {
        $options['chan-id'] = (int)substr($arg, 10);
    } elseif (strpos($arg, '--limit=') === 0) {
        $options['limit'] = (int)substr($arg, 8);
    } elseif ($arg === '--dry-run') {
        $options['dry-run'] = true;
    } elseif ($arg === '--web') {
        $options['web'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
    }
}

// Show help
if ($options['help']) {
    echo "Fetch Videos Script - Terminal Only\n\n";
    echo "Usage: php fetch_videos.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --chan-id=ID       Fetch videos for specific channel ID only\n";
    echo "  --limit=N          Maximum number of videos to fetch per channel (default: 15)\n";
    echo "  --web              Use web scraping instead of RSS feed (slower, less reliable)\n";
    echo "  --dry-run          Show what would be added without actually adding videos\n";
    echo "  --help, -h         Show this help message\n\n";
    echo "Examples:\n";
    echo "  php fetch_videos.php                    # Fetch up to 15 recent videos from all channels (RSS)\n";
    echo "  php fetch_videos.php --limit=5          # Fetch up to 5 recent videos from all channels\n";
    echo "  php fetch_videos.php --chan-id=3        # Fetch videos only from channel ID 3\n";
    echo "  php fetch_videos.php --web --limit=15   # Use web scraping instead of RSS\n";
    echo "  php fetch_videos.php --dry-run          # Preview what would be fetched\n";
    exit(0);
}

echo "Starting video fetch process...\n";
if ($options['dry-run']) {
    echo "DRY RUN MODE - No videos will be added to database\n";
}
echo "Method: " . ($options['web'] ? 'Web scraping' : 'RSS feed') . "\n";
echo "Limit: {$options['limit']} videos per channel\n";
if ($options['chan-id'] !== null && $options['chan-id'] > 0) {
    echo "Targeting channel ID: {$options['chan-id']}\n";
}
echo "\n";

try {
    $db = get_database();
    
    // Get channels to process
    if ($options['chan-id'] !== null && $options['chan-id'] > 0) {
        $stmt = $db->prepare("SELECT * FROM channels WHERE id = ? AND is_active = 1");
        $stmt->execute([$options['chan-id']]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            echo "Channel with ID {$options['chan-id']} not found or inactive.\n";
            exit(1);
        }
    } else {
        $stmt = $db->prepare("SELECT * FROM channels WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($channels)) {
        echo "No active channels found in database.\n";
        exit(0);
    }
    
    echo "Found " . count($channels) . " channel(s) to process.\n\n";
    
    $total_added = 0;
    $total_skipped = 0;
    $total_errors = 0;
    
    foreach ($channels as $channel) {
        echo "Processing: {$channel['name']} (ID: {$channel['id']})\n";
        
        $videos = fetch_channel_videos($channel['youtube_url'], $options['limit'], !$options['web']);
        
        if ($videos === null) {
            echo "  ERROR: Could not fetch videos for this channel\n";
            $total_errors++;
            continue;
        }
        
        if (empty($videos)) {
            echo "  No videos found for this channel\n";
            continue;
        }
        
        echo "  Found " . count($videos) . " videos\n";
        
        $added_count = 0;
        $skipped_count = 0;
        
        foreach ($videos as $video) {
            // Check if video already exists
            $stmt = $db->prepare("SELECT id FROM videos WHERE video_url = ? AND is_active = 1");
            $stmt->execute([$video['url']]);
            
            if ($stmt->fetch()) {
                $skipped_count++;
                continue;
            }
            
            if ($options['dry-run']) {
                echo "  [DRY RUN] {$video['title']} {$video['url']}\n";
                $added_count++;
            } else {
                // Add video to database
                $success = add_video(
                    $db,
                    $channel['id'],
                    $video['title'],
                    $video['description'],
                    $video['url'],
                    $video['thumbnail']
                );
                
                if ($success) {
                    echo "  ✓ Added: {$video['title']}\n";
                    $added_count++;
                } else {
                    echo "  ✗ Failed to add: {$video['title']}\n";
                    $total_errors++;
                }
            }
        }
        
        echo "  Added: {$added_count}, Skipped: {$skipped_count}\n\n";
        
        $total_added += $added_count;
        $total_skipped += $skipped_count;
        
        // Small delay to be respectful to YouTube's servers
        sleep(1);
    }
    
    echo "=== SUMMARY ===\n";
    echo "Total videos added: {$total_added}\n";
    echo "Total videos skipped: {$total_skipped}\n";
    echo "Total errors: {$total_errors}\n";
    
    if ($options['dry-run']) {
        echo "\n--- DRY RUN COMPLETE ---\n";
        echo "This was a dry run. No videos were actually added to the database.\n";
        echo "Run the command again without --dry-run to actually add the videos.\n";
    } else {
        echo "\nVideos have been successfully added to the database.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Fetch videos from a YouTube channel via RSS or web scraping
 */
function fetch_channel_videos($channel_url, $limit, $use_rss = false) {
    if ($use_rss) {
        return fetch_channel_videos_rss($channel_url, $limit);
    }
    
    // Fallback to web scraping
    return fetch_channel_videos_scraping($channel_url, $limit);
}

/**
 * Fetch videos from YouTube channel RSS feed
 */
function fetch_channel_videos_rss($channel_url, $limit = 10) {
    // Extract channel ID from URL for RSS feed
    $channel_id = extract_youtube_channel_id($channel_url);
    
    if (!$channel_id) {
        return null;
    }
    
    // For RSS feeds, we need the actual channel ID (UC format)
    // If we got a username/handle, try to get the real channel ID from the page
    if (!preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $channel_id)) {
        $real_channel_id = get_real_channel_id_from_page($channel_url);
        if ($real_channel_id) {
            $channel_id = $real_channel_id;
        }
    }
    
    // YouTube RSS feed URL
    $rss_url = "https://www.youtube.com/feeds/videos.xml?channel_id=" . $channel_id;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy Video Fetcher/1.0)'
        ]
    ]);
    
    $rss_content = @file_get_contents($rss_url, false, $context);
    
    if ($rss_content === false) {
        return null;
    }
    
    $videos = [];
    
    // Parse RSS XML
    $xml = @simplexml_load_string($rss_content);
    if (!$xml) {
        return null;
    }
    
    $count = 0;
    foreach ($xml->entry as $entry) {
        if ($count >= $limit) {
            break;
        }
        
        $video_id = (string)$entry->children('http://www.youtube.com/xml/schemas/2015')->videoId;
        $title = (string)$entry->title;
        $published = (string)$entry->published;
        
        if ($video_id && $title) {
            $video_url = "https://youtube.com/watch?v=" . $video_id;
            $thumbnail_url = generate_youtube_thumbnail($video_url);
            
            $videos[] = [
                'title' => $title,
                'description' => '',
                'url' => $video_url,
                'thumbnail' => $thumbnail_url,
                'published' => $published
            ];
            
            $count++;
        }
    }
    
    return $videos;
}

/**
 * Get real channel ID from YouTube channel page
 */
function get_real_channel_id_from_page($channel_url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy Video Fetcher/1.0)'
        ]
    ]);
    
    $html = @file_get_contents($channel_url, false, $context);
    
    if ($html === false) {
        return null;
    }
    
    // Look for the main channel ID in canonical URL - this is most reliable
    if (preg_match('/<link rel="canonical" href="https:\/\/www\.youtube\.com\/channel\/(UC[a-zA-Z0-9_-]{22})"/', $html, $matches)) {
        return $matches[1];
    }
    
    // Look for channel ID in meta tags
    if (preg_match('/<meta property="og:url" content="https:\/\/www\.youtube\.com\/channel\/(UC[a-zA-Z0-9_-]{22})"/', $html, $matches)) {
        return $matches[1];
    }
    
    // Look for channelId in the first occurrence of JSON data (main channel)
    if (preg_match('/"channelId":"(UC[a-zA-Z0-9_-]{22})"/', $html, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Fetch videos from YouTube channel by web scraping
 */
function fetch_channel_videos_scraping($channel_url, $limit = 10) {
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy Video Fetcher/1.0)'
        ]
    ]);
    
    // Convert channel URL to videos page
    $videos_url = str_replace(['/@', '/channel/', '/c/', '/user/'], ['/@', '/channel/', '/c/', '/user/'], $channel_url);
    if (!str_contains($videos_url, '/videos')) {
        $videos_url = rtrim($videos_url, '/') . '/videos';
    }
    
    $html = @file_get_contents($videos_url, false, $context);
    
    if ($html === false) {
        return null;
    }
    
    $videos = [];
    
    // Extract video data from the HTML
    // This is a basic implementation - YouTube's structure may change
    preg_match_all('/"videoId":"([^"]+)"/', $html, $video_ids);
    preg_match_all('/"title":{"runs":\[{"text":"([^"]+)"}/', $html, $titles);
    
    if (empty($video_ids[1]) || empty($titles[1])) {
        // Fallback: try a different pattern
        preg_match_all('/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $video_ids);
        preg_match_all('/<meta property="og:title" content="([^"]*)"/', $html, $titles);
    }
    
    $count = min(count($video_ids[1]), count($titles[1]), $limit);
    
    for ($i = 0; $i < $count; $i++) {
        $video_id = $video_ids[1][$i];
        $title = html_entity_decode($titles[1][$i], ENT_QUOTES | ENT_HTML5);
        
        // Skip shorts and livestreams for now
        if (strlen($title) < 10 || str_contains(strtolower($title), '#shorts')) {
            continue;
        }
        
        $video_url = "https://youtube.com/watch?v=" . $video_id;
        $thumbnail_url = generate_youtube_thumbnail($video_url);
        
        $videos[] = [
            'title' => $title,
            'description' => '',
            'url' => $video_url,
            'thumbnail' => $thumbnail_url
        ];
    }
    
    return array_slice($videos, 0, $limit);
}
?>