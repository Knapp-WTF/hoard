<?php
/**
 * 🗺️ QUARTERMASTER - Configuration
 *
 * Adjust paths and tools to match yer vessel.
 */

// ============================================================
// 📁 PATHS - Change these to match your deployment
// ============================================================

// Path to yt-dlp binary
define('QM_YTDLP_BIN', '/opt/homebrew/bin/yt-dlp');

// Path to ffprobe binary (fallback tool)
define('QM_FFPROBE_BIN', '/opt/homebrew/bin/ffprobe');

// Maximum concurrent extractions (background processes)
define('QM_MAX_CONCURRENT', 3);

// Timeout for extraction in seconds
define('QM_EXTRACT_TIMEOUT', 120);

// How many entries per page on the completed list
define('QM_PER_PAGE', 50);

// ============================================================
// 🔧 DATABASE - SQLite (auto-created in Hoard data dir)
// ============================================================

// DB file name (stored in HOARD_DATA_DIR)
define('QM_DB_FILE', 'quartermaster.sqlite');
