<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid p-0">
        <br>

        <!-- Suggested Videos Section -->
        <section class="video-section mb-4 px-4">
            <h2 class="section-title mb-3">Suggested Videos</h2>
            <div class="video-row d-flex pb-3">
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
                            <div class="video-info p-3">
                                <h3 class="video-title mb-2"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <p class="video-channel mb-2"><?php echo htmlspecialchars($video['channel_name']); ?></p>
                                <?php if ($video['category'] && $video['category'] !== 'Not Categorized'): ?>
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
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- New Channels Section -->
        <section class="video-section mb-4 px-4">
            <h2 class="section-title mb-3">New Channels</h2>
            <div class="video-row d-flex pb-3">
                <?php if (!empty($new_channels)): ?>
                    <?php foreach ($new_channels as $channel): ?>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <a href="/channel?id=<?php echo $channel['id']; ?>">
                                    <?php if (!empty($channel['thumbnail_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($channel['name']); ?> Channel">
                                    <?php else: ?>
                                        <div class="thumbnail-placeholder channel-placeholder d-flex flex-column justify-content-center align-items-center">
                                            <span class="placeholder-icon">📺</span>
                                            <span class="placeholder-text">Channel</span>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="video-info p-3">
                                <h3 class="video-title mb-2"><?php echo htmlspecialchars($channel['name']); ?></h3>
                                <p class="video-channel mb-2"><?php echo $channel['video_count']; ?> videos</p>
                                <p class="video-category mb-0">Channel</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 