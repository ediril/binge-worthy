<?php
// Default meta values
if (!isset($page_title)) {
    $page_title = 'Binge Worthy Shows - Edutainment Videos';
}
if (!isset($page_description)) {
    $page_description = 'Watch intellectually stimulating videos on tech, business, coding, science, and AI. A curated alternative to brain-rotting entertainment.';
}
if (!isset($page_keywords)) {
    $page_keywords = 'educational videos, tech videos, coding tutorials, science documentaries, AI learning, business insights, edutainment';
}
if (!isset($og_image)) {
    $og_image = APP_SITE_URL . '/og_screenshot.jpg';
}
if (!isset($og_type)) {
    $og_type = 'website';
}
$current_url = 'https://emrahdiril.com' . $_SERVER['REQUEST_URI'];
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Primary Meta Tags -->
    <meta name="title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="author" content="Binge Worthy Shows">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?php echo htmlspecialchars($og_type); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Binge Worthy Shows">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter / X -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
    
    <!-- Additional Meta Tags -->
    <meta name="theme-color" content="#212529">
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>">

    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="<?php echo app_url('favicon.ico'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo app_url('favicon-16x16.png'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo app_url('favicon-32x32.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo app_url('apple-touch-icon.png'); ?>">
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php if (isset($additional_css) && is_array($additional_css)): ?>
        <?php foreach ($additional_css as $css_url): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_url); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php 
    // Temporary aggressive cache busting for testing
    $css_file = __DIR__ . '/../../static/styles.css';
    // Add current minute to force refresh every minute
    $css_version = file_exists($css_file) ? filemtime($css_file) . '-' . date('YmdHi') : time();
    ?>
    <link rel="stylesheet" href="<?php echo app_url('static/styles.css'); ?>?v=<?php echo $css_version; ?>">
    <script>
        window.appBasePath = <?php echo json_encode(APP_BASE_PATH); ?>;
        window.appUrl = function(path = '') {
            return window.appBasePath + '/' + path.replace(/^\/+/, '');
        };
    </script>
</head>
