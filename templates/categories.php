<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();

// Get all categories
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

    <div class="container-fluid p-0">
        <br>
        
        <!-- Categories Buttons -->
        <div class="categories-header px-4 mb-4">
            <div class="categories-buttons d-flex flex-wrap gap-3 justify-content-center mb-4">
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
        <div class="category-content px-4">
            <?php if ($selected_category_name): ?>                
                <?php if (empty($videos)): ?>
                    <div class="no-videos-message text-center py-5 px-4 my-4">
                        <div class="no-videos-icon">🎬</div>
                        <h4 class="mt-3 mb-2">No videos yet</h4>
                        <p class="mb-0">There are no videos in this category yet. Check back later!</p>
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
                                <div class="video-info p-3">
                                    <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                    <p class="video-channel">
                                        <a href="/channel?id=<?php echo $video['channel_id']; ?>" class="channel-link">
                                            <?php echo htmlspecialchars($video['channel_name']); ?>
                                        </a>
                                    </p>
                                    <?php if ($video['category']): ?>
                                        <div class="video-categories mb-2">
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($video['category']); ?></span>
                                        </div>
                                    <?php endif; ?>
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