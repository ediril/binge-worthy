<?php
/**
 * Universal routing - works with both PHP built-in server and Apache
 */

require_once __DIR__ . '/banalytiq/banalytiq.php';
require_once __DIR__ . '/common.php';

// 404 function is now in common.php

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
    'channels' => 'channels',
    'channels.php' => 'channels',
    'channel' => 'channel',
    'channel.php' => 'channel',
    'play' => 'play',
    'play.php' => 'play'
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
        require_once 'database.php';

        // Get featured videos for homepage
        $db = get_database();
        $new_videos = get_new_videos($db, 10);
        $new_channels = get_new_channels($db);
        $suggested_videos = get_suggested_videos($db, 10);
        
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
        
    default:
        show_404_page();
} 