<?php
/**
 * Universal routing - works with both PHP built-in server and Apache
 */

require_once __DIR__ . '/../_banalytiq/banalytiq.php';
require_once __DIR__ . '/common.php';

// 404 function is now in common.php

// Get the current path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = preg_replace('#^' . preg_quote(APP_BASE_PATH, '#') . '(?:/|$)#', '', $path);
$path = trim($path, '/');

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
    'blog' => 'blog',
    'blog.php' => 'blog',
    'categories' => 'categories',
    'categories.php' => 'categories',
    'channels' => 'channels',
    'channels.php' => 'channels',
    'channel' => 'channel',
    'channel.php' => 'channel',
    'play' => 'play',
    'play.php' => 'play',
    'legal' => 'legal',
    'legal.php' => 'legal',
    'privacy' => 'privacy',
    'privacy.php' => 'privacy'
];

// Determine which page to load
$page_to_load = 'home'; // default

// Handle /channel/slug-name pattern (from .htaccess rewrite or direct)
if (preg_match('#^channel/([a-zA-Z0-9-]+)/?$#', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $page_to_load = 'channel';
} elseif (isset($_GET['slug']) && !empty($_GET['slug'])) {
    // Slug passed via query string from .htaccess rewrite
    $page_to_load = 'channel';
}
// Handle /play/slug-name pattern (from .htaccess rewrite or direct)
elseif (preg_match('#^play/([a-zA-Z0-9-]+)/?$#', $path, $matches)) {
    $_GET['video_slug'] = $matches[1];
    $page_to_load = 'play';
} elseif (isset($_GET['video_slug']) && !empty($_GET['video_slug'])) {
    // Video slug passed via query string from .htaccess rewrite
    $page_to_load = 'play';
}
// Handle /categories/slug-name pattern (from .htaccess rewrite or direct)
elseif (preg_match('#^categories/([a-zA-Z0-9-]+)/?$#', $path, $matches)) {
    $_GET['category_slug'] = $matches[1];
    $page_to_load = 'categories';
} elseif (isset($_GET['category_slug']) && !empty($_GET['category_slug'])) {
    // Category slug passed via query string from .htaccess rewrite
    $page_to_load = 'categories';
} elseif (array_key_exists($path, $valid_pages)) {
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
        require_once 'database.php';

        // Get featured videos for homepage
        $db = get_database();
        $new_videos = get_new_videos($db, 10);
        $new_channels = get_new_channels($db);
        $suggested_videos = get_suggested_videos($db, 10);
        
        // Check if we should show the new channels section
        // Show if: 1) There are new channels, OR 2) There are no channels at all
        // Hide if: There are channels but none are new
        $has_any_channels = has_any_channels($db);
        $show_new_channels_section = !empty($new_channels) || !$has_any_channels;
        
        $page_title = 'BingeWorthy - Edutainment Videos';
        $current_page = 'home';
        $additional_css = ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'];
        include 'templates/index.php';
        break;
        
    case 'about':
        $page_title = 'About - BingeWorthy';
        $current_page = 'about';
        include 'templates/about.php';
        break;
        
    case 'blog':
        $page_title = 'Blog - BingeWorthy';
        $current_page = 'blog';
        include 'templates/blog.php';
        break;
        
    case 'admin':
        require_once 'database.php';
        $page_title = 'Admin - BingeWorthy';
        $current_page = 'admin';
        include 'templates/admin.php';
        break;
        
    case 'categories':
        include 'templates/categories.php';
        break;
        
    case 'channels':
        include 'templates/channels.php';
        break;
        
    case 'channel':
        include 'templates/channel.php';
        break;
        
    case 'play':
        include 'templates/play.php';
        break;

    case 'legal':
        $page_title = 'Legal - BingeWorthy';
        $current_page = 'legal';
        include 'templates/legal.php';
        break;

    case 'privacy':
        $page_title = 'Privacy Policy - BingeWorthy';
        $current_page = 'privacy';
        include 'templates/privacy.php';
        break;

    default:
        show_404_page();
}
