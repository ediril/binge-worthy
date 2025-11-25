<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();
$channel = null;

// Check for slug-based access first
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $_GET['slug'];
    $channel = get_channel_by_slug($db, $slug);

    if (!$channel) {
        show_404_page();
    }
}
// Handle legacy ID-based access with 301 redirect
elseif (isset($_GET['id'])) {
    $channel_id = intval($_GET['id']);
    if ($channel_id <= 0) {
        header('Location: /');
        exit;
    }

    $channel = get_channel_by_id($db, $channel_id);
    if (!$channel) {
        header('Location: /');
        exit;
    }

    // 301 permanent redirect to slug-based URL if slug exists
    if (!empty($channel['slug'])) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: /channel/' . urlencode($channel['slug']));
        exit;
    }
} else {
    header('Location: /');
    exit;
}

// Get videos for this channel
$videos = get_videos_by_channel($db, $channel['id']);

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
        <div class="channel-header mb-4 p-3">
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
                            <i class="fas fa-external-link-alt"></i> Visit YouTube Channel
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
                                         onclick="openVideo('<?php echo htmlspecialchars($video['video_url']); ?>', <?php echo $video['id']; ?>)">
                                </div>
                                <div class="video-info p-3">
                                    <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                    <p class="video-channel"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                                    <p class="video-date text-muted small"><?php echo format_upload_date($video['published_at']); ?></p>
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
