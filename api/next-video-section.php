<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../common.php';

$video_ts = $_GET['video_ts'] ?? '';
$channel_id = $_GET['channel_id'] ?? '';

if (empty($video_ts) || empty($channel_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Video timestamp and Channel ID are required'
    ]);
    exit;
}

try {
    $db = get_database();

    $next_video = get_next_channel_video($db, $channel_id, $video_ts);

    // Generate the HTML for the next video section
    $html = generate_next_video_section($db, $next_video);
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>