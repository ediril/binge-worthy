<!-- Unified Channel Modal (Add/Edit) -->
<div class="modal fade" id="channelModal" tabindex="-1" aria-labelledby="channelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="channelModalLabel">Add New Channel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_channel" id="channel-action">
                    <input type="hidden" name="content_type" value="channel">
                    <input type="hidden" name="channel_id" id="channel-id">
                    <input type="hidden" name="pending_video_url" id="pending-video-url">
                    
                    <!-- Channel Name Display (Edit Mode) -->
                    <div id="channel-name-display" class="mb-3" style="display: none;">
                        <h6 id="channel-name" class="mb-0"></h6>
                    </div>
                    
                    <!-- Channel URL Input (Add Mode) -->
                    <div id="channel-url-section" class="mb-3">
                        <label for="channel-url" class="form-label">YouTube Channel URL or Handle</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="channel-url" name="content_input" 
                                   placeholder="@username, channelname, or full URL">
                        </div>
                        <div class="form-text">Enter @username, channel name, or full YouTube channel URL</div>
                    </div>
                    
                    <!-- Channel Info Display -->
                    <div id="channel-info" class="mb-3 p-3 text-info bg-info bg-opacity-10 border border-info rounded" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="channel-info-text">Channel information will appear here after entering URL.</span>
                    </div>
                    
                    <!-- Channel Description -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="channel-description" class="form-label mb-0">Channel Description</label>
                            <button type="button" class="btn btn-sm btn-outline-info" id="refetch-description-btn">
                                <i class="fas fa-sync-alt me-1"></i>Refetch
                            </button>
                        </div>
                        <textarea class="form-control" id="channel-description" name="channel_description" rows="3"
                                  placeholder="Brief description of the channel"></textarea>
                    </div>
                    
                    <!-- Current Categories Display (Edit Mode) -->
                    <div id="current-categories-section" class="mb-3" style="display: none;">
                        <label class="form-label">Current Categories:</label>
                        <div id="current-categories-display"></div>
                    </div>
                    
                    <!-- Category Selection Section -->
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="channel-submit-btn" disabled>
                        <i class="fas fa-tv me-1"></i><span id="channel-submit-text">Add Channel</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>