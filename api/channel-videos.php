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
$exclude_id = $_GET['exclude'] ?? '';
$limit = (int)($_GET['limit'] ?? 10);

if (empty($channel_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Channel ID is required'
    ]);
    exit;
}

try {
    $db = get_database();
    
    // Build query to get videos from the channel, excluding the current one
    $sql = 'SELECT v.*, c.name as channel_name 
            FROM videos v 
            LEFT JOIN channels c ON v.channel_id = c.id 
            WHERE v.channel_id = ?';
    $params = [$channel_id];
    
    if (!empty($exclude_id)) {
        $sql .= ' AND v.id != ?';
        $params[] = $exclude_id;
    }
    
    $sql .= ' ORDER BY RANDOM() LIMIT ?';
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'videos' => $videos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>