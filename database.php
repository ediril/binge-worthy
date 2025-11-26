<?php

/**
 * Get database connection
 */
function get_database() {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/binge_worthy.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
        return $db;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Create database tables
 */
function create_tables($db) {
    // Create categories table
    $db->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create channels table
    $db->exec("
        CREATE TABLE IF NOT EXISTS channels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT,
            youtube_url TEXT NOT NULL UNIQUE,
            thumbnail_url TEXT,
            last_fetched DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create videos table
    $db->exec("
        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            video_url TEXT NOT NULL UNIQUE,
            thumbnail_url TEXT,
            is_featured INTEGER DEFAULT 0,
            published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (channel_id) REFERENCES channels (id)
        )
    ");
    
    // Create channel_categories junction table for many-to-many relationship
    $db->exec("
        CREATE TABLE IF NOT EXISTS channel_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
            UNIQUE(channel_id, category_id)
        )
    ");
    
    // Insert default categories
    $db->exec("
        INSERT OR IGNORE INTO categories (name) VALUES
        ('Tech'),
        ('Business'),
        ('Science'),
        ('DIY'),
        ('AI')
    ");
    
    // Migration of existing channel categories to junction table is handled separately
    // This ensures compatibility when creating fresh databases
    
    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_channel_id ON videos(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_published_at ON videos(published_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_is_featured ON videos(is_featured)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_name ON categories(name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_channel_categories_channel_id ON channel_categories(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_channel_categories_category_id ON channel_categories(category_id)");

    // Create search_logs table for analytics
    $db->exec("
        CREATE TABLE IF NOT EXISTS search_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            keywords TEXT NOT NULL,
            sel_type INTEGER NOT NULL,
            sel_slug TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_search_logs_created_at ON search_logs(created_at)");

    // Create FTS5 tables and triggers for search
    create_fts_tables($db);
}

/**
 * Create FTS5 virtual tables and triggers for search functionality
 */
function create_fts_tables($db) {
    // Create FTS5 virtual table for videos
    $db->exec("
        CREATE VIRTUAL TABLE IF NOT EXISTS videos_fts USING FTS5(
            slug,
            tokenize = 'porter unicode61'
        )
    ");

    // Create FTS5 virtual table for channels
    $db->exec("
        CREATE VIRTUAL TABLE IF NOT EXISTS channels_fts USING FTS5(
            slug,
            tokenize = 'porter unicode61'
        )
    ");

    // NOTE: FTS triggers are NOT created because SQLite FTS5 operations inside
    // triggers cause "unsafe use of virtual table" errors in many configurations.
    // FTS index updates are handled manually in add_video(), add_channel_with_categories(),
    // and delete operations.
}

/**
 * Populate FTS tables with existing data (run once for migration)
 */
function populate_fts_tables($db) {
    // Clear existing FTS data
    $db->exec("DELETE FROM videos_fts");
    $db->exec("DELETE FROM channels_fts");

    // Populate videos_fts from existing videos with slugs
    $db->exec("
        INSERT INTO videos_fts(rowid, slug)
        SELECT id, slug FROM videos WHERE slug IS NOT NULL
    ");

    // Populate channels_fts from existing channels with slugs
    $db->exec("
        INSERT INTO channels_fts(rowid, slug)
        SELECT id, slug FROM channels WHERE slug IS NOT NULL
    ");
}

// Check if this file is being run directly from command line
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $db_file = 'binge_worthy.db';
    
    if (file_exists($db_file)) {
        echo "Warning: Database file '$db_file' already exists. No action taken.\n";
        echo "If you want to recreate the database, delete the existing file first.\n";
        exit(1);
    } else {
        echo "Creating database '$db_file'...\n";
        try {
            $db = get_database();
            create_tables($db);
            echo "Database created successfully with all tables and default data.\n";
        } catch (Exception $e) {
            echo "Error creating database: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

?> 