<?php
/**
 * Handle suggestion submissions with semaphore locking
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['link'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$link = trim($input['link']);

// Basic input validation
if (empty($link)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a link']);
    exit;
}

// Validate YouTube URL
function isValidYouTubeURL($url) {
    // Check if it's a valid URL first
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Check if it's a YouTube domain
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return false;
    }
    
    $host = strtolower($parsed['host']);
    $validHosts = ['youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com'];
    
    if (!in_array($host, $validHosts)) {
        return false;
    }
    
    // Check for valid YouTube patterns
    $patterns = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',           // Video
        '/youtube\.com\/channel\/([a-zA-Z0-9_-]+)/',              // Channel ID
        '/youtube\.com\/c\/([a-zA-Z0-9_-]+)/',                    // Custom channel
        '/youtube\.com\/user\/([a-zA-Z0-9_-]+)/',                 // User channel
        '/youtube\.com\/@([a-zA-Z0-9_.-]+)/',                     // Handle
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',                       // Short video URL
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }
    
    return false;
}

if (!isValidYouTubeURL($link)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid YouTube channel or video URL']);
    exit;
}

// Semaphore locking to prevent concurrent writes
$semaphore_key = ftok(__FILE__, 's');
$semaphore = sem_get($semaphore_key, 1, 0666, 1);

if (!sem_acquire($semaphore)) {
    echo json_encode(['success' => false, 'error' => 'Unable to acquire lock. Please try again.']);
    exit;
}

try {
    // Connect to suggestions database
    $suggestions_db = new PDO('sqlite:suggestions.db');
    $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Use INSERT OR REPLACE to handle duplicates gracefully
    // This will insert new record or update timestamp if link already exists
    $upsert_stmt = $suggestions_db->prepare("
        INSERT OR REPLACE INTO suggestions (id, link, timestamp) 
        VALUES (
            (SELECT id FROM suggestions WHERE link = ?), 
            ?, 
            CURRENT_TIMESTAMP
        )
    ");
    
    if ($upsert_stmt->execute([$link, $link])) {
        echo json_encode(['success' => true, 'message' => 'Thank you! Your suggestion has been submitted.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save suggestion. Please try again.']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
} finally {
    // Always release the semaphore
    sem_release($semaphore);
}
?>