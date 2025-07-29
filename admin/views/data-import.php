<?php
/**
 * TPAK DQ System - Data Import Page
 * 
 * หน้าสำหรับ import ข้อมูลจาก LimeSurvey
 */

if (!defined('ABSPATH')) {
    exit;
}

$core = TPAK_DQ_Core::get_instance();
$limesurvey_client = $core->get_limesurvey_client();
?>

<div class="wrap">
    <h1><?php _e('Data Import', 'tpak-dq-system'); ?></h1>
    
    <hr class="wp-header-end">
    
    <!-- LimeSurvey Connection -->
    <div class="tpak-section">
        <h2><?php _e('LimeSurvey Connection', 'tpak-dq-system'); ?></h2>
        
        <div class="tpak-connection-status">
            <p>
                <strong><?php _e('Connection Status:', 'tpak-dq-system'); ?></strong>
                <span id="connection-status"><?php _e('Checking...', 'tpak-dq-system'); ?></span>
            </p>
            
            <button type="button" class="button" id="test-connection">
                <?php _e('Test Connection', 'tpak-dq-system'); ?>
            </button>
        </div>
    </div>
    
    <!-- Available Surveys -->
    <div class="tpak-section">
        <h2><?php _e('Available Surveys', 'tpak-dq-system'); ?></h2>
        
        <div id="surveys-container">
            <p><?php _e('Loading surveys...', 'tpak-dq-system'); ?></p>
        </div>
        
        <div class="tpak-survey-actions">
            <button type="button" class="button button-primary" id="sync-all-surveys">
                <?php _e('Sync All Surveys', 'tpak-dq-system'); ?>
            </button>
            
            <button type="button" class="button" id="refresh-surveys">
                <?php _e('Refresh List', 'tpak-dq-system'); ?>
            </button>
        </div>
    </div>
    
    <!-- Import History -->
    <div class="tpak-section">
        <h2><?php _e('Import History', 'tpak-dq-system'); ?></h2>
        
        <div class="tpak-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-survey-id">
                            <?php _e('Survey ID', 'tpak-dq-system'); ?>
                        </th>
                        <th scope="col" class="manage-column column-title">
                            <?php _e('Title', 'tpak-dq-system'); ?>
                        </th>
                        <th scope="col" class="manage-column column-last-sync">
                            <?php _e('Last Sync', 'tpak-dq-system'); ?>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <?php _e('Status', 'tpak-dq-system'); ?>
                        </th>
                        <th scope="col" class="manage-column column-actions">
                            <?php _e('Actions', 'tpak-dq-system'); ?>
                        </th>
                    </tr>
                </thead>
                
                <tbody id="import-history">
                    <tr>
                        <td colspan="5" class="no-items">
                            <?php _e('No import history found.', 'tpak-dq-system'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Import Settings -->
    <div class="tpak-section">
        <h2><?php _e('Import Settings', 'tpak-dq-system'); ?></h2>
        
        <form id="import-settings-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_sync_enabled"><?php _e('Auto Sync', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="auto_sync_enabled" name="auto_sync_enabled" value="1" 
                               <?php checked(get_option('tpak_dq_auto_sync_enabled', '1'), '1'); ?>>
                        <label for="auto_sync_enabled">
                            <?php _e('Enable automatic synchronization', 'tpak-dq-system'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sync_interval"><?php _e('Sync Interval', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <select id="sync_interval" name="sync_interval">
                            <option value="15min" <?php selected(get_option('tpak_dq_sync_interval', '1hour'), '15min'); ?>>
                                <?php _e('Every 15 minutes', 'tpak-dq-system'); ?>
                            </option>
                            <option value="30min" <?php selected(get_option('tpak_dq_sync_interval', '1hour'), '30min'); ?>>
                                <?php _e('Every 30 minutes', 'tpak-dq-system'); ?>
                            </option>
                            <option value="1hour" <?php selected(get_option('tpak_dq_sync_interval', '1hour'), '1hour'); ?>>
                                <?php _e('Every hour', 'tpak-dq-system'); ?>
                            </option>
                            <option value="2hours" <?php selected(get_option('tpak_dq_sync_interval', '1hour'), '2hours'); ?>>
                                <?php _e('Every 2 hours', 'tpak-dq-system'); ?>
                            </option>
                            <option value="disabled" <?php selected(get_option('tpak_dq_sync_interval', '1hour'), 'disabled'); ?>>
                                <?php _e('Disabled', 'tpak-dq-system'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sync_new_only"><?php _e('Sync New Only', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="sync_new_only" name="sync_new_only" value="1" 
                               <?php checked(get_option('tpak_dq_sync_new_only', '0'), '1'); ?>>
                        <label for="sync_new_only">
                            <?php _e('Only sync new responses (skip existing)', 'tpak-dq-system'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_responses_per_sync"><?php _e('Max Responses per Sync', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_responses_per_sync" name="max_responses_per_sync" 
                               value="<?php echo esc_attr(get_option('tpak_dq_max_responses_per_sync', '1000')); ?>" 
                               min="100" max="10000" step="100">
                        <p class="description">
                            <?php _e('Maximum number of responses to sync in one operation', 'tpak-dq-system'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Settings', 'tpak-dq-system'); ?>">
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test connection
    $('#test-connection').on('click', function() {
        var button = $(this);
        var status = $('#connection-status');
        
        button.prop('disabled', true).text('<?php _e('Testing...', 'tpak-dq-system'); ?>');
        status.text('<?php _e('Testing connection...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_test_limesurvey_connection',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ <?php _e('Connected', 'tpak-dq-system'); ?></span>');
                    loadSurveys();
                } else {
                    status.html('<span style="color: red;">✗ <?php _e('Connection failed', 'tpak-dq-system'); ?></span>');
                }
            },
            error: function() {
                status.html('<span style="color: red;">✗ <?php _e('Connection failed', 'tpak-dq-system'); ?></span>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Test Connection', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Load surveys
    function loadSurveys() {
        var container = $('#surveys-container');
        container.html('<p><?php _e('Loading surveys...', 'tpak-dq-system'); ?></p>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_get_limesurvey_surveys',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySurveys(response.data);
                } else {
                    container.html('<p style="color: red;">' + response.data + '</p>');
                }
            },
            error: function() {
                container.html('<p style="color: red;"><?php _e('Failed to load surveys.', 'tpak-dq-system'); ?></p>');
            }
        });
    }
    
    // Display surveys
    function displaySurveys(surveys) {
        var container = $('#surveys-container');
        
        if (surveys.length === 0) {
            container.html('<p><?php _e('No surveys found.', 'tpak-dq-system'); ?></p>');
            return;
        }
        
        var html = '<div class="tpak-surveys-grid">';
        surveys.forEach(function(survey) {
            html += '<div class="tpak-survey-card">';
            html += '<h3>' + survey.title + '</h3>';
            html += '<p><strong><?php _e('ID:', 'tpak-dq-system'); ?></strong> ' + survey.sid + '</p>';
            html += '<p><strong><?php _e('Status:', 'tpak-dq-system'); ?></strong> ' + survey.active + '</p>';
            html += '<button type="button" class="button sync-survey" data-sid="' + survey.sid + '">';
            html += '<?php _e('Sync Survey', 'tpak-dq-system'); ?>';
            html += '</button>';
            html += '</div>';
        });
        html += '</div>';
        
        container.html(html);
    }
    
    // Sync all surveys
    $('#sync-all-surveys').on('click', function() {
        var button = $(this);
        
        if (confirm('<?php _e('Sync all surveys from LimeSurvey? This may take some time.', 'tpak-dq-system'); ?>')) {
            button.prop('disabled', true).text('<?php _e('Syncing...', 'tpak-dq-system'); ?>');
            
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_dq_sync_all_surveys',
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('All surveys synced successfully.', 'tpak-dq-system'); ?>');
                        loadImportHistory();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Sync failed. Please try again.', 'tpak-dq-system'); ?>');
                },
                complete: function() {
                    button.prop('disabled', false).text('<?php _e('Sync All Surveys', 'tpak-dq-system'); ?>');
                }
            });
        }
    });
    
    // Refresh surveys
    $('#refresh-surveys').on('click', function() {
        loadSurveys();
    });
    
    // Sync individual survey
    $(document).on('click', '.sync-survey', function() {
        var button = $(this);
        var sid = button.data('sid');
        
        button.prop('disabled', true).text('<?php _e('Syncing...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_sync_single_survey',
                survey_id: sid,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Survey synced successfully.', 'tpak-dq-system'); ?>');
                    loadImportHistory();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('<?php _e('Sync failed. Please try again.', 'tpak-dq-system'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Sync Survey', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Load import history
    function loadImportHistory() {
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_get_import_history',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayImportHistory(response.data);
                }
            }
        });
    }
    
    // Display import history
    function displayImportHistory(history) {
        var tbody = $('#import-history');
        
        if (history.length === 0) {
            tbody.html('<tr><td colspan="5" class="no-items"><?php _e('No import history found.', 'tpak-dq-system'); ?></td></tr>');
            return;
        }
        
        var html = '';
        history.forEach(function(item) {
            html += '<tr>';
            html += '<td>' + item.survey_id + '</td>';
            html += '<td>' + item.title + '</td>';
            html += '<td>' + item.last_sync + '</td>';
            html += '<td><span class="tpak-status-badge tpak-status-' + item.status + '">' + item.status + '</span></td>';
            html += '<td>';
            html += '<a href="#" class="sync-again" data-sid="' + item.survey_id + '"><?php _e('Sync Again', 'tpak-dq-system'); ?></a>';
            html += '</td>';
            html += '</tr>';
        });
        
        tbody.html(html);
    }
    
    // Save import settings
    $('#import-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=tpak_dq_save_import_settings&nonce=' + tpak_dq_ajax.nonce;
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Settings saved successfully.', 'tpak-dq-system'); ?>');
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('<?php _e('Failed to save settings. Please try again.', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Initialize
    $('#test-connection').click();
    loadImportHistory();
});
</script> 