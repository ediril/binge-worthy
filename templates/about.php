<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();

// Get counts
$channel_count_stmt = $db->prepare("SELECT COUNT(*) as count FROM channels");
$channel_count_stmt->execute();
$channel_count = $channel_count_stmt->fetch()['count'];

$video_count_stmt = $db->prepare("SELECT COUNT(*) as count FROM videos");
$video_count_stmt->execute();
$video_count = $video_count_stmt->fetch()['count'];

$page_title = 'About - Binge-Worthy';
$current_page = 'about';
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid">
        <br>
        
        <!-- About Section -->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="about-content">
                    <h1 class="text-center mb-4">About</h1>
                    
                    <div class="about-description">
                        <p class="lead">
                            You are back from work, ready to relax a bit, maybe grab a bite, and need something to watch? Or you want something in the background while you work?
                        </p><p class="lead">
                            Don't fret! We curated videos from all the best channels across YouTube, designed to help you binge without guilt. Our inventory is full of edutainment
                            videos you will love watching or simply leave running in the background while you get your work done.
                        </p>

                        <h3 class="mt-5 mb-3">Get Started</h3>
                        <p>
                            Browse our <a href="/categories" class="text-decoration-none">categories</a> to discover videos in your areas of interest, or check out our <a href="/" class="text-decoration-none">homepage</a> for the latest additions and suggested videos.
                        </p>
                        <div class="row text-center mb-4">
                            <div class="col-md-6">
                                <div class="stat-card p-4 mb-3 h-100">
                                    <h2 class="text-primary mb-2"><?php echo number_format($channel_count); ?></h2>
                                    <p class="mb-0">Curated Channels</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card p-4 mb-3 h-100">
                                    <h2 class="text-primary mb-2"><?php echo number_format($video_count); ?></h2>
                                    <p class="mb-0">Binge-Worthy Videos</p>
                                </div>
                            </div>
                        </div>


                        <h3 class="mt-5 mb-3">The Team</h3>
                        <div class="row justify-content-center">
                            <div class="col-lg-12 col-xl-10">
                                <div class="d-flex justify-content-center align-items-start">
                                    <div class="team-member text-center">
                                        <h5 class="mb-2">CEO</h5>
                                        <img src="static/IMG_7312.png" alt="Team member 3" class="team-member__image">
                                    </div>
                                    <div class="team-member text-center">
                                        <h5 class="mb-2">COO</h5>
                                        <img src="static/IMG_7106.png" alt="Team member 2" class="team-member__image">
                                    </div>
                                    <div class="team-member text-center">
                                        <h5 class="mb-2">CFO</h5>
                                        <img src="static/IMG_7561.png" alt="Team member 4" class="team-member__image">
                                    </div>
                                        <div class="team-member text-center">
                                        <h5 class="mb-2">Developer</h5>
                                        <img src="static/IMG_3598.png" alt="Team member 1" class="team-member__image">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="mt-5 mb-3">Feedback</h3>
                        <p>
                            Your feedback is very valuable! If you have any suggestions or comments, please feel free to <a href="https://x.com/emrahdma" class="text-decoration-none">reach out</a>.
                        </p>
                        <br><br>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 