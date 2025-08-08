<?php
header('Content-Type: application/json');

// Include database and common functions
require_once '../database.php';
require_once '../common.php';

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$youtube_id = $_GET['youtube_id'] ?? '';

if (!$youtube_id) {
    echo json_encode(['success' => false, 'error' => 'YouTube ID required']);
    exit;
}

try {
    $db = getDB();
    
    // Search for video by YouTube ID in the video URL
    $stmt = $db->prepare('SELECT id FROM videos WHERE video_url LIKE ? AND is_active = 1 LIMIT 1');
    $stmt->execute(["%{$youtube_id}%"]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($video) {
        echo json_encode([
            'success' => true, 
            'video_id' => $video['id']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Video not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error finding video: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error'
    ]);
}
?>