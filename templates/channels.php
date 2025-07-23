<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();

// Get all channels with their categories and video counts
$channels_stmt = $db->prepare("
    SELECT c.*, 
           GROUP_CONCAT(cat.name, ', ') as category_names,
           (SELECT COUNT(*) FROM videos v WHERE v.channel_id = c.id AND v.is_active = 1) as video_count
    FROM channels c 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id AND cat.is_active = 1
    WHERE c.is_active = 1 
    GROUP BY c.id, c.name, c.description, c.youtube_url, c.thumbnail_url, c.created_at, c.is_active
    ORDER BY c.created_at DESC
");
$channels_stmt->execute();
$channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'All Channels - Binge-Worthy';
$current_page = 'channels';
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'templates/partials/head.php'; ?>
<body>
    <?php include 'templates/partials/navbar.php'; ?>

    <div class="container">
        <br>

        <!-- Page Header -->
        <div class="page-header mb-4">
            <h1 class="section-title">All Channels</h1>
            <p class="text-light">Discover all the educational and intellectually stimulating channels in our collection.</p>
        </div>

        <!-- Channels Section -->
        <section class="channels-section">            
            <?php if (empty($channels)): ?>
                <div class="alert alert-info">
                    <h4>No channels found</h4>
                    <p>No channels have been added to our database yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($channels as $channel): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-4">
                            <div class="channel-card h-100">
                                <div class="channel-thumbnail">
                                    <?php if (!empty($channel['thumbnail_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($channel['thumbnail_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($channel['name']); ?>"
                                             onclick="window.location.href='channel.php?id=<?php echo $channel['id']; ?>'">
                                    <?php else: ?>
                                        <div class="channel-thumbnail-placeholder" 
                                             onclick="window.location.href='channel.php?id=<?php echo $channel['id']; ?>'">
                                            <span class="placeholder-icon">📺</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="channel-info">
                                    <h3 class="channel-title">
                                        <a href="channel.php?id=<?php echo $channel['id']; ?>" class="text-decoration-none text-light">
                                            <?php echo htmlspecialchars($channel['name']); ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($channel['description'])): ?>
                                        <p class="channel-description">
                                            <?php 
                                                $description = htmlspecialchars($channel['description']);
                                                echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Channel Categories -->
                                    <?php if ($channel['category_names']): ?>
                                        <div class="channel-categories mb-2">
                                            <?php
                                            $categories = explode(', ', $channel['category_names']);
                                            $unique_categories = array_unique(array_map('trim', $categories));
                                            foreach ($unique_categories as $cat):
                                                if (!empty($cat)):
                                            ?>
                                                <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($cat); ?></span>
                                            <?php
                                                endif;
                                            endforeach;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Channel Stats -->
                                    <div class="channel-stats mb-2">
                                        <span class="badge bg-primary"><?php echo $channel['video_count']; ?> videos</span>
                                    </div>
                                    
                                    <!-- External Link -->
                                    <div class="channel-actions">
                                        <a href="<?php echo htmlspecialchars($channel['youtube_url']); ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-light btn-sm me-2">
                                            <i class="fas fa-external-link-alt"></i> YouTube
                                        </a>
                                        <a href="channel.php?id=<?php echo $channel['id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            View Videos
                                        </a>
                                    </div>
                                    
                                    <small class="channel-date text-muted">
                                        Added <?php echo date('M j, Y', strtotime($channel['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'templates/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="static/script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>