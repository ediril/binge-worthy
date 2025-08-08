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

$limit = (int)($_GET['limit'] ?? 10);

try {
    $db = get_database();
    
    // Get suggested videos (random videos from database)
    $stmt = $db->prepare('SELECT v.*, c.name as channel_name 
                          FROM videos v 
                          LEFT JOIN channels c ON v.channel_id = c.id 
                          ORDER BY RANDOM() 
                          LIMIT ?');
    $stmt->execute([$limit]);
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