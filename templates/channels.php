<?php
require_once 'common.php';
require_once 'database.php';

// Get database connection
$db = get_database();

// Get sorting parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$valid_sorts = ['newest', 'alphabetical'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'newest';
}

// Determine ORDER BY clause based on sort parameter
$order_by = $sort === 'alphabetical' ? 'c.name ASC' : 'c.created_at DESC';

// Get all channels with their categories and video counts
$channels_stmt = $db->prepare("
    SELECT c.*, 
           GROUP_CONCAT(cat.name, ', ') as category_names,
           (SELECT COUNT(*) FROM videos v WHERE v.channel_id = c.id) as video_count
    FROM channels c 
    LEFT JOIN channel_categories cc ON c.id = cc.channel_id 
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY c.id, c.name, c.description, c.youtube_url, c.thumbnail_url, c.created_at
    ORDER BY {$order_by}
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

    <div class="container-fluid p-0">
        <br>

        <!-- Page Header -->
        <div class="page-header mb-4 px-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-3">
                <div class="mb-3 mb-md-0">
                    <h1 class="section-title mb-2">All Channels</h1>
                    <p class="text-light mb-0">Discover all the educational and intellectually stimulating channels in our collection.</p>
                </div>
                <div class="sort-controls">
                    <div class="btn-group" role="group" aria-label="Sort channels">
                        <a href="?sort=newest" class="btn <?php echo $sort === 'newest' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">
                            <i class="fas fa-clock"></i> <span class="d-none d-sm-inline">Newest</span>
                        </a>
                        <a href="?sort=alphabetical" class="btn <?php echo $sort === 'alphabetical' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">
                            <i class="fas fa-sort-alpha-down"></i> <span class="d-none d-sm-inline">Alphabetical</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Channels Section -->
        <section class="channels-section px-4">            
            <?php if (empty($channels)): ?>
                <div class="alert alert-info mx-4">
                    <h4 class="alert-heading">No channels found</h4>
                    <p class="mb-0">No channels have been added to our database yet.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($channels as $channel): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 col-12 mb-4">
                            <?php render_channel_card($channel); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'templates/partials/footer.php'; ?>
