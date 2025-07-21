<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();

// Get all categories excluding "Not Categorized"
$categories = get_display_categories($db);

// Get selected category from URL parameter
$selected_category_id = isset($_GET['category']) ? intval($_GET['category']) : null;

// If no category selected, default to first category
if (!$selected_category_id && !empty($categories)) {
    $selected_category_id = $categories[0]['id'];
}

// Get videos for selected category
$videos = [];
$selected_category_name = '';
if ($selected_category_id) {
    $videos = get_videos_by_category($db, $selected_category_id);
    // Find the selected category name
    foreach ($categories as $category) {
        if ($category['id'] == $selected_category_id) {
            $selected_category_name = $category['name'];
            break;
        }
    }
}

$page_title = 'Categories - Binge-Worthy';
$current_page = 'categories';
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container-fluid">
        <br>
        
        <!-- Categories Buttons -->
        <div class="categories-header">
            <div class="categories-buttons">
                <?php foreach ($categories as $category): ?>
                    <a href="/categories?category=<?php echo $category['id']; ?>" 
                       class="category-button <?php echo ($category['id'] == $selected_category_id) ? 'active' : ''; ?>">
                        <span class="category-name"><?php echo htmlspecialchars($category['name']); ?></span>
                        <span class="category-count">
                            <?php
                            $category_videos = get_videos_by_category($db, $category['id']);
                            echo count($category_videos);
                            ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Videos Content -->
        <div class="category-content">
            <?php if ($selected_category_name): ?>                
                <?php if (empty($videos)): ?>
                    <div class="no-videos-message">
                        <div class="no-videos-icon">🎬</div>
                        <h4>No videos yet</h4>
                        <p>There are no videos in this category yet. Check back later!</p>
                    </div>
                <?php else: ?>
                    <div class="videos-grid">
                        <?php foreach ($videos as $video): ?>
                            <div class="video-card">
                                <div class="video-thumbnail">
                                    <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($video['title']); ?>"
                                         onclick="openVideo('<?php echo htmlspecialchars($video['video_url']); ?>')">
                                </div>
                                <div class="video-info">
                                    <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                    <p class="video-channel">
                                        <a href="/channel?id=<?php echo $video['channel_id']; ?>" class="channel-link">
                                            <?php echo htmlspecialchars($video['channel_name']); ?>
                                        </a>
                                    </p>
                                    <?php if ($video['category'] !== 'Not Categorized'): ?>
                                        <p class="video-category"><?php echo htmlspecialchars($video['category']); ?></p>
                                    <?php endif; ?>
                                    <small class="video-date text-muted">
                                        Added <?php echo date('M j, Y', strtotime($video['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-category-selected">
                    <h3>Select a category to view videos</h3>
                    <p>Choose a category from above to see videos in that category.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
</body>
</html> 