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

$channel_id = $_GET['channel_id'] ?? '';
$published_at = $_GET['published_at'] ?? '';

if (empty($channel_id) || empty($published_at)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Channel ID and published_at are required'
    ]);
    exit;
}

try {
    $db = get_database();
    
    // Use the shared function to get the next video
    $next_video = get_next_channel_video($db, $channel_id, $published_at);
    
    echo json_encode([
        'success' => true,
        'video' => $next_video
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>