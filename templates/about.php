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
                            You are back from work, ready to relax a bit or eat dinner, and need something to watch? Or you have 5-10 mins to kill before you start a task?
                        </p><p class="lead">
                            Don't fret! We curated videos from all the "best" lists across to web, designed to help you binge without the guilt. Our inventory is full of edutainment
                            videos you will love watching or simply leave running in the background while you get your work done.
                        </p>
                                                
                        <h3 class="mt-4 mb-3">Our Collection</h3>
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

                        <h3 class="mt-4 mb-3">Get Started</h3>
                        <p>
                            Browse our <a href="/categories" class="text-decoration-none">categories</a> to discover videos in your areas of interest, or check out our <a href="/" class="text-decoration-none">homepage</a> for the latest additions and suggested content.
                        </p>

                        <div class="text-center mt-5">
                            <a href="/categories" class="btn btn-primary btn-lg me-3">Browse Categories</a>
                            <a href="/" class="btn btn-outline-primary btn-lg">Back to Home</a>
                        </div>
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