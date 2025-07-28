<?php
/**
 * Handle suggestion submissions with semaphore locking
 */

require_once 'common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if this is a JSON request or regular form submission
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
$is_json_request = strpos($content_type, 'application/json') !== false;

if ($is_json_request) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['link'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    $link = trim($input['link']);
} else {
    // Regular form submission
    if (!isset($_POST['link'])) {
        header('Location: /?error=' . urlencode('Invalid input'));
        exit;
    }
    $link = trim($_POST['link']);
}

// Basic input validation
if (empty($link)) {
    if ($is_json_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Please enter a link']);
    } else {
        header('Location: /?error=' . urlencode('Please enter a link'));
    }
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
    if ($is_json_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Please enter a valid YouTube channel or video URL']);
    } else {
        header('Location: /?error=' . urlencode('Please enter a valid YouTube channel or video URL'));
    }
    exit;
}

// Clean up the URL before saving (only for channel URLs, not video URLs)
if (preg_match('/watch\?v=|youtu\.be\//', $link)) {
    // This is a video URL - don't clean it, keep the video ID
    // Just ensure it's properly formatted
    $link = $link;
} else {
    // This is a channel URL - clean it
    $link = cleanup_youtube_url($link);
}

// File-based locking as fallback for servers without semaphore support
$lock_file = 'suggestions.lock';
$lock_handle = null;

// Try semaphore locking first (better), fall back to file locking
$use_semaphore = function_exists('sem_get') && function_exists('sem_acquire');

if ($use_semaphore) {
    $semaphore_key = ftok(__FILE__, 's');
    $semaphore = sem_get($semaphore_key, 1, 0666, 1);
    if (!sem_acquire($semaphore)) {
        if ($is_json_request) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unable to acquire lock. Please try again.']);
        } else {
            header('Location: /?error=' . urlencode('Unable to acquire lock. Please try again.'));
        }
        exit;
    }
} else {
    // Use file locking as fallback
    $lock_handle = fopen($lock_file, 'c');
    if (!$lock_handle || !flock($lock_handle, LOCK_EX)) {
        if ($is_json_request) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unable to acquire lock. Please try again.']);
        } else {
            header('Location: /?error=' . urlencode('Unable to acquire lock. Please try again.'));
        }
        exit;
    }
}

try {
    // Connect to suggestions database
    $suggestions_db = new PDO('sqlite:suggestions.db');
    $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create table if it doesn't exist
    $suggestions_db->exec("CREATE TABLE IF NOT EXISTS suggestions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        link TEXT NOT NULL UNIQUE,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
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
        if ($is_json_request) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Thank you! Your suggestion has been submitted.']);
        } else {
            header('Location: /?success=' . urlencode('Thank you! Your suggestion has been submitted.'));
        }
    } else {
        if ($is_json_request) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to save suggestion. Please try again.']);
        } else {
            header('Location: /?error=' . urlencode('Failed to save suggestion. Please try again.'));
        }
    }
    
} catch (PDOException $e) {
    if ($is_json_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    } else {
        header('Location: /?error=' . urlencode('Database error. Please try again.'));
    }
} finally {
    // Release the appropriate lock
    if ($use_semaphore) {
        sem_release($semaphore);
    } else {
        if ($lock_handle) {
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
        }
    }
}
?>