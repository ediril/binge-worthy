<?php
$video_id = $_GET['id'] ?? '';
if (!$video_id) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/../database.php';
$db = get_database();
$stmt = $db->prepare('SELECT v.*, c.name as channel_title 
                      FROM videos v 
                      LEFT JOIN channels c ON v.channel_id = c.id 
                      WHERE v.id = ?');
$stmt->execute([$video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    header('Location: /');
    exit;
}

$youtube_id = extractYouTubeId($video['video_url']);
if (!$youtube_id) {
    header('Location: /');
    exit;
}

$page_title = $video['title'] . ' - BingeWorthy';
$page_description = 'Watch ' . $video['title'] . ' on BingeWorthy';
?>
<!DOCTYPE html>
<html lang="en">
<?php include 'partials/head.php'; ?>
<body>
    <?php include 'partials/navbar.php'; ?>
    
    <div class="video-player-container">
        <div class="container">
            <div class="video-player-info mb-4">
                <h1 class="video-player-title"><?php echo htmlspecialchars($video['title']); ?></h1>
                
                <div class="video-player-meta">
                    <a href="/channel?id=<?php echo htmlspecialchars($video['channel_id']); ?>" 
                       class="video-player-channel">
                        <?php echo htmlspecialchars($video['channel_title']); ?>
                    </a>
                    <span class="video-player-date">
                        <?php echo date('M j, Y', strtotime($video['published_at'])); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="video-player-wrapper">
            <iframe 
                id="video-player"
                class="video-player-iframe"
                src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>?autoplay=1&rel=0&enablejsapi=1"
                title="<?php echo htmlspecialchars($video['title']); ?>"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen>
            </iframe>
            
            <!-- Video End Overlay -->
            <div id="video-end-overlay" class="video-end-overlay">
                <button class="overlay-close" onclick="hideVideoOverlay()">&times;</button>
                <div class="overlay-content">
                    <h2 class="overlay-title">What's Next?</h2>
                    <div class="overlay-sections">
                        <!-- Next Video from Channel -->
                        <div class="overlay-section">
                            <h4>The next video in <?php echo htmlspecialchars($video['channel_title']); ?></h4>
                            <div class="next-video-container">
                                <?php
                                $stmt = $db->prepare('SELECT v.*, c.name as channel_title 
                                                      FROM videos v 
                                                      LEFT JOIN channels c ON v.channel_id = c.id 
                                                      WHERE v.id != ? AND v.channel_id = ?
                                                      ORDER BY RANDOM() 
                                                      LIMIT 1');
                                $stmt->execute([$video_id, $video['channel_id']]);
                                $next_video = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($next_video): 
                                    $next_youtube_id = extractYouTubeId($next_video['video_url']);
                                    $next_thumbnail = getVideoThumbnail($next_youtube_id);
                                ?>
                                <div class="next-video-card" data-video-id="<?php echo $next_video['id']; ?>">
                                    <div class="next-video-thumbnail-container">
                                        <img src="<?php echo htmlspecialchars($next_video['thumbnail_url'] ?: $next_thumbnail); ?>" 
                                             class="next-video-thumbnail" 
                                             alt="<?php echo htmlspecialchars($next_video['title']); ?>">
                                        <div class="countdown-overlay">
                                            <div class="countdown-circle">
                                                <span id="countdown-number">5</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="next-video-info">
                                        <h5 class="next-video-title">
                                            <?php echo htmlspecialchars($next_video['title']); ?>
                                        </h5>
                                        <p class="next-video-channel">
                                            <?php echo htmlspecialchars($next_video['channel_title']); ?>
                                        </p>
                                        <p class="next-video-countdown">Starting in <span id="countdown-text">5</span> seconds...</p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">No more videos in this channel</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Suggested Videos -->
                        <div class="overlay-section">
                            <h4>More Suggested Videos</h4>
                            <div class="overlay-videos" id="overlay-suggested-videos">
                                <?php
                                $suggested_videos = get_suggested_videos($db, 3);
                                
                                foreach ($suggested_videos as $suggested): 
                                    $suggested_youtube_id = extractYouTubeId($suggested['video_url']);
                                    $suggested_thumbnail = getVideoThumbnail($suggested_youtube_id);
                                ?>
                                <div class="overlay-video-card" onclick="navigateToVideo(<?php echo $suggested['id']; ?>)">
                                    <img src="<?php echo htmlspecialchars($suggested['thumbnail_url'] ?: $suggested_thumbnail); ?>" 
                                         class="overlay-video-thumbnail" 
                                         alt="<?php echo htmlspecialchars($suggested['title']); ?>">
                                    <div class="overlay-video-info">
                                        <h6 class="overlay-video-title">
                                            <?php echo htmlspecialchars($suggested['title']); ?>
                                        </h6>
                                        <p class="overlay-video-channel">
                                            <?php echo htmlspecialchars($suggested['channel_name']); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <?php include 'partials/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="static/script.js"></script>
</body>
</html>