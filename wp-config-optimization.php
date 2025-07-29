<?php
/**
 * TPAK DQ System - WordPress Configuration Optimization
 * 
 * เพิ่มการตั้งค่าเหล่านี้ใน wp-config.php เพื่อ optimize performance
 */

// Memory limit optimization for TPAK DQ System
if (!defined('WP_MEMORY_LIMIT')) {
    define('WP_MEMORY_LIMIT', '512M');
}

if (!defined('WP_MAX_MEMORY_LIMIT')) {
    define('WP_MAX_MEMORY_LIMIT', '512M');
}

// Increase execution time for large operations
if (!defined('WP_TIMEOUT_LIMIT')) {
    define('WP_TIMEOUT_LIMIT', 300);
}

// Database optimization
if (!defined('WP_CACHE')) {
    define('WP_CACHE', true);
}

// Disable automatic updates for better performance
if (!defined('AUTOMATIC_UPDATER_DISABLED')) {
    define('AUTOMATIC_UPDATER_DISABLED', true);
}

// Disable file editing in admin
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

// Disable file modifications
if (!defined('DISALLOW_FILE_MODS')) {
    define('DISALLOW_FILE_MODS', true);
}

// Optimize database queries
if (!defined('SAVEQUERIES')) {
    define('SAVEQUERIES', false);
}

// Disable debug mode in production
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Force SSL for admin
if (!defined('FORCE_SSL_ADMIN')) {
    define('FORCE_SSL_ADMIN', true);
}

// Optimize cron jobs
if (!defined('ALTERNATE_WP_CRON')) {
    define('ALTERNATE_WP_CRON', false);
}

// Disable post revisions for better performance
if (!defined('WP_POST_REVISIONS')) {
    define('WP_POST_REVISIONS', false);
}

// Optimize autosave
if (!defined('AUTOSAVE_INTERVAL')) {
    define('AUTOSAVE_INTERVAL', 300); // 5 minutes
}

// Disable trash
if (!defined('EMPTY_TRASH_DAYS')) {
    define('EMPTY_TRASH_DAYS', 0);
}

// Optimize database connections
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('DB_COLLATE')) {
    define('DB_COLLATE', 'utf8mb4_unicode_ci');
}

// Custom memory management for TPAK DQ System
function tpak_memory_optimization() {
    // Set memory limit for TPAK operations
    if (function_exists('ini_set')) {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300);
        ini_set('max_input_time', 300);
    }
    
    // Enable garbage collection
    if (function_exists('gc_enable')) {
        gc_enable();
    }
}

// Hook memory optimization
add_action('init', 'tpak_memory_optimization', 1);

// Custom error handling for memory issues
function tpak_error_handler($errno, $errstr, $errfile, $errline) {
    if (strpos($errstr, 'memory') !== false || strpos($errstr, 'Memory') !== false) {
        error_log("TPAK DQ System Memory Error: $errstr in $errfile on line $errline");
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        return true; // Don't execute PHP internal error handler
    }
    
    return false; // Execute PHP internal error handler
}

// Set custom error handler
set_error_handler('tpak_error_handler');

// Memory usage monitoring
function tpak_memory_monitor() {
    $memory_usage = memory_get_usage(true) / 1024 / 1024; // MB
    $memory_limit = ini_get('memory_limit');
    
    if ($memory_limit !== '-1') {
        $limit_mb = tpak_parse_memory_limit($memory_limit);
        $usage_percent = ($memory_usage / $limit_mb) * 100;
        
        if ($usage_percent > 80) {
            error_log("TPAK DQ System: High memory usage - {$memory_usage}MB ({$usage_percent}%)");
            
            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
}

// Parse memory limit string
function tpak_parse_memory_limit($limit) {
    $unit = strtolower(substr($limit, -1));
    $value = (int)substr($limit, 0, -1);
    
    switch ($unit) {
        case 'k': return $value / 1024;
        case 'm': return $value;
        case 'g': return $value * 1024;
        default: return $value / 1024 / 1024;
    }
}

// Hook memory monitoring
add_action('wp_loaded', 'tpak_memory_monitor');

// Optimize database queries for TPAK
function tpak_optimize_queries($query) {
    // Add query optimization for TPAK tables
    if (strpos($query, 'tpak_') !== false) {
        // Add query hints for better performance
        $query = str_replace('SELECT', 'SELECT SQL_NO_CACHE', $query);
    }
    
    return $query;
}

// Hook query optimization
add_filter('query', 'tpak_optimize_queries');

// Clean up memory after TPAK operations
function tpak_cleanup_memory() {
    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Force garbage collection
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Reset peak memory usage
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }
}

// Hook cleanup after TPAK operations
add_action('wp_ajax_tpak', 'tpak_cleanup_memory');
add_action('wp_ajax_nopriv_tpak', 'tpak_cleanup_memory');

// Disable unnecessary WordPress features for better performance
function tpak_disable_features() {
    // Disable emojis
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    
    // Disable embed
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    
    // Disable XML-RPC
    add_filter('xmlrpc_enabled', '__return_false');
}

// Hook feature disabling
add_action('init', 'tpak_disable_features');

// Optimize admin for TPAK
function tpak_admin_optimization() {
    if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'tpak') !== false) {
        // Increase memory limit for admin pages
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }
        
        // Disable unnecessary admin features
        remove_action('admin_head', 'wp_generator');
        remove_action('admin_head', 'wlwmanifest_link');
        remove_action('admin_head', 'rsd_link');
    }
}

// Hook admin optimization
add_action('admin_init', 'tpak_admin_optimization'); 