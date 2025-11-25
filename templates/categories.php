<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();

// Get all categories
$categories = get_display_categories($db);

// Get selected category - check for slug first, then ID
$selected_category = null;
$selected_category_id = null;

// Check for slug-based access first
if (isset($_GET['category_slug']) && !empty($_GET['category_slug'])) {
    $slug = $_GET['category_slug'];
    $selected_category = get_category_by_slug($db, $slug);

    if (!$selected_category) {
        show_404_page();
    }
    $selected_category_id = $selected_category['id'];
}
// Handle legacy ID-based access with 301 redirect
elseif (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_id = intval($_GET['category']);
    if ($category_id <= 0) {
        // Invalid ID, redirect to categories page
        header('Location: /categories');
        exit;
    }

    $selected_category = get_category_by_id($db, $category_id);

    if (!$selected_category) {
        // Category not found, redirect to categories page
        header('Location: /categories');
        exit;
    }

    // 301 permanent redirect to slug-based URL if slug exists
    if (!empty($selected_category['slug'])) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: /categories/' . urlencode($selected_category['slug']));
        exit;
    }

    $selected_category_id = $selected_category['id'];
}

// If no category selected, default to random category
if (!$selected_category_id && !empty($categories)) {
    $selected_category_id = $categories[array_rand($categories)]['id'];
    // Find the category data
    foreach ($categories as $cat) {
        if ($cat['id'] == $selected_category_id) {
            $selected_category = $cat;
            break;
        }
    }
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
            <div class="categories-buttons d-flex flex-wrap gap-2 justify-content-center mb-4">
                <?php foreach ($categories as $category):
                    $cat_url = get_category_url($category);
                ?>
                    <a href="<?php echo $cat_url; ?>"
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
                            <?php render_video_card($video); ?>
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
