<?php
/**
 * Universal routing - works with both PHP built-in server and Apache
 */

require_once __DIR__ . '/banalytiq/banalytiq.php';

// Function to show 404 error page
function show_404_page() {
    http_response_code(404);
    record_visit(); // Record the 404 visit
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Page Not Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="static/styles.css?v=' . filemtime('static/styles.css') . '">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center">
                    <h1 style="font-size: 4rem; color: #e50914;">404</h1>
                    <h2>Page Not Found</h2>
                    <p>The page you are looking for does not exist.</p>
                    <a href="/" class="btn btn-primary">Go Home</a>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

// Get the current path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = ltrim($path, '/');

// Handle static files for PHP built-in server
if (php_sapi_name() === 'cli-server' && preg_match('/\.(css|js|jpg|jpeg|png|gif|ico)$/', $path)) {
    return false; // Let the built-in server handle static files
}

// Define valid pages and their corresponding files
$valid_pages = [
    '' => 'home',
    'index.php' => 'home',
    'about' => 'about',
    'about.php' => 'about',
    'admin' => 'admin',
    'admin.php' => 'admin',
    'categories' => 'categories',
    'categories.php' => 'categories',
    'channel' => 'channel',
    'channel.php' => 'channel'
];

// Determine which page to load
$page_to_load = 'home'; // default
if (array_key_exists($path, $valid_pages)) {
    $page_to_load = $valid_pages[$path];
} else {
    // Check if it's a path with parameters (like categories?category=1 or channel?id=1)
    $path_parts = explode('?', $path);
    $base_path = $path_parts[0];
    
    if (array_key_exists($base_path, $valid_pages)) {
        $page_to_load = $valid_pages[$base_path];
    } else {
        show_404_page();
    }
}

// Record the visit after routing decisions are made
record_visit();

// Load the appropriate page
switch ($page_to_load) {
    case 'home':
        // Homepage logic
        require_once 'common.php';
        require_once 'database.php';

        // Get featured videos for homepage
        $db = get_database();
        $new_videos = get_new_videos($db);
        $new_channels = get_new_channels($db);
        $suggested_videos = get_suggested_videos($db);
        
        $page_title = 'BingeWorthy - Edutainment Videos';
        $current_page = 'home';
        include 'templates/home.php';
        break;
        
    case 'about':
        $page_title = 'About - BingeWorthy';
        $current_page = 'about';
        include 'templates/about.php';
        break;
        
    case 'admin':
        include 'templates/admin.php';
        break;
        
    case 'categories':
        include 'templates/categories.php';
        break;
        
    case 'channel':
        include 'templates/channel.php';
        break;
        
    default:
        show_404_page();
} 