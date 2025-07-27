<?php
/**
 * AJAX endpoint for admin pagination
 */

require_once 'common.php';
require_once 'database.php';

/**
 * Clean up YouTube URLs to their base form
 */
function cleanup_youtube_url($url) {
    $url = trim($url);
    
    // Handle bare handles like "@StarTalk" or "StarTalk"
    if (preg_match('/^@?([a-zA-Z0-9_.-]+)$/', $url, $matches)) {
        $handle = $matches[1];
        return "https://www.youtube.com/@" . $handle;
    }
    
    // If it's not a YouTube URL, return as is
    if (strpos($url, 'youtube.com') === false && strpos($url, 'youtu.be') === false) {
        return $url;
    }
    
    // Handle video URLs - extract video ID and create clean URL (only for valid 11-character IDs)
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
        $video_id = $matches[1];
        return "https://youtube.com/watch?v=" . $video_id;
    }
    
    // For youtu.be short URLs without video ID pattern, remove query parameters
    if (strpos($url, 'youtu.be') !== false) {
        return strtok($url, '?');
    }
    
    // For channel URLs, remove query parameters and trailing paths
    $base_url = strtok($url, '?'); // Remove query parameters first
    
    // Normalize domain to include www for consistency
    $base_url = str_replace('https://youtube.com/', 'https://www.youtube.com/', $base_url);
    
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
 * Generate possible URL variations for a YouTube channel
 */
function generate_channel_url_variations($url, $channel_name) {
    $variations = [];
    
    // Extract handle if present
    if (preg_match('/youtube\.com\/@([^\/\?]+)/', $url, $matches)) {
        $handle = $matches[1];
        $variations[] = "https://www.youtube.com/@" . $handle;
        $variations[] = "https://youtube.com/@" . $handle;
        $variations[] = "https://www.youtube.com/c/" . $handle;
        $variations[] = "https://youtube.com/c/" . $handle;
        $variations[] = "https://www.youtube.com/user/" . $handle;
        $variations[] = "https://youtube.com/user/" . $handle;
    }
    
    // Extract user if present
    if (preg_match('/youtube\.com\/user\/([^\/\?]+)/', $url, $matches)) {
        $user = $matches[1];
        $variations[] = "https://www.youtube.com/user/" . $user;
        $variations[] = "https://youtube.com/user/" . $user;
        $variations[] = "https://www.youtube.com/@" . $user;
        $variations[] = "https://youtube.com/@" . $user;
        $variations[] = "https://www.youtube.com/c/" . $user;
        $variations[] = "https://youtube.com/c/" . $user;
    }
    
    // Extract custom channel if present
    if (preg_match('/youtube\.com\/c\/([^\/\?]+)/', $url, $matches)) {
        $custom = $matches[1];
        $variations[] = "https://www.youtube.com/c/" . $custom;
        $variations[] = "https://youtube.com/c/" . $custom;
        $variations[] = "https://www.youtube.com/@" . $custom;
        $variations[] = "https://youtube.com/@" . $custom;
        $variations[] = "https://www.youtube.com/user/" . $custom;
        $variations[] = "https://youtube.com/user/" . $custom;
    }
    
    // Generate variations based on channel name (for cases like StarTalk -> startalk)
    if ($channel_name) {
        $name_variations = [
            strtolower($channel_name),
            strtolower(str_replace(' ', '', $channel_name)),
            strtolower(str_replace(' ', '-', $channel_name)),
            $channel_name
        ];
        
        foreach ($name_variations as $name_var) {
            $variations[] = "https://www.youtube.com/@" . $name_var;
            $variations[] = "https://youtube.com/@" . $name_var;
            $variations[] = "https://www.youtube.com/c/" . $name_var;
            $variations[] = "https://youtube.com/c/" . $name_var;
            $variations[] = "https://www.youtube.com/user/" . $name_var;
            $variations[] = "https://youtube.com/user/" . $name_var;
        }
    }
    
    // Remove duplicates and the original URL
    $variations = array_unique($variations);
    $variations = array_filter($variations, function($var) use ($url) {
        return $var !== $url;
    });
    
    return array_values($variations);
}

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Handle content existence check
if (isset($input['action']) && $input['action'] === 'check_content_exists') {
    if (!isset($input['url'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    $url = trim($input['url']);
    
    try {
        $db = get_database();
        
        // Clean up the URL first for exact matching
        $cleaned_url = cleanup_youtube_url($url);
        
        // First check if it looks like a video URL pattern (regardless of ID length)
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $cleaned_url, $matches)) {
            $video_id = $matches[1];
            
            // Check if the video ID has the correct length (11 characters)
            if (strlen($video_id) !== 11) {
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'error' => 'url_not_found',
                    'message' => 'This YouTube video URL is invalid (video ID must be 11 characters).',
                    'debug' => "Invalid video ID length: $video_id (length: " . strlen($video_id) . ")"
                ]);
                exit;
            }
            
            // Check videos table
            $stmt = $db->prepare("SELECT id, title FROM videos WHERE video_url = ?");
            $stmt->execute([$cleaned_url]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($video) {
                echo json_encode([
                    'success' => true,
                    'exists' => true,
                    'type' => 'video',
                    'title' => $video['title'],
                    'id' => $video['id']
                ]);
                exit;
            }
            
            // Video not in our database - check if it actually exists on YouTube
            $video_metadata = fetch_youtube_video_metadata($cleaned_url);
            
            if ($video_metadata === null) {
                // Video URL doesn't exist on YouTube
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'error' => 'url_not_found',
                    'message' => 'This YouTube video URL does not exist or is not accessible.',
                    'debug' => "Video check failed for: $cleaned_url"
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'debug' => "Video exists on YouTube but not in our DB: $cleaned_url"
                ]);
                exit;
            }
        } else {
            // For channels, we need to check multiple possible URL formats
            // First try exact URL match
            $stmt = $db->prepare("SELECT id, name, youtube_url FROM channels WHERE youtube_url = ?");
            $stmt->execute([$cleaned_url]);
            $channel = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($channel) {
                echo json_encode([
                    'success' => true,
                    'exists' => true,
                    'type' => 'channel',
                    'name' => $channel['name'],
                    'id' => $channel['id']
                ]);
                exit;
            }
            
            // If no exact match, try to get channel metadata and check by YouTube channel ID
            $channel_metadata = fetch_youtube_channel_metadata($cleaned_url);
            if ($channel_metadata === null) {
                // URL looks valid but doesn't exist on YouTube or returned 404
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'error' => 'url_not_found',
                    'message' => 'This YouTube channel or video URL does not exist or is not accessible.'
                ]);
                exit;
            }
            
            if ($channel_metadata && isset($channel_metadata['name'])) {
                // Simple and fast: Check by channel name (case-insensitive)
                $stmt = $db->prepare("SELECT id, name, youtube_url FROM channels WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$channel_metadata['name']]);
                $channel = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($channel) {
                    echo json_encode([
                        'success' => true,
                        'exists' => true,
                        'type' => 'channel',
                        'name' => $channel['name'],
                        'id' => $channel['id']
                    ]);
                    exit;
                }
            }
        }
        
        // Not found
        echo json_encode([
            'success' => true,
            'exists' => false
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to check content existence'
        ]);
    }
    exit;
}

// Handle channel existence check
if (isset($input['action']) && $input['action'] === 'check_channel_exists') {
    if (!isset($input['video_url'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    $video_url = normalize_video_input(trim($input['video_url']));
    if (!$video_url) {
        echo json_encode(['success' => false, 'error' => 'Invalid video URL']);
        exit;
    }
    
    try {
        $db = get_database();
        
        // Fetch video metadata to get channel info
        $video_metadata = fetch_youtube_video_metadata($video_url);
        if (!$video_metadata || !isset($video_metadata['channel_url'])) {
            echo json_encode(['success' => false, 'error' => 'Could not fetch video metadata']);
            exit;
        }
        
        // Check if channel exists in database - first by URL
        $stmt = $db->prepare("SELECT id, name FROM channels WHERE youtube_url = ?");
        $stmt->execute([$video_metadata['channel_url']]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found by URL, try by channel name (case-insensitive)
        if (!$channel && isset($video_metadata['channel_name'])) {
            $stmt = $db->prepare("SELECT id, name FROM channels WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$video_metadata['channel_name']]);
            $channel = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($channel) {
            echo json_encode([
                'success' => true,
                'channel_exists' => true,
                'channel_name' => $channel['name'],
                'channel_id' => $channel['id']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'channel_exists' => false,
                'channel_name' => $video_metadata['channel_name'] ?? 'Unknown Channel'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to check channel existence'
        ]);
    }
    exit;
}

// Handle YouTube metadata fetching
if (isset($input['action']) && $input['action'] === 'fetch_metadata') {
    if (!isset($input['url']) || !isset($input['type'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    $url = trim($input['url']);
    $type = $input['type'];
    
    // Normalize the input
    if ($type === 'channel') {
        $url = normalize_channel_input($url);
    } else if ($type === 'video') {
        $url = normalize_video_input($url);
    }
    
    if (!$url) {
        echo json_encode(['success' => false, 'error' => 'Invalid input format']);
        exit;
    }
    
    try {
        $description = '';
        
        if ($type === 'video') {
            // Fetch video metadata
            $metadata = fetch_youtube_video_metadata($url);
            if ($metadata && isset($metadata['channel_name'])) {
                // For videos, we can use the channel name or video title as description base
                $description = "Video from " . $metadata['channel_name'];
                if (!empty($metadata['title'])) {
                    $description = $metadata['title'];
                }
            }
        } else if ($type === 'channel') {
            // Fetch channel metadata
            $metadata = fetch_youtube_channel_metadata($url);
            if ($metadata && isset($metadata['description']) && !empty($metadata['description'])) {
                // Truncate description if too long
                $description = $metadata['description'];
                if (strlen($description) > 500) {
                    $description = substr($description, 0, 500) . '...';
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'description' => $description,
            'metadata' => $metadata ?? null
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch metadata'
        ]);
    }
    exit;
}

// Handle pagination requests
if (!isset($input['table']) || !isset($input['page'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$table = $input['table'];
$page = max(1, intval($input['page']));

try {
    $db = get_database();
    
    switch ($table) {
        case 'channels':
            $per_page = ADMIN_CHANNELS_PER_PAGE;
            $offset = ($page - 1) * $per_page;
            
            // Get total count
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM channels");
            $count_stmt->execute();
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = max(1, ceil($total / $per_page));
            
            // Get channels data
            $stmt = $db->prepare("
                SELECT c.*, GROUP_CONCAT(cat.name, ', ') as category_names
                FROM channels c 
                LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
                LEFT JOIN categories cat ON cc.category_id = cat.id
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $per_page
            ]);
            break;
            
        case 'videos':
            $per_page = ADMIN_VIDEOS_PER_PAGE;
            $offset = ($page - 1) * $per_page;
            
            // Get total count
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM videos");
            $count_stmt->execute();
            $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = max(1, ceil($total / $per_page));
            
            // Get videos data
            $stmt = $db->prepare("
                SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
                FROM videos v 
                JOIN channels c ON v.channel_id = c.id 
                LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
                LEFT JOIN categories cat ON cc.category_id = cat.id
                GROUP BY v.id
                ORDER BY v.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $per_page
            ]);
            break;
            
        case 'suggestions':
            $per_page = ADMIN_SUGGESTIONS_PER_PAGE;
            $offset = ($page - 1) * $per_page;
            
            try {
                $suggestions_db = new PDO('sqlite:suggestions.db');
                $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Get total count
                $count_stmt = $suggestions_db->prepare("SELECT COUNT(*) as total FROM suggestions");
                $count_stmt->execute();
                $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $total_pages = max(1, ceil($total / $per_page));
                
                // Get suggestions data
                $stmt = $suggestions_db->prepare("SELECT * FROM suggestions ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_items' => $total,
                    'per_page' => $per_page
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => true,
                    'data' => [],
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'per_page' => $per_page
                ]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid table']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
?>