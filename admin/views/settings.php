<?php
/**
 * TPAK DQ System - Settings Page
 * 
 * หน้าตั้งค่าของระบบ
 */

if (!defined('ABSPATH')) {
    exit;
}

$core = TPAK_DQ_Core::get_instance();
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System - Settings', 'tpak-dq-system'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('tpak_dq_settings', 'tpak_dq_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="limesurvey_api_url"><?php _e('LimeSurvey API URL', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <input type="url" id="limesurvey_api_url" name="limesurvey_api_url" 
                           value="<?php echo esc_attr($settings['limesurvey_api_url'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('URL ของ LimeSurvey API (เช่น: https://your-limesurvey.com/admin/remotecontrol)', 'tpak-dq-system'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="limesurvey_username"><?php _e('LimeSurvey Username', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <input type="text" id="limesurvey_username" name="limesurvey_username" 
                           value="<?php echo esc_attr($settings['limesurvey_username'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Username สำหรับ LimeSurvey API', 'tpak-dq-system'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="limesurvey_password"><?php _e('LimeSurvey Password', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <input type="password" id="limesurvey_password" name="limesurvey_password" 
                           value="<?php echo esc_attr($settings['limesurvey_password'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Password สำหรับ LimeSurvey API', 'tpak-dq-system'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="auto_sync_interval"><?php _e('Auto Sync Interval', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <select id="auto_sync_interval" name="auto_sync_interval">
                        <option value="15min" <?php selected($settings['auto_sync_interval'] ?? '', '15min'); ?>>
                            <?php _e('Every 15 minutes', 'tpak-dq-system'); ?>
                        </option>
                        <option value="30min" <?php selected($settings['auto_sync_interval'] ?? '', '30min'); ?>>
                            <?php _e('Every 30 minutes', 'tpak-dq-system'); ?>
                        </option>
                        <option value="1hour" <?php selected($settings['auto_sync_interval'] ?? '', '1hour'); ?>>
                            <?php _e('Every hour', 'tpak-dq-system'); ?>
                        </option>
                        <option value="2hours" <?php selected($settings['auto_sync_interval'] ?? '', '2hours'); ?>>
                            <?php _e('Every 2 hours', 'tpak-dq-system'); ?>
                        </option>
                        <option value="disabled" <?php selected($settings['auto_sync_interval'] ?? '', 'disabled'); ?>>
                            <?php _e('Disabled', 'tpak-dq-system'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('ความถี่ในการ sync ข้อมูลจาก LimeSurvey', 'tpak-dq-system'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="quality_check_enabled"><?php _e('Quality Check Enabled', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="quality_check_enabled" name="quality_check_enabled" 
                           value="1" <?php checked($settings['quality_check_enabled'] ?? '', '1'); ?> />
                    <label for="quality_check_enabled">
                        <?php _e('เปิดใช้งานการตรวจสอบคุณภาพข้อมูลอัตโนมัติ', 'tpak-dq-system'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="report_auto_generate"><?php _e('Auto Generate Reports', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="report_auto_generate" name="report_auto_generate" 
                           value="1" <?php checked($settings['report_auto_generate'] ?? '', '1'); ?> />
                    <label for="report_auto_generate">
                        <?php _e('สร้างรายงานอัตโนมัติ', 'tpak-dq-system'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_email"><?php _e('Notification Email', 'tpak-dq-system'); ?></label>
                </th>
                <td>
                    <input type="email" id="notification_email" name="notification_email" 
                           value="<?php echo esc_attr($settings['notification_email'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Email สำหรับส่งการแจ้งเตือน (เว้นว่างเพื่อใช้ admin email)', 'tpak-dq-system'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button-primary" 
                   value="<?php _e('Save Settings', 'tpak-dq-system'); ?>" />
        </p>
    </form>
    
    <hr />
    
    <h2><?php _e('System Information', 'tpak-dq-system'); ?></h2>
    
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('Plugin Version', 'tpak-dq-system'); ?></th>
            <td><?php echo esc_html(TPAK_DQ_SYSTEM_VERSION); ?></td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('WordPress Version', 'tpak-dq-system'); ?></th>
            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('PHP Version', 'tpak-dq-system'); ?></th>
            <td><?php echo esc_html(PHP_VERSION); ?></td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Database Tables', 'tpak-dq-system'); ?></th>
            <td>
                <?php
                global $wpdb;
                $tables = array(
                    $wpdb->prefix . 'tpak_questionnaires',
                    $wpdb->prefix . 'tpak_quality_checks',
                    $wpdb->prefix . 'tpak_check_results',
                    $wpdb->prefix . 'tpak_verification_batches',
                    $wpdb->prefix . 'tpak_verification_logs',
                    $wpdb->prefix . 'tpak_workflow_status',
                    $wpdb->prefix . 'tpak_notifications'
                );
                
                $existing_tables = array();
                foreach ($tables as $table) {
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                        $existing_tables[] = $table;
                    }
                }
                
                if (count($existing_tables) === count($tables)) {
                    echo '<span style="color: green;">✓ ' . __('All tables exist', 'tpak-dq-system') . '</span>';
                } else {
                    echo '<span style="color: red;">✗ ' . __('Some tables are missing', 'tpak-dq-system') . '</span>';
                    echo '<br><small>' . __('Please deactivate and reactivate the plugin to create missing tables.', 'tpak-dq-system') . '</small>';
                }
                ?>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('LimeSurvey Connection', 'tpak-dq-system'); ?></th>
            <td>
                <?php
                if ($core->is_limesurvey_ready()) {
                    echo '<span style="color: green;">✓ ' . __('Configured', 'tpak-dq-system') . '</span>';
                } else {
                    echo '<span style="color: red;">✗ ' . __('Not configured', 'tpak-dq-system') . '</span>';
                }
                ?>
            </td>
        </tr>
    </table>
    
    <hr />
    
    <h2><?php _e('Actions', 'tpak-dq-system'); ?></h2>
    
    <p>
        <button type="button" class="button" id="test-connection">
            <?php _e('Test LimeSurvey Connection', 'tpak-dq-system'); ?>
        </button>
        
        <button type="button" class="button" id="sync-now">
            <?php _e('Sync Data Now', 'tpak-dq-system'); ?>
        </button>
        
        <button type="button" class="button" id="run-quality-checks">
            <?php _e('Run Quality Checks', 'tpak-dq-system'); ?>
        </button>
        
        <button type="button" class="button" id="generate-reports">
            <?php _e('Generate Reports', 'tpak-dq-system'); ?>
        </button>
        
        <button type="button" class="button button-primary" id="force-create-tables">
            <?php _e('Force Create Database Tables', 'tpak-dq-system'); ?>
        </button>
    </p>
    
    <div id="action-results"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test connection
    $('#test-connection').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Testing...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_test_connection',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#action-results').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                } else {
                    $('#action-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#action-results').html('<div class="notice notice-error"><p><?php _e('Connection test failed', 'tpak-dq-system'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Test LimeSurvey Connection', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Sync data
    $('#sync-now').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Syncing...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_sync_questionnaires',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#action-results').html('<div class="notice notice-success"><p><?php _e('Data sync completed successfully', 'tpak-dq-system'); ?></p></div>');
                } else {
                    $('#action-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#action-results').html('<div class="notice notice-error"><p><?php _e('Data sync failed', 'tpak-dq-system'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Sync Data Now', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Run quality checks
    $('#run-quality-checks').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Running...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_run_quality_checks',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#action-results').html('<div class="notice notice-success"><p><?php _e('Quality checks completed successfully', 'tpak-dq-system'); ?></p></div>');
                } else {
                    $('#action-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#action-results').html('<div class="notice notice-error"><p><?php _e('Quality checks failed', 'tpak-dq-system'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Run Quality Checks', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Generate reports
    $('#generate-reports').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Generating...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_generate_reports',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#action-results').html('<div class="notice notice-success"><p><?php _e('Reports generated successfully', 'tpak-dq-system'); ?></p></div>');
                } else {
                    $('#action-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#action-results').html('<div class="notice notice-error"><p><?php _e('Report generation failed', 'tpak-dq-system'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Generate Reports', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Force create tables
    $('#force-create-tables').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Creating Tables...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_force_create_tables',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#action-results').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    // Reload page after successful table creation
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#action-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#action-results').html('<div class="notice notice-error"><p><?php _e('Failed to create database tables', 'tpak-dq-system'); ?></p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Force Create Database Tables', 'tpak-dq-system'); ?>');
            }
        });
    });
});
</script> 