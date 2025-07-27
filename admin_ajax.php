<?php
/**
 * AJAX endpoint for admin pagination
 */

require_once 'common.php';
require_once 'database.php';

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