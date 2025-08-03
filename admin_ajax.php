<?php
/**
 * AJAX endpoint for admin pagination
 */

require_once 'common.php';
require_once 'database.php';

// Function moved to common.php

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
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
if (!$input) {
    // Debug info for invalid input
    error_log("Invalid JSON input: " . $raw_input);
    echo json_encode(['error' => 'Invalid input', 'debug' => 'Raw input: ' . substr($raw_input, 0, 100)]);
    exit;
}

// Background video fetching endpoint  
if (isset($input['action']) && $input['action'] === 'fetch_channel_videos') {
    header('Content-Type: application/json');
    
    // Debug logging
    error_log("Fetch endpoint reached. Action: " . ($input['action'] ?? 'none'));
    error_log("Channel ID: " . ($input['channel_id'] ?? 'none'));
    
    try {
        require_once __DIR__ . '/fetch.php';
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load fetch module: ' . $e->getMessage()]);
        exit;
    }
    
    $channel_id = $input['channel_id'] ?? null;
    
    if (!$channel_id) {
        echo json_encode(['error' => 'Channel ID is required', 'debug' => 'Input received: ' . json_encode($input)]);
        exit;
    }
    
    try {
        // Get database connection
        $db = get_database();
        
        // Get channel info
        $stmt = $db->prepare("SELECT * FROM channels WHERE id = ?");
        $stmt->execute([$channel_id]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$channel) {
            echo json_encode(['error' => 'Channel not found']);
            exit;
        }
        
        // Fetch videos using RSS method
        $videos = fetch_channel_videos_rss($channel['youtube_url'], 15);
        $videos_added = add_videos($db, $channel_id, $videos);
        
        if ($videos_added > 0) {
            // Update last_fetched timestamp
            $update_stmt = $db->prepare("UPDATE channels SET last_fetched = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->execute([$channel_id]);
        }
        
        echo json_encode([
            'success' => true,
            'videos_added' => $videos_added,
            'channel_name' => $channel['name']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch videos: ' . $e->getMessage()]);
    }
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
        
        // For content existence check, normalize bare video IDs to full URLs
        // but don't clean video URLs as cleanup_youtube_url removes the video ID
        $url_to_check = $url;
        
        // If it's just a video ID (11 characters, alphanumeric), convert to full URL
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', trim($url))) {
            $url_to_check = 'https://www.youtube.com/watch?v=' . trim($url);
        }
        
        // First check if it looks like a video URL pattern (regardless of ID length)
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url_to_check, $matches)) {
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
            
            // Check videos table by extracting video ID from all stored URLs
            // This handles different URL formats (youtube.com, www.youtube.com, youtu.be, etc.)
            $stmt = $db->prepare("
                SELECT id, title, video_url FROM videos 
                WHERE video_url LIKE ? 
                OR video_url LIKE ? 
                OR video_url LIKE ?
            ");
            $video_id_pattern1 = "%watch?v=" . $video_id . "%";
            $video_id_pattern2 = "%youtu.be/" . $video_id . "%";
            $video_id_pattern3 = "%embed/" . $video_id . "%";
            
            $stmt->execute([$video_id_pattern1, $video_id_pattern2, $video_id_pattern3]);
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
            $video_metadata = fetch_youtube_video_metadata($url_to_check);
            
            if ($video_metadata === null) {
                // Video URL doesn't exist on YouTube
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'error' => 'url_not_found',
                    'message' => 'This YouTube video URL does not exist or is not accessible.',
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => true,
                    'exists' => false
                ]);
                exit;
            }
        } else {
            // For channels, we need to check multiple possible URL formats
            // First normalize the input, then clean it
            $normalized_url = normalize_channel_input($url);
            if (!$normalized_url) {
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'error' => 'url_not_found',
                    'message' => 'Invalid channel format. Please provide a valid YouTube channel URL, handle, or name.'
                ]);
                exit;
            }
            $cleaned_url = cleanup_youtube_url($normalized_url);
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
                // Use full description without truncation
                $description = $metadata['description'];
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

// Handle refetch channel description
if (isset($input['action']) && $input['action'] === 'refetch_channel_description') {
    if (!isset($input['channel_id']) && !isset($input['channel_url'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    try {
        $db = get_database();
        $channel_url = null;
        
        if (isset($input['channel_id'])) {
            // Edit mode - get URL from database
            $channel_id = intval($input['channel_id']);
            $stmt = $db->prepare("SELECT youtube_url FROM channels WHERE id = ?");
            $stmt->execute([$channel_id]);
            $channel = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$channel) {
                echo json_encode(['success' => false, 'error' => 'Channel not found']);
                exit;
            }
            $channel_url = $channel['youtube_url'];
        } else {
            // Add mode - use provided URL
            $channel_url = trim($input['channel_url']);
            $channel_url = normalize_channel_input($channel_url);
            
            if (!$channel_url) {
                echo json_encode(['success' => false, 'error' => 'Invalid channel URL']);
                exit;
            }
        }
        
        // Fetch latest metadata from YouTube
        $metadata = fetch_youtube_channel_metadata($channel_url);
        
        if ($metadata && isset($metadata['description']) && !empty($metadata['description'])) {
            echo json_encode([
                'success' => true,
                'description' => $metadata['description']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Could not fetch channel description from YouTube'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch channel description'
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
                ORDER BY v.published_at DESC
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