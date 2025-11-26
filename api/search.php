<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../common.php';

// Only handle GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$query = trim($_GET['q'] ?? '');

// Validate query length
if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Query too short', 'videos' => [], 'channels' => []]);
    exit;
}

try {
    $db = get_database();

    // Prepare query for FTS5 MATCH
    // Lowercase and convert spaces to AND logic
    $search_terms = preg_split('/\s+/', strtolower($query));
    $search_terms = array_filter($search_terms, fn($t) => strlen($t) >= 2);

    if (empty($search_terms)) {
        echo json_encode(['success' => true, 'videos' => [], 'channels' => []]);
        exit;
    }

    // Build FTS5 query with prefix matching
    $fts_query = implode(' AND ', array_map(fn($t) => '"' . $t . '"*', $search_terms));

    $videos = [];
    $channels = [];

    // Search videos
    try {
        $stmt = $db->prepare("
            SELECT 'video' as type, v.title, v.slug, v.thumbnail_url, c.name as channel_name
            FROM videos_fts
            JOIN videos v ON videos_fts.rowid = v.id
            JOIN channels c ON v.channel_id = c.id
            WHERE videos_fts MATCH ?
            ORDER BY rank
            LIMIT 8
        ");
        $stmt->execute([$fts_query]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // FTS query failed, possibly due to special characters - try simpler approach
        error_log("FTS video search failed: " . $e->getMessage());
    }

    // Search channels
    try {
        $stmt = $db->prepare("
            SELECT 'channel' as type, c.name as title, c.slug, c.thumbnail_url
            FROM channels_fts
            JOIN channels c ON channels_fts.rowid = c.id
            WHERE channels_fts MATCH ?
            ORDER BY rank
            LIMIT 4
        ");
        $stmt->execute([$fts_query]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // FTS query failed
        error_log("FTS channel search failed: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'query' => $query,
        'videos' => $videos,
        'channels' => $channels
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
