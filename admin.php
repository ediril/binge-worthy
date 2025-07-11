<?php
require_once 'common.php';
require_once 'database.php';

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
                                if (add_channel($db, $channel_metadata['name'], $channel_metadata['description'], $channel_metadata['youtube_url'], $channel_metadata['thumbnail_url'])) {
                                    $message = "Channel '{$channel_metadata['name']}' added successfully!";
                                } else {
                                    $error = "Failed to add channel to database.";
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
                            // Get or create channel for this video
                            $channel_id = get_or_create_channel_from_video($db, $video_metadata);
                            
                            if ($channel_id) {
                                // Auto-categorize the video
                                $category_id = auto_categorize_video($db, $video_metadata['title'], $video_metadata['channel_name']);
                                
                                if (add_video($db, $channel_id, $video_metadata['title'], $video_metadata['description'], $video_url, $video_metadata['thumbnail_url'], $category_id)) {
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
$all_channels = get_all_channels($db);

// Get all videos to display in admin
$all_videos = $db->prepare("
    SELECT v.*, c.name as channel_name, cat.name as category 
    FROM videos v 
    JOIN channels c ON v.channel_id = c.id 
    JOIN categories cat ON v.category_id = cat.id
    WHERE v.is_active = 1 
    ORDER BY v.created_at DESC
");
$all_videos->execute();
$all_videos = $all_videos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Binge-Worthy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="static/styles.css">
</head>
<body>
    <?php $current_page = 'admin'; include 'templates/navbar.php'; ?>

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
                            Enter a YouTube channel URL, channel name, handle, or video URL/ID. We'll automatically fetch the information.
                        </p>
                        <form method="POST" id="add-content-form">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="content-type" class="form-label">Content Type</label>
                                    <select class="form-select" id="content-type" name="content_type" required>
                                        <option value="channel">Channel</option>
                                        <option value="video">Video</option>
                                    </select>
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label for="content-input" class="form-label">YouTube URL or ID</label>
                                    <input type="text" class="form-control" id="content-input" name="content_input" required 
                                           placeholder="@username, channelname, video URL, or video ID">
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

    <?php include 'templates/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
    <script>
        // Update UI based on content type selection
        document.getElementById('content-type').addEventListener('change', function() {
            const contentType = this.value;
            const description = document.getElementById('input-description');
            const input = document.getElementById('content-input');
            const submitText = document.getElementById('submit-text');
            
            if (contentType === 'channel') {
                description.textContent = 'Enter a YouTube channel URL, channel name, or handle. We\'ll automatically fetch the channel information.';
                input.placeholder = '@username, channelname, or full URL';
                submitText.textContent = 'Add Channel';
            } else if (contentType === 'video') {
                description.textContent = 'Enter a YouTube video URL or video ID. We\'ll automatically fetch the video information and create the channel if needed.';
                input.placeholder = 'dQw4w9WgXcQ or full URL';
                submitText.textContent = 'Add Video';
            }
        });
    </script>
</body>
</html> 