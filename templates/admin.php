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

// Handle form submissions
$message = '';
$error = '';

if ($_POST) {
    if (isset($_POST['content_type']) && isset($_POST['content_input'])) {
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
                    $existing_channel = $db->prepare("SELECT id FROM channels WHERE youtube_url = ? AND is_active = 1");
                    $existing_channel->execute([$channel_url]);
                    
                    if ($existing_channel->fetch()) {
                        $error = "This channel URL already exists in the database.";
                    } else {
                        // Fetch channel metadata
                        $channel_metadata = fetch_youtube_channel_metadata($channel_url);
                        
                        if ($channel_metadata) {
                            // Check if channel name already exists
                            $existing_name = $db->prepare("SELECT id FROM channels WHERE name = ? AND is_active = 1");
                            $existing_name->execute([$channel_metadata['name']]);
                            
                            if ($existing_name->fetch()) {
                                $error = "A channel with the name '{$channel_metadata['name']}' already exists in the database.";
                            } else {
                                // Handle category selection for channel
                                $category_id = null;
                                
                                if (isset($_POST['category_choice']) && $_POST['category_choice'] === 'existing' && !empty($_POST['existing_category'])) {
                                    // Use existing category
                                    $category_id = intval($_POST['existing_category']);
                                } else if (isset($_POST['category_choice']) && $_POST['category_choice'] === 'new' && !empty($_POST['new_category'])) {
                                    // Create new category
                                    $new_category_name = trim($_POST['new_category']);
                                    $new_category_description = trim($_POST['new_category_description'] ?? '');
                                    
                                    // Check if category already exists
                                    $existing_category = $db->prepare("SELECT id FROM categories WHERE name = ? AND is_active = 1");
                                    $existing_category->execute([$new_category_name]);
                                    
                                    if ($existing_category->fetch()) {
                                        $error = "Category '{$new_category_name}' already exists.";
                                    } else {
                                        if (add_category($db, $new_category_name, $new_category_description)) {
                                            $category_id = $db->lastInsertId();
                                        } else {
                                            $error = "Failed to create new category.";
                                        }
                                    }
                                } else {
                                    // Category selection is required
                                    $error = "Please select a category or create a new one.";
                                }
                                
                                if ($category_id && !$error) {
                                    if (add_channel($db, $channel_metadata['name'], $channel_metadata['description'], $channel_metadata['youtube_url'], $category_id, $channel_metadata['thumbnail_url'])) {
                                        $message = "Channel '{$channel_metadata['name']}' added successfully!";
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
                    $existing_video = $db->prepare("SELECT id FROM videos WHERE video_url = ? AND is_active = 1");
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

// Get all channels for display
$all_channels = $db->prepare("
    SELECT c.*, cat.name as category_name 
    FROM channels c 
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.is_active = 1 
    ORDER BY c.name
");
$all_channels->execute();
$all_channels = $all_channels->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for dropdown (excluding "Not Categorized")
$all_categories = get_display_categories($db);

// Get all videos to display in admin
$all_videos = $db->prepare("
    SELECT v.*, c.name as channel_name, cat.name as category 
    FROM videos v 
    JOIN channels c ON v.channel_id = c.id 
    JOIN categories cat ON c.category_id = cat.id
    WHERE v.is_active = 1 
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
                            
                            <!-- Category Selection (only for channels) -->
                            <div id="category-section" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category_choice" id="existing-category-choice" value="existing" checked>
                                        <label class="form-check-label" for="existing-category-choice">
                                            Use existing category
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category_choice" id="new-category-choice" value="new">
                                        <label class="form-check-label" for="new-category-choice">
                                            Create new category
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Existing Category Selection -->
                                <div id="existing-category-section" class="mb-3">
                                    <label for="existing-category" class="form-label">Select Category</label>
                                    <select class="form-select" id="existing-category" name="existing_category">
                                        <option value="">-- Select a category --</option>
                                        <?php foreach ($all_categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- New Category Creation -->
                                <div id="new-category-section" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="new-category" class="form-label">Category Name</label>
                                            <input type="text" class="form-control" id="new-category" name="new_category" 
                                                   placeholder="e.g., Education, Entertainment">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="new-category-description" class="form-label">Description (optional)</label>
                                            <input type="text" class="form-control" id="new-category-description" name="new_category_description" 
                                                   placeholder="Brief description of the category">
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
                                            <th class="text-light">YouTube URL</th>
                                            <th class="text-light">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_channels as $channel): ?>
                                            <tr>
                                                <td class="text-light"><?php echo htmlspecialchars($channel['name']); ?></td>
                                                <td class="text-light"><?php echo htmlspecialchars($channel['description']); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($channel['category_name']); ?></span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($channel['youtube_url']); ?>" target="_blank" class="btn btn-sm btn-outline-light">
                                                        <i class="fas fa-external-link-alt"></i> View Channel
                                                    </a>
                                                </td>
                                                <td class="text-light"><?php echo date('Y-m-d', strtotime($channel['created_at'])); ?></td>
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
                                            <th class="text-light">Category</th>
                                            <th class="text-light">Video URL</th>
                                            <th class="text-light">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_videos as $video): ?>
                                            <tr>
                                                <td class="text-light"><?php echo $video['id']; ?></td>
                                                <td class="text-light"><?php echo htmlspecialchars($video['title']); ?></td>
                                                <td class="text-light"><?php echo htmlspecialchars($video['channel_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($video['category']); ?></span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($video['video_url']); ?>" target="_blank" class="btn btn-sm btn-outline-light">
                                                        <i class="fas fa-play"></i> Watch
                                                    </a>
                                                </td>
                                                <td class="text-light"><?php echo date('Y-m-d H:i', strtotime($video['created_at'])); ?></td>
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
        });
        
        // Handle category choice radio buttons
        document.querySelectorAll('input[name="category_choice"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const existingSection = document.getElementById('existing-category-section');
                const newSection = document.getElementById('new-category-section');
                
                if (this.value === 'existing') {
                    existingSection.style.display = 'block';
                    newSection.style.display = 'none';
                } else if (this.value === 'new') {
                    existingSection.style.display = 'none';
                    newSection.style.display = 'block';
                }
            });
        });
        
        // Form validation
        document.getElementById('add-content-form').addEventListener('submit', function(e) {
            const contentType = document.getElementById('content-type').value;
            
            if (contentType === 'channel') {
                const categoryChoice = document.querySelector('input[name="category_choice"]:checked').value;
                
                if (categoryChoice === 'existing') {
                    const existingCategory = document.getElementById('existing-category').value;
                    if (!existingCategory) {
                        e.preventDefault();
                        alert('Please select a category or choose to create a new one.');
                        return;
                    }
                } else if (categoryChoice === 'new') {
                    const newCategory = document.getElementById('new-category').value.trim();
                    if (!newCategory) {
                        e.preventDefault();
                        alert('Please enter a name for the new category.');
                        return;
                    }
                }
            }
        });
    </script>
</body>
</html> 