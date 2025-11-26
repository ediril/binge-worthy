<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../common.php';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
$keywords = trim($input['query'] ?? '');
$selected_type = $input['type'] ?? '';
$selected_slug = $input['slug'] ?? '';

// Validate
if (strlen($keywords) < 2 || empty($selected_type) || empty($selected_slug)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Convert type string to integer (0=video, 1=channel)
$sel_type = ($selected_type === 'channel') ? 1 : 0;

try {
    $db = get_database();

    // Log the search selection
    $stmt = $db->prepare("
        INSERT INTO search_logs (keywords, sel_type, sel_slug, created_at)
        VALUES (?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$keywords, $sel_type, $selected_slug]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Search log error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Log failed']);
}
