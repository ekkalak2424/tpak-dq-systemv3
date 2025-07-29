<?php
/**
 * TPAK DQ System - Questionnaires Page
 * 
 * หน้าแสดงรายการแบบสอบถาม
 */

if (!defined('ABSPATH')) {
    exit;
}

$core = TPAK_DQ_Core::get_instance();
$questionnaire_manager = $core->get_questionnaire_manager();

// รับข้อมูลแบบสอบถาม
$questionnaires = $questionnaire_manager->get_questionnaires();
$total_questionnaires = count($questionnaires);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Questionnaires', 'tpak-dq-system'); ?></h1>
    
    <a href="#" class="page-title-action" id="sync-questionnaires">
        <?php _e('Sync from LimeSurvey', 'tpak-dq-system'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Statistics -->
    <div class="tpak-stats-grid">
        <div class="tpak-stat-card">
            <div class="tpak-stat-number"><?php echo esc_html($total_questionnaires); ?></div>
            <div class="tpak-stat-label"><?php _e('Total Questionnaires', 'tpak-dq-system'); ?></div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-number">
                <?php 
                $active_count = 0;
                foreach ($questionnaires as $q) {
                    if ($q->status === 'active') $active_count++;
                }
                echo esc_html($active_count);
                ?>
            </div>
            <div class="tpak-stat-label"><?php _e('Active', 'tpak-dq-system'); ?></div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-number">
                <?php 
                $inactive_count = $total_questionnaires - $active_count;
                echo esc_html($inactive_count);
                ?>
            </div>
            <div class="tpak-stat-label"><?php _e('Inactive', 'tpak-dq-system'); ?></div>
        </div>
    </div>
    
    <!-- Questionnaires Table -->
    <div class="tpak-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-title">
                        <?php _e('Title', 'tpak-dq-system'); ?>
                    </th>
                    <th scope="col" class="manage-column column-limesurvey-id">
                        <?php _e('LimeSurvey ID', 'tpak-dq-system'); ?>
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
                <?php if (empty($questionnaires)): ?>
                    <tr>
                        <td colspan="5" class="no-items">
                            <?php _e('No questionnaires found.', 'tpak-dq-system'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($questionnaires as $questionnaire): ?>
                        <tr>
                            <td class="column-title">
                                <strong>
                                    <a href="#" class="questionnaire-title" data-id="<?php echo esc_attr($questionnaire->id); ?>">
                                        <?php echo esc_html($questionnaire->title); ?>
                                    </a>
                                </strong>
                                <?php if ($questionnaire->description): ?>
                                    <br>
                                    <small class="description">
                                        <?php echo esc_html(wp_trim_words($questionnaire->description, 20)); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            
                            <td class="column-limesurvey-id">
                                <code><?php echo esc_html($questionnaire->limesurvey_id); ?></code>
                            </td>
                            
                            <td class="column-status">
                                <span class="tpak-status-badge tpak-status-<?php echo esc_attr($questionnaire->status); ?>">
                                    <?php echo esc_html(ucfirst($questionnaire->status)); ?>
                                </span>
                            </td>
                            
                            <td class="column-created">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($questionnaire->created_at))); ?>
                            </td>
                            
                            <td class="column-actions">
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="#" class="view-questionnaire" data-id="<?php echo esc_attr($questionnaire->id); ?>">
                                            <?php _e('View', 'tpak-dq-system'); ?>
                                        </a> |
                                    </span>
                                    <span class="edit">
                                        <a href="#" class="edit-questionnaire" data-id="<?php echo esc_attr($questionnaire->id); ?>">
                                            <?php _e('Edit', 'tpak-dq-system'); ?>
                                        </a> |
                                    </span>
                                    <span class="quality-checks">
                                        <a href="#" class="run-quality-checks" data-id="<?php echo esc_attr($questionnaire->id); ?>">
                                            <?php _e('Quality Checks', 'tpak-dq-system'); ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" class="delete-questionnaire" data-id="<?php echo esc_attr($questionnaire->id); ?>" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this questionnaire?', 'tpak-dq-system'); ?>')">
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
    
    <!-- Questionnaire Details Modal -->
    <div id="questionnaire-modal" class="tpak-modal" style="display: none;">
        <div class="tpak-modal-content">
            <div class="tpak-modal-header">
                <h2 id="modal-title"><?php _e('Questionnaire Details', 'tpak-dq-system'); ?></h2>
                <span class="tpak-modal-close">&times;</span>
            </div>
            <div class="tpak-modal-body" id="modal-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sync questionnaires
    $('#sync-questionnaires').on('click', function(e) {
        e.preventDefault();
        
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
                    location.reload();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('<?php _e('Sync failed. Please try again.', 'tpak-dq-system'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Sync from LimeSurvey', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // View questionnaire details
    $('.view-questionnaire, .questionnaire-title').on('click', function(e) {
        e.preventDefault();
        
        var questionnaireId = $(this).data('id');
        var modal = $('#questionnaire-modal');
        var modalContent = $('#modal-content');
        
        modalContent.html('<div class="tpak-loading"><?php _e('Loading...', 'tpak-dq-system'); ?></div>');
        modal.show();
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_get_questionnaire_details',
                questionnaire_id: questionnaireId,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    modalContent.html(response.data);
                } else {
                    modalContent.html('<div class="tpak-error">' + response.data + '</div>');
                }
            },
            error: function() {
                modalContent.html('<div class="tpak-error"><?php _e('Failed to load questionnaire details.', 'tpak-dq-system'); ?></div>');
            }
        });
    });
    
    // Run quality checks
    $('.run-quality-checks').on('click', function(e) {
        e.preventDefault();
        
        var questionnaireId = $(this).data('id');
        var link = $(this);
        
        if (confirm('<?php _e('Run quality checks for this questionnaire?', 'tpak-dq-system'); ?>')) {
            link.text('<?php _e('Running...', 'tpak-dq-system'); ?>');
            
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_dq_run_quality_check',
                    questionnaire_id: questionnaireId,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Quality checks completed successfully.', 'tpak-dq-system'); ?>');
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Quality checks failed. Please try again.', 'tpak-dq-system'); ?>');
                },
                complete: function() {
                    link.text('<?php _e('Quality Checks', 'tpak-dq-system'); ?>');
                }
            });
        }
    });
    
    // Delete questionnaire
    $('.delete-questionnaire').on('click', function(e) {
        e.preventDefault();
        
        var questionnaireId = $(this).data('id');
        var row = $(this).closest('tr');
        
        if (confirm('<?php _e('Are you sure you want to delete this questionnaire? This action cannot be undone.', 'tpak-dq-system'); ?>')) {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_dq_delete_questionnaire',
                    questionnaire_id: questionnaireId,
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
    
    // Close modal
    $('.tpak-modal-close, .tpak-modal').on('click', function(e) {
        if (e.target === this) {
            $('#questionnaire-modal').hide();
        }
    });
});
</script> 