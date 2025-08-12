# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview
BingeWorthy is a Netflix-like platform for educational and intellectually stimulating videos, focusing on tech, business, coding, science, and AI content. The platform provides a curated alternative to brain-rotting entertainment.

## Recent Updates & Important Context
- **Mobile URL Support**: Added support for m.youtube.com URLs throughout the system
- **Lock File System**: Implemented lock file mechanism to prevent concurrent fetch operations
- **Channel Update Interval**: Configurable via CHANNEL_UPDATE_INTERVAL_HOURS (currently 23 hours)
- **Suggestion Auto-Removal**: Channels/videos are automatically removed from suggestions when added

## Technology Stack
- **Backend**: PHP 8.3
- **Database**: SQLite (binge_worthy.db)
- **CSS Framework**: Bootstrap 5.3
- **Frontend**: Vanilla JavaScript (ES6+)
- **Analytics**: Custom analytics system (banalytiq)

## Development Commands

### Local Development Server
```bash
# PHP built-in server (recommended for development)
php -S localhost:8080

# Alternative ports if 8080 is busy
php -S localhost:3000
php -S 127.0.0.1:8080
```

### Database Management
```bash
# Create/recreate database with tables and default data
php database.php

# Note: Running this will warn if database already exists
```

### Production Deployment
- Uses Apache with .htaccess for URL rewriting
- Configured for bingeworthyshows.tv domain with HTTPS redirect
- Static files served from /static/ directory

## Architecture Overview

### Core Files
- `index.php` - Universal router handling all page requests with 404 fallback
- `database.php` - Database connection, table creation, and schema management
- `common.php` - Business logic functions for videos, channels, categories, and YouTube API integration
- `static/script.js` - Single JavaScript file with all frontend functionality
- `static/styles.css` - Single CSS file with all styling (BEM naming convention)

### Database Schema
- **categories** - Video categories (Tech, Business, Science, AI, DIY, Not Categorized)
- **channels** - YouTube channels with metadata and category assignment
- **videos** - Individual videos linked to channels with thumbnails and metadata

### Page Structure
- `templates/home.php` - Homepage with new videos, new channels, and suggested content
- `templates/admin.php` - Admin panel for adding channels/videos (localhost-only access)
- `templates/categories.php` - Browse by category
- `templates/channel.php` - Individual channel pages
- `templates/about.php` - About page
- `templates/partials/` - Shared components (head, navbar, footer)

### Key Features
- **Universal Routing**: Clean URLs through index.php with fallback to templates
- **YouTube Integration**: Automatic metadata fetching from YouTube oEmbed API
- **Thumbnail Handling**: Multiple fallback options for YouTube thumbnails
- **Auto-categorization**: Smart category assignment based on channel and video content
- **Admin Interface**: Localhost-only admin panel for content management
- **Analytics**: Integrated banalytiq system for visit tracking

### Security Measures
- Admin panel restricted to localhost access only
- SQL injection prevention with prepared statements
- Input sanitization and validation
- HTTPS enforced in production
- XSS protection through htmlspecialchars()

### Content Management Workflow
1. Add channels via admin panel with category assignment
2. Add individual videos (auto-creates channels if needed)
3. System fetches YouTube metadata automatically
4. Categories can be created on-the-fly or selected from existing ones

## Development Guidelines

### Code Organization
- Keep all JavaScript in `static/script.js`
- Keep all CSS in `static/styles.css` 
- Put shared functions in `common.php`
- Follow BEM naming for CSS classes
- Use descriptive variable names with underscores (e.g., `is_active`, `video_count`)

### Database Patterns
- All tables have `is_active` flags for soft deletion
- Created timestamps with `created_at` fields
- Foreign key relationships between tables
- Indexes on frequently queried columns

### Error Handling
- Handle errors early with guard clauses
- Use early returns for error conditions
- Avoid deeply nested if statements
- Place happy path last in functions

### YouTube API Integration
- Uses oEmbed API for video metadata
- Scrapes channel pages for channel information
- Multiple thumbnail resolution fallbacks
- Handles different YouTube URL formats (watch?v=, youtu.be/, @handles)

## Critical Code Synchronization Points

### YouTube URL Handling (MUST UPDATE ALL WHEN ADDING NEW FORMATS)
When adding support for new YouTube URL formats (e.g., m.youtube.com), ALL of these locations must be updated:

#### PHP Backend Functions (common.php):
- **extract_youtube_channel_id()** (line 698) - Extracts channel ID from URLs
- **normalize_channel_input()** (line 668) - Normalizes channel input to full URLs
- **cleanup_youtube_url()** (line 1077) - Cleans up YouTube URLs to base form
- **fetch_youtube_channel_metadata()** (line 795) - Fetches channel metadata
- **auto_remove_matching_suggestions()** (line 1128) - Removes matching suggestions

#### PHP Fetch Functions (fetch.php):
- **get_real_channel_id_from_page()** (line 446) - Gets channel ID from page HTML

#### JavaScript Validation (static/admin.js):
- **isValidYouTubeURL()** (line 146) - Validates channel/video URLs
  - Patterns array at line 168-175
- **isValidYouTubeVideoURL()** (line 1551) - Validates video-only URLs
  - Patterns array at line 1557-1562

#### JavaScript Main Site (static/script.js):
- **isValidYouTubeURL()** (line 383) - Main site URL validation

### Lock File System
- **Lock file name**: FETCHER_LOCK_FILE_NAME = 'bw_fetch.lock' (common.php:14)
- **acquireLock()** (common.php:20) - Acquires lock with PID tracking
- **releaseLock()** (common.php:43) - Releases lock and cleans up file
- **Usage in fetch.php**: Lines 120-127 (acquisition) and 329-333 (release)

### Fetch Configuration
- **CHANNEL_UPDATE_INTERVAL_HOURS** (common.php:13) - Currently 23 hours
- **Check in fetch.php**: Lines 183-191 - Skips channels fetched within interval

### Admin Panel Video Fetching Fix
- **templates/admin.php:440-441** - Fixed to extract 'videos' array from fetch_channel_videos_rss() result
- Returns structure: `['videos' => [...], 'shorts_skipped' => N, 'shorts_found' => [...]]`

## Important Notes
- SQLite database file should not be committed to repository
- Admin panel only accessible on localhost for security
- All external YouTube content opens in new tabs
- Responsive design supports modern browsers only
- Analytics data stored in separate banalytiq.db
- When adding new URL formats, search for ALL YouTube URL validation patterns in both PHP and JS