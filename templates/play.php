<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../common.php';

$db = get_database();
$video = null;

// Check for slug-based access first
if (isset($_GET['video_slug']) && !empty($_GET['video_slug'])) {
    $slug = $_GET['video_slug'];
    $stmt = $db->prepare('SELECT v.*, c.name as channel_title, c.slug as channel_slug
                          FROM videos v
                          LEFT JOIN channels c ON v.channel_id = c.id
                          WHERE v.slug = ?');
    $stmt->execute([$slug]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        show_404_page();
    }
}
// Handle legacy ID-based access with 301 redirect
elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $video_id = intval($_GET['id']);
    if ($video_id <= 0) {
        header('Location: ' . app_url());
        exit;
    }

    $stmt = $db->prepare('SELECT v.*, c.name as channel_title, c.slug as channel_slug
                          FROM videos v
                          LEFT JOIN channels c ON v.channel_id = c.id
                          WHERE v.id = ?');
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        header('Location: ' . app_url());
        exit;
    }

    // 301 permanent redirect to slug-based URL if slug exists
    if (!empty($video['slug'])) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . app_url('play/' . urlencode($video['slug'])));
        exit;
    }
} else {
    header('Location: ' . app_url());
    exit;
}

$youtube_id = extractYouTubeId($video['video_url']);
if (!$youtube_id) {
    header('Location: ' . app_url());
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
                    <a href="<?php echo !empty($video['channel_slug']) ? app_url('channel/' . urlencode($video['channel_slug'])) : app_url('channel?id=' . htmlspecialchars($video['channel_id'])); ?>"
                       class="video-player-channel">
                        <?php echo htmlspecialchars($video['channel_title']); ?>
                    </a>
                    <span class="video-player-date">
                        <?php echo date('M j, Y', strtotime($video['published_at'])); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="video-container" id="video-player-wrapper">
            <div class="video-player-wrapper">
                <iframe 
                    id="video-player"
                    class="video-player-iframe"
                    src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>?enablejsapi=1&fs=0&controls=1&rel=0&autoplay=1"
                    title="<?php echo htmlspecialchars($video['title']); ?>"
                    frameborder="0"
                    allowfullscreen
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen">
                </iframe>
            </div>
            
            <!-- Working fullscreen button (bottom right) -->
            <button id="fullscreen-button" class="fullscreen-button" title="Enter Fullscreen (F)">
                <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                </svg>
            </button>
            
            <!-- Mobile Controls like Scrubbler (keeping for potential future use) -->
            <div id="mobileControls" class="mobile-controls" style="display: none;">
                <button class="control-btn" id="fullscreenBtn" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
            
            <!-- Video End Overlay -->
            <div id="video-end-overlay" class="video-end-overlay">
                <button class="overlay-close" onclick="hideVideoOverlay()">&times;</button>
                <div class="overlay-content">
                    <div class="overlay-sections">
                        <!-- Next Video from Channel -->
                        <?php 
                            echo generate_next_video_section($db, $video); 
                        ?>
                        
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
                                <div class="overlay-video-card" data-video-id="<?php echo $suggested['id']; ?>" data-youtube-id="<?php echo htmlspecialchars($suggested_youtube_id); ?>" onclick="navigateToVideo(<?php echo $suggested['id']; ?>)">
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
        
    <!-- Inline JavaScript for fullscreen functionality (backup/override) -->
    <script>
        // Only define if not already defined by external script
        if (typeof toggleCustomFullscreen === 'undefined') {
            let isFullscreen = false;
            
            // Fullscreen toggle function
            window.toggleCustomFullscreen = function() {
            const videoContainer = document.getElementById('video-player-wrapper');
            if (!videoContainer) return;
            
            if (!isFullscreen) {
                // Check if we're on iOS
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                
                if (isIOS) {
                    // For iOS, use custom fullscreen approach
                    document.body.classList.add('ios-fullscreen');
                    videoContainer.classList.add('ios-fullscreen');
                    isFullscreen = true;
                    updateFullscreenButtonIcon(true);
                } else {
                    // Standard fullscreen for other devices
                    if (videoContainer.requestFullscreen) {
                        videoContainer.requestFullscreen();
                    } else if (videoContainer.mozRequestFullScreen) {
                        videoContainer.mozRequestFullScreen();
                    } else if (videoContainer.webkitRequestFullscreen) {
                        videoContainer.webkitRequestFullscreen();
                    } else if (videoContainer.msRequestFullscreen) {
                        videoContainer.msRequestFullscreen();
                    }
                }
            } else {
                // Exit fullscreen
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                
                if (isIOS) {
                    document.body.classList.remove('ios-fullscreen');
                    videoContainer.classList.remove('ios-fullscreen');
                    isFullscreen = false;
                    updateFullscreenButtonIcon(false);
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.mozCancelFullScreen) {
                        document.mozCancelFullScreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                }
            }
        }
        
        // Update fullscreen button icon
        function updateFullscreenButtonIcon(inFullscreen) {
            const button = document.getElementById('fullscreen-button');
            if (!button) return;
            
            const svg = button.querySelector('svg');
            if (!svg) return;
            
            if (inFullscreen) {
                // Exit fullscreen icon
                svg.innerHTML = '<path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/>';
                button.title = 'Exit Fullscreen (F)';
            } else {
                // Enter fullscreen icon  
                svg.innerHTML = '<path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>';
                button.title = 'Enter Fullscreen (F)';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize fullscreen button
            const button = document.getElementById('fullscreen-button');
            if (button) {
                button.onclick = function() {
                    toggleCustomFullscreen();
                };
            }
            
            // Add F key handler for desktop
            document.addEventListener('keydown', function(e) {
                if (e.code === 'KeyF') {
                    e.preventDefault();
                    toggleCustomFullscreen();
                }
            });
            
            // Handle fullscreen change events
            function handleFullscreenChange() {
                const fullscreenElement = document.fullscreenElement || 
                                         document.webkitFullscreenElement || 
                                         document.mozFullScreenElement || 
                                         document.msFullscreenElement;
                
                const isIOSFullscreen = document.body.classList.contains('ios-fullscreen');
                isFullscreen = !!(fullscreenElement || isIOSFullscreen);
                updateFullscreenButtonIcon(isFullscreen);
                
                // Update overlay z-index for fullscreen
                const overlay = document.getElementById('video-end-overlay');
                if (overlay) {
                    if (isFullscreen) {
                        overlay.classList.add('fullscreen-overlay');
                        overlay.style.zIndex = '999999999';
                    } else {
                        overlay.classList.remove('fullscreen-overlay');
                        overlay.style.zIndex = '10';
                    }
                }
            }
            
            // Listen for fullscreen change events
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', handleFullscreenChange);
            
            // Update HTML countdown display
            const COUNTDOWN_SECONDS = 10; // Define it here too for inline script
            const countdownNumber = document.getElementById('countdown-number');
            const countdownText = document.getElementById('countdown-text');
            if (countdownNumber) countdownNumber.textContent = COUNTDOWN_SECONDS;
            if (countdownText) countdownText.textContent = COUNTDOWN_SECONDS;
        });
    </script>

    <?php include 'templates/partials/footer.php'; ?>
