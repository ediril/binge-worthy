<?php

define('FETCHER_VERSION', '0.0812.001');
define('APP_BASE_PATH', '/bingeworthy');
define('APP_SITE_URL', 'https://emrahdiril.com' . APP_BASE_PATH);

function app_url($path = '') {
    if ($path === '' || $path === '/') {
        return APP_BASE_PATH . '/';
    }

    return APP_BASE_PATH . '/' . ltrim($path, '/');
}


// ================================
// Admin Configuration
// ================================
define('ADMIN_CHANNELS_PER_PAGE', 10);
define('ADMIN_VIDEOS_PER_PAGE', 10);
define('ADMIN_SUGGESTIONS_PER_PAGE', 10);

// ================================
// Search Configuration
// ================================
define('SEARCH_RATE_LIMIT', 30); // Max requests per minute per /24 subnet

/**
 * Extract /24 subnet (first 3 octets) from IP address for rate limiting
 */
function get_ip_subnet($ip) {
    $parts = explode('.', $ip);
    return count($parts) >= 3 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] : $ip;
}

// ================================
// Fetch Configuration
// ================================
define('CHANNEL_UPDATE_INTERVAL_HOURS', 23); // Hours to wait before fetching channel again
define('FETCHER_LOCK_FILE_NAME', 'bw_fetch.lock');


// ================================
// Lock File Management
// ================================
function acquireLock($lockName) {
    $lockFile = __DIR__ . '/' . $lockName;       
    $lockHandle = fopen($lockFile, 'c');
    
    if (!$lockHandle) {
        echo "ERROR: Could not create lock file: {$lockFile}\n";
        exit(1);
    }
    
    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        echo "Another fetch process is already running.\n";
        echo "If you're sure no other process is running, delete the lock file: {$lockFile}\n";
        fclose($lockHandle);
        exit(1);
    }
    
    // Write PID to lock file
    ftruncate($lockHandle, 0);
    fwrite($lockHandle, getmypid() . "\n");
    
    return $lockHandle;
}

function releaseLock(&$lockHandle, $lockFile) {
    if ($lockHandle && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        $lockHandle = null;  // Clear the handle to prevent double-release
        
        // Remove the lock file
        $lockPath = __DIR__ . '/' . $lockFile;
        if (file_exists($lockPath)) {
            @unlink($lockPath);
        }
    }
}

// ================================
// 404 Error Handling
// ================================
function show_404_page() {
    http_response_code(404);
    record_visit(); // Record the 404 visit
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Page Not Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="' . app_url('static/styles.css') . '?v=' . filemtime(__DIR__ . '/static/styles.css') . '">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <h1 style="font-size: 4rem; color: #e50914;">404</h1>
                    <h2>Page Not Found</h2>
                    <p>The page you are looking for does not exist.</p>
                    <a href="' . app_url() . '" class="btn btn-primary">Go Home</a>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

/**
 * Get new videos from the database (last 7 days)
 */
function get_new_videos($db, $limit) {
    $stmt = $db->prepare("
        SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
        FROM videos v 
        JOIN channels c ON v.channel_id = c.id 
        LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
        LEFT JOIN categories cat ON cc.category_id = cat.id
        WHERE v.published_at >= datetime('now', '-7 days')
        GROUP BY v.id
        ORDER BY v.published_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get new channels from the database (last 7 days)
 */
function get_new_channels($db) {
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM videos v WHERE v.channel_id = c.id) as video_count,
               (SELECT GROUP_CONCAT(cat.name, ', ') 
                FROM channel_categories cc 
                JOIN categories cat ON cc.category_id = cat.id 
                WHERE cc.channel_id = c.id) as category_names
        FROM channels c 
        WHERE c.created_at >= datetime('now', '-7 days')
        ORDER BY RANDOM() 
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if any channels exist in the database
 */
function has_any_channels($db) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM channels LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Get suggested videos from the database
 */
function get_suggested_videos($db, $limit) {
    // Get recent videos from the last 30 days
    $stmt = $db->prepare("
        SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
        FROM videos v 
        JOIN channels c ON v.channel_id = c.id 
        LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
        LEFT JOIN categories cat ON cc.category_id = cat.id
        WHERE v.published_at >= datetime('now', '-7 days')
        GROUP BY v.id
        ORDER BY v.published_at DESC 
    ");
    $stmt->execute();
    $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group videos by channel to ensure diversity
    $videos_by_channel = [];
    foreach ($all_videos as $video) {
        $channel_id = $video['channel_id'];
        if (!isset($videos_by_channel[$channel_id])) {
            $videos_by_channel[$channel_id] = [];
        }
        $videos_by_channel[$channel_id][] = $video;
    }
    
    // Shuffle channels to randomize selection order
    $channel_ids = array_keys($videos_by_channel);
    shuffle($channel_ids);
    
    // Build diverse video list: max 2 videos per channel
    $suggested_videos = [];
    $max_per_channel = 2;
    $rounds = 0;
    
    while (count($suggested_videos) < $limit && $rounds < 10) {
        $added_this_round = false;
        
        foreach ($channel_ids as $channel_id) {
            if (count($suggested_videos) >= $limit) break;
            
            // Count how many videos we already have from this channel
            $channel_count = 0;
            foreach ($suggested_videos as $video) {
                if ($video['channel_id'] == $channel_id) {
                    $channel_count++;
                }
            }
            
            // Add a video if we haven't hit the per-channel limit
            if ($channel_count < $max_per_channel && !empty($videos_by_channel[$channel_id])) {
                $video = array_shift($videos_by_channel[$channel_id]);
                $suggested_videos[] = $video;
                $added_this_round = true;
            }
        }
        
        // If we didn't add any videos this round, break to avoid infinite loop
        if (!$added_this_round) break;
        $rounds++;
    }
    
    // Final shuffle to mix up the order
    shuffle($suggested_videos);
    
    return array_slice($suggested_videos, 0, $limit);
}

/**
 * Get the chronologically next video from the same channel
 */
function get_next_channel_video($db, $channel_id, $published_at) {
    $stmt = $db->prepare('SELECT v.*, c.name as channel_title 
                          FROM videos v 
                          LEFT JOIN channels c ON v.channel_id = c.id 
                          WHERE v.published_at > ? AND v.channel_id = ?
                          ORDER BY v.published_at ASC
                          LIMIT 1');
    $stmt->execute([$published_at, $channel_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate the next video section HTML for the overlay
 */
function generate_next_video_section($db, $video) {
    $html = '<div class="overlay-section">';
    
    $next_video = null;
    if ($video) {
        $next_video = get_next_channel_video($db, $video['channel_id'], $video['published_at']);
    }

    if (!$video || !$next_video) {
        $html .= '<h4>No more new videos in ' . htmlspecialchars($video['channel_title']) . '</h4>';
        $html .= '<p class="text-muted">Try one of the suggested videos below.</p>';
        $html .= '</div>';
        return $html;
    }

    $html .= '<h2 class="overlay-title">What\'s Next?</h2>';
    $html .= '<h4>The next video in ' . htmlspecialchars($next_video['channel_title']) . '</h4>';
    $html .= '<div class="next-video-container">';

    $next_youtube_id = extractYouTubeId($next_video['video_url']);
    $next_thumbnail = getVideoThumbnail($next_youtube_id);
    
    $html .= '<div class="next-video-card" data-video-id="' . $next_video['id'] . '" data-youtube-id="' . htmlspecialchars($next_youtube_id) . '">';
    $html .= '<div class="next-video-thumbnail-container">';
    $html .= '<img src="' . htmlspecialchars($next_video['thumbnail_url'] ?: $next_thumbnail) . '" ';
    $html .= 'class="next-video-thumbnail" ';
    $html .= 'alt="' . htmlspecialchars($next_video['title']) . '">';
    $html .= '<div class="countdown-overlay">';
    $html .= '<div class="countdown-circle">';
    $html .= '<span id="countdown-number">10</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="next-video-info">';
    $html .= '<h5 class="next-video-title">';
    $html .= htmlspecialchars($next_video['title']);
    $html .= '</h5>';
    $html .= '<p class="next-video-channel">';
    $html .= htmlspecialchars($next_video['channel_title']);
    $html .= '</p>';
    $html .= '<p class="next-video-countdown">Starting in <span id="countdown-text">10</span> seconds...</p>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get all channels
 */
function get_all_channels($db) {
    $stmt = $db->prepare("SELECT * FROM channels ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all categories
 */
function get_all_categories($db) {
    $stmt = $db->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all categories (for display on categories page)
 */
function get_display_categories($db) {
    $stmt = $db->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get categories for a specific channel
 */
function get_channel_categories($db, $channel_id) {
    $stmt = $db->prepare("
        SELECT cat.* 
        FROM categories cat 
        JOIN channel_categories cc ON cat.id = cc.category_id 
        WHERE cc.channel_id = ?
        ORDER BY cat.name
    ");
    $stmt->execute([$channel_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add categories to a channel
 */
function add_channel_categories($db, $channel_id, $category_ids) {
    // First remove existing categories
    $stmt = $db->prepare("DELETE FROM channel_categories WHERE channel_id = ?");
    $stmt->execute([$channel_id]);
    
    // Add new categories
    $stmt = $db->prepare("INSERT INTO channel_categories (channel_id, category_id) VALUES (?, ?)");
    foreach ($category_ids as $category_id) {
        $stmt->execute([$channel_id, $category_id]);
    }
    return true;
}

/**
 * Add a new channel with multiple categories
 */
function add_channel_with_categories($db, $name, $description, $youtube_url, $category_ids, $thumbnail_url = null) {
    // Check if channel with this name already exists
    $stmt = $db->prepare("SELECT id FROM channels WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        return false; // Channel with this name already exists
    }

    // Generate slug (pass $db to check for uniqueness)
    $slug = generate_channel_slug($name, $db);

    // Add channel with slug
    $stmt = $db->prepare("
        INSERT INTO channels (name, description, youtube_url, thumbnail_url, slug, last_fetched, created_at)
        VALUES (?, ?, ?, ?, ?, NULL, datetime('now'))
    ");
    if ($stmt->execute([$name, $description, $youtube_url, $thumbnail_url, $slug])) {
        $channel_id = $db->lastInsertId();

        // Manually update FTS index (triggers don't work safely with FTS5)
        $fts_stmt = $db->prepare("INSERT INTO channels_fts(rowid, slug) VALUES (?, ?)");
        $fts_stmt->execute([$channel_id, $slug]);

        add_channel_categories($db, $channel_id, $category_ids);
        return $channel_id;
    }
    return false;
}

/**
 * Add a new channel (legacy function - maintain compatibility)
 */
function add_channel($db, $name, $description, $youtube_url, $category_id, $thumbnail_url = null) {
    // Use the new multi-category function with single category
    return add_channel_with_categories($db, $name, $description, $youtube_url, [$category_id], $thumbnail_url);
}

/**
 * Add a new video
 */
function add_video($db, $channel_id, $title, $video_url, $thumbnail_url, $is_featured = 0, $created_at = null) {
    // Generate slug
    $slug = generate_video_slug($title, $db);

    if ($created_at) {
        $stmt = $db->prepare("
            INSERT INTO videos (channel_id, title, video_url, thumbnail_url, is_featured, published_at, slug)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([$channel_id, $title, $video_url, $thumbnail_url, $is_featured, $created_at, $slug]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO videos (channel_id, title, video_url, thumbnail_url, is_featured, slug)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([$channel_id, $title, $video_url, $thumbnail_url, $is_featured, $slug]);
    }

    // Manually update FTS index (triggers don't work safely with FTS5)
    if ($result) {
        $video_id = $db->lastInsertId();
        $fts_stmt = $db->prepare("INSERT INTO videos_fts(rowid, slug) VALUES (?, ?)");
        $fts_stmt->execute([$video_id, $slug]);
    }

    return $result;
}

/**
 * Add multiple videos for a channel (works with RSS, backfill, or any video array)
 * Returns the number of videos successfully added
 */
function add_videos($db, $channel_id, $videos) {
    if (empty($videos)) {
        return 0;
    }
    
    $videos_added = 0;
    
    foreach ($videos as $video) {
        // Check if video already exists
        $stmt = $db->prepare("SELECT id FROM videos WHERE video_url = ?");
        $stmt->execute([$video['url']]);
        if (!$stmt->fetch()) {
            // Add video to database
            $success = add_video(
                $db,
                $channel_id,
                $video['title'],
                $video['url'],
                $video['thumbnail'],
                0, // is_featured
                isset($video['published']) ? $video['published'] : null
            );
            if ($success) {
                $videos_added++;
            }
        }
    }
    
    return $videos_added;
}

/**
 * Process videos for fetch script with advanced options
 * Returns array with stats: ['added' => int, 'skipped' => int, 'timestamp_updates' => int, 'errors' => int]
 */
function process_videos_for_fetch($db, $channel_id, $videos, $options = []) {
    $default_options = [
        'dry_run' => false,
        'limit' => null,
        'verbose' => false
    ];
    $options = array_merge($default_options, $options);
    
    if (empty($videos)) {
        return ['added' => 0, 'skipped' => 0, 'timestamp_updates' => 0, 'errors' => 0];
    }
    
    $added_count = 0;
    $skipped_count = 0;
    $timestamp_updates = 0;
    $error_count = 0;
    
    foreach ($videos as $video) {
        // Check limit
        if ($options['limit'] && $added_count >= $options['limit']) {
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
                
                if (!$options['dry_run']) {
                    $update_stmt = $db->prepare("UPDATE videos SET published_at = ? WHERE id = ?");
                    $update_stmt->execute([$video['published'], $existing_video['id']]);
                }
                $timestamp_updates++;
            }
            $skipped_count++;
            continue;
        }
        
        if ($options['dry_run']) {
            if ($options['verbose']) {
                echo "  [DRY RUN] {$video['title']} {$video['url']}\n";
            }
            $added_count++;
        } else {
            // Add video to database
            $success = add_video(
                $db,
                $channel_id,
                $video['title'],
                $video['url'],
                $video['thumbnail'],
                0, // is_featured
                isset($video['published']) ? $video['published'] : null
            );
            
            if ($success) {
                if ($options['verbose']) {
                    echo "  ✓ Added: {$video['title']}\n";
                }
                $added_count++;
            } else {
                if ($options['verbose']) {
                    echo "  ✗ Failed to add: {$video['title']}\n";
                }
                $error_count++;
            }
        }
    }
    
    return ['added' => $added_count, 'skipped' => $skipped_count, 'timestamp_updates' => $timestamp_updates, 'errors' => $error_count];
}

/**
 * Format date for display
 */
function format_upload_date($date_string) {
    if (!$date_string) {
        return '';
    }
    
    $timestamp = strtotime($date_string);
    if (!$timestamp) {
        return '';
    }
    
    $now = time();
    $diff = $now - $timestamp;
    
    // Less than a minute
    if ($diff < 60) {
        return 'just now';
    }
    
    // Less than an hour
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    }
    
    // Less than a day
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    
    // Less than a week
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    
    // Less than a month
    if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    }
    
    // Less than a year
    if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
    
    // Years
    $years = floor($diff / 31536000);
    return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
}

/**
 * Extract YouTube video ID from URL
 */
function extract_youtube_id($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? null;
}

/**
 * Extract YouTube video ID from URL (alias for consistency)
 */
function extractYouTubeId($url) {
    return extract_youtube_id($url);
}

/**
 * Get video thumbnail URL for a YouTube video ID
 */
function getVideoThumbnail($youtube_id) {
    if (!$youtube_id) {
        return 'https://img.youtube.com/vi/default/hqdefault.jpg';
    }
    
    $thumbnail_options = [
        "https://img.youtube.com/vi/{$youtube_id}/maxresdefault.jpg",
        "https://img.youtube.com/vi/{$youtube_id}/hqdefault.jpg",
        "https://img.youtube.com/vi/{$youtube_id}/mqdefault.jpg",
        "https://img.youtube.com/vi/{$youtube_id}/default.jpg"
    ];
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'HEAD',
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy/1.0)'
        ]
    ]);
    
    $headers = @get_headers($thumbnail_options[0], 1, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $thumbnail_options[0];
    }
    
    return $thumbnail_options[1];
}

/**
 * Generate YouTube thumbnail URL from video URL with fallback options
 */
function generate_youtube_thumbnail($video_url) {
    $video_id = extract_youtube_id($video_url);
    if (!$video_id) {
        return null;
    }
    
    // Try different thumbnail resolutions in order of preference
    $thumbnail_options = [
        "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg",  // 1280x720 (not always available)
        "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg",     // 480x360 (always available)
        "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg",     // 320x180 (always available)
        "https://img.youtube.com/vi/{$video_id}/default.jpg"        // 120x90 (always available)
    ];
    
    // Check if maxresdefault exists, if not use hqdefault (which always exists)
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'HEAD',
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy/1.0)'
        ]
    ]);
    
    // First try maxresdefault
    $headers = @get_headers($thumbnail_options[0], 1, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $thumbnail_options[0];
    }
    
    // Fallback to hqdefault which always exists
    return $thumbnail_options[1];
}

/**
 * Normalize video input to full YouTube URL
 */
function normalize_video_input($input) {
    $input = trim($input);
    
    // If it's already a full URL, return as is
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        return $input;
    }
    
    // If it looks like a video ID (11 characters, alphanumeric with dashes/underscores)
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
        return "https://youtube.com/watch?v=" . $input;
    }
    
    // If it contains watch?v= but isn't a full URL, try to extract the ID
    if (preg_match('/watch\?v=([a-zA-Z0-9_-]{11})/', $input, $matches)) {
        return "https://youtube.com/watch?v=" . $matches[1];
    }
    
    // If it contains youtu.be/ but isn't a full URL, try to extract the ID
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
        return "https://youtube.com/watch?v=" . $matches[1];
    }
    
    return null;
}

/**
 * Normalize channel input to full YouTube URL
 */
function normalize_channel_input($input) {
    $input = trim($input);
    
    // If it's already a full URL, normalize mobile URLs and return
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        // Convert m.youtube.com to youtube.com
        $input = str_replace('m.youtube.com', 'youtube.com', $input);
        return $input;
    }
    
    // If it starts with @, create a handle URL
    if (substr($input, 0, 1) === '@') {
        return "https://youtube.com/" . $input;
    }
    
    // If it starts with UC and is 24 characters, it's likely a channel ID
    if (preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $input)) {
        return "https://youtube.com/channel/" . $input;
    }
    
    // If it's just a username/channel name without @, try both formats
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $input)) {
        // Try as a handle first (more common nowadays)
        return "https://youtube.com/@" . $input;
    }
    
    return null;
}

/**
 * Extract YouTube channel ID from URL
 */
function extract_youtube_channel_id($url) {
    // Normalize mobile URLs to standard format
    $url = str_replace('m.youtube.com', 'youtube.com', $url);
    
    // Handle different YouTube channel URL formats
    if (preg_match('/youtube\.com\/channel\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    if (preg_match('/youtube\.com\/c\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    if (preg_match('/youtube\.com\/user\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    if (preg_match('/youtube\.com\/@([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Generate YouTube channel thumbnail URL
 */
function generate_youtube_channel_thumbnail($channel_url) {
    $channel_id = extract_youtube_channel_id($channel_url);
    if (!$channel_id) {
        return null;
    }
    
    // For channel URLs with UC IDs, we can try to get the avatar
    if (preg_match('/^UC[a-zA-Z0-9_-]{22}$/', $channel_id)) {
        return "https://yt3.ggpht.com/a/default-user=s240-c-k-c0x00ffffff-no-rj";
    }
    
    // For other channel formats, we can't reliably get thumbnails without API
    return null;
}

/**
 * Fetch YouTube video metadata
 */
function fetch_youtube_video_metadata($video_url) {
    $video_id = extract_youtube_id($video_url);
    if (!$video_id) {
        return null;
    }
    
    // Use YouTube oEmbed API to get basic metadata
    $oembed_url = "https://www.youtube.com/oembed?url=" . urlencode($video_url) . "&format=json";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy/1.0)'
        ]
    ]);
    
    $response = @file_get_contents($oembed_url, false, $context);
    
    if ($response === false) {
        error_log("DEBUG: Video doesn't exist - returning null");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        return null;
    }
    
    // Try to get upload date by fetching the video page
    $published_at = null;
    $video_page = @file_get_contents("https://www.youtube.com/watch?v=" . $video_id, false, $context);
    if ($video_page !== false) {
        // Look for upload date in the page - YouTube uses various formats
        if (preg_match('/"uploadDate":"([^"]+)"/', $video_page, $matches)) {
            $published_at = $matches[1];
        } elseif (preg_match('/"datePublished":"([^"]+)"/', $video_page, $matches)) {
            $published_at = $matches[1];
        }
    }
    
    return [
        'title' => html_entity_decode($data['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'description' => '',
        'thumbnail_url' => generate_youtube_thumbnail($video_url),
        'channel_name' => html_entity_decode($data['author_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'channel_url' => $data['author_url'] ?? '',
        'published_at' => $published_at
    ];
}

/**
 * Fetch YouTube channel metadata with fallback for different URL formats
 */
function fetch_youtube_channel_metadata($channel_url) {
    // Normalize mobile URLs to standard format
    $channel_url = str_replace('m.youtube.com', 'youtube.com', $channel_url);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (compatible; Binge-Worthy/1.0)'
        ]
    ]);
    
    $html = @file_get_contents($channel_url, false, $context);
    
    if ($html === false) {
        // If the URL failed and it's a handle URL, try the legacy format
        if (str_contains($channel_url, '/@')) {
            $handle = str_replace('https://youtube.com/@', '', $channel_url);
            $legacy_url = "https://youtube.com/c/" . $handle;
            $html = @file_get_contents($legacy_url, false, $context);
            
            if ($html !== false) {
                $channel_url = $legacy_url;
            }
        }
    }
    
    if ($html === false) {
        return null;
    }
    
    // Extract channel name from title tag
    $channel_name = '';
    if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
        $channel_name = html_entity_decode(trim(str_replace(' - YouTube', '', $matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Extract description - try multiple methods to get full description
    $description = '';
    
    // Method 1: Try Open Graph description first (often has full description) - handle multiline content
    if (preg_match('/<meta property="og:description" content="(.*?)"(?:[^>]*)>/s', $html, $matches)) {
        $description = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Method 2: Try schema.org itemprop description - handle multiline content
    if (empty($description) && preg_match('/<meta itemprop="description" content="(.*?)"(?:[^>]*)>/s', $html, $matches)) {
        $description = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Method 3: Try to extract from JSON-LD structured data (most complete)
    if (empty($description) && preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
        $json_data = json_decode($matches[1], true);
        if (isset($json_data['description'])) {
            $description = html_entity_decode($json_data['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    // Method 4: Try to extract from YouTube's internal JSON data - improved pattern
    if (empty($description)) {
        // Find all "description" fields and get the longest one (likely the full description)
        preg_match_all('/"description":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches);
        if (!empty($matches[1])) {
            $longest_desc = '';
            foreach ($matches[1] as $match) {
                $decoded_json = json_decode('"' . $match . '"');
                if ($decoded_json !== null && strlen($decoded_json) > strlen($longest_desc)) {
                    $longest_desc = $decoded_json;
                }
            }
            if (!empty($longest_desc)) {
                $description = html_entity_decode($longest_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
    }
    
    // Method 5: Fallback to meta description (usually truncated)
    if (empty($description) && preg_match('/<meta name="description" content="([^"]*)"/', $html, $matches)) {
        $description = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Try to extract channel thumbnail
    $thumbnail_url = null;
    if (preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $matches)) {
        $thumbnail_url = $matches[1];
    }
    
    // Try to extract the actual YouTube channel ID (UC...)
    $youtube_channel_id = null;
    // Method 1: Look for channel ID in the HTML
    if (preg_match('/"channelId":"([^"]*)"/', $html, $matches)) {
        $youtube_channel_id = $matches[1];
    }
    // Method 2: Look in URL redirects or canonical links
    elseif (preg_match('/youtube\.com\/channel\/([a-zA-Z0-9_-]+)/', $html, $matches)) {
        $youtube_channel_id = $matches[1];
    }
    // Method 3: Look in JSON-LD data
    elseif (preg_match('/"@id"[^"]*"[^"]*\/channel\/([a-zA-Z0-9_-]+)"/', $html, $matches)) {
        $youtube_channel_id = $matches[1];
    }
    
    return [
        'name' => $channel_name,
        'description' => $description,
        'youtube_url' => $channel_url,
        'thumbnail_url' => $thumbnail_url,
        'youtube_channel_id' => $youtube_channel_id
    ];
}

/**
 * Get or create channel from video metadata
 */
function get_or_create_channel_from_video($db, $video_metadata, $category_id) {
    if (empty($video_metadata['channel_name']) || empty($video_metadata['channel_url'])) {
        return null;
    }
    
    // Check if channel already exists by URL
    $stmt = $db->prepare("SELECT id FROM channels WHERE youtube_url = ?");
    $stmt->execute([$video_metadata['channel_url']]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($channel) {
        return $channel['id'];
    }
    
    // Check if channel already exists by name (since names are unique)
    $stmt = $db->prepare("SELECT id FROM channels WHERE name = ?");
    $stmt->execute([$video_metadata['channel_name']]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($channel) {
        // Channel exists with same name but different URL - use existing channel
        return $channel['id'];
    }
    
    // Try to get channel thumbnail
    $thumbnail_url = generate_youtube_channel_thumbnail($video_metadata['channel_url']);
    
    // Don't create channel without categories
    if (!$category_id) {
        return null;
    }
    
    // Create new channel using the multi-category function
    return add_channel_with_categories(
        $db, 
        $video_metadata['channel_name'], 
        '', 
        $video_metadata['channel_url'], 
        [$category_id], 
        $thumbnail_url
    );
}

/**
 * Auto-categorize channel based on title and channel name
 */
function auto_categorize_channel($db, $title, $channel_name) {
    $title_lower = strtolower($title);
    $channel_lower = strtolower($channel_name);
    
    // Tech keywords
    if (preg_match('/\b(tech|technology|programming|coding|developer|software|hardware|computer|ai|artificial intelligence|machine learning|blockchain|crypto)\b/', $title_lower . ' ' . $channel_lower)) {
        return get_category_id_by_name($db, 'Tech');
    }
    
    // Business keywords
    if (preg_match('/\b(business|entrepreneur|startup|marketing|sales|finance|investment|money|economy)\b/', $title_lower . ' ' . $channel_lower)) {
        return get_category_id_by_name($db, 'Business');
    }
    
    // Science keywords
    if (preg_match('/\b(science|physics|chemistry|biology|research|study|experiment|theory)\b/', $title_lower . ' ' . $channel_lower)) {
        return get_category_id_by_name($db, 'Science');
    }
    
    // Education keywords
    if (preg_match('/\b(education|learn|tutorial|how to|guide|course|lesson|teach)\b/', $title_lower . ' ' . $channel_lower)) {
        return get_category_id_by_name($db, 'Education');
    }
    
    return get_category_id_by_name($db, 'Other');
}

/**
 * Get category ID by name
 */
function get_category_id_by_name($db, $category_name) {
    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$category_name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result['id'];
    }
    
    // Return null if category not found
    return null;
}

/**
 * Add a new category
 */
function add_category($db, $name) {
    $slug = generate_category_slug($name, $db);
    $stmt = $db->prepare("
        INSERT INTO categories (name, slug, created_at)
        VALUES (?, ?, datetime('now'))
    ");
    return $stmt->execute([$name, $slug]);
}

/**
 * Generate a URL slug from category name
 * Format: lowercase, hyphenated
 * Only adds 5-char MD5 hash suffix if needed for uniqueness
 */
function generate_category_slug($category_name, $db = null) {
    // Normalize: lowercase, remove special chars except spaces and hyphens
    $normalized = strtolower(trim($category_name));
    $normalized = preg_replace('/[^a-z0-9\s-]/', '', $normalized);

    // Replace multiple spaces/hyphens with single hyphen
    $slug_base = preg_replace('/[\s-]+/', '-', $normalized);
    $slug_base = trim($slug_base, '-');

    // Handle edge case where name has no alphanumeric chars
    if (empty($slug_base)) {
        return 'category-' . substr(md5($category_name), 0, 8);
    }

    // If no db provided, always add hash for safety
    if (!$db) {
        $hash = substr(md5($category_name), 0, 5);
        return $slug_base . '-' . $hash;
    }

    // Check if base slug is unique
    $stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
    $stmt->execute([$slug_base]);
    if ($stmt->fetchColumn() == 0) {
        return $slug_base;
    }

    // Collision - add hash
    $hash = substr(md5($category_name), 0, 5);
    return $slug_base . '-' . $hash;
}

/**
 * Get category by slug
 */
function get_category_by_slug($db, $slug) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get category by ID
 */
function get_category_by_id($db, $category_id) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate category URL from category data
 * Returns slug-based URL if slug exists, falls back to ID-based URL
 */
function get_category_url($category) {
    if (!empty($category['slug'])) {
        return app_url('categories/' . urlencode($category['slug']));
    }
    return app_url('categories?category=' . $category['id']);
}

/**
 * Get channel by ID
 */
function get_channel_by_id($db, $channel_id) {
    $stmt = $db->prepare("SELECT * FROM channels WHERE id = ?");
    $stmt->execute([$channel_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate a URL slug from channel name
 * Format: lowercase, hyphenated, first 5 words
 * Only adds 5-char MD5 hash suffix if needed for uniqueness
 * Example: "3Blue1Brown" -> "3blue1brown"
 * Example: "5-Minute Crafts" -> "5-minute-crafts"
 * Example (collision): "Tech Reviews" -> "tech-reviews-a1b2c"
 */
function generate_channel_slug($channel_name, $db = null) {
    // Normalize: lowercase, remove special chars except spaces and hyphens
    $normalized = strtolower(trim($channel_name));
    $normalized = preg_replace('/[^a-z0-9\s-]/', '', $normalized);

    // Replace multiple spaces/hyphens with single space, then split
    $normalized = preg_replace('/[\s-]+/', ' ', $normalized);
    $words = explode(' ', $normalized);
    $words = array_filter($words);
    $words = array_slice($words, 0, 5);

    // Join with hyphens
    $slug_base = implode('-', $words);

    // If no db provided, always add hash for safety
    if (!$db) {
        $hash = substr(md5($channel_name), 0, 5);
        return $slug_base . '-' . $hash;
    }

    // Check if base slug is unique
    $stmt = $db->prepare("SELECT COUNT(*) FROM channels WHERE slug = ?");
    $stmt->execute([$slug_base]);
    if ($stmt->fetchColumn() == 0) {
        return $slug_base;
    }

    // Collision - add hash
    $hash = substr(md5($channel_name), 0, 5);
    return $slug_base . '-' . $hash;
}

/**
 * Get channel by slug
 */
function get_channel_by_slug($db, $slug) {
    $stmt = $db->prepare("SELECT * FROM channels WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate channel URL from channel data
 * Returns slug-based URL if slug exists, falls back to ID-based URL
 */
function get_channel_url($channel) {
    if (!empty($channel['slug'])) {
        return app_url('channel/' . urlencode($channel['slug']));
    }
    return app_url('channel?id=' . $channel['id']);
}

/**
 * Generate a URL slug from video title
 * Format: lowercase, hyphenated, first 5 words
 * Only adds 5-char MD5 hash suffix if needed for uniqueness
 */
function generate_video_slug($video_title, $db = null) {
    // Normalize: lowercase, remove special chars except spaces and hyphens
    $normalized = strtolower(trim($video_title));
    $normalized = preg_replace('/[^a-z0-9\s-]/', '', $normalized);

    // Replace multiple spaces/hyphens with single space, then split
    $normalized = preg_replace('/[\s-]+/', ' ', $normalized);
    $words = explode(' ', $normalized);
    $words = array_filter($words);
    $words = array_slice($words, 0, 5);

    // Join with hyphens
    $slug_base = implode('-', $words);

    // Handle edge case where title has no alphanumeric chars (e.g., Japanese only)
    if (empty($slug_base)) {
        $slug_base = 'video-' . substr(md5($video_title), 0, 8);
        // No need to check uniqueness, hash is unique enough
        return $slug_base;
    }

    // If no db provided, always add hash for safety
    if (!$db) {
        $hash = substr(md5($video_title), 0, 5);
        return $slug_base . '-' . $hash;
    }

    // Check if base slug is unique
    $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE slug = ?");
    $stmt->execute([$slug_base]);
    if ($stmt->fetchColumn() == 0) {
        return $slug_base;
    }

    // Collision - add hash based on full title
    $slug_with_hash = $slug_base . '-' . substr(md5($video_title), 0, 5);

    // Check if slug with hash is also taken (rare edge case)
    $stmt->execute([$slug_with_hash]);
    if ($stmt->fetchColumn() == 0) {
        return $slug_with_hash;
    }

    // Still collision - use longer hash
    return $slug_base . '-' . substr(md5($video_title . microtime()), 0, 8);
}

/**
 * Get video by slug
 */
function get_video_by_slug($db, $slug) {
    $stmt = $db->prepare("SELECT * FROM videos WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate video URL from video data
 * Returns slug-based URL if slug exists, falls back to ID-based URL
 */
function get_video_url($video) {
    if (!empty($video['slug'])) {
        return app_url('play/' . urlencode($video['slug']));
    }
    return app_url('play?id=' . $video['id']);
}

/**
 * Get videos by channel ID
 */
function get_videos_by_channel($db, $channel_id, $limit = null) {
    $sql = "
        SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
        FROM videos v 
        JOIN channels c ON v.channel_id = c.id 
        LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
        LEFT JOIN categories cat ON cc.category_id = cat.id
        WHERE v.channel_id = ?
        GROUP BY v.id
        ORDER BY v.published_at DESC
    ";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$channel_id, $limit]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([$channel_id]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get videos by category ID
 */
function get_videos_by_category($db, $category_id, $limit = null) {
    $sql = "
        SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
        FROM videos v 
        JOIN channels c ON v.channel_id = c.id 
        JOIN channel_categories cc ON c.id = cc.channel_id 
        LEFT JOIN categories cat ON cc.category_id = cat.id
        WHERE v.id IN (
            SELECT DISTINCT v2.id 
            FROM videos v2 
            JOIN channels c2 ON v2.channel_id = c2.id 
            JOIN channel_categories cc2 ON c2.id = cc2.channel_id 
            WHERE cc2.category_id = ?
        )
        GROUP BY v.id
        ORDER BY v.published_at DESC
    ";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$category_id, $limit]);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([$category_id]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Clean up YouTube URLs to their base form
 */
function cleanup_youtube_url($url) {
    $url = trim($url);
    
    // Normalize mobile URLs to standard format
    $url = str_replace('m.youtube.com', 'youtube.com', $url);
    
    // If it's not a YouTube URL, return as is
    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) {
        return $url;
    }
    
    // For youtu.be short URLs, they're already clean
    if (strpos($url, 'youtu.be') !== false) {
        // Remove any query parameters
        return strtok($url, '?');
    }
    
    // For regular YouTube URLs, remove common trailing paths
    $base_url = strtok($url, '?'); // Remove query parameters first
    
    // Remove common trailing paths from channel URLs
    $paths_to_remove = [
        '/videos',
        '/playlists', 
        '/community',
        '/channels',
        '/about',
        '/search',
        '/shorts',
        '/live',
        '/streams',
        '/releases',
        '/podcasts'
    ];
    
    foreach ($paths_to_remove as $path) {
        if (substr($base_url, -strlen($path)) === $path) {
            $base_url = substr($base_url, 0, -strlen($path));
            break; // Only remove one trailing path
        }
    }
    
    return $base_url;
}

/**
 * Auto-remove matching suggestions from the suggestions database
 */
function auto_remove_matching_suggestions($content_url) {
    try {
        // Connect to suggestions database
        $suggestions_db = new PDO('sqlite:suggestions.db');
        $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $deleted_count = 0;

        // Check if this is a video URL (has video ID)
        $video_id = null;
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $content_url, $matches)) {
            $video_id = $matches[1];
        }

        if ($video_id) {
            // For video URLs, match by video ID to avoid cleanup_youtube_url stripping the ID
            // Delete suggestions containing this video ID
            $delete_stmt = $suggestions_db->prepare("DELETE FROM suggestions WHERE link LIKE ? OR link LIKE ? OR link LIKE ?");
            $pattern1 = "%watch?v=" . $video_id . "%";
            $pattern2 = "%youtu.be/" . $video_id . "%";
            $pattern3 = "%embed/" . $video_id . "%";

            if ($delete_stmt->execute([$pattern1, $pattern2, $pattern3])) {
                $deleted_count = $delete_stmt->rowCount();
            }
        } else {
            // For channel URLs, use cleanup_youtube_url for normalization
            $normalized_url = cleanup_youtube_url($content_url);

            // Remove suggestions that match the cleaned URL exactly
            $delete_stmt = $suggestions_db->prepare("DELETE FROM suggestions WHERE TRIM(link) = ?");

            if ($delete_stmt->execute([$normalized_url])) {
                $deleted_count = $delete_stmt->rowCount();
            }

            // Also check existing suggestions and clean them for comparison
            $all_suggestions_stmt = $suggestions_db->prepare("SELECT id, link FROM suggestions");
            $all_suggestions_stmt->execute();
            $all_suggestions = $all_suggestions_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_suggestions as $suggestion) {
                $cleaned_suggestion = cleanup_youtube_url($suggestion['link']);
                if ($cleaned_suggestion === $normalized_url) {
                    $delete_specific = $suggestions_db->prepare("DELETE FROM suggestions WHERE id = ?");
                    if ($delete_specific->execute([$suggestion['id']])) {
                        $deleted_count += $delete_specific->rowCount();
                    }
                }
            }
        }

        // For channel URLs (non-video case), also check additional URL format variations
        if (!$video_id && isset($normalized_url)) {
            $url_patterns = [];

            // For channel URLs, check all variations
            if (preg_match('/youtube\.com\/(channel|c|user)\/([a-zA-Z0-9_.-]+)/', $normalized_url, $matches)) {
                $channel_type = $matches[1];
                $channel_id = $matches[2];
                $url_patterns[] = "youtube.com/{$channel_type}/{$channel_id}";
                $url_patterns[] = "www.youtube.com/{$channel_type}/{$channel_id}";
            } elseif (preg_match('/youtube\.com\/@([a-zA-Z0-9_.-]+)/', $normalized_url, $matches)) {
                $channel_handle = $matches[1];
                $url_patterns[] = "youtube.com/@{$channel_handle}";
                $url_patterns[] = "www.youtube.com/@{$channel_handle}";
            }

            // Delete any matching patterns
            foreach ($url_patterns as $pattern) {
                $delete_stmt = $suggestions_db->prepare("DELETE FROM suggestions WHERE link LIKE ?");
                if ($delete_stmt->execute(["%{$pattern}%"])) {
                    $deleted_count += $delete_stmt->rowCount();
                }
            }
        }

    } catch (Exception $e) {
        // Don't break the main flow if suggestion removal fails
    }
}

/**
 * Generate pagination data for any paginated list
 */
function generate_pagination_data($total_items, $current_page, $items_per_page) {
    $total_pages = max(1, ceil($total_items / $items_per_page));
    $current_page = max(1, min($current_page, $total_pages));
    
    return [
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'items_per_page' => $items_per_page,
        'offset' => ($current_page - 1) * $items_per_page,
        'showing_start' => (($current_page - 1) * $items_per_page) + 1,
        'showing_end' => min($current_page * $items_per_page, $total_items)
    ];
}

/**
 * Render category checkboxes in two columns
 */
function render_category_checkboxes($categories, $input_name = 'existing_categories[]', $id_prefix = 'category', $selected_ids = []) {
    if (empty($categories)) {
        return '';
    }
    
    $total = count($categories);
    $per_column = ceil($total / 2);
    $first_column = array_slice($categories, 0, $per_column);
    $second_column = array_slice($categories, $per_column);
    
    $html = '<div class="row">';
    
    // First column
    $html .= '<div class="col-md-6">';
    foreach ($first_column as $category) {
        $checked = in_array($category['id'], $selected_ids) ? 'checked' : '';
        $html .= sprintf(
            '<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="%s" id="%s-%d" value="%d" %s>
                <label class="form-check-label" for="%s-%d">%s</label>
            </div>',
            htmlspecialchars($input_name),
            htmlspecialchars($id_prefix),
            $category['id'],
            $category['id'],
            $checked,
            htmlspecialchars($id_prefix),
            $category['id'],
            htmlspecialchars($category['name'])
        );
    }
    $html .= '</div>';
    
    // Second column
    $html .= '<div class="col-md-6">';
    foreach ($second_column as $category) {
        $checked = in_array($category['id'], $selected_ids) ? 'checked' : '';
        $html .= sprintf(
            '<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="%s" id="%s-%d" value="%d" %s>
                <label class="form-check-label" for="%s-%d">%s</label>
            </div>',
            htmlspecialchars($input_name),
            htmlspecialchars($id_prefix),
            $category['id'],
            $category['id'],
            $checked,
            htmlspecialchars($id_prefix),
            $category['id'],
            htmlspecialchars($category['name'])
        );
    }
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render pagination controls
 */
function render_pagination_controls($pagination_data, $base_url, $page_param = 'page', $additional_params = []) {
    if ($pagination_data['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<div class="top-pagination d-flex justify-content-center align-items-center mb-3" style="gap: 8px;">';
    
    // Previous button
    $prev_disabled = $pagination_data['current_page'] <= 1 ? 'disabled' : '';
    $prev_url = $pagination_data['current_page'] > 1 
        ? $base_url . '?' . http_build_query(array_merge([$page_param => $pagination_data['current_page'] - 1], $additional_params)) 
        : '#';
    
    $html .= sprintf(
        '<a href="%s" class="btn btn-sm btn-secondary %s" %s>&laquo; Previous</a>',
        htmlspecialchars($prev_url),
        $prev_disabled,
        $prev_disabled ? 'onclick="return false;"' : ''
    );
    
    // Page selector
    $html .= sprintf(
        '<select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=\'%s?\' + \'%s=\' + this.value + \'%s\'">',
        htmlspecialchars($base_url),
        htmlspecialchars($page_param),
        $additional_params ? '&' . http_build_query($additional_params) : ''
    );
    
    for ($i = 1; $i <= $pagination_data['total_pages']; $i++) {
        $selected = $i == $pagination_data['current_page'] ? 'selected' : '';
        $html .= sprintf(
            '<option value="%d" %s>Page %d of %d</option>',
            $i,
            $selected,
            $i,
            $pagination_data['total_pages']
        );
    }
    $html .= '</select>';
    
    // Next button
    $next_disabled = $pagination_data['current_page'] >= $pagination_data['total_pages'] ? 'disabled' : '';
    $next_url = $pagination_data['current_page'] < $pagination_data['total_pages']
        ? $base_url . '?' . http_build_query(array_merge([$page_param => $pagination_data['current_page'] + 1], $additional_params))
        : '#';
    
    $html .= sprintf(
        '<a href="%s" class="btn btn-sm btn-secondary %s" %s>Next &raquo;</a>',
        htmlspecialchars($next_url),
        $next_disabled,
        $next_disabled ? 'onclick="return false;"' : ''
    );
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render item count display
 */
function render_item_count($pagination_data, $item_name) {
    return sprintf(
        '<div class="bottom-item-count text-center text-light mt-3">
            Showing %d-%d of %d %s
        </div>',
        $pagination_data['showing_start'],
        $pagination_data['showing_end'],
        $pagination_data['total_items'],
        htmlspecialchars($item_name)
    );
}

/**
 * Render admin pagination controls with JavaScript integration
 */
function render_admin_pagination($pagination_data, $table_name) {
    if ($pagination_data['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<!-- Top Pagination Controls -->
                            <div class="top-pagination d-flex justify-content-center align-items-center mb-3" style="gap: 8px;">
                                <button class="btn btn-outline-light btn-sm" ' . ($pagination_data['current_page'] <= 1 ? 'disabled' : '') . '
                                        onclick="loadTableDataNoScroll(\'' . htmlspecialchars($table_name) . '\', ' . ($pagination_data['current_page'] - 1) . ')" style="margin-right: 10px; min-width: 80px;">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                                <div class="dropdown" style="margin-right: 10px;">
                                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" 
                                            data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 160px;">
                                        Page ' . $pagination_data['current_page'] . ' of ' . $pagination_data['total_pages'] . '
                                    </button>
                                    <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">';
    
    for ($i = 1; $i <= $pagination_data['total_pages']; $i++) {
        $active_class = $i == $pagination_data['current_page'] ? 'active' : '';
        $html .= sprintf(
            '<li><a class="dropdown-item %s" href="#" data-page="%d" data-table="%s">Page %d of %d</a></li>',
            $active_class,
            $i,
            htmlspecialchars($table_name),
            $i,
            $pagination_data['total_pages']
        );
    }
    
    $html .= '                    </ul>
                                </div>
                                <button class="btn btn-outline-light btn-sm" ' . ($pagination_data['current_page'] >= $pagination_data['total_pages'] ? 'disabled' : '') . '
                                        onclick="loadTableDataNoScroll(\'' . htmlspecialchars($table_name) . '\', ' . ($pagination_data['current_page'] + 1) . ')" style="min-width: 70px;">
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>';
    
    return $html;
}

/**
 * Render admin table with pagination
 */
function render_admin_table($config) {
    // Extract configuration
    $data = $config['data'] ?? [];
    $columns = $config['columns'] ?? [];
    $pagination_data = $config['pagination_data'] ?? null;
    $table_name = $config['table_name'] ?? '';
    $empty_message = $config['empty_message'] ?? 'No items found.';
    
    $html = '';
    
    // If no data, show empty message
    if (empty($data)) {
        return '<p class="text-light">' . htmlspecialchars($empty_message) . '</p>';
    }
    
    // Render pagination controls if needed
    if ($pagination_data && $pagination_data['total_pages'] > 1) {
        $html .= render_admin_pagination($pagination_data, $table_name);
    }
    
    // Start table
    $html .= '<div class="table-responsive">
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>';
    
    // Render column headers
    foreach ($columns as $column) {
        $html .= '<th class="text-light">' . htmlspecialchars($column['title']) . '</th>';
    }
    
    $html .= '      </tr>
                    </thead>
                    <tbody>';
    
    // Render data rows
    foreach ($data as $row) {
        $html .= '<tr>';
        
        foreach ($columns as $column) {
            $html .= '<td class="text-light">';
            
            // Handle different column types
            if (isset($column['render']) && is_callable($column['render'])) {
                // Custom render function
                $html .= $column['render']($row);
            } elseif (isset($column['key'])) {
                // Simple field display
                $value = $row[$column['key']] ?? '';
                
                // Apply formatting based on column type
                switch ($column['type'] ?? 'text') {
                    case 'link':
                        $url = $row[$column['url_key'] ?? $column['key']] ?? '';
                        $text = $value;
                        if (isset($column['truncate']) && strlen($text) > $column['truncate']) {
                            $text = substr($text, 0, $column['truncate']) . '...';
                        }
                        $html .= sprintf(
                            '<a href="%s" target="_blank" class="text-info text-decoration-underline">%s</a>',
                            htmlspecialchars($url),
                            htmlspecialchars($text)
                        );
                        break;
                        
                    case 'external_link':
                        $url = $row[$column['url_key'] ?? $column['key']] ?? '';
                        $text = $value;
                        if (isset($column['truncate']) && strlen($text) > $column['truncate']) {
                            $text = substr($text, 0, $column['truncate']) . '...';
                        }
                        $html .= sprintf(
                            '<a href="%s" target="_blank" class="text-info text-decoration-none">%s <i class="fas fa-external-link-alt ms-1" style="font-size: 0.8rem;"></i></a>',
                            htmlspecialchars($url),
                            htmlspecialchars($text)
                        );
                        break;
                        
                    case 'date':
                        $date = $value ? date('Y-m-d H:i', strtotime($value)) : 'Unknown';
                        $html .= htmlspecialchars($date);
                        break;
                        
                    case 'truncated_text':
                        $truncate_length = $column['truncate'] ?? 200;
                        $text = strlen($value) > $truncate_length ? substr($value, 0, $truncate_length) . '...' : $value;
                        $html .= htmlspecialchars($text);
                        break;
                        
                    default:
                        $html .= htmlspecialchars($value);
                }
            }
            
            $html .= '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '    </tbody>
                </table>
            </div>';
    
    // Render bottom item count
    if ($pagination_data) {
        $html .= render_item_count($pagination_data, $table_name);
    }
    
    return $html;
}

/**
 * Render a channel card
 */
function render_channel_card($channel) {
    $channel_url = get_channel_url($channel);
    ?>
    <div class="channel-card h-100 d-flex flex-column">
        <div class="channel-thumbnail">
            <?php if (!empty($channel['thumbnail_url'])): ?>
                <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>"
                     alt="<?php echo htmlspecialchars($channel['name']); ?>"
                     onclick="window.location.href='<?php echo $channel_url; ?>'">
            <?php else: ?>
                <div class="channel-thumbnail-placeholder d-flex justify-content-center align-items-center"
                     onclick="window.location.href='<?php echo $channel_url; ?>'">
                    <span class="placeholder-icon">📺</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="channel-info p-3 d-flex flex-column flex-grow-1">
            <h3 class="channel-title mb-2">
                <a href="<?php echo $channel_url; ?>" class="text-decoration-none text-light">
                    <?php echo htmlspecialchars($channel['name']); ?>
                </a>
            </h3>

            <!-- Channel Stats -->
            <div class="channel-stats mb-2">
                <span class="badge bg-secondary"><?php echo $channel['video_count']; ?> videos</span>
            </div>

            <?php if (!empty($channel['description'])): ?>
                <p class="channel-description mb-2">
                    <?php
                        $description = htmlspecialchars($channel['description']);
                        echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                    ?>
                </p>
            <?php endif; ?>

            <!-- Channel Categories -->
            <?php if (!empty($channel['category_names'])): ?>
                <div class="channel-categories mb-2 mt-auto">
                    <?php
                    $categories = explode(', ', $channel['category_names']);
                    $unique_categories = array_unique(array_map('trim', $categories));
                    foreach ($unique_categories as $cat):
                        if (!empty($cat)):
                    ?>
                        <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($cat); ?></span>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>

            <!-- External Link -->
            <div class="channel-actions">
                <a href="<?php echo $channel_url; ?>"
                   class="btn btn-primary btn-sm me-2">
                    View Channel
                </a>
                <a href="<?php echo htmlspecialchars($channel['youtube_url']); ?>"
                   target="_blank"
                   class="btn btn-outline-light btn-sm">
                    <i class="fas fa-external-link-alt"></i> YouTube
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render a video card
 */
function render_video_card($video) {
    $video_url = get_video_url($video);
    $channel_url = !empty($video['channel_slug'])
        ? app_url('channel/' . urlencode($video['channel_slug']))
        : app_url('channel?id=' . $video['channel_id']);
    ?>
    <div class="video-card d-flex flex-column">
        <div class="video-thumbnail">
            <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>"
                 alt="<?php echo htmlspecialchars($video['title']); ?>"
                 onclick="window.location.href='<?php echo $video_url; ?>'">
        </div>
        <div class="video-info p-3 d-flex flex-column flex-grow-1">
            <h3 class="video-title mb-2"><?php echo htmlspecialchars($video['title']); ?></h3>
            <p class="video-channel mb-2">
                <a href="<?php echo $channel_url; ?>" class="channel-link">
                    <?php echo htmlspecialchars($video['channel_name']); ?>
                </a>
            </p>
            <p class="video-date text-muted small mb-2"><?php echo format_upload_date($video['published_at']); ?></p>
            <?php if ($video['category']): ?>
                <div class="video-categories mb-2 mt-auto">
                    <?php
                    $categories = explode(', ', $video['category']);
                    foreach ($categories as $cat):
                        if (trim($cat)):
                    ?>
                        <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($cat)); ?></span>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

?>
