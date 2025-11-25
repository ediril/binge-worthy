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

$video_id = $_GET['id'] ?? '';

if (empty($video_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Video ID is required'
    ]);
    exit;
}

try {
    $db = get_database();
    $stmt = $db->prepare('SELECT v.*, v.slug as video_slug, c.name as channel_title, c.slug as channel_slug
                          FROM videos v
                          LEFT JOIN channels c ON v.channel_id = c.id
                          WHERE v.id = ?');
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Video not found'
        ]);
        exit;
    }

    // Ensure we have a YouTube ID
    $youtube_id = extractYouTubeId($video['video_url']);
    if (!$youtube_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid YouTube URL'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'video' => [
            'id' => $video['id'],
            'title' => $video['title'],
            'slug' => $video['video_slug'],
            'video_url' => $video['video_url'],
            'youtube_id' => $youtube_id,
            'channel_id' => $video['channel_id'],
            'channel_title' => $video['channel_title'],
            'channel_slug' => $video['channel_slug'],
            'published_at' => $video['published_at'],
            'thumbnail_url' => $video['thumbnail_url']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>