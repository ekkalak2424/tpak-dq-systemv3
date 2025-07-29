<?php
/**
 * TPAK DQ System - Quality Checks Page
 * 
 * หน้าแสดงรายการการตรวจสอบคุณภาพข้อมูล
 */

if (!defined('ABSPATH')) {
    exit;
}

$core = TPAK_DQ_Core::get_instance();
$quality_checks = $this->get_quality_checks();
$total_checks = count($quality_checks);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Quality Checks', 'tpak-dq-system'); ?></h1>
    
    <a href="#" class="page-title-action" id="add-quality-check">
        <?php _e('Add Quality Check', 'tpak-dq-system'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Statistics -->
    <div class="tpak-stats-grid">
        <div class="tpak-stat-card">
            <div class="tpak-stat-number"><?php echo esc_html($total_checks); ?></div>
            <div class="tpak-stat-label"><?php _e('Total Quality Checks', 'tpak-dq-system'); ?></div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-number">
                <?php 
                $active_count = 0;
                foreach ($quality_checks as $check) {
                    if ($check->is_active) $active_count++;
                }
                echo esc_html($active_count);
                ?>
            </div>
            <div class="tpak-stat-label"><?php _e('Active', 'tpak-dq-system'); ?></div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-number">
                <?php 
                $inactive_count = $total_checks - $active_count;
                echo esc_html($inactive_count);
                ?>
            </div>
            <div class="tpak-stat-label"><?php _e('Inactive', 'tpak-dq-system'); ?></div>
        </div>
    </div>
    
    <!-- Quality Checks Table -->
    <div class="tpak-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-questionnaire">
                        <?php _e('Questionnaire', 'tpak-dq-system'); ?>
                    </th>
                    <th scope="col" class="manage-column column-check-type">
                        <?php _e('Check Type', 'tpak-dq-system'); ?>
                    </th>
                    <th scope="col" class="manage-column column-config">
                        <?php _e('Configuration', 'tpak-dq-system'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'tpak-dq-system'); ?>
                    </th>
                    <th scope="col" class="manage-column column-created">
                        <?php _e('Created', 'tpak-dq-system'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'tpak-dq-system'); ?>
                    </th>
                </tr>
            </thead>
            
            <tbody>
                <?php if (empty($quality_checks)): ?>
                    <tr>
                        <td colspan="6" class="no-items">
                            <?php _e('No quality checks found.', 'tpak-dq-system'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quality_checks as $check): ?>
                        <tr>
                            <td class="column-questionnaire">
                                <strong><?php echo esc_html($check->questionnaire_title); ?></strong>
                            </td>
                            
                            <td class="column-check-type">
                                <span class="tpak-check-type tpak-check-type-<?php echo esc_attr($check->check_type); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $check->check_type))); ?>
                                </span>
                            </td>
                            
                            <td class="column-config">
                                <?php 
                                $config = json_decode($check->check_config, true);
                                if ($config) {
                                    echo '<small>' . esc_html(json_encode($config, JSON_PRETTY_PRINT)) . '</small>';
                                } else {
                                    echo '<em>' . __('No configuration', 'tpak-dq-system') . '</em>';
                                }
                                ?>
                            </td>
                            
                            <td class="column-status">
                                <span class="tpak-status-badge tpak-status-<?php echo $check->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $check->is_active ? __('Active', 'tpak-dq-system') : __('Inactive', 'tpak-dq-system'); ?>
                                </span>
                            </td>
                            
                            <td class="column-created">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($check->created_at))); ?>
                            </td>
                            
                            <td class="column-actions">
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" class="edit-quality-check" data-id="<?php echo esc_attr($check->id); ?>">
                                            <?php _e('Edit', 'tpak-dq-system'); ?>
                                        </a> |
                                    </span>
                                    <span class="run">
                                        <a href="#" class="run-quality-check" data-id="<?php echo esc_attr($check->id); ?>">
                                            <?php _e('Run Check', 'tpak-dq-system'); ?>
                                        </a> |
                                    </span>
                                    <span class="toggle">
                                        <a href="#" class="toggle-quality-check" data-id="<?php echo esc_attr($check->id); ?>" 
                                           data-status="<?php echo esc_attr($check->is_active); ?>">
                                            <?php echo $check->is_active ? __('Deactivate', 'tpak-dq-system') : __('Activate', 'tpak-dq-system'); ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" class="delete-quality-check" data-id="<?php echo esc_attr($check->id); ?>" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this quality check?', 'tpak-dq-system'); ?>')">
                                            <?php _e('Delete', 'tpak-dq-system'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add Quality Check Modal -->
    <div id="quality-check-modal" class="tpak-modal" style="display: none;">
        <div class="tpak-modal-content">
            <div class="tpak-modal-header">
                <h2 id="modal-title"><?php _e('Add Quality Check', 'tpak-dq-system'); ?></h2>
                <span class="tpak-modal-close">&times;</span>
            </div>
            <div class="tpak-modal-body">
                <form id="quality-check-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="questionnaire_id"><?php _e('Questionnaire', 'tpak-dq-system'); ?></label>
                            </th>
                            <td>
                                <select id="questionnaire_id" name="questionnaire_id" required>
                                    <option value=""><?php _e('Select Questionnaire', 'tpak-dq-system'); ?></option>
                                    <?php
                                    $questionnaires = $core->get_questionnaire_manager()->get_questionnaires();
                                    foreach ($questionnaires as $questionnaire) {
                                        echo '<option value="' . esc_attr($questionnaire->id) . '">' . esc_html($questionnaire->title) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="check_type"><?php _e('Check Type', 'tpak-dq-system'); ?></label>
                            </th>
                            <td>
                                <select id="check_type" name="check_type" required>
                                    <option value=""><?php _e('Select Check Type', 'tpak-dq-system'); ?></option>
                                    <option value="completeness"><?php _e('Completeness', 'tpak-dq-system'); ?></option>
                                    <option value="consistency"><?php _e('Consistency', 'tpak-dq-system'); ?></option>
                                    <option value="validity"><?php _e('Validity', 'tpak-dq-system'); ?></option>
                                    <option value="accuracy"><?php _e('Accuracy', 'tpak-dq-system'); ?></option>
                                    <option value="timeliness"><?php _e('Timeliness', 'tpak-dq-system'); ?></option>
                                    <option value="uniqueness"><?php _e('Uniqueness', 'tpak-dq-system'); ?></option>
                                    <option value="custom"><?php _e('Custom', 'tpak-dq-system'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="check_config"><?php _e('Configuration (JSON)', 'tpak-dq-system'); ?></label>
                            </th>
                            <td>
                                <textarea id="check_config" name="check_config" rows="5" cols="50" 
                                          placeholder='{"field": "value", "threshold": 0.8}'></textarea>
                                <p class="description">
                                    <?php _e('Enter configuration as JSON. Leave empty for default settings.', 'tpak-dq-system'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="is_active"><?php _e('Active', 'tpak-dq-system'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <label for="is_active">
                                    <?php _e('Enable this quality check', 'tpak-dq-system'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Add Quality Check', 'tpak-dq-system'); ?>">
                        <button type="button" class="button" id="cancel-quality-check">
                            <?php _e('Cancel', 'tpak-dq-system'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Add quality check
    $('#add-quality-check').on('click', function(e) {
        e.preventDefault();
        $('#quality-check-modal').show();
    });
    
    // Close modal
    $('.tpak-modal-close, #cancel-quality-check').on('click', function() {
        $('#quality-check-modal').hide();
    });
    
    // Submit quality check form
    $('#quality-check-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&action=tpak_dq_add_quality_check&nonce=' + tpak_dq_ajax.nonce;
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('<?php _e('Failed to add quality check. Please try again.', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Run quality check
    $('.run-quality-check').on('click', function(e) {
        e.preventDefault();
        
        var checkId = $(this).data('id');
        var link = $(this);
        
        if (confirm('<?php _e('Run this quality check now?', 'tpak-dq-system'); ?>')) {
            link.text('<?php _e('Running...', 'tpak-dq-system'); ?>');
            
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_dq_run_quality_check',
                    check_id: checkId,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Quality check completed successfully.', 'tpak-dq-system'); ?>');
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Quality check failed. Please try again.', 'tpak-dq-system'); ?>');
                },
                complete: function() {
                    link.text('<?php _e('Run Check', 'tpak-dq-system'); ?>');
                }
            });
        }
    });
    
    // Toggle quality check status
    $('.toggle-quality-check').on('click', function(e) {
        e.preventDefault();
        
        var checkId = $(this).data('id');
        var currentStatus = $(this).data('status');
        var link = $(this);
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_toggle_quality_check',
                check_id: checkId,
                current_status: currentStatus,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('<?php _e('Failed to toggle quality check status. Please try again.', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Delete quality check
    $('.delete-quality-check').on('click', function(e) {
        e.preventDefault();
        
        var checkId = $(this).data('id');
        var row = $(this).closest('tr');
        
        if (confirm('<?php _e('Are you sure you want to delete this quality check? This action cannot be undone.', 'tpak-dq-system'); ?>')) {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_dq_delete_quality_check',
                    check_id: checkId,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Delete failed. Please try again.', 'tpak-dq-system'); ?>');
                }
            });
        }
    });
});
</script> 