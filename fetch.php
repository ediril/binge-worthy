<?php
/**
 * Fetch Videos Script - Terminal Only
 * 
 * This script fetches videos from existing YouTube channels in the database
 * and adds them as new videos. It only runs from the command line.
 * 
 * Usage: php fetch.php [--chan-id=ID] [--limit=N] [--backfill] [--dry-run]
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
    'limit' => null,  // Will be set based on backfill mode
    'dry-run' => false,
    'backfill' => false,
    'recent' => false,
    'list-chans' => false,
    'help' => false
];

// Track if limit was explicitly set
$limit_explicitly_set = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--chan-id=') === 0) {
        $options['chan-id'] = (int)substr($arg, 10);
    } elseif (strpos($arg, '--limit=') === 0) {
        $options['limit'] = (int)substr($arg, 8);
        $limit_explicitly_set = true;
    } elseif ($arg === '--dry-run') {
        $options['dry-run'] = true;
    } elseif ($arg === '--backfill') {
        $options['backfill'] = true;
    } elseif ($arg === '--recent') {
        $options['recent'] = true;
    } elseif ($arg === '--list-chans') {
        $options['list-chans'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
    }
}

// Set default limit based on mode
if (!$limit_explicitly_set) {
    $options['limit'] = $options['backfill'] ? 30 : 15;
}

// Show help
if ($options['help']) {
    echo "Fetch Videos Script - Terminal Only\n\n";
    echo "Usage: php fetch.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --chan-id=ID       Fetch videos for specific channel ID only\n";
    echo "  --limit=N          Maximum number of videos to fetch per channel\n";
    echo "                     (default: 15 for RSS mode, 30 for backfill mode)\n";
    echo "  --backfill         Use web scraping to fetch older videos\n";
    echo "                     Works with or without --chan-id (all channels if not specified)\n";
    echo "                     Note: Limited to ~30 videos per channel due to YouTube's page structure\n";
    echo "  --recent           Fetch videos only from channels created in the last 2 hours\n";
    echo "                     Cannot be used with --chan-id\n";
    echo "  --list-chans       List all channels with IDs and video counts\n";
    echo "  --dry-run          Show what would be added without actually adding videos\n";
    echo "  --help, -h         Show this help message\n\n";
    echo "Examples:\n";
    echo "  php fetch_videos.php                        # Fetch up to 15 recent videos from all channels (RSS)\n";
    echo "  php fetch_videos.php --limit=5              # Fetch up to 5 recent videos from all channels\n";
    echo "  php fetch_videos.php --chan-id=3            # Fetch videos only from channel ID 3\n";
    echo "  php fetch_videos.php --backfill --chan-id=3 # Backfill older videos for channel ID 3\n";
    echo "  php fetch_videos.php --backfill --chan-id=3 --limit=10  # Backfill up to 10 older videos\n";
    echo "  php fetch_videos.php --backfill             # Backfill older videos for all channels (default: 30)\n";
    echo "  php fetch_videos.php --recent               # Fetch videos from channels added in the last hour\n";
    echo "  php fetch_videos.php --dry-run              # Preview what would be fetched\n";
    echo "  php fetch.php --list-chans                  # List all channels with IDs and video counts\n";
    exit(0);
}

// Handle channel listing mode
if ($options['list-chans']) {
    try {
        $db = get_database();
        list_channels($db);
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
    exit(0);
}

// Validation: --recent and --chan-id cannot be used together
if ($options['recent'] && $options['chan-id'] !== null) {
    echo "ERROR: --recent and --chan-id cannot be used together\n";
    echo "Use --help for usage information\n";
    exit(1);
}


echo "Starting video fetch process...\n";
if ($options['dry-run']) {
    echo "DRY RUN MODE - No videos will be added to database\n";
}
echo "Method: " . ($options['backfill'] ? 'Web scraping (backfill)' : 'RSS feed') . "\n";
$limit_message = $limit_explicitly_set ? "{$options['limit']} videos per channel" : "{$options['limit']} videos per channel (default)";
echo "Limit: {$limit_message}\n";
if ($options['chan-id'] !== null && $options['chan-id'] > 0) {
    echo "Targeting channel ID: {$options['chan-id']}\n";
}
if ($options['recent']) {
    echo "Targeting channels created in the last 2 hour\n";
}
echo "\n";

try {
    $db = get_database();
    
    // Get channels to process
    if ($options['chan-id'] !== null && $options['chan-id'] > 0) {
        $stmt = $db->prepare("SELECT * FROM channels WHERE id = ?");
        $stmt->execute([$options['chan-id']]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            echo "Channel with ID {$options['chan-id']} not found or inactive.\n";
            exit(1);
        }
    } elseif ($options['recent']) {
        // Get channels created in the last hour
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-2 hour'));
        $stmt = $db->prepare("SELECT * FROM channels WHERE created_at >= ? ORDER BY created_at DESC");
        $stmt->execute([$one_hour_ago]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channels)) {
            echo "No channels found that were created in the last hour.\n";
            exit(0);
        }
    } else {
        $stmt = $db->prepare("SELECT * FROM channels ORDER BY name");
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
    $total_timestamp_updates = 0;
    
    foreach ($channels as $channel) {
        echo "Processing: {$channel['name']} (ID: {$channel['id']})\n";
        
        if ($options['backfill']) {
            echo "  Backfill mode: Fetching additional videos beyond latest ones\n";
        }
        
        $videos = fetch_channel_videos($channel['youtube_url'], $options['limit'], !$options['backfill']);
        
        if ($videos === null) {
            echo "  ERROR: Could not fetch videos for this channel\n";
            $total_errors++;
            continue;
        }
        
        if (empty($videos)) {
            echo "  No videos found for this channel\n";
            continue;
        }
        
        $added_count = 0;
        $skipped_count = 0;
        $timestamp_updates = 0;
        
        // For backfill mode, fetch from YouTube's video page (limited to ~30 videos)
        if ($options['backfill']) {
            echo "  Backfill mode: Fetching from YouTube (limit: ~30 videos per channel)\n";
            
            // Since YouTube only provides ~30 videos, just fetch once
            $backfill_videos = fetch_channel_videos($channel['youtube_url'], 50, false);
            
            if (!$backfill_videos || empty($backfill_videos)) {
                echo "  No videos could be fetched\n";
                continue;
            }
            
            echo "  Received " . count($backfill_videos) . " videos from YouTube\n";
            
            foreach ($backfill_videos as $video) {
                if ($added_count >= $options['limit']) {
                    break;
                }
                
                // Check if video already exists
                $stmt = $db->prepare("SELECT id, published_at FROM videos WHERE video_url = ?");
                $stmt->execute([$video['url']]);
                $existing_video = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_video) {
                    // Check if we have a published date and it's different
                    if (isset($video['published']) && $video['published'] && 
                        $existing_video['published_at'] !== $video['published']) {
                        
                        if (!$options['dry-run']) {
                            $update_stmt = $db->prepare("UPDATE videos SET published_at = ? WHERE id = ?");
                            $update_stmt->execute([$video['published'], $existing_video['id']]);
                        }
                        $timestamp_updates++;
                    }
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
                        $video['url'],
                        $video['thumbnail'],
                        0, // is_featured
                        isset($video['published']) ? $video['published'] : null // published_at
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
        } else {
            // Normal mode - process the videos we already fetched
            foreach ($videos as $video) {
                // Check if video already exists
                $stmt = $db->prepare("SELECT id, published_at FROM videos WHERE video_url = ?");
                $stmt->execute([$video['url']]);
                $existing_video = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_video) {
                    // Check if we have a published date and it's different
                    if (isset($video['published']) && $video['published'] && 
                        $existing_video['published_at'] !== $video['published']) {
                        
                        if (!$options['dry-run']) {
                            $update_stmt = $db->prepare("UPDATE videos SET published_at = ? WHERE id = ?");
                            $update_stmt->execute([$video['published'], $existing_video['id']]);
                        }
                        $timestamp_updates++;
                    }
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
                        $video['url'],
                        $video['thumbnail'],
                        0, // is_featured
                        isset($video['published']) ? $video['published'] : null // published_at
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
        }
        
        if ($timestamp_updates > 0) {
            echo "  Added: {$added_count} (Skipped: {$skipped_count}) (Updated timestamps: {$timestamp_updates})\n\n";
        } else {
            echo "  Added: {$added_count} (Skipped: {$skipped_count})\n\n";
        }
        
        $total_added += $added_count;
        $total_skipped += $skipped_count;
        $total_timestamp_updates += $timestamp_updates;
        
        // Small delay to be respectful to YouTube's servers
        sleep(1);
    }
    
    echo "=== SUMMARY ===\n";
    echo "Total videos added: {$total_added}\n";
    echo "Total videos skipped: {$total_skipped}\n";
    if ($total_timestamp_updates > 0) {
        echo "Total timestamps updated: {$total_timestamp_updates}\n";
    }
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
    
    // Use web scraping for backfill
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
    
    // Extract video data from the HTML - look for video containers with metadata
    // Use a more specific pattern to extract video IDs that correspond correctly to titles
    preg_match_all('/"videoId":"([^"]+)","thumbnail"/', $html, $video_ids_specific);
    preg_match_all('/"title":{"runs":\[{"text":"([^"]+)"}/', $html, $titles);
    
    // Try to extract publish times - YouTube uses various formats
    preg_match_all('/"publishedTimeText":{"simpleText":"([^"]+)"}/', $html, $publish_times);
    
    $matches = [];
    if (!empty($video_ids_specific[1]) && !empty($titles[1])) {
        $count = min(count($video_ids_specific[1]), count($titles[1]));
        for ($i = 0; $i < $count; $i++) {
            $published_text = isset($publish_times[1][$i]) ? $publish_times[1][$i] : null;
            $matches[] = [
                '',
                $video_ids_specific[1][$i],
                $titles[1][$i],
                $published_text
            ];
        }
    } else {
        // Fallback to the old method if specific pattern fails
        preg_match_all('/"videoId":"([^"]+)"/', $html, $video_ids);
        
        if (!empty($video_ids[1]) && !empty($titles[1])) {
            $count = min(count($video_ids[1]), count($titles[1]));
            for ($i = 0; $i < $count; $i++) {
                $published_text = isset($publish_times[1][$i]) ? $publish_times[1][$i] : null;
                $matches[] = [
                    '',
                    $video_ids[1][$i],
                    $titles[1][$i],
                    $published_text
                ];
            }
        }
    }
    
    $processed_count = 0;
    
    foreach ($matches as $match) {
        if ($processed_count >= $limit) {
            break;
        }
        
        $video_id = $match[1];
        $title = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $published_text = isset($match[3]) ? $match[3] : '';
        
        // Skip shorts and livestreams
        if (strlen($title) < 10 || str_contains(strtolower($title), '#shorts')) {
            continue;
        }
        
        // Convert relative date to actual date if possible
        $published_date = null;
        if ($published_text) {
            $published_date = parse_youtube_date($published_text);
        }
        
        $video_url = "https://youtube.com/watch?v={$video_id}";
        $thumbnail_url = generate_youtube_thumbnail($video_url);
        
        $videos[] = [
            'title' => $title,
            'url' => $video_url,
            'thumbnail' => $thumbnail_url,
            'published' => $published_date
        ];
        
        $processed_count++;
    }
    
    return $videos;
}

/**
 * Parse YouTube's relative date format to timestamp
 */
function parse_youtube_date($date_text) {
    // YouTube uses formats like "2 weeks ago", "1 month ago", "1 year ago", etc.
    $date_text = strtolower(trim($date_text));
    
    // Handle "ago" format
    if (str_contains($date_text, 'ago')) {
        $date_text = str_replace(' ago', '', $date_text);
        
        // Parse number and unit
        preg_match('/(\d+)\s*(second|minute|hour|day|week|month|year)s?/', $date_text, $matches);
        
        if (empty($matches)) {
            return null;
        }
        
        $number = (int)$matches[1];
        $unit = $matches[2];
        
        $interval_map = [
            'second' => 'seconds',
            'minute' => 'minutes', 
            'hour' => 'hours',
            'day' => 'days',
            'week' => 'weeks',
            'month' => 'months',
            'year' => 'years'
        ];
        
        $interval = $interval_map[$unit] ?? $unit;
        $timestamp = strtotime("-{$number} {$interval}");
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
    
    // Try to parse as regular date
    $timestamp = strtotime($date_text);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}


/**
 * List all channels with their IDs and video counts
 */
function list_channels($db) {
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.name,
            c.youtube_url,
            COUNT(v.id) as video_count,
            GROUP_CONCAT(cat.name, ', ') as categories
        FROM channels c
        LEFT JOIN videos v ON c.id = v.channel_id
        LEFT JOIN channel_categories cc ON c.id = cc.channel_id
        LEFT JOIN categories cat ON cc.category_id = cat.id
        GROUP BY c.id, c.name, c.youtube_url
        ORDER BY c.name ASC
    ");
    
    $stmt->execute();
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($channels)) {
        echo "No channels found in the database.\n";
        return;
    }
    
    echo "=== CHANNELS LIST ===\n\n";
    echo sprintf("%-4s %-40s %-8s %s\n", "ID", "Channel Name", "Videos", "Categories");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($channels as $channel) {
        $name = strlen($channel['name']) > 40 ? substr($channel['name'], 0, 37) . '...' : $channel['name'];
        $categories = $channel['categories'] ?: 'None';
        if (strlen($categories) > 30) {
            $categories = substr($categories, 0, 27) . '...';
        }
        
        echo sprintf("%-4d %-40s %-8d %s\n", 
            $channel['id'], 
            $name, 
            $channel['video_count'],
            $categories
        );
    }
    
    echo "\nTotal channels: " . count($channels) . "\n";
    echo "Total videos: " . array_sum(array_column($channels, 'video_count')) . "\n";
}