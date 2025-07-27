<?php
/**
 * Fetch YouTube metadata for auto-populating descriptions
 */

require_once 'common.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['url']) || !isset($input['type'])) {
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
?>