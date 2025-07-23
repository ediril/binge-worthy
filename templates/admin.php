<?php
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
                                    $channel_id = add_channel_with_categories($db, $channel_metadata['name'], $channel_metadata['description'], $channel_metadata['youtube_url'], $category_ids, $channel_metadata['thumbnail_url']);
                                    if ($channel_id) {
                                        $message = "Channel '{$channel_metadata['name']}' added successfully with " . count($category_ids) . " categories!";
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
                                if (add_video($db, $channel_id, $video_metadata['title'], $video_metadata['description'], $video_url, $video_metadata['thumbnail_url'], 1)) {
                                    $message = "Video '{$video_metadata['title']}' added successfully!";
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

// Get all channels for display with their categories
$all_channels = $db->prepare("
    SELECT c.*, GROUP_CONCAT(cat.name, ', ') as category_names
    FROM channels c 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$all_channels->execute();
$all_channels = $all_channels->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for dropdown (excluding "Not Categorized")
$all_categories = get_display_categories($db);

// Get all videos to display in admin
$all_videos = $db->prepare("
    SELECT v.*, c.name as channel_name, GROUP_CONCAT(cat.name, ', ') as category 
    FROM videos v 
    JOIN channels c ON v.channel_id = c.id 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY v.id
    ORDER BY v.created_at DESC
");
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

    <div class="container mt-4">
        <h1>Admin Panel</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3>Add New Content</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-light mb-3" id="input-description">
                            Enter a YouTube channel URL, channel name, or handle. We'll automatically fetch the channel information.
                        </p>
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
                                    <input type="text" class="form-control" id="content-input" name="content_input" required 
                                           placeholder="@username, channelname, or full URL">
                                </div>
                            </div>
                            
                            <!-- Multiple Category Selection (only for channels) -->
                            <div id="category-section" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Categories (select multiple)</label>
                                    
                                    <!-- Existing Categories as Checkboxes -->
                                    <div class="mb-3">
                                        <div class="row">
                                            <?php foreach ($all_categories as $category): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="existing_categories[]" 
                                                               id="category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                                        <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- New Category Creation -->
                                    <div class="border-top pt-3">
                                        <h6>Or create new categories:</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="new-category" class="form-label">Category Names</label>
                                                <input type="text" class="form-control" id="new-category" name="new_category" 
                                                       placeholder="e.g., Education, Entertainment, Science (comma-separated)">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="new-category-description" class="form-label">Description (optional)</label>
                                                <input type="text" class="form-control" id="new-category-description" name="new_category_description" 
                                                       placeholder="Brief description of the category">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> <span id="submit-text">Add Channel</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Existing Channels</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_channels)): ?>
                            <p class="text-light">No channels added yet. Add your first channel above!</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
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
                                                <td class="text-light"><?php echo htmlspecialchars($channel['name']); ?></td>
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
                                                            <a href="<?php echo htmlspecialchars($channel['youtube_url']); ?>" target="_blank" class="btn btn-sm btn-outline-light w-100 d-flex justify-content-center align-items-center"
                                                               title="View channel on YouTube" data-bs-toggle="tooltip" data-bs-placement="top">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        </div>
                                                        <div class="col-6">
                                                            <button type="button" class="btn btn-sm btn-outline-warning w-100 d-flex justify-content-center align-items-center" 
                                                                    onclick="openEditModal(<?php echo $channel['id']; ?>, '<?php echo htmlspecialchars($channel['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($channel['description'], ENT_QUOTES); ?>')"
                                                                    title="Edit channel" data-bs-toggle="tooltip" data-bs-placement="top">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </div>
                                                        <div class="col-6">
                                                            <button type="button" class="btn btn-sm btn-outline-danger w-100 d-flex justify-content-center align-items-center" 
                                                                    onclick="deleteChannel(<?php echo $channel['id']; ?>, '<?php echo htmlspecialchars($channel['name'], ENT_QUOTES); ?>')"
                                                                    title="Delete channel and all its videos" data-bs-toggle="tooltip" data-bs-placement="top">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                        <div class="col-6">
                                                            <!-- Placeholder for future action -->
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>All Videos</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_videos)): ?>
                            <p class="text-light">No videos added yet. Add your first video above!</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th class="text-light">ID</th>
                                            <th class="text-light">Title</th>
                                            <th class="text-light">Channel</th>
                                            <th class="text-light">Video URL</th>
                                            <th class="text-light">Added</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_videos as $video): ?>
                                            <tr>
                                                <td class="text-light"><?php echo $video['id']; ?></td>
                                                <td class="text-light"><?php echo htmlspecialchars($video['title']); ?></td>
                                                <td class="text-light"><?php echo htmlspecialchars($video['channel_name']); ?></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="btn btn-sm btn-outline-light">
                                                        <i class="fas fa-play"></i> Watch
                                                    </a>
                                                </td>
                                                <td class="text-light"><?php echo $video['created_at'] ? date('Y-m-d H:i', strtotime($video['created_at'])) : 'Unknown'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                                <?php foreach ($all_categories as $category): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="edit_existing_categories[]" 
                                                   id="edit-category-<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>">
                                            <label class="form-check-label" for="edit-category-<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
                                    <input type="text" class="form-control" id="edit-new-category-description" name="edit_new_category_description" 
                                           placeholder="Brief description of the category">
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
                
                // Update button text and hidden input
                document.getElementById('content-type-text').textContent = text;
                document.getElementById('content-type').value = value;
                
                // Update UI based on selection
                updateUIForContentType(value);
            });
        });
        
        // Function to update UI based on content type
        function updateUIForContentType(contentType) {
            const description = document.getElementById('input-description');
            const input = document.getElementById('content-input');
            const submitText = document.getElementById('submit-text');
            const categorySection = document.getElementById('category-section');
            
            if (contentType === 'channel') {
                description.textContent = 'Enter a YouTube channel URL, channel name, or handle. We\'ll automatically fetch the channel information.';
                input.placeholder = '@username, channelname, or full URL';
                submitText.textContent = 'Add Channel';
                categorySection.style.display = 'block';
            } else if (contentType === 'video') {
                description.textContent = 'Enter a YouTube video URL or video ID. We\'ll automatically fetch the video information and create the channel if needed.';
                input.placeholder = 'dQw4w9WgXcQ or full URL';
                submitText.textContent = 'Add Video';
                categorySection.style.display = 'none';
            }
        }
        
        // Initialize UI on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateUIForContentType('channel'); // Default to channel
            
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
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
    </script>
</body>
</html> 