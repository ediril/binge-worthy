<?php
session_start();
require_once 'common.php';
require_once 'database.php';

// Prevent caching of admin page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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

// Functions moved to common.php: cleanup_youtube_url, auto_remove_matching_suggestions

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
                    if (add_category($db, $new_category_name)) {
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
                $_SESSION['admin_message'] = "Channel " . implode(' and ', $success_messages) . " updated successfully!";
                header('Location: admin.php');
                exit;
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
                    $_SESSION['admin_message'] = "Channel '{$channel['name']}' and all its videos have been deleted successfully.";
                    header('Location: admin.php');
                    exit;
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
                $cleaned_input = cleanup_youtube_url($content_input);
                $channel_url = normalize_channel_input($cleaned_input);
                
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
                                            if (add_category($db, $new_category_name)) {
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
                                    $channel_id = add_channel_with_categories($db, $channel_metadata['name'], $channel_metadata['description'], $channel_metadata['youtube_url'], $category_ids, $channel_metadata['thumbnail_url']);
                                    if ($channel_id) {
                                        // Auto-remove matching suggestions
                                        auto_remove_matching_suggestions($channel_metadata['youtube_url']);
                                        
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
                // Don't clean video URLs - cleanup_youtube_url strips video IDs
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
                            // Check if channel exists
                            $stmt = $db->prepare("SELECT id FROM channels WHERE youtube_url = ? OR name = ?");
                            $stmt->execute([$video_metadata['channel_url'], $video_metadata['channel_name']]);
                            $channel = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($channel) {
                                $channel_id = $channel['id'];
                                $created_at = isset($video_metadata['published_at']) ? $video_metadata['published_at'] : null;
                                if (add_video($db, $channel_id, $video_metadata['title'], $video_url, $video_metadata['thumbnail_url'], 1, $created_at)) {
                                    // Auto-remove matching suggestions
                                    auto_remove_matching_suggestions($video_url);
                                    
                                    // Redirect to prevent form resubmission
                                    $_SESSION['admin_message'] = "Video '{$video_metadata['title']}' added successfully!";
                                    header('Location: admin.php');
                                    exit;
                                } else {
                                    $error = "Failed to add video to database.";
                                }
                            } else {
                                $error = "Channel '{$video_metadata['channel_name']}' does not exist. Please add the channel first before adding videos.";
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
$channels_pagination = generate_pagination_data($total_channels, $channels_page, $channels_per_page);

// Get total count of videos
$videos_count_stmt = $db->prepare("SELECT COUNT(*) as total FROM videos");
$videos_count_stmt->execute();
$total_videos = $videos_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$videos_pagination = generate_pagination_data($total_videos, $videos_page, $videos_per_page);

// Get channels for current page
$all_channels = $db->prepare("
    SELECT c.*, GROUP_CONCAT(cat.name, ', ') as category_names
    FROM channels c 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");
$all_channels->bindValue(':limit', $channels_pagination['items_per_page'], PDO::PARAM_INT);
$all_channels->bindValue(':offset', $channels_pagination['offset'], PDO::PARAM_INT);
$all_channels->execute();
$all_channels = $all_channels->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for dropdown
$all_categories = get_display_categories($db);

// Get suggestions from suggestions database with pagination
$suggestions = [];
$suggestions_pagination = generate_pagination_data(0, $suggestions_page, $suggestions_per_page);
try {
    $suggestions_db = new PDO('sqlite:suggestions.db');
    $suggestions_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get total count of suggestions
    $suggestions_count_stmt = $suggestions_db->prepare("SELECT COUNT(*) as total FROM suggestions");
    $suggestions_count_stmt->execute();
    $total_suggestions = $suggestions_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Generate pagination data
    $suggestions_pagination = generate_pagination_data($total_suggestions, $suggestions_page, $suggestions_per_page);
    
    // Get suggestions for current page
    $suggestions_stmt = $suggestions_db->prepare("SELECT * FROM suggestions ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
    $suggestions_stmt->bindValue(':limit', $suggestions_pagination['items_per_page'], PDO::PARAM_INT);
    $suggestions_stmt->bindValue(':offset', $suggestions_pagination['offset'], PDO::PARAM_INT);
    $suggestions_stmt->execute();
    $suggestions = $suggestions_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Suggestions database might not exist yet, that's okay
}

// Get videos for current page
$all_videos = $db->prepare("
    SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
    FROM videos v 
    JOIN channels c ON v.channel_id = c.id 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY v.id
    ORDER BY v.published_at DESC
    LIMIT :limit OFFSET :offset
");
$all_videos->bindValue(':limit', $videos_pagination['items_per_page'], PDO::PARAM_INT);
$all_videos->bindValue(':offset', $videos_pagination['offset'], PDO::PARAM_INT);
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
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Add New Content</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3 d-md-flex justify-content-md-start">
                            <button type="button" class="btn btn-info btn-lg" onclick="openChannelModal('add')">
                                <i class="fas fa-tv me-2"></i>Add Channel
                            </button>
                            <button type="button" class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#addVideoModal">
                                <i class="fas fa-video me-2"></i>Add Video
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Suggestions Section -->
        <div class="row mt-4" id="suggestions-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>User Suggestions</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Configure suggestions table
                        echo render_admin_table([
                            'data' => $suggestions,
                            'pagination_data' => $suggestions_pagination,
                            'table_name' => 'suggestions',
                            'empty_message' => 'No suggestions submitted yet.',
                            'columns' => [
                                ['title' => 'ID', 'key' => 'id', 'type' => 'text'],
                                ['title' => 'Link', 'key' => 'link', 'type' => 'external_link', 'truncate' => 60],
                                [
                                    'title' => 'Type', 
                                    'render' => function($row) {
                                        $link = $row['link'];
                                        if (preg_match('/watch\?v=|youtu\.be\//', $link)) {
                                            return '<span class="badge bg-danger">Video</span>';
                                        } elseif (preg_match('/channel\/|@|\/c\/|\/user\//', $link)) {
                                            return '<span class="badge bg-primary">Channel</span>';
                                        } else {
                                            return '<span class="badge bg-secondary">Unknown</span>';
                                        }
                                    }
                                ],
                                [
                                    'title' => 'Submitted', 
                                    'render' => function($row) {
                                        $timestamp = strtotime($row['timestamp']);
                                        return date('M j, Y g:i A', $timestamp);
                                    }
                                ],
                                [
                                    'title' => 'Actions',
                                    'render' => function($row) {
                                        return sprintf(
                                            '<div class="btn-group btn-group-sm" role="group">
                                                <button type="button" 
                                                        class="btn btn-outline-success" 
                                                        onclick="addSuggestionToForm(\'%s\')"
                                                        title="Add to Form">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-outline-danger" 
                                                        onclick="deleteSuggestion(%d)"
                                                        title="Delete Suggestion">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>',
                                            htmlspecialchars($row['link']),
                                            $row['id']
                                        );
                                    }
                                ]
                            ]
                        ]);
                        ?>
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
                        <?php
                        // Configure channels table
                        echo render_admin_table([
                            'data' => $all_channels,
                            'pagination_data' => $channels_pagination,
                            'table_name' => 'channels',
                            'empty_message' => 'No channels added yet. Add your first channel above!',
                            'columns' => [
                                ['title' => 'ID', 'key' => 'id', 'type' => 'text'],
                                ['title' => 'Name', 'key' => 'name', 'type' => 'link', 'url_key' => 'youtube_url'],
                                ['title' => 'Description', 'key' => 'description', 'type' => 'truncated_text', 'truncate' => 200],
                                [
                                    'title' => 'Category',
                                    'render' => function($row) {
                                        if ($row['category_names']) {
                                            $categories = explode(', ', $row['category_names']);
                                            $badges = '';
                                            foreach ($categories as $cat) {
                                                if (trim($cat)) {
                                                    $badges .= '<span class="badge bg-secondary me-1">' . htmlspecialchars(trim($cat)) . '</span>';
                                                }
                                            }
                                            return $badges;
                                        } else {
                                            return '<span class="badge bg-light text-dark">No categories</span>';
                                        }
                                    }
                                ],
                                ['title' => 'Created', 'key' => 'created_at', 'type' => 'date'],
                                [
                                    'title' => 'Actions',
                                    'render' => function($row) {
                                        return sprintf(
                                            '<div class="row g-1">
                                                <div class="col-6">
                                                    <button type="button" class="btn btn-sm btn-outline-warning w-100 d-flex justify-content-center align-items-center edit-channel-btn" 
                                                            data-channel-id="%d"
                                                            data-channel-name="%s"
                                                            data-channel-description="%s"
                                                            title="Edit channel" data-bs-toggle="tooltip" data-bs-placement="top">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                                <div class="col-6">
                                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 d-flex justify-content-center align-items-center delete-channel-btn" 
                                                            data-channel-id="%d"
                                                            data-channel-name="%s"
                                                            title="Delete channel and all its videos" data-bs-toggle="tooltip" data-bs-placement="top">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>',
                                            $row['id'],
                                            htmlspecialchars($row['name'], ENT_QUOTES),
                                            htmlspecialchars($row['description'] ?? '', ENT_QUOTES),
                                            $row['id'],
                                            htmlspecialchars($row['name'], ENT_QUOTES)
                                        );
                                    }
                                ]
                            ]
                        ]);
                        ?>
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
                        <?php
                        // Configure videos table
                        echo render_admin_table([
                            'data' => $all_videos,
                            'pagination_data' => $videos_pagination,
                            'table_name' => 'videos',
                            'empty_message' => 'No videos added yet. Add your first video above!',
                            'columns' => [
                                ['title' => 'ID', 'key' => 'id', 'type' => 'text'],
                                ['title' => 'Title', 'key' => 'title', 'type' => 'link', 'url_key' => 'video_url'],
                                ['title' => 'Channel', 'key' => 'channel_name', 'type' => 'text'],
                                ['title' => 'Added', 'key' => 'published_at', 'type' => 'date']
                            ]
                        ]);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/partials/admin/add_video_modal.php'; ?>
    <?php include 'templates/partials/admin/channel_modal.php'; ?>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
    <script>
        // Pass PHP variables to JavaScript
        window.adminConfig = {
            currentPages: {
                channels: <?php echo $channels_pagination['current_page']; ?>,
                videos: <?php echo $videos_pagination['current_page']; ?>,
                suggestions: <?php echo $suggestions_pagination['current_page']; ?>
            }
        };
    </script>
    <script src="static/admin.js"></script>
    
    <?php if ($message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?php echo json_encode($message); ?>, 'success');
        });
    </script>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?php echo json_encode($error); ?>, 'danger');
        });
    </script>
    <?php endif; ?>
</body>
</html>
