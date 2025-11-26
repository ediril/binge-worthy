<?php
/**
 * Fetch Videos Script - Terminal Only
 * 
 * This script fetches videos from existing YouTube channels in the database
 * and adds them as new videos. It only runs from the command line.
 * 
 * Usage: php fetch.php [--chan-id=ID] [--limit=N] [--backfill] [--dry-run]
 */

// Allow CLI or when included by admin.php specifically, block direct web access
$calling_script = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '';
$is_admin_include = basename($calling_script) === 'admin.php';

if (php_sapi_name() !== 'cli' && !$is_admin_include) {
    require_once __DIR__ . '/common.php';
    show_404_page();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/common.php';

// Only execute main logic when script is run directly, not when included
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {

// Parse command line arguments
$options = [
    'chan-id' => null,
    'limit' => null,  // Will be set based on backfill mode
    'dry-run' => false,
    'backfill' => false,
    'recent' => false,
    'list-chans' => false,
    'force' => false,
    'remove-shorts' => false,
    'allow-shorts' => false,
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
    } elseif ($arg === '--force') {
        $options['force'] = true;
    } elseif ($arg === '--remove-shorts') {
        $options['remove-shorts'] = true;
    } elseif ($arg === '--allow-shorts') {
        $options['allow-shorts'] = true;
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
    echo "BingeWorthy Video Fetcher v" . FETCHER_VERSION . "\n";
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
    echo "  --force            Force fetch regardless of last_fetched timestamp\n";
    echo "  --remove-shorts    Remove any Shorts found in RSS feeds from the database\n";
    echo "  --allow-shorts     Include YouTube Shorts in fetched videos (normally filtered out)\n";
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
    echo "  php fetch.php --allow-shorts                # Include YouTube Shorts in fetched videos\n";
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

// Acquire lock before starting fetch process
$lockHandle = acquireLock(FETCHER_LOCK_FILE_NAME);

// Register shutdown function to release lock on exit
register_shutdown_function(function() use (&$lockHandle) {
    releaseLock($lockHandle, FETCHER_LOCK_FILE_NAME);
});

echo "BingeWorthy Video Fetcher v" . FETCHER_VERSION . "\n";
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
    $total_shorts_skipped = 0;
    $total_shorts_removed = 0;
    
    foreach ($channels as $channel) {
        echo "Processing: {$channel['name']} (ID: {$channel['id']})\n";
        
        // Check if we should skip this channel based on last_fetched timestamp
        if (!$options['force'] && $channel['last_fetched']) {
            $last_fetched_time = strtotime($channel['last_fetched']);
            $min_interval_seconds = CHANNEL_UPDATE_INTERVAL_HOURS * 60 * 60;
            $time_since_fetch = time() - $last_fetched_time;
            
            if ($time_since_fetch < $min_interval_seconds) {
                echo "  Skipping: Last fetched " . format_upload_date($channel['last_fetched']) . " (less than " . CHANNEL_UPDATE_INTERVAL_HOURS . " hours ago)\n";
                continue;
            }
        }
        
        if ($options['backfill']) {
            echo "  Backfill mode: Fetching additional videos beyond latest ones\n";
        }
        
        $fetch_result = fetch_channel_videos($channel['youtube_url'], $options['limit'], !$options['backfill'], $options['allow-shorts']);
        $videos = $fetch_result['videos'];
        $shorts_skipped = $fetch_result['shorts_skipped'] ?? 0;
        $shorts_found = $fetch_result['shorts_found'] ?? [];
        
        if ($videos === null) {
            echo "  ERROR: Could not fetch videos for this channel\n";
            $total_errors++;
            continue;
        }
        
        // Handle removing Shorts from database if requested
        if ($options['remove-shorts'] && !empty($shorts_found)) {
            $removed_count = 0;
            foreach ($shorts_found as $short_url) {
                if (!$options['dry-run']) {
                    // Get video ID first to clean up FTS
                    $get_id_stmt = $db->prepare("SELECT id FROM videos WHERE video_url = ?");
                    $get_id_stmt->execute([$short_url]);
                    $video = $get_id_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($video) {
                        // Clean up FTS before delete
                        $db->exec("DELETE FROM videos_fts WHERE rowid = " . $video['id']);
                    }
                    $delete_stmt = $db->prepare("DELETE FROM videos WHERE video_url = ?");
                    if ($delete_stmt->execute([$short_url])) {
                        $removed_count += $delete_stmt->rowCount();
                    }
                }
            }
            if ($options['dry-run']) {
                echo "  [DRY RUN] Would remove " . count($shorts_found) . " Shorts from database\n";
                $total_shorts_removed += count($shorts_found);
            } else if ($removed_count > 0) {
                echo "  Removed {$removed_count} Shorts from database\n";
                $total_shorts_removed += $removed_count;
            }
        }
        
        if ($shorts_skipped > 0) {
            echo "  Skipped {$shorts_skipped} Shorts\n";
        }
        
        if (empty($videos)) {
            echo "  No regular videos found for this channel\n";
            continue;
        }
        
        $added_count = 0;
        $skipped_count = 0;
        $timestamp_updates = 0;
        
        // For backfill mode, fetch from YouTube's video page (limited to ~30 videos)
        if ($options['backfill']) {
            echo "  Backfill mode: Fetching from YouTube (limit: ~30 videos per channel)\n";
            
            // Since YouTube only provides ~30 videos, just fetch once
            $backfill_result = fetch_channel_videos($channel['youtube_url'], 50, false, $options['allow-shorts']);
            $backfill_videos = $backfill_result['videos'];
            
            if (!$backfill_videos || empty($backfill_videos)) {
                echo "  No videos could be fetched\n";
                continue;
            }
            
            echo "  Received " . count($backfill_videos) . " videos from YouTube\n";
            
            $stats = process_videos_for_fetch($db, $channel['id'], $backfill_videos, [
                'dry_run' => $options['dry-run'],
                'limit' => $options['limit'],
                'verbose' => true
            ]);
            
            $added_count = $stats['added'];
            $skipped_count = $stats['skipped'];
            $timestamp_updates = $stats['timestamp_updates'];
            $total_errors += $stats['errors'];
        } else {
            // Normal mode - process the videos we already fetched
            $stats = process_videos_for_fetch($db, $channel['id'], $videos, [
                'dry_run' => $options['dry-run'],
                'limit' => null,
                'verbose' => true
            ]);
            
            $added_count = $stats['added'];
            $skipped_count = $stats['skipped'];
            $timestamp_updates = $stats['timestamp_updates'];
            $total_errors += $stats['errors'];
        }
        
        if ($timestamp_updates > 0) {
            echo "  Added: {$added_count} (Skipped: {$skipped_count}) (Updated timestamps: {$timestamp_updates})\n\n";
        } else {
            echo "  Added: {$added_count} (Skipped: {$skipped_count})\n\n";
        }
        
        $total_added += $added_count;
        $total_skipped += $skipped_count;
        $total_timestamp_updates += $timestamp_updates;
        $total_shorts_skipped += $shorts_skipped;
        
        // Update last_fetched timestamp for this channel
        if (!$options['dry-run']) {
            $update_stmt = $db->prepare("UPDATE channels SET last_fetched = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->execute([$channel['id']]);
        }
        
        // Small delay to be respectful to YouTube's servers
        sleep(1);
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total videos added: {$total_added}\n";
    echo "Total videos skipped: {$total_skipped}\n";
    if ($total_shorts_skipped > 0) {
        echo "Total Shorts filtered out: {$total_shorts_skipped}\n";
    }
    if ($total_shorts_removed > 0) {
        echo "Total Shorts removed from database: {$total_shorts_removed}\n";
    }
    if ($total_timestamp_updates > 0) {
        echo "Total timestamps updated: {$total_timestamp_updates}\n";
    }
    echo "Total errors: {$total_errors}\n";
    
    if ($options['dry-run']) {
        echo "\n--- DRY RUN COMPLETE ---\n";
        echo "This was a dry run. No videos were actually added to the database.\n";
        echo "Run the command again without --dry-run to actually add the videos.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Release lock when done
    releaseLock($lockHandle, FETCHER_LOCK_FILE_NAME);
}

} // End of main execution block

/**
 * Fetch videos from a YouTube channel via RSS or web scraping
 */
function fetch_channel_videos($channel_url, $limit, $use_rss = false, $allow_shorts = false) {
    if ($use_rss) {
        $result = fetch_channel_videos_rss($channel_url, $limit, $allow_shorts);
        return ['videos' => $result['videos'], 'shorts_skipped' => $result['shorts_skipped'], 'shorts_found' => $result['shorts_found']];
    }
    
    // Use web scraping for backfill
    $result = fetch_channel_videos_scraping($channel_url, $limit, $allow_shorts);
    return $result;
}

/**
 * Fetch videos from YouTube channel RSS feed
 */
function fetch_channel_videos_rss($channel_url, $limit = 10, $allow_shorts = false) {
    // Extract channel ID from URL for RSS feed
    $channel_id = extract_youtube_channel_id($channel_url);
    
    if (!$channel_id) {
        return ['videos' => null, 'shorts_skipped' => 0, 'shorts_found' => []];
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
        return ['videos' => null, 'shorts_skipped' => 0, 'shorts_found' => []];
    }
    
    $videos = [];
    $shorts_skipped = 0;
    $shorts_found = [];
    
    // Parse RSS XML
    $xml = @simplexml_load_string($rss_content);
    if (!$xml) {
        return ['videos' => null, 'shorts_skipped' => 0, 'shorts_found' => []];
    }
    
    $count = 0;
    foreach ($xml->entry as $entry) {
        // Check if this is a Short by looking at the link
        $link = (string)$entry->link['href'];
        $video_id = (string)$entry->children('http://www.youtube.com/xml/schemas/2015')->videoId;
        
        if (strpos($link, '/shorts/') !== false) {
            // Track this Short
            if (!$allow_shorts) {
                $shorts_skipped++;
                if ($video_id) {
                    $shorts_found[] = "https://youtube.com/watch?v=" . $video_id;
                }
                continue;
            }
            // If allow_shorts is true, we'll process it as a regular video
        }
        
        // Only add regular videos up to the limit
        if ($count >= $limit) {
            continue;
        }
        
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
    
    return ['videos' => $videos, 'shorts_skipped' => $shorts_skipped, 'shorts_found' => $shorts_found];
}

/**
 * Get real channel ID from YouTube channel page
 */
function get_real_channel_id_from_page($channel_url) {
    // Normalize mobile URLs to standard format
    $channel_url = str_replace('m.youtube.com', 'youtube.com', $channel_url);
    
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
function fetch_channel_videos_scraping($channel_url, $limit = 10, $allow_shorts = false) {
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy Video Fetcher/1.0)'
        ]
    ]);
    
    $videos = [];
    $shorts_skipped = 0;
    $shorts_found = [];
    
    // Convert channel URL to videos page
    $base_url = str_replace(['/@', '/channel/', '/c/', '/user/'], ['/@', '/channel/', '/c/', '/user/'], $channel_url);
    $base_url = rtrim($base_url, '/');
    
    // Fetch regular videos first
    $videos_url = $base_url . '/videos';
    $html = @file_get_contents($videos_url, false, $context);
    
    if ($html === false) {
        return ['videos' => null, 'shorts_skipped' => 0, 'shorts_found' => []];
    }
    
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
    
    // If allow_shorts is true, also fetch from /shorts page
    if ($allow_shorts && $processed_count < $limit) {
        $shorts_url = $base_url . '/shorts';
        $shorts_html = @file_get_contents($shorts_url, false, $context);
        
        if ($shorts_html !== false) {
            // Extract shorts data
            preg_match_all('/"videoId":"([^"]+)","thumbnail"/', $shorts_html, $shorts_ids_specific);
            preg_match_all('/"title":{"runs":\[{"text":"([^"]+)"}/', $shorts_html, $shorts_titles);
            
            $shorts_matches = [];
            if (!empty($shorts_ids_specific[1]) && !empty($shorts_titles[1])) {
                $count = min(count($shorts_ids_specific[1]), count($shorts_titles[1]));
                for ($i = 0; $i < $count; $i++) {
                    $shorts_matches[] = [
                        $shorts_ids_specific[1][$i],
                        $shorts_titles[1][$i]
                    ];
                }
            }
            
            foreach ($shorts_matches as $short) {
                if ($processed_count >= $limit) {
                    break;
                }
                
                $video_id = $short[0];
                $title = html_entity_decode($short[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                $video_url = "https://youtube.com/shorts/{$video_id}";
                $thumbnail_url = generate_youtube_thumbnail("https://youtube.com/watch?v={$video_id}");
                
                $videos[] = [
                    'title' => $title,
                    'url' => $video_url,
                    'thumbnail' => $thumbnail_url,
                    'published' => null
                ];
                
                $processed_count++;
            }
        }
    }
    
    return ['videos' => $videos, 'shorts_skipped' => $shorts_skipped, 'shorts_found' => $shorts_found];
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