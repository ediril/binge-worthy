<?php

require_once 'database.php';


/**
 * Load FTP configuration from YAML file
 */
function load_ftp_config() {
    $config_file = 'ftp_config.yaml';
    
    if (!file_exists($config_file)) {
        throw new Exception("FTP configuration file not found: {$config_file}");
    }
    
    $yaml_content = file_get_contents($config_file);
    $lines = explode("\n", $yaml_content);
    $config = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    // Validate required fields
    $required_fields = ['host', 'user', 'password', 'remote_dir'];
    foreach ($required_fields as $field) {
        if (empty($config[$field])) {
            throw new Exception("Missing required FTP configuration field: {$field}");
        }
    }
    
    return $config;
}

// Load FTP configuration
$ftp_config = load_ftp_config();

const LOCAL_APP_DB_PATH = 'binge_worthy.db';
const LOCAL_SUGGESTIONS_DB_PATH = 'suggestions.db';

/**
 * Log message with timestamp
 */
function log_message($message) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

/**
 * Download database from FTP server
 */
function download_remote_database($ftp_config, $db_type) {
    $timestamp = date('YmdHis');
    
    $local_filename = "{$db_type}_{$timestamp}.db";
    $remote_filename = "{$db_type}.db";
    
    $remote_dir = !empty($ftp_config['remote_dir']) ? $ftp_config['remote_dir'] : '';
    $remote_db_path = $remote_dir ? $remote_dir . '/' . $remote_filename : $remote_filename;
    
    log_message("Connecting to FTP server: " . $ftp_config['host']);
    
    $ftp_conn = ftp_connect($ftp_config['host']);
    if (!$ftp_conn) {
        throw new Exception("Failed to connect to FTP server");
    }
    
    if (!ftp_login($ftp_conn, $ftp_config['user'], $ftp_config['password'])) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to login to FTP server");
    }
    
    ftp_pasv($ftp_conn, true);
    
    log_message("Downloading remote {$db_type} database from: " . $remote_db_path . " to: " . $local_filename);
    
    if (!ftp_get($ftp_conn, $local_filename, $remote_db_path, FTP_BINARY)) {
        ftp_close($ftp_conn);
        throw new Exception("Failed to download remote {$db_type} database");
    }
    
    ftp_close($ftp_conn);
    log_message("Remote {$db_type} database downloaded successfully");
    
    return $local_filename;
}

/**
 * Delete database file from server
 */
function delete_from_server($ftp_config, $db_type = 'suggestions') {
    $remote_dir = !empty($ftp_config['remote_dir']) ? $ftp_config['remote_dir'] : '';
    $remote_db_path = $remote_dir ? $remote_dir . "/{$db_type}.db" : "{$db_type}.db";
    
    log_message("Connecting to FTP server to delete {$db_type}.db");
    
    $ftp_conn = ftp_connect($ftp_config['host']);
    if (!$ftp_conn) {
        log_message("Warning: Failed to connect to FTP server for deletion");
        return;
    }
    
    if (!ftp_login($ftp_conn, $ftp_config['user'], $ftp_config['password'])) {
        ftp_close($ftp_conn);
        log_message("Warning: Failed to login to FTP server for deletion");
        return;
    }
    
    ftp_pasv($ftp_conn, true);
    
    log_message("Deleting suggestions.db from server after successful processing");
    if (!ftp_delete($ftp_conn, $remote_db_path)) {
        log_message("Warning: Could not delete suggestions.db from server");
    } else {
        log_message("Successfully deleted suggestions.db from server");
    }
    
    ftp_close($ftp_conn);
}

/**
 * Get database connection based on type
 */
function get_database_by_type($db_type = 'binge_worthy') {
    $db_path = "{$db_type}.db";
    
    if ($db_type === 'suggestions' && !file_exists($db_path)) {
        // Create suggestions database if it doesn't exist
        $db = new PDO("sqlite:{$db_path}");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create suggestions table structure (you may need to adjust this)
        $db->exec("CREATE TABLE IF NOT EXISTS suggestions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        return $db;
    }
    
    if ($db_type === 'binge_worthy') {
        return get_database(); // Use existing function for binge_worthy
    }
    
    // For other db types, create PDO connection directly
    $db = new PDO("sqlite:{$db_path}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

/**
 * Merge remote database into local database
 */
function merge($remote_db_file, $db_type = 'binge_worthy') {
    log_message("Starting {$db_type} database merge process");
    
    // Open both databases
    $local_db = get_database_by_type($db_type);
    $remote_db = new PDO('sqlite:' . $remote_db_file);
    $remote_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $local_db->beginTransaction();
    
    try {
        log_message("Merging '{$db_type}'...");
        
        if ($db_type === 'suggestions') {                
            // Get remote table structure and create local table if needed
            $create_table_query = $remote_db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='suggestions'")->fetch();
            if ($create_table_query) {
                // Replace CREATE TABLE with CREATE TABLE IF NOT EXISTS to avoid errors
                $sql = str_replace('CREATE TABLE suggestions', 'CREATE TABLE IF NOT EXISTS suggestions', $create_table_query['sql']);
                $local_db->exec($sql);
            }
            
            // Get all records from remote database
            $remote_suggestions = $remote_db->query("SELECT * FROM suggestions")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($remote_suggestions)) {
                // Get column names from the first record
                $columns = array_keys($remote_suggestions[0]);
                $columns_list = implode(', ', $columns);
                $placeholders = str_repeat('?,', count($columns) - 1) . '?';
                
                // Use INSERT OR IGNORE to skip duplicates
                $stmt = $local_db->prepare("INSERT OR IGNORE INTO suggestions ({$columns_list}) VALUES ({$placeholders})");
                
                foreach ($remote_suggestions as $suggestion) {
                    $stmt->execute(array_values($suggestion));
                }
                
                log_message("Processed " . count($remote_suggestions) . " suggestions records");
            }
        } else {
            // Merge categories
            log_message("   Merging categories...");
            $remote_categories = $remote_db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $local_db->prepare("INSERT OR IGNORE INTO categories (name, created_at) VALUES (?, ?)");
            
            foreach ($remote_categories as $category) {
                $stmt->execute([$category['name'], $category['created_at']]);
            }
            
            // Merge channels
            log_message("   Merging channels...");
            $remote_channels = $remote_db->query("SELECT * FROM channels")->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $local_db->prepare("
                INSERT OR REPLACE INTO channels 
                (name, description, youtube_url, thumbnail_url, last_fetched, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($remote_channels as $channel) {
                $stmt->execute([
                    $channel['name'],
                    $channel['description'],
                    $channel['youtube_url'],
                    $channel['thumbnail_url'],
                    $channel['last_fetched'],
                    $channel['created_at']
                ]);
            }
            
            // Merge videos
            log_message("   Merging videos...");
            $remote_videos = $remote_db->query("
                SELECT v.*, c.youtube_url as channel_url 
                FROM videos v 
                JOIN channels c ON v.channel_id = c.id
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $find_channel_stmt = $local_db->prepare("SELECT id FROM channels WHERE youtube_url = ?");
            $insert_video_stmt = $local_db->prepare("
                INSERT OR REPLACE INTO videos 
                (channel_id, title, video_url, thumbnail_url, is_featured, published_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($remote_videos as $video) {
                $find_channel_stmt->execute([$video['channel_url']]);
                $local_channel = $find_channel_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($local_channel) {
                    $insert_video_stmt->execute([
                        $local_channel['id'],
                        $video['title'],
                        $video['video_url'],
                        $video['thumbnail_url'],
                        $video['is_featured'],
                        $video['published_at']
                    ]);
                }
            }
            
            // Merge channel_categories
            log_message("Merging channel categories...");
            $remote_channel_categories = $remote_db->query("
                SELECT cc.*, ch.youtube_url as channel_url, cat.name as category_name
                FROM channel_categories cc
                JOIN channels ch ON cc.channel_id = ch.id
                JOIN categories cat ON cc.category_id = cat.id
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $find_local_channel_stmt = $local_db->prepare("SELECT id FROM channels WHERE youtube_url = ?");
            $find_local_category_stmt = $local_db->prepare("SELECT id FROM categories WHERE name = ?");
            $insert_cc_stmt = $local_db->prepare("
                INSERT OR IGNORE INTO channel_categories (channel_id, category_id, created_at) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($remote_channel_categories as $cc) {
                $find_local_channel_stmt->execute([$cc['channel_url']]);
                $local_channel = $find_local_channel_stmt->fetch(PDO::FETCH_ASSOC);
                
                $find_local_category_stmt->execute([$cc['category_name']]);
                $local_category = $find_local_category_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($local_channel && $local_category) {
                    $insert_cc_stmt->execute([
                        $local_channel['id'],
                        $local_category['id'],
                        $cc['created_at']
                    ]);
                }
            }
        }
        
        $local_db->commit();
        log_message("{$db_type} database merge completed successfully");
        
    } catch (Exception $e) {
        $local_db->rollBack();
        throw new Exception("{$db_type} database merge failed: " . $e->getMessage());
    }
}

/**
 * Find all timestamped database files that haven't been processed
 */
function find_unprocessed_db_files($db_type) {
    $pattern = "{$db_type}_*.db";
    
    $files = glob($pattern);
    $unprocessed = [];
    
    foreach ($files as $file) {
        // Skip if already has .bkp extension
        if (pathinfo($file, PATHINFO_EXTENSION) === 'db' && !file_exists($file . '.bkp')) {
            $unprocessed[] = $file;
        }
    }
    
    // Sort by filename (which includes timestamp) to process in order
    sort($unprocessed);
    
    return $unprocessed;
}

/**
 * Process a single database file
 */
function process_db_file($db_file, $db_type = 'binge_worthy') {
    log_message("Processing {$db_type} database file: " . $db_file);
    
    // Merge databases
    merge($db_file, $db_type);

    // Rename file to .bkp to prevent re-processing
    $backup_filename = $db_file . '.bkp';
    if (rename($db_file, $backup_filename)) {
        log_message("Database file renamed to: " . $backup_filename);
    } else {
        log_message("Warning: Could not rename database file: " . $db_file);
    }
}

/**
 * Process existing unprocessed database files for a specific type
 */
function process_db_type($db_type) {
    $unprocessed_files = find_unprocessed_db_files($db_type);
    if (!empty($unprocessed_files)) {
        log_message("Found " . count($unprocessed_files) . " unprocessed {$db_type} database file(s)");
        foreach ($unprocessed_files as $db_file) {
            process_db_file($db_file, $db_type);
        }
        return true;
    }
    return false;
}


/**
 * Download databases only
 */
function run_download() {
    global $ftp_config;
    
    log_message("Starting downloads...");
    
    foreach (['binge_worthy', 'suggestions'] as $db_type) {
        log_message("Downloading {$db_type} database...");
        
        try {
            $remote_db_file = download_remote_database($ftp_config, $db_type);
            log_message("{$db_type} database download completed successfully: " . $remote_db_file);
        
            // Delete suggestions.db from server after successful download
            if ($db_type === 'suggestions') {
                delete_from_server($ftp_config, $db_type);
            }

        } catch (Exception $e) {
            if ($db_type === 'suggestions') {
                log_message("No suggestions database found on server (this is normal)");
            } else {
                throw $e; // Re-throw for binge_worthy database failures
            }
        }
    }
    
    log_message("All downloads completed successfully");
    log_message("To merge downloaded databases, run: php db_merge.php --merge-only");
}

/**
 * Merge existing database files only
 */
function run_merge() {
    global $ftp_config;
    
    log_message("Starting merge of db files...");
    
    foreach (['binge_worthy', 'suggestions'] as $db_type) {
        log_message("Processing {$db_type}.db...");

        $found_files = process_db_type($db_type);
        if (!$found_files) {
            log_message("No unprocessed '{$db_type}' files found");
        }
    }
    
    log_message("All merge processes completed successfully");
    log_message("To upload the merged database to the server, run: php db_upload.php");
}


// Check if this file is being run directly from command line
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    echo "BingeWorthy Database Merge Tool\n";
    echo "================================\n\n";
    
    // Check for options
    $download_only = in_array('--download-only', $argv ?? []);
    $merge_only = in_array('--merge-only', $argv ?? []);
    
    // Validate options
    if ($download_only && $merge_only) {
        echo "ERROR: Cannot use both --download-only and --merge-only options together\n";
        exit(1);
    }
    
    try {
        if ($download_only) {
            echo "[DOWNLOAD-ONLY MODE]\n\n";
            run_download();
        } elseif ($merge_only) {
            echo "[MERGE-ONLY MODE]\n\n";
            run_merge();
        } else {
            echo "[FULL MODE]\n\n";
            run_download();
            run_merge();
        }
    } catch (Exception $e) {
        log_message("ERROR: " . $e->getMessage());
        exit(1);
    }
}

?>