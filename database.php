<?php

/**
 * Get database connection
 */
function get_database() {
    try {
        $db = new PDO('sqlite:binge_worthy.db');
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
            description TEXT,
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create videos table
    $db->exec("
        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            video_url TEXT NOT NULL UNIQUE,
            thumbnail_url TEXT,
            is_featured INTEGER DEFAULT 0,
            view_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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
        INSERT OR IGNORE INTO categories (name, description) VALUES
        ('Tech', 'Technology, programming, and software development'),
        ('Business', 'Business, entrepreneurship, and marketing'),
        ('Science', 'Science, research, and educational content'),
        ('DIY', ''),
        ('AI', 'Artificial Intelligence'),
        ('Not Categorized', 'Channels that have not been categorized yet')
    ");
    
    // Migration of existing channel categories to junction table is handled separately
    // This ensures compatibility when creating fresh databases
    
    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_channel_id ON videos(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_created_at ON videos(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_videos_is_featured ON videos(is_featured)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_name ON categories(name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_channel_categories_channel_id ON channel_categories(channel_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_channel_categories_category_id ON channel_categories(category_id)");
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