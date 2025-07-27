<?php

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
 * Get all categories excluding "Not Categorized" (for display on categories page)
 */
function get_display_categories($db) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE name != 'Not Categorized' ORDER BY name");
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
function add_video($db, $channel_id, $title, $description, $video_url, $thumbnail_url, $is_featured = 0) {
    $stmt = $db->prepare("
        INSERT INTO videos (channel_id, title, description, video_url, thumbnail_url, is_featured, view_count) 
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    return $stmt->execute([$channel_id, $title, $description, $video_url, $thumbnail_url, $is_featured]);
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
    
    if ($response === false) {
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
    
    // Method 1: Try to extract from JSON-LD structured data (most complete)
    if (preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
        $json_data = json_decode($matches[1], true);
        if (isset($json_data['description'])) {
            $description = html_entity_decode($json_data['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    // Method 2: Try to extract from YouTube's internal JSON data
    if (empty($description) && preg_match('/"description":"([^"]*(?:\\.[^"]*)*)"/', $html, $matches)) {
        $description = html_entity_decode(json_decode('"' . $matches[1] . '"'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Method 3: Fallback to meta description (usually truncated)
    if (empty($description) && preg_match('/<meta name="description" content="([^"]*)"/', $html, $matches)) {
        $description = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Try to extract channel thumbnail
    $thumbnail_url = null;
    if (preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $matches)) {
        $thumbnail_url = $matches[1];
    }
    
    return [
        'name' => $channel_name,
        'description' => $description,
        'youtube_url' => $channel_url,
        'thumbnail_url' => $thumbnail_url
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
    
    // Default to 'Not Categorized' category ID if category not found
    $stmt = $db->prepare("SELECT id FROM categories WHERE name = 'Not Categorized'");
    $stmt->execute();
    $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
    return $fallback ? $fallback['id'] : 1; // Fallback to ID 1 if 'Not Categorized' doesn't exist
}

/**
 * Add a new category
 */
function add_category($db, $name, $description = null) {
    $stmt = $db->prepare("
        INSERT INTO categories (name, description, created_at) 
        VALUES (?, ?, datetime('now'))
    ");
    return $stmt->execute([$name, $description]);
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

?> 