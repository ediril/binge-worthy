<?php
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Binge-Worthy - Educational Content Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="static/styles.css">
</head>
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
                                <p class="video-category"><?php echo htmlspecialchars($video['category']); ?></p>
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
                                <a href="channel.php?id=<?php echo $channel['id']; ?>">
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
                                <p class="video-category"><?php echo htmlspecialchars($video['category']); ?></p>
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