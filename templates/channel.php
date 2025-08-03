<?php
require_once 'common.php';
require_once 'database.php';

// Get channel ID from URL parameter
$channel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($channel_id <= 0) {
    header('Location: /');
    exit;
}

// Get database connection
$db = get_database();

// Get channel information
$channel = get_channel_by_id($db, $channel_id);
if (!$channel) {
    header('Location: /');
    exit;
}

// Get videos for this channel
$videos = get_videos_by_channel($db, $channel_id);

$page_title = $channel['name'] . ' - Binge-Worthy';
$current_page = 'channel';
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid">
        <br>

        <!-- Channel Header -->
        <div class="channel-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <?php if (!empty($channel['thumbnail_url'])): ?>
                        <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" 
                             alt="<?php echo htmlspecialchars($channel['name']); ?>" 
                             class="channel-avatar">
                    <?php else: ?>
                        <div class="channel-avatar-placeholder">
                            <span class="placeholder-icon">📺</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <h1 class="channel-name"><?php echo htmlspecialchars($channel['name']); ?></h1>
                    <?php if (!empty($channel['description'])): ?>
                        <p class="channel-description"><?php echo htmlspecialchars($channel['description']); ?></p>
                    <?php endif; ?>
                    <div class="channel-stats">
                        <span class="badge bg-secondary"><?php echo count($videos); ?> videos</span>
                        <a href="<?php echo htmlspecialchars($channel['youtube_url']); ?>" 
                           target="_blank" 
                           class="btn btn-outline-primary btn-sm ms-2">
                            Visit YouTube Channel
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Videos Section -->
        <section class="video-section">            
            <?php if (empty($videos)): ?>
                <div class="alert alert-info">
                    <h4>No videos found</h4>
                    <p>This channel doesn't have any videos in our database yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($videos as $video): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-4">
                            <div class="video-card h-100">
                                <div class="video-thumbnail">
                                    <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($video['title']); ?>"
                                         onclick="openVideo('<?php echo htmlspecialchars($video['video_url']); ?>')">
                                </div>
                                <div class="video-info p-3">
                                    <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                    <p class="video-channel"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                                    <?php if ($video['category']): ?>
                                        <div class="video-categories mb-2">
                                            <?php
                                            $categories = explode(', ', $video['category']);
                                            foreach ($categories as $cat):
                                                if (trim($cat)):
                                            ?>
                                                <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                            <?php
                                                endif;
                                            endforeach;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 