<?php
session_start();
require_once 'common.php';
require_once 'database.php';

// Localhost-only restriction - only allow access when running on localhost
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8080', '127.0.0.1:8080']) || 
               (isset($_SERVER['HTTP_HOST']) && preg_match('/^localhost:\d+$/', $_SERVER['HTTP_HOST']));

// Check if running on localhost
if (!$is_localhost) {
    // Return 404 error to hide the existence of the admin panel
    http_response_code(404);
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="static/styles.css?v=' . filemtime('static/styles.css') . '">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <h1 style="font-size: 4rem; color: #e50914;">404</h1>
                <h2>Page Not Found</h2>
                <p>The page you are looking for does not exist.</p>
                <a href="/" class="btn btn-primary">Go Home</a>
            </div>
        </div>
    </div>
</body>
</html>';
    exit;
}

$db = get_database();

// Handle AJAX request for getting channel categories
if (isset($_GET['action']) && $_GET['action'] === 'get_channel_categories' && isset($_GET['channel_id'])) {
    $channel_id = intval($_GET['channel_id']);
    $categories = get_channel_categories($db, $channel_id);
    
    header('Content-Type: application/json');
    echo json_encode(['categories' => $categories]);
    exit;
}

// Handle form submissions
$message = '';
$error = '';

// Check for session messages (from redirects)
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

if ($_POST) {
    // Handle editing channel
    if (isset($_POST['action']) && $_POST['action'] === 'edit_channel' && isset($_POST['channel_id'])) {
        $channel_id = intval($_POST['channel_id']);
        $category_ids = [];
        
        // Get selected existing categories
        if (isset($_POST['edit_existing_categories']) && is_array($_POST['edit_existing_categories'])) {
            $category_ids = array_map('intval', $_POST['edit_existing_categories']);
        }
        
        // Handle new category creation (supports comma-separated names)
        if (!empty($_POST['edit_new_category'])) {
            $new_category_input = trim($_POST['edit_new_category']);
            $new_category_description = trim($_POST['edit_new_category_description'] ?? '');
            
            // Split by comma and process each category name
            $category_names = array_map('trim', explode(',', $new_category_input));
            $category_names = array_filter($category_names, fn($name) => !empty($name));
            
            foreach ($category_names as $new_category_name) {
                // Check if category already exists
                $existing_category = $db->prepare("SELECT id FROM categories WHERE name = ?");
                $existing_category->execute([$new_category_name]);
                $existing_cat = $existing_category->fetch();
                
                if ($existing_cat) {
                    // Add existing category to selection if not already selected
                    if (!in_array($existing_cat['id'], $category_ids)) {
                        $category_ids[] = $existing_cat['id'];
                    }
                } else {
                    if (add_category($db, $new_category_name, $new_category_description)) {
                        $category_ids[] = $db->lastInsertId();
                    } else {
                        $error = "Failed to create new category: " . htmlspecialchars($new_category_name);
                        break;
                    }
                }
            }
        }
        
        // At least one category is required
        if (empty($category_ids) && !$error) {
            $error = "Please select at least one category.";
        }
        
        if (!empty($category_ids) && !$error) {
            $success_messages = [];
            
            // Update description if provided
            if (isset($_POST['channel_description'])) {
                $new_description = trim($_POST['channel_description']);
                $update_desc_stmt = $db->prepare("UPDATE channels SET description = ? WHERE id = ?");
                if ($update_desc_stmt->execute([$new_description, $channel_id])) {
                    $success_messages[] = "description";
                } else {
                    $error = "Failed to update channel description.";
                }
            }
            
            // Update categories
            if (!$error && add_channel_categories($db, $channel_id, $category_ids)) {
                $success_messages[] = "categories";
            } elseif (!$error) {
                $error = "Failed to update channel categories.";
            }
            
            if (!$error && !empty($success_messages)) {
                $message = "Channel " . implode(' and ', $success_messages) . " updated successfully!";
            }
        }
    }
    // Handle category editing
    else if (isset($_POST['action']) && $_POST['action'] === 'edit_category' && isset($_POST['category_id']) && isset($_POST['category_name'])) {
        $category_id = intval($_POST['category_id']);
        $new_name = trim($_POST['category_name']);
        
        header('Content-Type: application/json');
        
        if (empty($new_name) || strlen($new_name) < 2) {
            echo json_encode(['success' => false, 'error' => 'Category name must be at least 2 characters long.']);
            exit;
        }
        
        // Check if category name already exists (excluding current category)
        $check_stmt = $db->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $check_stmt->execute([$new_name, $category_id]);
        if ($check_stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'A category with this name already exists.']);
            exit;
        }
        
        // Update category name
        $update_stmt = $db->prepare("UPDATE categories SET name = ? WHERE id = ?");
        if ($update_stmt->execute([$new_name, $category_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update category name.']);
        }
        exit;
    }
    // Handle category deletion
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_category' && isset($_POST['category_id'])) {
        $category_id = intval($_POST['category_id']);
        
        header('Content-Type: application/json');
        
        try {
            $db->beginTransaction();
            
            // Delete all channel-category associations for this category
            $delete_associations = $db->prepare("DELETE FROM channel_categories WHERE category_id = ?");
            $delete_associations->execute([$category_id]);
            
            // Delete the category itself
            $delete_category = $db->prepare("DELETE FROM categories WHERE id = ?");
            $delete_category->execute([$category_id]);
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to delete category.']);
        }
        exit;
    }
    // Handle suggestion deletion
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_suggestion' && isset($_POST['suggestion_id'])) {
        $suggestion_id = intval($_POST['suggestion_id']);
        
        header('Content-Type: application/json');
        
        try {
            $suggestions_db = new PDO('sqlite:suggestions.db');
            $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $delete_stmt = $suggestions_db->prepare("DELETE FROM suggestions WHERE id = ?");
            if ($delete_stmt->execute([$suggestion_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete suggestion.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        exit;
    }
    // Handle channel deletion
    else if (isset($_POST['action']) && $_POST['action'] === 'delete_channel' && isset($_POST['channel_id'])) {
        $channel_id = intval($_POST['channel_id']);
        
        if ($channel_id > 0) {
            try {
                $db->beginTransaction();
                
                // First, get channel name for confirmation message
                $channel_stmt = $db->prepare("SELECT name FROM channels WHERE id = ?");
                $channel_stmt->execute([$channel_id]);
                $channel = $channel_stmt->fetch();
                
                if ($channel) {
                    // Delete all videos for this channel (hard delete)
                    $delete_videos_stmt = $db->prepare("DELETE FROM videos WHERE channel_id = ?");
                    $delete_videos_stmt->execute([$channel_id]);
                    
                    // Delete channel categories relationships
                    $delete_categories_stmt = $db->prepare("DELETE FROM channel_categories WHERE channel_id = ?");
                    $delete_categories_stmt->execute([$channel_id]);
                    
                    // Delete the channel (hard delete)
                    $delete_channel_stmt = $db->prepare("DELETE FROM channels WHERE id = ?");
                    $delete_channel_stmt->execute([$channel_id]);
                    
                    $db->commit();
                    $message = "Channel '{$channel['name']}' and all its videos have been deleted successfully.";
                } else {
                    $error = "Channel not found or already deleted.";
                }
            } catch (Exception $e) {
                $db->rollback();
                $error = "Failed to delete channel: " . $e->getMessage();
            }
        } else {
            $error = "Invalid channel ID.";
        }
    }
    // Handle adding new content
    else if (isset($_POST['content_type']) && isset($_POST['content_input'])) {
        $content_type = $_POST['content_type'];
        $content_input = trim($_POST['content_input']);
        
        if (empty($content_input)) {
            $error = "Content URL or ID is required.";
        } else {
            if ($content_type === 'channel') {
                // Handle channel addition
                $channel_url = normalize_channel_input($content_input);
                
                if (!$channel_url) {
                    $error = "Please provide a valid YouTube channel URL, channel name, or handle (e.g., @username).";
                } else {
                    // Check if channel already exists (by URL)
                    $existing_channel = $db->prepare("SELECT id FROM channels WHERE youtube_url = ?");
                    $existing_channel->execute([$channel_url]);
                    
                    if ($existing_channel->fetch()) {
                        $error = "This channel URL already exists in the database.";
                    } else {
                        // Fetch channel metadata
                        $channel_metadata = fetch_youtube_channel_metadata($channel_url);
                        
                        if ($channel_metadata) {
                            // Check if channel name already exists
                            $existing_name = $db->prepare("SELECT id FROM channels WHERE name = ?");
                            $existing_name->execute([$channel_metadata['name']]);
                            
                            if ($existing_name->fetch()) {
                                $error = "A channel with the name '{$channel_metadata['name']}' already exists in the database.";
                            } else {
                                // Handle multiple category selection for channel
                                $category_ids = [];
                                
                                // Get selected existing categories
                                if (isset($_POST['existing_categories']) && is_array($_POST['existing_categories'])) {
                                    $category_ids = array_map('intval', $_POST['existing_categories']);
                                }
                                
                                // Handle new category creation (supports comma-separated names)
                                if (!empty($_POST['new_category'])) {
                                    $new_category_input = trim($_POST['new_category']);
                                    $new_category_description = trim($_POST['new_category_description'] ?? '');
                                    
                                    // Split by comma and process each category name
                                    $category_names = array_map('trim', explode(',', $new_category_input));
                                    $category_names = array_filter($category_names, fn($name) => !empty($name));
                                    
                                    foreach ($category_names as $new_category_name) {
                                        // Check if category already exists
                                        $existing_category = $db->prepare("SELECT id FROM categories WHERE name = ?");
                                        $existing_category->execute([$new_category_name]);
                                        $existing_cat = $existing_category->fetch();
                                        
                                        if ($existing_cat) {
                                            // Add existing category to selection if not already selected
                                            if (!in_array($existing_cat['id'], $category_ids)) {
                                                $category_ids[] = $existing_cat['id'];
                                            }
                                        } else {
                                            if (add_category($db, $new_category_name, $new_category_description)) {
                                                $category_ids[] = $db->lastInsertId();
                                            } else {
                                                $error = "Failed to create new category: " . htmlspecialchars($new_category_name);
                                                break;
                                            }
                                        }
                                    }
                                }
                                
                                // At least one category is required
                                if (empty($category_ids) && !$error) {
                                    $error = "Please select at least one category or create a new one.";
                                }
                                
                                if (!empty($category_ids) && !$error) {
                                    // Use description from form field instead of fetched metadata
                                    $form_description = trim($_POST['new_category_description'] ?? '');
                                    $final_description = !empty($form_description) ? $form_description : $channel_metadata['description'];
                                    
                                    $channel_id = add_channel_with_categories($db, $channel_metadata['name'], $final_description, $channel_metadata['youtube_url'], $category_ids, $channel_metadata['thumbnail_url']);
                                    if ($channel_id) {
                                        // Redirect to prevent form resubmission
                                        $_SESSION['admin_message'] = "Channel '{$channel_metadata['name']}' added successfully with " . count($category_ids) . " categories!";
                                        header('Location: admin.php');
                                        exit;
                                    } else {
                                        $error = "Failed to add channel to database.";
                                    }
                                }
                            }
                        } else {
                            $error = "Could not fetch channel information. Please check the input and try again.";
                        }
                    }
                }
            } else if ($content_type === 'video') {
                // Handle video addition
                $video_url = normalize_video_input($content_input);
                
                if (!$video_url) {
                    $error = "Please provide a valid YouTube video URL or video ID.";
                } else {
                    // Check if video already exists
                    $existing_video = $db->prepare("SELECT id FROM videos WHERE video_url = ?");
                    $existing_video->execute([$video_url]);
                    
                    if ($existing_video->fetch()) {
                        $error = "This video already exists in the database.";
                    } else {
                        // Fetch video metadata
                        $video_metadata = fetch_youtube_video_metadata($video_url);
                        
                        if ($video_metadata) {
                            // Use "Not Categorized" for channels created from videos
                            $category_id = get_category_id_by_name($db, 'Not Categorized');
                            
                            // Get or create channel for this video
                            $channel_id = get_or_create_channel_from_video($db, $video_metadata, $category_id);
                            
                            if ($channel_id) {
                                if (add_video($db, $channel_id, $video_metadata['title'], $video_url, $video_metadata['thumbnail_url'], 1)) {
                                    // Redirect to prevent form resubmission
                                    $_SESSION['admin_message'] = "Video '{$video_metadata['title']}' added successfully!";
                                    header('Location: admin.php');
                                    exit;
                                } else {
                                    $error = "Failed to add video to database.";
                                }
                            } else {
                                $error = "Could not create or find channel for this video.";
                            }
                        } else {
                            $error = "Could not fetch video information. Please check the input and try again.";
                        }
                    }
                }
            }
        }
    }
}

// Pagination setup
$channels_per_page = ADMIN_CHANNELS_PER_PAGE;
$videos_per_page = ADMIN_VIDEOS_PER_PAGE;
$suggestions_per_page = ADMIN_SUGGESTIONS_PER_PAGE;
$channels_page = max(1, intval($_GET['channels_page'] ?? 1));
$videos_page = max(1, intval($_GET['videos_page'] ?? 1));
$suggestions_page = max(1, intval($_GET['suggestions_page'] ?? 1));

// Get total count of channels
$channels_count_stmt = $db->prepare("SELECT COUNT(*) as total FROM channels");
$channels_count_stmt->execute();
$total_channels = $channels_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_channels_pages = max(1, ceil($total_channels / $channels_per_page));

// Get total count of videos
$videos_count_stmt = $db->prepare("SELECT COUNT(*) as total FROM videos");
$videos_count_stmt->execute();
$total_videos = $videos_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_videos_pages = max(1, ceil($total_videos / $videos_per_page));

// Get channels for current page
$channels_offset = ($channels_page - 1) * $channels_per_page;
$all_channels = $db->prepare("
    SELECT c.*, GROUP_CONCAT(cat.name, ', ') as category_names
    FROM channels c 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");
$all_channels->bindValue(':limit', $channels_per_page, PDO::PARAM_INT);
$all_channels->bindValue(':offset', $channels_offset, PDO::PARAM_INT);
$all_channels->execute();
$all_channels = $all_channels->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for dropdown (excluding "Not Categorized")
$all_categories = get_display_categories($db);

// Get suggestions from suggestions database with pagination
$suggestions = [];
$total_suggestions = 0;
$total_suggestions_pages = 1;
try {
    $suggestions_db = new PDO('sqlite:suggestions.db');
    $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get total count of suggestions
    $suggestions_count_stmt = $suggestions_db->prepare("SELECT COUNT(*) as total FROM suggestions");
    $suggestions_count_stmt->execute();
    $total_suggestions = $suggestions_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_suggestions_pages = max(1, ceil($total_suggestions / $suggestions_per_page));
    
    // Get suggestions for current page
    $suggestions_offset = ($suggestions_page - 1) * $suggestions_per_page;
    $suggestions_stmt = $suggestions_db->prepare("SELECT * FROM suggestions ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
    $suggestions_stmt->bindValue(':limit', $suggestions_per_page, PDO::PARAM_INT);
    $suggestions_stmt->bindValue(':offset', $suggestions_offset, PDO::PARAM_INT);
    $suggestions_stmt->execute();
    $suggestions = $suggestions_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Suggestions database might not exist yet, that's okay
}

// Get videos for current page
$videos_offset = ($videos_page - 1) * $videos_per_page;
$all_videos = $db->prepare("
    SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
    FROM videos v 
    JOIN channels c ON v.channel_id = c.id 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY v.id
    ORDER BY v.created_at DESC
    LIMIT :limit OFFSET :offset
");
$all_videos->bindValue(':limit', $videos_per_page, PDO::PARAM_INT);
$all_videos->bindValue(':offset', $videos_offset, PDO::PARAM_INT);
$all_videos->execute();
$all_videos = $all_videos->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Admin - Binge-Worthy';
$current_page = 'admin';
$additional_css = ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'];
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <!-- Toast Container -->
    <div class="position-fixed start-50 translate-middle-x p-3" style="z-index: 1050; top: 70px;">
        <div id="toastContainer"></div>
    </div>

    <div class="container mt-4">
        <h1>Admin Panel</h1>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Add New Content</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="add-content-form">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Content Type</label>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-light dropdown-toggle w-100" type="button" id="content-type-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span id="content-type-text">Channel</span>
                                        </button>
                                        <ul class="dropdown-menu w-100" aria-labelledby="content-type-dropdown">
                                            <li><a class="dropdown-item" href="#" data-value="channel">Channel</a></li>
                                            <li><a class="dropdown-item" href="#" data-value="video">Video</a></li>
                                        </ul>
                                        <input type="hidden" id="content-type" name="content_type" value="channel" required>
                                    </div>
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label for="content-input" class="form-label">YouTube URL or ID</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="content-input" name="content_input" required 
                                               placeholder="@username, channelname, or full URL">
                                        <button type="button" class="btn btn-outline-secondary" id="clear-content-btn" 
                                                title="Clear URL and Description" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Description Field -->
                            <div class="mb-3">
                                <label for="new-category-description" class="form-label">Description</label>
                                <textarea class="form-control" id="new-category-description" name="new_category_description" 
                                          rows="3" placeholder="Brief description of the category"></textarea>
                            </div>
                            
                            <!-- Content Type Info -->
                            <div id="content-info" class="mb-3 p-3 text-info bg-info bg-opacity-10 border border-info rounded">
                                <i class="fas fa-info-circle me-2"></i>
                                <span id="content-info-text">Select content type above to see relevant information.</span>
                            </div>
                            
                            <!-- Multiple Category Selection (only for channels) -->
                            <div id="category-section" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Categories (select multiple)</label>
                                    
                                    <!-- New Category Creation Input -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <label for="new-category" class="form-label me-3 mb-0">Create New:</label>
                                            <input type="text" class="form-control" id="new-category" name="new_category" 
                                                   placeholder="e.g., Education, Entertainment, Science (comma-separated)">
                                        </div>
                                    </div>
                                    
                                    <!-- Existing Categories as Checkboxes -->
                                    <div class="mb-3">
                                        <div class="row">
                                            <?php 
                                            // Split categories into three columns for vertical ordering
                                            $total_categories = count($all_categories);
                                            $per_column = ceil($total_categories / 3);
                                            $first_column = array_slice($all_categories, 0, $per_column);
                                            $second_column = array_slice($all_categories, $per_column, $per_column);
                                            $third_column = array_slice($all_categories, $per_column * 2);
                                            ?>
                                            <div class="col-md-4">
                                                <?php foreach ($first_column as $category): ?>
                                                    <div class="form-check mb-2 d-flex align-items-center">
                                                        <input class="form-check-input" type="checkbox" name="existing_categories[]" 
                                                               id="category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                                        <label class="form-check-label flex-grow-1 ms-2" for="category-<?php echo $category['id']; ?>">
                                                            <span class="category-name" data-category-id="<?php echo $category['id']; ?>">
                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                            </span>
                                                        </label>
                                                        <div class="category-actions ms-2">
                                                            <button type="button" class="btn btn-sm btn-link p-0 me-1 edit-category-btn" 
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    title="Edit category">
                                                                <i class="fas fa-edit text-warning" style="font-size: 0.8rem;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-link p-0 delete-category-btn" 
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    title="Delete category">
                                                                <i class="fas fa-trash text-danger" style="font-size: 0.8rem;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <?php foreach ($second_column as $category): ?>
                                                    <div class="form-check mb-2 d-flex align-items-center">
                                                        <input class="form-check-input" type="checkbox" name="existing_categories[]" 
                                                               id="category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                                        <label class="form-check-label flex-grow-1 ms-2" for="category-<?php echo $category['id']; ?>">
                                                            <span class="category-name" data-category-id="<?php echo $category['id']; ?>">
                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                            </span>
                                                        </label>
                                                        <div class="category-actions ms-2">
                                                            <button type="button" class="btn btn-sm btn-link p-0 me-1 edit-category-btn" 
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    title="Edit category">
                                                                <i class="fas fa-edit text-warning" style="font-size: 0.8rem;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-link p-0 delete-category-btn" 
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    title="Delete category">
                                                                <i class="fas fa-trash text-danger" style="font-size: 0.8rem;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <?php foreach ($third_column as $category): ?>
                                                    <div class="form-check mb-2 d-flex align-items-center">
                                                        <input class="form-check-input" type="checkbox" name="existing_categories[]" 
                                                               id="category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                                        <label class="form-check-label flex-grow-1 ms-2" for="category-<?php echo $category['id']; ?>">
                                                            <span class="category-name" data-category-id="<?php echo $category['id']; ?>">
                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                            </span>
                                                        </label>
                                                        <div class="category-actions ms-2">
                                                            <button type="button" class="btn btn-sm btn-link p-0 me-1 edit-category-btn" 
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    title="Edit category">
                                                                <i class="fas fa-edit text-warning" style="font-size: 0.8rem;"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-link p-0 delete-category-btn" 
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    title="Delete category">
                                                                <i class="fas fa-trash text-danger" style="font-size: 0.8rem;"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> <span id="submit-text">Add Channel</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Suggestions Section -->
        <div class="row mt-4" id="suggestions-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>User Suggestions (<?php echo $total_suggestions; ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($suggestions)): ?>
                            <p class="text-light">No suggestions submitted yet.</p>
                        <?php else: ?>
                            <!-- Top Pagination Controls -->
                            <?php if ($total_suggestions_pages > 1): ?>
                                <div class="top-pagination d-flex justify-content-center align-items-center mb-3" style="gap: 8px;">
                                    <button class="btn btn-outline-light btn-sm" <?php echo $suggestions_page <= 1 ? 'disabled' : ''; ?>
                                            onclick="loadTableDataNoScroll('suggestions', <?php echo $suggestions_page - 1; ?>)" style="margin-right: 10px; min-width: 80px;">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </button>
                                    <div class="dropdown" style="margin-right: 10px;">
                                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 160px;">
                                            Page <?php echo $suggestions_page; ?> of <?php echo $total_suggestions_pages; ?>
                                        </button>
                                        <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                                            <?php for ($i = 1; $i <= $total_suggestions_pages; $i++): ?>
                                                <li><a class="dropdown-item <?php echo $i == $suggestions_page ? 'active' : ''; ?>" href="#" data-page="<?php echo $i; ?>" data-table="suggestions">Page <?php echo $i; ?> of <?php echo $total_suggestions_pages; ?></a></li>
                                            <?php endfor; ?>
                                        </ul>
                                    </div>
                                    <button class="btn btn-outline-light btn-sm" <?php echo $suggestions_page >= $total_suggestions_pages ? 'disabled' : ''; ?>
                                            onclick="loadTableDataNoScroll('suggestions', <?php echo $suggestions_page + 1; ?>)" style="min-width: 70px;">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th class="text-light">ID</th>
                                            <th class="text-light">Link</th>
                                            <th class="text-light">Type</th>
                                            <th class="text-light">Submitted</th>
                                            <th class="text-light">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($suggestions as $suggestion): ?>
                                            <tr>
                                                <td class="text-light"><?php echo $suggestion['id']; ?></td>
                                                <td class="text-light">
                                                    <a href="<?php echo htmlspecialchars($suggestion['link']); ?>" 
                                                       target="_blank" 
                                                       class="text-info text-decoration-none">
                                                        <?php 
                                                        $display_link = htmlspecialchars($suggestion['link']);
                                                        echo strlen($display_link) > 60 ? substr($display_link, 0, 60) . '...' : $display_link;
                                                        ?>
                                                        <i class="fas fa-external-link-alt ms-1" style="font-size: 0.8rem;"></i>
                                                    </a>
                                                </td>
                                                <td class="text-light">
                                                    <?php
                                                    $link = $suggestion['link'];
                                                    if (preg_match('/watch\?v=|youtu\.be\//', $link)) {
                                                        echo '<span class="badge bg-danger">Video</span>';
                                                    } elseif (preg_match('/channel\/|@|\/c\/|\/user\//', $link)) {
                                                        echo '<span class="badge bg-primary">Channel</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary">Unknown</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-light">
                                                    <?php 
                                                    $timestamp = strtotime($suggestion['timestamp']);
                                                    echo date('M j, Y g:i A', $timestamp);
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" 
                                                                class="btn btn-outline-success" 
                                                                onclick="addSuggestionToForm('<?php echo htmlspecialchars($suggestion['link']); ?>')"
                                                                title="Add to Form">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-outline-danger" 
                                                                onclick="deleteSuggestion(<?php echo $suggestion['id']; ?>)"
                                                                title="Delete Suggestion">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bottom Item Count -->
                            <div class="bottom-item-count text-center text-light mt-3">
                                Showing <?php echo (($suggestions_page - 1) * $suggestions_per_page) + 1; ?>-<?php echo min($suggestions_page * $suggestions_per_page, $total_suggestions); ?> of <?php echo $total_suggestions; ?> suggestions
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4" id="channels-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>All Channels</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_channels)): ?>
                            <p class="text-light">No channels added yet. Add your first channel above!</p>
                        <?php else: ?>
                            <!-- Top Pagination Controls -->
                            <?php if ($total_channels_pages > 1): ?>
                                <div class="top-pagination d-flex justify-content-center align-items-center mb-3" style="gap: 8px;">
                                    <button class="btn btn-outline-light btn-sm" <?php echo $channels_page <= 1 ? 'disabled' : ''; ?>
                                            onclick="loadTableDataNoScroll('channels', <?php echo $channels_page - 1; ?>)" style="margin-right: 10px; min-width: 80px;">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </button>
                                    <div class="dropdown" style="margin-right: 10px;">
                                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 160px;">
                                            Page <?php echo $channels_page; ?> of <?php echo $total_channels_pages; ?>
                                        </button>
                                        <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                                            <?php for ($i = 1; $i <= $total_channels_pages; $i++): ?>
                                                <li><a class="dropdown-item <?php echo $i == $channels_page ? 'active' : ''; ?>" href="#" data-page="<?php echo $i; ?>" data-table="channels">Page <?php echo $i; ?> of <?php echo $total_channels_pages; ?></a></li>
                                            <?php endfor; ?>
                                        </ul>
                                    </div>
                                    <button class="btn btn-outline-light btn-sm" <?php echo $channels_page >= $total_channels_pages ? 'disabled' : ''; ?>
                                            onclick="loadTableDataNoScroll('channels', <?php echo $channels_page + 1; ?>)" style="min-width: 70px;">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th class="text-light">ID</th>
                                            <th class="text-light">Name</th>
                                            <th class="text-light">Description</th>
                                            <th class="text-light">Category</th>
                                            <th class="text-light">Created</th>
                                            <th class="text-light">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_channels as $channel): ?>
                                            <tr>
                                                <td class="text-light"><?php echo $channel['id']; ?></td>
                                                <td class="text-light">
                                                    <a href="<?php echo htmlspecialchars($channel['youtube_url']); ?>" target="_blank" class="text-info text-decoration-underline">
                                                        <?php echo htmlspecialchars($channel['name']); ?>
                                                    </a>
                                                </td>
                                                <td class="text-light">
                                                    <?php 
                                                    $description = htmlspecialchars($channel['description']); 
                                                    echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($channel['category_names']): ?>
                                                        <?php
                                                        $categories = explode(', ', $channel['category_names']);
                                                        foreach ($categories as $cat):
                                                            if (trim($cat)):
                                                        ?>
                                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                                        <?php
                                                            endif;
                                                        endforeach;
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark">No categories</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-light"><?php echo date('Y-m-d', strtotime($channel['created_at'])); ?></td>
                                                <td>
                                                    <div class="row g-1">
                                                        <div class="col-6">
                                                            <button type="button" class="btn btn-sm btn-outline-warning w-100 d-flex justify-content-center align-items-center edit-channel-btn" 
                                                                    data-channel-id="<?php echo $channel['id']; ?>"
                                                                    data-channel-name="<?php echo htmlspecialchars($channel['name'], ENT_QUOTES); ?>"
                                                                    data-channel-description="<?php echo htmlspecialchars($channel['description'] ?? '', ENT_QUOTES); ?>"
                                                                    title="Edit channel" data-bs-toggle="tooltip" data-bs-placement="top">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </div>
                                                        <div class="col-6">
                                                            <button type="button" class="btn btn-sm btn-outline-danger w-100 d-flex justify-content-center align-items-center delete-channel-btn" 
                                                                    data-channel-id="<?php echo $channel['id']; ?>"
                                                                    data-channel-name="<?php echo htmlspecialchars($channel['name'], ENT_QUOTES); ?>"
                                                                    title="Delete channel and all its videos" data-bs-toggle="tooltip" data-bs-placement="top">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bottom Item Count -->
                            <div class="bottom-item-count text-center text-light mt-3">
                                Showing <?php echo (($channels_page - 1) * $channels_per_page) + 1; ?>-<?php echo min($channels_page * $channels_per_page, $total_channels); ?> of <?php echo $total_channels; ?> channels
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4" id="videos-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>All Videos</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_videos)): ?>
                            <p class="text-light">No videos added yet. Add your first video above!</p>
                        <?php else: ?>
                            <!-- Top Pagination Controls -->
                            <?php if ($total_videos_pages > 1): ?>
                                <div class="top-pagination d-flex justify-content-center align-items-center mb-3" style="gap: 8px;">
                                    <button class="btn btn-outline-light btn-sm" <?php echo $videos_page <= 1 ? 'disabled' : ''; ?>
                                            onclick="loadTableDataNoScroll('videos', <?php echo $videos_page - 1; ?>)" style="margin-right: 10px; min-width: 80px;">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </button>
                                    <div class="dropdown" style="margin-right: 10px;">
                                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 160px;">
                                            Page <?php echo $videos_page; ?> of <?php echo $total_videos_pages; ?>
                                        </button>
                                        <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                                            <?php for ($i = 1; $i <= $total_videos_pages; $i++): ?>
                                                <li><a class="dropdown-item <?php echo $i == $videos_page ? 'active' : ''; ?>" href="#" data-page="<?php echo $i; ?>" data-table="videos">Page <?php echo $i; ?> of <?php echo $total_videos_pages; ?></a></li>
                                            <?php endfor; ?>
                                        </ul>
                                    </div>
                                    <button class="btn btn-outline-light btn-sm" <?php echo $videos_page >= $total_videos_pages ? 'disabled' : ''; ?>
                                            onclick="loadTableDataNoScroll('videos', <?php echo $videos_page + 1; ?>)" style="min-width: 70px;">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th class="text-light">ID</th>
                                            <th class="text-light">Title</th>
                                            <th class="text-light">Channel</th>
                                            <th class="text-light">Added</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_videos as $video): ?>
                                            <tr>
                                                <td class="text-light"><?php echo $video['id']; ?></td>
                                                <td class="text-light">
                                                    <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="text-info text-decoration-underline">
                                                        <?php echo htmlspecialchars($video['title']); ?>
                                                    </a>
                                                </td>
                                                <td class="text-light"><?php echo htmlspecialchars($video['channel_name']); ?></td>
                                                <td class="text-light"><?php echo $video['created_at'] ? date('Y-m-d H:i', strtotime($video['created_at'])) : 'Unknown'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bottom Item Count -->
                            <div class="bottom-item-count text-center text-light mt-3">
                                Showing <?php echo (($videos_page - 1) * $videos_per_page) + 1; ?>-<?php echo min($videos_page * $videos_per_page, $total_videos); ?> of <?php echo $total_videos; ?> videos
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Channel Modal -->
    <div class="modal fade" id="editChannelModal" tabindex="-1" aria-labelledby="editChannelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="editChannelModalLabel">Edit Channel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_channel">
                        <input type="hidden" name="channel_id" id="edit-channel-id">
                        
                        <h6 id="edit-channel-name" class="mb-3"></h6>
                        
                        <!-- Channel Description -->
                        <div class="mb-3">
                            <label for="edit-channel-description" class="form-label">Channel Description:</label>
                            <textarea class="form-control" id="edit-channel-description" name="channel_description" rows="3"
                                      placeholder="Enter channel description..."></textarea>
                        </div>
                        
                        <!-- Current Categories Display -->
                        <div class="mb-3">
                            <label class="form-label">Current Categories:</label>
                            <div id="current-categories-display"></div>
                        </div>
                        
                        <!-- Category Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select Categories (select multiple):</label>
                            <div class="row" id="edit-categories-checkboxes">
                                <?php 
                                // Split categories into two columns for vertical ordering (same as add form)
                                $total_categories_edit = count($all_categories);
                                $per_column_edit = ceil($total_categories_edit / 2);
                                $first_column_edit = array_slice($all_categories, 0, $per_column_edit);
                                $second_column_edit = array_slice($all_categories, $per_column_edit);
                                ?>
                                <div class="col-md-6">
                                    <?php foreach ($first_column_edit as $category): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="edit_existing_categories[]" 
                                                   id="edit-category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                            <label class="form-check-label" for="edit-category-<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php foreach ($second_column_edit as $category): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="edit_existing_categories[]" 
                                                   id="edit-category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                            <label class="form-check-label" for="edit-category-<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Category Creation -->
                        <div class="border-top pt-3">
                            <h6>Or create new categories:</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="edit-new-category" class="form-label">Category Names</label>
                                    <input type="text" class="form-control" id="edit-new-category" name="edit_new_category" 
                                           placeholder="e.g., Education, Entertainment, Science (comma-separated)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="edit-new-category-description" class="form-label">Description (optional)</label>
                                    <textarea class="form-control" id="edit-new-category-description" name="edit_new_category_description" 
                                              rows="3" placeholder="Brief description of the category"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Channel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
    <script>
        // Handle dropdown content type selection
        document.querySelectorAll('#content-type-dropdown + .dropdown-menu .dropdown-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const value = this.getAttribute('data-value');
                const text = this.textContent;
                
                // Check if form has content before changing type
                const contentInput = document.getElementById('content-input');
                const descriptionField = document.getElementById('new-category-description');
                const newCategoryField = document.getElementById('new-category');
                const hasContent = (contentInput && contentInput.value.trim()) || 
                                 (descriptionField && descriptionField.value.trim()) ||
                                 (newCategoryField && newCategoryField.value.trim()) ||
                                 document.querySelector('input[name="existing_categories[]"]:checked');
                
                // If form has content, clear it first
                if (hasContent) {
                    clearAddNewContentForm();
                }
                
                // Update button text and hidden input
                document.getElementById('content-type-text').textContent = text;
                document.getElementById('content-type').value = value;
                
                // Update UI based on selection
                updateUIForContentType(value);
            });
        });
        
        // Function to update UI based on content type
        function updateUIForContentType(contentType) {
            const input = document.getElementById('content-input');
            const submitText = document.getElementById('submit-text');
            const categorySection = document.getElementById('category-section');
            const contentInfo = document.getElementById('content-info');
            const contentInfoText = document.getElementById('content-info-text');
            
            if (contentType === 'channel') {
                input.placeholder = '@username, channelname, or full URL';
                submitText.textContent = 'Add Channel';
                categorySection.style.display = 'block';
                contentInfo.style.display = 'none';
            } else if (contentType === 'video') {
                input.placeholder = 'dQw4w9WgXcQ or full URL';
                submitText.textContent = 'Add Video';
                
                // For videos, show default state until we check if channel exists
                categorySection.style.display = 'block';
                contentInfo.style.display = 'block';
                contentInfoText.textContent = 'Enter a video URL to check if its channel exists in the database.';
            }
            
            console.log('Updated UI for content type:', contentType);
        }
        
        // Function to clear the Add New Content form completely
        function clearAddNewContentForm() {
            // Clear URL input
            const contentInput = document.getElementById('content-input');
            if (contentInput) contentInput.value = '';
            
            // Clear description
            const descriptionField = document.getElementById('new-category-description');
            if (descriptionField) descriptionField.value = '';
            
            // Clear new category input
            const newCategoryField = document.getElementById('new-category');
            if (newCategoryField) newCategoryField.value = '';
            
            // Uncheck all existing category checkboxes
            const categoryCheckboxes = document.querySelectorAll('input[name="existing_categories[]"]');
            categoryCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Hide clear button
            const clearButton = document.getElementById('clear-content-btn');
            if (clearButton) clearButton.style.display = 'none';
            
            // Reset to default content type (channel)
            const contentTypeField = document.getElementById('content-type');
            const contentTypeText = document.getElementById('content-type-text');
            if (contentTypeField && contentTypeText) {
                contentTypeField.value = 'channel';
                contentTypeText.textContent = 'Channel';
                updateUIForContentType('channel');
            }
        }
        
        // Function to initialize tooltips
        function initializeTooltips() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                // Check if tooltip already exists
                var existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (!existingTooltip) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                }
                return existingTooltip;
            });
        }
        
        // Clear button functionality
        function initializeClearButton() {
            const contentInput = document.getElementById('content-input');
            const clearButton = document.getElementById('clear-content-btn');
            const descriptionField = document.getElementById('new-category-description');
            
            // Show/hide clear button based on input content
            function toggleClearButton() {
                const hasContent = contentInput.value.trim() || descriptionField.value.trim();
                clearButton.style.display = hasContent ? 'block' : 'none';
            }
            
            // Listen for input changes
            contentInput.addEventListener('input', toggleClearButton);
            descriptionField.addEventListener('input', toggleClearButton);
            
            // Clear button click handler
            clearButton.addEventListener('click', function() {
                contentInput.value = '';
                descriptionField.value = '';
                toggleClearButton();
                contentInput.focus();
            });
            
            // Initial state
            toggleClearButton();
        }
        
        // Auto-fetch functionality with debouncing
        function initializeAutoFetch() {
            const contentInput = document.getElementById('content-input');
            const contentType = document.getElementById('content-type');
            let fetchTimeout;
            
            contentInput.addEventListener('input', function() {
                // Clear previous timeout
                clearTimeout(fetchTimeout);
                
                // Set new timeout (500ms delay)
                fetchTimeout = setTimeout(() => {
                    const url = contentInput.value.trim();
                    const descriptionField = document.getElementById('new-category-description');
                    
                    // Only fetch if URL is valid and description field is empty
                    if (url && isValidYouTubeURL(url) && !descriptionField.value.trim()) {
                        fetchContentDescription(url, contentType.value);
                    }
                }, 500);
            });
        }
        
        // Validate YouTube URL or identifier
        function isValidYouTubeURL(input) {
            // First check if it's a handle
            if (input.startsWith('@')) {
                return true;
            }
            
            // Check if it's just a video ID (11 characters)
            if (/^[a-zA-Z0-9_-]{11}$/.test(input)) {
                return true;
            }
            
            // Check if it's a channel name (alphanumeric)
            if (/^[a-zA-Z0-9_-]+$/.test(input) && input.length > 3 && input.length < 50) {
                return true;
            }
            
            // Check URL patterns
            const patterns = [
                /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,           // Video
                /youtube\.com\/channel\/([a-zA-Z0-9_-]+)/,              // Channel ID
                /youtube\.com\/c\/([a-zA-Z0-9_-]+)/,                    // Custom channel
                /youtube\.com\/user\/([a-zA-Z0-9_-]+)/,                 // User channel
                /youtube\.com\/@([a-zA-Z0-9_.-]+)/,                     // Handle
                /youtu\.be\/([a-zA-Z0-9_-]{11})/,                       // Short video URL
            ];
            
            return patterns.some(pattern => pattern.test(input));
        }
        
        // Fetch content description from YouTube
        function checkChannelExistence(videoUrl) {
            const categorySection = document.getElementById('category-section');
            const contentInfo = document.getElementById('content-info');
            const contentInfoText = document.getElementById('content-info-text');
            
            // Show checking state
            contentInfoText.textContent = 'Checking if channel exists...';
            
            fetch('admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_channel_exists',
                    video_url: videoUrl
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.channel_exists) {
                        // Channel exists - hide category section
                        categorySection.style.display = 'none';
                        contentInfoText.textContent = `Channel "${data.channel_name}" already exists. Video will be added to this channel with its existing categories.`;
                    } else {
                        // Channel doesn't exist - show category section
                        categorySection.style.display = 'block';
                        contentInfoText.textContent = `Channel "${data.channel_name}" doesn't exist yet. It will be created with the categories you select below.`;
                    }
                } else {
                    // Error checking - show category section as fallback
                    categorySection.style.display = 'block';
                    contentInfoText.textContent = 'Could not check if channel exists. Select categories in case a new channel needs to be created.';
                }
            })
            .catch(error => {
                console.error('Error checking channel existence:', error);
                // Error - show category section as fallback
                categorySection.style.display = 'block';
                contentInfoText.textContent = 'Could not check if channel exists. Select categories in case a new channel needs to be created.';
            });
        }
        
        function fetchContentDescription(url, type) {
            // Show loading indicator
            const descriptionField = document.getElementById('new-category-description');
            const originalPlaceholder = descriptionField.placeholder;
            descriptionField.placeholder = 'Fetching description...';
            descriptionField.disabled = true;
            
            // For videos, also check if channel exists
            if (type === 'video') {
                checkChannelExistence(url);
            }
            
            // Fetch YouTube metadata using the admin_ajax.php endpoint
            fetch('admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'fetch_metadata',
                    url: url,
                    type: type
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.description) {
                    descriptionField.value = data.description;
                }
                console.log('Auto-fetch completed:', data);
            })
            .catch(error => {
                console.error('Error fetching description:', error);
                // Make sure field is restored even on error
                descriptionField.placeholder = originalPlaceholder;
                descriptionField.disabled = false;
            })
            .finally(() => {
                // Restore field state
                descriptionField.placeholder = originalPlaceholder;
                descriptionField.disabled = false;
            });
        }
        
        // Initialize UI on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check initial content type
            const contentType = document.getElementById('content-type').value || 'channel';
            updateUIForContentType(contentType);
            
            // Initialize Bootstrap tooltips
            initializeTooltips();
            
            // Initialize auto-fetch functionality
            initializeAutoFetch();
            
            // Initialize clear button functionality
            initializeClearButton();
            
            
            // Add event listener for pagination dropdown items
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-item') && e.target.hasAttribute('data-page')) {
                    e.preventDefault();
                    const table = e.target.getAttribute('data-table');
                    const page = parseInt(e.target.getAttribute('data-page'));
                    if (table && page) {
                        loadTableDataNoScroll(table, page);
                    }
                }
            });
            
            // Add event listeners for edit and delete buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.edit-channel-btn')) {
                    const btn = e.target.closest('.edit-channel-btn');
                    const channelId = parseInt(btn.getAttribute('data-channel-id'));
                    const channelName = btn.getAttribute('data-channel-name');
                    const channelDescription = btn.getAttribute('data-channel-description');
                    openEditModal(channelId, channelName, channelDescription);
                } else if (e.target.closest('.delete-channel-btn')) {
                    const btn = e.target.closest('.delete-channel-btn');
                    const channelId = parseInt(btn.getAttribute('data-channel-id'));
                    const channelName = btn.getAttribute('data-channel-name');
                    deleteChannel(channelId, channelName);
                } else if (e.target.closest('.edit-category-btn')) {
                    const btn = e.target.closest('.edit-category-btn');
                    const categoryId = parseInt(btn.getAttribute('data-category-id'));
                    editCategoryInline(categoryId);
                } else if (e.target.closest('.delete-category-btn')) {
                    const btn = e.target.closest('.delete-category-btn');
                    const categoryId = parseInt(btn.getAttribute('data-category-id'));
                    deleteCategory(categoryId);
                }
            });
        });
        
        // Also initialize tooltips after the page has fully loaded (catches any late-rendered elements)
        window.addEventListener('load', function() {
            setTimeout(initializeTooltips, 100);
        });
        
        // Function to show toast
        function showToast(message, type = 'success') {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            const toastContainer = document.getElementById('toastContainer');
            toastContainer.innerHTML = toastHTML;
            
            const toastElement = toastContainer.querySelector('.toast');
            const toast = new bootstrap.Toast(toastElement, {
                animation: true,
                autohide: true,
                delay: 5000
            });
            
            toast.show();
        }
        
        // Show toast if there's a message or error
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?php echo json_encode($message); ?>, 'success');
        });
        <?php endif; ?>
        
        <?php if ($error): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?php echo json_encode($error); ?>, 'danger');
        });
        <?php endif; ?>
        
        // No longer needed - categories are always visible in the new interface
        
        // Form validation for multiple categories
        document.getElementById('add-content-form').addEventListener('submit', function(e) {
            const contentType = document.getElementById('content-type').value;
            
            if (contentType === 'channel') {
                const selectedCategories = document.querySelectorAll('input[name="existing_categories[]"]:checked');
                const newCategory = document.getElementById('new-category').value.trim();
                
                if (selectedCategories.length === 0 && !newCategory) {
                    e.preventDefault();
                    alert('Please select at least one existing category or create a new one.');
                    return;
                }
                
                if (newCategory) {
                    // Check if any category name is too short when split by comma
                    const categoryNames = newCategory.split(',').map(name => name.trim()).filter(name => name.length > 0);
                    const hasShortName = categoryNames.some(name => name.length < 2);
                    
                    if (hasShortName) {
                        e.preventDefault();
                        alert('Each category name must be at least 2 characters long.');
                        return;
                    }
                }
            }
        });
        
        // Function to open edit channel modal
        function openEditModal(channelId, channelName, channelDescription) {
            // Set channel info
            document.getElementById('edit-channel-id').value = channelId;
            document.getElementById('edit-channel-name').textContent = 'Editing: ' + channelName;
            
            // Set channel description
            document.getElementById('edit-channel-description').value = channelDescription || '';
            
            // Clear previous selections and new category fields
            document.querySelectorAll('input[name="edit_existing_categories[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('edit-new-category').value = '';
            document.getElementById('edit-new-category-description').value = '';
            
            // Fetch current categories for this channel
            fetch('?action=get_channel_categories&channel_id=' + channelId)
                .then(response => response.json())
                .then(data => {
                    // Display current categories
                    const currentCategoriesDiv = document.getElementById('current-categories-display');
                    if (data.categories && data.categories.length > 0) {
                        currentCategoriesDiv.innerHTML = data.categories.map(cat => 
                            '<span class="badge bg-secondary me-1">' + cat.name + '</span>'
                        ).join('');
                        
                        // Pre-select current categories
                        data.categories.forEach(cat => {
                            const checkbox = document.getElementById('edit-category-' + cat.id);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    } else {
                        currentCategoriesDiv.innerHTML = '<span class="badge bg-light text-dark">No categories</span>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching categories:', error);
                    document.getElementById('current-categories-display').innerHTML = '<span class="text-danger">Error loading current categories</span>';
                });
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editChannelModal'));
            modal.show();
        }
        
        // Function to delete channel with confirmation
        function deleteChannel(channelId, channelName) {
            if (confirm(`Are you sure you want to delete the channel "${channelName}" and all its videos? This action cannot be undone.`)) {
                // Create and submit a form for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_channel';
                
                const channelIdInput = document.createElement('input');
                channelIdInput.type = 'hidden';
                channelIdInput.name = 'channel_id';
                channelIdInput.value = channelId;
                
                form.appendChild(actionInput);
                form.appendChild(channelIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Function to edit category inline
        function editCategoryInline(categoryId) {
            const categorySpan = document.querySelector(`.category-name[data-category-id="${categoryId}"]`);
            if (!categorySpan) return;
            
            const currentName = categorySpan.textContent.trim();
            
            // Create input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentName;
            input.className = 'form-control form-control-sm';
            input.style.width = '150px';
            input.style.display = 'inline-block';
            
            // Replace span with input
            categorySpan.style.display = 'none';
            categorySpan.parentNode.insertBefore(input, categorySpan);
            input.focus();
            input.select();
            
            // Save on Enter key
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    saveCategoryName(categoryId, input.value.trim(), categorySpan, input);
                }
            });
            
            // Cancel on Escape or blur
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cancelCategoryEdit(categorySpan, input);
                }
            });
            
            input.addEventListener('blur', function() {
                setTimeout(() => cancelCategoryEdit(categorySpan, input), 100);
            });
        }
        
        // Function to save category name
        function saveCategoryName(categoryId, newName, categorySpan, input) {
            if (!newName || newName.length < 2) {
                alert('Category name must be at least 2 characters long.');
                input.focus();
                return;
            }
            
            // Send AJAX request
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=edit_category&category_id=${categoryId}&category_name=${encodeURIComponent(newName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    categorySpan.textContent = newName;
                    categorySpan.style.display = 'inline';
                    input.remove();
                } else {
                    alert(data.error || 'Failed to update category name.');
                    input.focus();
                }
            })
            .catch(error => {
                alert('Error updating category name.');
                input.focus();
            });
        }
        
        // Function to cancel category edit
        function cancelCategoryEdit(categorySpan, input) {
            categorySpan.style.display = 'inline';
            input.remove();
        }
        
        // Function to delete category
        function deleteCategory(categoryId) {
            // Get category name for confirmation
            const categorySpan = document.querySelector(`.category-name[data-category-id="${categoryId}"]`);
            const categoryName = categorySpan ? categorySpan.textContent.trim() : 'this category';
            
            if (confirm(`Are you sure you want to delete "${categoryName}"? This will remove it from all channels. This action cannot be undone.`)) {
                // Send AJAX request
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_category&category_id=${categoryId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to refresh category lists
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to delete category.');
                    }
                })
                .catch(error => {
                    alert('Error deleting category.');
                });
            }
        }
        
        // Function to add suggestion to content form
        function addSuggestionToForm(link) {
            const contentInput = document.getElementById('content-input');
            if (contentInput) {
                // Clear the entire form first
                clearAddNewContentForm();
                
                // Now add the new content
                contentInput.value = link;
                
                // Detect content type and update UI
                const contentType = detectContentType(link);
                const contentTypeField = document.getElementById('content-type');
                const contentTypeText = document.getElementById('content-type-text');
                
                if (contentType) {
                    contentTypeField.value = contentType;
                    contentTypeText.textContent = contentType === 'video' ? 'Video' : 'Channel';
                    updateUIForContentType(contentType);
                }
                
                // Auto-fetch description if valid URL
                if (isValidYouTubeURL(link)) {
                    fetchContentDescription(link, contentType);
                }
                
                // Update clear button visibility
                const clearButton = document.getElementById('clear-content-btn');
                if (clearButton) {
                    clearButton.style.display = 'block';
                }
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Highlight the input briefly
                contentInput.focus();
                contentInput.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                setTimeout(() => {
                    contentInput.style.backgroundColor = '';
                }, 2000);
            }
        }
        
        // Detect content type from URL
        function detectContentType(url) {
            if (/watch\?v=|youtu\.be\//.test(url)) {
                return 'video';
            } else if (/channel\/|@|\/c\/|\/user\//.test(url)) {
                return 'channel';
            }
            return 'channel'; // Default to channel
        }
        
        // Function to delete suggestion
        function deleteSuggestion(suggestionId) {
            if (confirm('Are you sure you want to delete this suggestion?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_suggestion&suggestion_id=${suggestionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Suggestion deleted successfully', 'success');
                        // Reload suggestions table
                        loadTableData('suggestions', getCurrentPage('suggestions'));
                    } else {
                        showToast(data.error || 'Failed to delete suggestion', 'danger');
                    }
                })
                .catch(error => {
                    showToast('Error deleting suggestion', 'danger');
                });
            }
        }
        
        // AJAX Pagination Functions
        const currentPages = {
            channels: <?php echo $channels_page; ?>,
            videos: <?php echo $videos_page; ?>,
            suggestions: <?php echo $suggestions_page; ?>
        };
        
        function getCurrentPage(table) {
            return currentPages[table] || 1;
        }
        
        function setCurrentPage(table, page) {
            currentPages[table] = page;
        }
        
        function loadTableData(table, page) {
            // Show loading state
            showLoadingState(table);
            
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table: table,
                    page: page
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(table, data);
                    updatePagination(table, data);
                    setCurrentPage(table, data.current_page);
                } else {
                    showToast('Error loading data: ' + (data.error || 'Unknown error'), 'danger');
                }
            })
            .catch(error => {
                showToast('Network error loading data', 'danger');
                console.error('Error:', error);
            })
            .finally(() => {
                hideLoadingState(table);
            });
        }
        
        function loadTableDataNoScroll(table, page) {
            // Prevent any scrolling during the entire operation
            const currentScrollY = window.scrollY;
            const currentScrollX = window.scrollX;
            
            // Lock scroll position
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            
            // Show loading state
            showLoadingState(table);
            
            fetch('/admin_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    table: table,
                    page: page
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Preserve table container height to prevent layout shift
                    const tableContainer = document.querySelector(`#${table}-section .table-responsive`);
                    const originalHeight = tableContainer ? tableContainer.offsetHeight : null;
                    
                    if (tableContainer && originalHeight) {
                        tableContainer.style.minHeight = originalHeight + 'px';
                    }
                    
                    updateTable(table, data);
                    updatePagination(table, data);
                    setCurrentPage(table, data.current_page);
                    
                    // Remove height constraint after a brief moment
                    setTimeout(() => {
                        if (tableContainer) {
                            tableContainer.style.minHeight = '';
                        }
                    }, 100);
                    
                } else {
                    showToast('Error loading data: ' + (data.error || 'Unknown error'), 'danger');
                }
            })
            .catch(error => {
                showToast('Network error loading data', 'danger');
                console.error('Error:', error);
            })
            .finally(() => {
                hideLoadingState(table);
                
                // Restore scroll and unlock scrolling
                window.scrollTo(currentScrollX, currentScrollY);
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            });
        }
        
        function showLoadingState(table) {
            const tbody = document.querySelector(`#${table}-section tbody`);
            if (tbody) {
                // Just add opacity without replacing content to reduce flashing
                tbody.style.opacity = '0.5';
                tbody.style.pointerEvents = 'none';
            }
        }
        
        function hideLoadingState(table) {
            const tbody = document.querySelector(`#${table}-section tbody`);
            if (tbody) {
                tbody.style.opacity = '1';
                tbody.style.pointerEvents = 'auto';
            }
        }
        
        function updateTable(table, data) {
            const tbody = document.querySelector(`#${table}-section tbody`);
            if (!tbody) return;
            
            // Reset any height constraints from loading state
            tbody.style.height = '';
            
            if (data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="100%" class="text-light">No ${table} found.</td></tr>`;
                return;
            }
            
            let html = '';
            
            if (table === 'channels') {
                data.data.forEach(channel => {
                    const categories = channel.category_names ? 
                        channel.category_names.split(', ').map(cat => 
                            `<span class="badge bg-secondary me-1">${escapeHtml(cat.trim())}</span>`
                        ).join('') : 
                        '<span class="badge bg-light text-dark">No categories</span>';
                    
                    const description = escapeHtml(channel.description || '');
                    const truncatedDesc = description.length > 200 ? description.substring(0, 200) + '...' : description;
                    
                    html += `
                        <tr>
                            <td class="text-light">${channel.id}</td>
                            <td class="text-light">
                                <a href="${escapeHtml(channel.youtube_url)}" target="_blank" class="text-info text-decoration-underline">
                                    ${escapeHtml(channel.name)}
                                </a>
                            </td>
                            <td class="text-light">${truncatedDesc}</td>
                            <td>${categories}</td>
                            <td class="text-light">${formatDate(channel.created_at)}</td>
                            <td>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-sm btn-outline-warning w-100 d-flex justify-content-center align-items-center edit-channel-btn" 
                                                data-channel-id="${channel.id}"
                                                data-channel-name="${escapeHtml(channel.name)}"
                                                data-channel-description="${escapeHtml(channel.description || '')}"
                                                title="Edit channel">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 d-flex justify-content-center align-items-center delete-channel-btn" 
                                                data-channel-id="${channel.id}"
                                                data-channel-name="${escapeHtml(channel.name)}"
                                                title="Delete channel and all its videos">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else if (table === 'videos') {
                data.data.forEach(video => {
                    html += `
                        <tr>
                            <td class="text-light">${video.id}</td>
                            <td class="text-light">
                                <a href="${escapeHtml(video.video_url)}" target="_blank" class="text-info text-decoration-underline">
                                    ${escapeHtml(video.title)}
                                </a>
                            </td>
                            <td class="text-light">${escapeHtml(video.channel_name)}</td>
                            <td class="text-light">${video.created_at ? formatDateTime(video.created_at) : 'Unknown'}</td>
                        </tr>
                    `;
                });
            } else if (table === 'suggestions') {
                data.data.forEach(suggestion => {
                    const link = escapeHtml(suggestion.link);
                    const displayLink = link.length > 60 ? link.substring(0, 60) + '...' : link;
                    
                    let badgeClass = 'bg-secondary';
                    let badgeText = 'Unknown';
                    if (/watch\?v=|youtu\.be\//.test(suggestion.link)) {
                        badgeClass = 'bg-danger';
                        badgeText = 'Video';
                    } else if (/channel\/|@|\/c\/|\/user\//.test(suggestion.link)) {
                        badgeClass = 'bg-primary';
                        badgeText = 'Channel';
                    }
                    
                    html += `
                        <tr>
                            <td class="text-light">${suggestion.id}</td>
                            <td class="text-light">
                                <a href="${link}" target="_blank" class="text-info text-decoration-none">
                                    ${displayLink}
                                    <i class="fas fa-external-link-alt ms-1" style="font-size: 0.8rem;"></i>
                                </a>
                            </td>
                            <td class="text-light">
                                <span class="badge ${badgeClass}">${badgeText}</span>
                            </td>
                            <td class="text-light">${formatDateTime(suggestion.timestamp)}</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-success" onclick="addSuggestionToForm('${link}')" title="Add to Form">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteSuggestion(${suggestion.id})" title="Delete Suggestion">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            
            tbody.innerHTML = html;
        }
        
        function updatePagination(table, data) {
            // Update top pagination controls
            const topPaginationContainer = document.querySelector(`#${table}-section .top-pagination`);
            const itemCountContainer = document.querySelector(`#${table}-section .bottom-item-count`);
            
            // Update item count at bottom
            if (itemCountContainer) {
                const start = ((data.current_page - 1) * data.per_page) + 1;
                const end = Math.min(data.current_page * data.per_page, data.total_items);
                itemCountContainer.textContent = `Showing ${start}-${end} of ${data.total_items} ${table}`;
            }
            
            if (!topPaginationContainer) return;
            
            // Hide pagination if only one page
            if (data.total_pages <= 1) {
                topPaginationContainer.style.display = 'none';
                return;
            } else {
                topPaginationContainer.style.display = 'flex';
            }
            
            // Create dropdown options
            let options = '';
            for (let i = 1; i <= data.total_pages; i++) {
                const selected = i === data.current_page ? 'selected' : '';
                options += `<option value="${i}" ${selected}>Page ${i} of ${data.total_pages}</option>`;
            }
            
            // Build pagination HTML
            const prevDisabled = data.current_page <= 1 ? 'disabled' : '';
            const nextDisabled = data.current_page >= data.total_pages ? 'disabled' : '';
            
            // Create dropdown menu items
            let menuItems = '';
            for (let i = 1; i <= data.total_pages; i++) {
                const activeClass = i === data.current_page ? 'active' : '';
                menuItems += `<li><a class="dropdown-item ${activeClass}" href="#" data-page="${i}" data-table="${table}">Page ${i} of ${data.total_pages}</a></li>`;
            }
            
            topPaginationContainer.innerHTML = `
                <button class="btn btn-outline-light btn-sm" ${prevDisabled} 
                        onclick="loadTableDataNoScroll('${table}', ${data.current_page - 1})" 
                        style="margin-right: 10px; min-width: 80px;">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="dropdown" style="margin-right: 10px;">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 160px;">
                        Page ${data.current_page} of ${data.total_pages}
                    </button>
                    <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                        ${menuItems}
                    </ul>
                </div>
                <button class="btn btn-outline-light btn-sm" ${nextDisabled} 
                        onclick="loadTableDataNoScroll('${table}', ${data.current_page + 1})" style="min-width: 70px;">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toISOString().split('T')[0]; // YYYY-MM-DD format
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
    </script>
</body>
</html> 