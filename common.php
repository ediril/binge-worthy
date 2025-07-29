<?php

// ================================
// Admin Configuration
// ================================
define('ADMIN_CHANNELS_PER_PAGE', 10);
define('ADMIN_VIDEOS_PER_PAGE', 10);
define('ADMIN_SUGGESTIONS_PER_PAGE', 10);

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
        WHERE v.created_at >= datetime('now', '-7 days')
        GROUP BY v.id
        ORDER BY v.created_at DESC 
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
        SELECT c.*, COUNT(v.id) as video_count
        FROM channels c 
        LEFT JOIN videos v ON c.id = v.channel_id
        WHERE c.created_at >= datetime('now', '-7 days')
        GROUP BY c.id
        ORDER BY RANDOM() 
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        WHERE v.created_at >= datetime('now', '-30 days')
        GROUP BY v.id
        ORDER BY v.created_at DESC 
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
    
    // Add channel without category_id
    $stmt = $db->prepare("
        INSERT INTO channels (name, description, youtube_url, thumbnail_url, created_at) 
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    if ($stmt->execute([$name, $description, $youtube_url, $thumbnail_url])) {
        $channel_id = $db->lastInsertId();
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
function add_video($db, $channel_id, $title, $video_url, $thumbnail_url, $is_featured = 0) {
    $stmt = $db->prepare("
        INSERT INTO videos (channel_id, title, video_url, thumbnail_url, is_featured, view_count) 
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    return $stmt->execute([$channel_id, $title, $video_url, $thumbnail_url, $is_featured]);
}

/**
 * Extract YouTube video ID from URL
 */
function extract_youtube_id($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? null;
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
    
    // If it's already a full URL, return as is
    if (filter_var($input, FILTER_VALIDATE_URL)) {
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
    error_log("DEBUG: oEmbed URL: $oembed_url");
    error_log("DEBUG: oEmbed response: " . ($response === false ? 'FALSE' : $response));
    
    if ($response === false) {
        error_log("DEBUG: Video doesn't exist - returning null");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        return null;
    }
    
    return [
        'title' => html_entity_decode($data['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'description' => '',
        'thumbnail_url' => generate_youtube_thumbnail($video_url),
        'channel_name' => html_entity_decode($data['author_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'channel_url' => $data['author_url'] ?? ''
    ];
}

/**
 * Fetch YouTube channel metadata with fallback for different URL formats
 */
function fetch_youtube_channel_metadata($channel_url) {
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
    $stmt = $db->prepare("
        INSERT INTO categories (name, created_at) 
        VALUES (?, datetime('now'))
    ");
    return $stmt->execute([$name]);
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
        ORDER BY v.created_at DESC
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
        WHERE cc.category_id = ?
        GROUP BY v.id
        ORDER BY v.created_at DESC
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
        
        // Clean up and normalize the input URL
        $normalized_url = cleanup_youtube_url($content_url);
        
        // Remove suggestions that match the cleaned URL exactly
        $delete_stmt = $suggestions_db->prepare("DELETE FROM suggestions WHERE TRIM(link) = ?");
        $deleted_count = 0;
        
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
        
        // Also try to match different URL formats for the same content
        $url_patterns = [];
        
        // For video URLs, check all variations
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/', $normalized_url, $matches) ||
            preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $normalized_url, $matches)) {
            $video_id = $matches[1];
            $url_patterns[] = "youtube.com/watch?v={$video_id}";
            $url_patterns[] = "youtu.be/{$video_id}";
            $url_patterns[] = "m.youtube.com/watch?v={$video_id}";
            $url_patterns[] = "www.youtube.com/watch?v={$video_id}";
        }
        
        // For channel URLs, check all variations
        if (preg_match('/youtube\.com\/(channel|c|user|@)\/([a-zA-Z0-9_.-]+)/', $normalized_url, $matches)) {
            $channel_type = $matches[1];
            $channel_id = $matches[2];
            if ($channel_type === '@') {
                $url_patterns[] = "youtube.com/@{$channel_id}";
                $url_patterns[] = "www.youtube.com/@{$channel_id}";
            } else {
                $url_patterns[] = "youtube.com/{$channel_type}/{$channel_id}";
                $url_patterns[] = "www.youtube.com/{$channel_type}/{$channel_id}";
            }
        }
        
        // Delete any matching patterns
        foreach ($url_patterns as $pattern) {
            $delete_stmt = $suggestions_db->prepare("DELETE FROM suggestions WHERE link LIKE ?");
            if ($delete_stmt->execute(["%{$pattern}%"])) {
                $deleted_count += $delete_stmt->rowCount();
            }
        }
        
        if ($deleted_count > 0) {
            error_log("Auto-removed {$deleted_count} matching suggestion(s) for: {$normalized_url}");
        }
        
    } catch (Exception $e) {
        // Don't break the main flow if suggestion removal fails
        error_log("Failed to auto-remove suggestions: " . $e->getMessage());
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

?> 