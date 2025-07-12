<?php
/**
 * Universal routing - works with both PHP built-in server and Apache
 */

// Check if we're running under PHP's built-in server
if (php_sapi_name() === 'cli-server') {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    $path = ltrim($path, '/');
    
    // Handle static files
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico)$/', $path)) {
        return false; // Let the built-in server handle static files
    }
    
    // Handle clean URLs for built-in server
    if (!empty($path) && $path !== 'index.php') {
        if (file_exists($path . '.php')) {
            // If corresponding PHP file exists, include it and exit
            require $path . '.php';
            exit;
        } else {
            // 404 - file not found
            http_response_code(404);
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>404 - Page Not Found</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body>
                <div class="container mt-5">
                    <div class="row justify-content-center">
                        <div class="col-md-6 text-center">
                            <h1>404</h1>
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
    }
}

// Regular homepage logic (for Apache or when accessing root)
require_once 'common.php';
require_once 'database.php';

// Get featured videos for homepage
$db = get_database();
$new_videos = get_new_videos($db);
$new_channels = get_new_channels($db);
$suggested_videos = get_suggested_videos($db);
?>

<!DOCTYPE html>
<html lang="en">
<?php $page_title = 'Binge-Worthy - Educational Content Platform'; include 'templates/head.php'; ?>
<body>
    <?php $current_page = 'home'; include 'templates/navbar.php'; ?>

    <div class="container-fluid">
        <br>

        <!-- New Videos Section -->
        <section class="video-section">
            <h2 class="section-title">New Videos</h2>
            <div class="video-row">
                <?php if (empty($new_videos)): ?>
                    <p class="text-muted">No new videos available.</p>
                <?php else: ?>
                    <?php foreach ($new_videos as $video): ?>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>"
                                     onclick="openVideo('<?php echo htmlspecialchars($video['video_url']); ?>')">
                            </div>
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <p class="video-channel"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                                <?php if ($video['category'] !== 'Not Categorized'): ?>
                                    <p class="video-category"><?php echo htmlspecialchars($video['category']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- New Channels Section -->
        <section class="video-section">
            <h2 class="section-title">New Channels</h2>
            <div class="video-row">
                <?php if (empty($new_channels)): ?>
                    <p class="text-muted">No new channels available.</p>
                <?php else: ?>
                    <?php foreach ($new_channels as $channel): ?>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <a href="/channel?id=<?php echo $channel['id']; ?>">
                                    <?php if (!empty($channel['thumbnail_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($channel['name']); ?> Channel">
                                    <?php else: ?>
                                        <div class="thumbnail-placeholder channel-placeholder">
                                            <span class="placeholder-icon">📺</span>
                                            <span class="placeholder-text">Channel</span>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($channel['name']); ?></h3>
                                <p class="video-channel"><?php echo $channel['video_count']; ?> videos</p>
                                <p class="video-category">Channel</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Suggested Videos Section -->
        <section class="video-section">
            <h2 class="section-title">Suggested Videos</h2>
            <div class="video-row">
                <?php if (empty($suggested_videos)): ?>
                    <p class="text-muted">No suggested videos available.</p>
                <?php else: ?>
                    <?php foreach ($suggested_videos as $video): ?>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>"
                                     onclick="openVideo('<?php echo htmlspecialchars($video['video_url']); ?>')">
                            </div>
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <p class="video-channel"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                                <?php if ($video['category'] !== 'Not Categorized'): ?>
                                    <p class="video-category"><?php echo htmlspecialchars($video['category']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include 'templates/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 