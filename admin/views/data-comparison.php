<?php
/**
 * TPAK DQ System - Data Comparison Interface
 * 
 * Side-by-side data comparison with diff highlighting and inline comments
 */

if (!defined('ABSPATH')) {
    exit;
}

$response_id = sanitize_text_field($_GET['response_id'] ?? '');
$batch_id = intval($_GET['batch_id'] ?? 0);

if (!$response_id || !$batch_id) {
    wp_die(__('Invalid response or batch ID', 'tpak-dq-system'));
}

// รับข้อมูล response
global $wpdb;
$table_survey_data = $wpdb->prefix . 'tpak_survey_data';
$table_verification_logs = $wpdb->prefix . 'tpak_verification_logs';

$response_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_survey_data WHERE response_id = %s AND batch_id = %d",
    $response_id, $batch_id
));

if (!$response_data) {
    wp_die(__('Response data not found', 'tpak-dq-system'));
}

$original_data = json_decode($response_data->response_data, true);
$verification_logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_verification_logs WHERE response_id = %s AND batch_id = %d ORDER BY verification_date DESC",
    $response_id, $batch_id
));

// รับข้อมูล workflow
$workflow = TPAK_Workflow::get_instance();
$workflow_data = $workflow->get_workflow_data($batch_id);
?>

<div class="wrap">
    <h1><?php _e('Data Comparison', 'tpak-dq-system'); ?></h1>
    
    <!-- Navigation -->
    <div class="tpak-comparison-nav">
        <a href="<?php echo admin_url('admin.php?page=tpak-dq-verification-queue'); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php _e('Back to Queue', 'tpak-dq-system'); ?>
        </a>
        
        <div class="tpak-response-info">
            <span class="tpak-response-id"><?php printf(__('Response ID: %s', 'tpak-dq-system'), esc_html($response_id)); ?></span>
            <span class="tpak-batch-id"><?php printf(__('Batch ID: %d', 'tpak-dq-system'), $batch_id); ?></span>
            <span class="tpak-workflow-status"><?php printf(__('Status: %s', 'tpak-dq-system'), esc_html($workflow_data['current_state'] ?? 'unknown')); ?></span>
        </div>
    </div>

    <!-- Comparison Controls -->
    <div class="tpak-comparison-controls">
        <div class="tpak-control-group">
            <label for="diff-mode"><?php _e('Diff Mode:', 'tpak-dq-system'); ?></label>
            <select id="diff-mode">
                <option value="word"><?php _e('Word Level', 'tpak-dq-system'); ?></option>
                <option value="line"><?php _e('Line Level', 'tpak-dq-system'); ?></option>
                <option value="character"><?php _e('Character Level', 'tpak-dq-system'); ?></option>
            </select>
        </div>
        
        <div class="tpak-control-group">
            <label for="highlight-mode"><?php _e('Highlight:', 'tpak-dq-system'); ?></label>
            <select id="highlight-mode">
                <option value="all"><?php _e('All Changes', 'tpak-dq-system'); ?></option>
                <option value="additions"><?php _e('Additions Only', 'tpak-dq-system'); ?></option>
                <option value="deletions"><?php _e('Deletions Only', 'tpak-dq-system'); ?></option>
            </select>
        </div>
        
        <div class="tpak-control-group">
            <button id="toggle-comments" class="button"><?php _e('Toggle Comments', 'tpak-dq-system'); ?></button>
            <button id="export-comparison" class="button button-secondary"><?php _e('Export', 'tpak-dq-system'); ?></button>
        </div>
    </div>

    <!-- Comparison Container -->
    <div class="tpak-comparison-container">
        <!-- Original Data -->
        <div class="tpak-data-panel">
            <div class="tpak-panel-header">
                <h3><?php _e('Original Data', 'tpak-dq-system'); ?></h3>
                <span class="tpak-panel-subtitle"><?php _e('From LimeSurvey', 'tpak-dq-system'); ?></span>
            </div>
            <div class="tpak-data-content" id="original-data">
                <div class="tpak-loading"><?php _e('Loading original data...', 'tpak-dq-system'); ?></div>
            </div>
        </div>

        <!-- Verified Data -->
        <div class="tpak-data-panel">
            <div class="tpak-panel-header">
                <h3><?php _e('Verified Data', 'tpak-dq-system'); ?></h3>
                <span class="tpak-panel-subtitle"><?php _e('After Verification', 'tpak-dq-system'); ?></span>
            </div>
            <div class="tpak-data-content" id="verified-data">
                <div class="tpak-loading"><?php _e('Loading verified data...', 'tpak-dq-system'); ?></div>
            </div>
        </div>
    </div>

    <!-- Comments Section -->
    <div class="tpak-comments-section">
        <h3><?php _e('Comments & Notes', 'tpak-dq-system'); ?></h3>
        <div class="tpak-comments-container">
            <div class="tpak-comments-list" id="comments-list">
                <?php foreach ($verification_logs as $log): ?>
                <div class="tpak-comment-item">
                    <div class="tpak-comment-header">
                        <span class="tpak-comment-author"><?php echo esc_html(get_userdata($log->verifier_id)->display_name); ?></span>
                        <span class="tpak-comment-date"><?php echo esc_html(date('Y-m-d H:i', strtotime($log->verification_date))); ?></span>
                        <span class="tpak-comment-action"><?php echo esc_html($log->verification_action); ?></span>
                    </div>
                    <div class="tpak-comment-content"><?php echo esc_html($log->verification_notes); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="tpak-add-comment">
                <h4><?php _e('Add Comment', 'tpak-dq-system'); ?></h4>
                <textarea id="new-comment" placeholder="<?php _e('Enter your comment here...', 'tpak-dq-system'); ?>"></textarea>
                <div class="tpak-comment-actions">
                    <button id="add-comment" class="button button-primary"><?php _e('Add Comment', 'tpak-dq-system'); ?></button>
                    <button id="add-inline-comment" class="button"><?php _e('Add Inline Comment', 'tpak-dq-system'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="tpak-comparison-actions">
        <button id="approve-data" class="button button-primary">
            <span class="dashicons dashicons-yes"></span>
            <?php _e('Approve Data', 'tpak-dq-system'); ?>
        </button>
        <button id="reject-data" class="button button-secondary">
            <span class="dashicons dashicons-no"></span>
            <?php _e('Reject Data', 'tpak-dq-system'); ?>
        </button>
        <button id="request-revision" class="button">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('Request Revision', 'tpak-dq-system'); ?>
        </button>
        <button id="save-draft" class="button">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('Save Draft', 'tpak-dq-system'); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let originalData = <?php echo json_encode($original_data); ?>;
    let verifiedData = null;
    let currentComments = [];
    
    // Load verified data
    loadVerifiedData();
    
    // Initialize diff highlighting
    initializeDiffHighlighting();
    
    // Initialize inline comments
    initializeInlineComments();
    
    function loadVerifiedData() {
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_get_verified_data',
                response_id: '<?php echo esc_js($response_id); ?>',
                batch_id: <?php echo $batch_id; ?>,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    verifiedData = response.data.verified_data;
                    renderDataComparison();
                } else {
                    $('#verified-data').html('<p class="tpak-error"><?php _e('No verified data available.', 'tpak-dq-system'); ?></p>');
                }
            }
        });
    }
    
    function renderDataComparison() {
        // Render original data
        renderDataPanel('#original-data', originalData, 'original');
        
        // Render verified data
        renderDataPanel('#verified-data', verifiedData, 'verified');
        
        // Apply diff highlighting
        applyDiffHighlighting();
    }
    
    function renderDataPanel(selector, data, type) {
        const container = $(selector);
        let html = '<div class="tpak-data-tree">';
        
        function renderNode(key, value, level = 0) {
            const indent = '  '.repeat(level);
            const nodeClass = type + '-node';
            const nodeId = type + '-' + key.replace(/[^a-zA-Z0-9]/g, '_');
            
            if (typeof value === 'object' && value !== null) {
                html += `<div class="tpak-tree-node ${nodeClass}" data-node-id="${nodeId}" data-key="${key}">`;
                html += `<span class="tpak-node-key">${indent}${key}:</span>`;
                html += '<div class="tpak-node-children">';
                
                Object.keys(value).forEach(function(childKey) {
                    renderNode(childKey, value[childKey], level + 1);
                });
                
                html += '</div></div>';
            } else {
                html += `<div class="tpak-tree-node ${nodeClass}" data-node-id="${nodeId}" data-key="${key}">`;
                html += `<span class="tpak-node-key">${indent}${key}:</span>`;
                html += `<span class="tpak-node-value" data-value="${escapeHtml(String(value))}">${escapeHtml(String(value))}</span>`;
                html += '</div>';
            }
        }
        
        Object.keys(data).forEach(function(key) {
            renderNode(key, data[key]);
        });
        
        html += '</div>';
        container.html(html);
    }
    
    function initializeDiffHighlighting() {
        // Diff highlighting using pure JavaScript
        window.diffHighlight = {
            mode: 'word',
            highlightMode: 'all',
            
            setMode: function(mode) {
                this.mode = mode;
                this.applyHighlighting();
            },
            
            setHighlightMode: function(mode) {
                this.highlightMode = mode;
                this.applyHighlighting();
            },
            
            applyHighlighting: function() {
                $('.tpak-node-value').each(function() {
                    const originalValue = $(this).data('value');
                    const verifiedValue = getVerifiedValue($(this));
                    
                    if (originalValue !== verifiedValue) {
                        const diff = this.calculateDiff(originalValue, verifiedValue);
                        $(this).html(diff);
                    }
                });
            },
            
            calculateDiff: function(original, verified) {
                if (this.mode === 'word') {
                    return this.wordDiff(original, verified);
                } else if (this.mode === 'line') {
                    return this.lineDiff(original, verified);
                } else {
                    return this.characterDiff(original, verified);
                }
            },
            
            wordDiff: function(original, verified) {
                const originalWords = original.split(/\s+/);
                const verifiedWords = verified.split(/\s+/);
                let result = '';
                
                let i = 0, j = 0;
                while (i < originalWords.length || j < verifiedWords.length) {
                    if (i < originalWords.length && j < verifiedWords.length && originalWords[i] === verifiedWords[j]) {
                        result += '<span class="diff-unchanged">' + escapeHtml(originalWords[i]) + '</span> ';
                        i++; j++;
                    } else {
                        if (i < originalWords.length) {
                            result += '<span class="diff-deleted">' + escapeHtml(originalWords[i]) + '</span> ';
                            i++;
                        }
                        if (j < verifiedWords.length) {
                            result += '<span class="diff-added">' + escapeHtml(verifiedWords[j]) + '</span> ';
                            j++;
                        }
                    }
                }
                
                return result;
            },
            
            lineDiff: function(original, verified) {
                const originalLines = original.split('\n');
                const verifiedLines = verified.split('\n');
                let result = '';
                
                originalLines.forEach(function(line, index) {
                    if (index < verifiedLines.length && line === verifiedLines[index]) {
                        result += '<span class="diff-unchanged">' + escapeHtml(line) + '</span><br>';
                    } else {
                        result += '<span class="diff-deleted">' + escapeHtml(line) + '</span><br>';
                        if (index < verifiedLines.length) {
                            result += '<span class="diff-added">' + escapeHtml(verifiedLines[index]) + '</span><br>';
                        }
                    }
                });
                
                return result;
            },
            
            characterDiff: function(original, verified) {
                let result = '';
                const maxLength = Math.max(original.length, verified.length);
                
                for (let i = 0; i < maxLength; i++) {
                    if (i < original.length && i < verified.length && original[i] === verified[i]) {
                        result += '<span class="diff-unchanged">' + escapeHtml(original[i]) + '</span>';
                    } else {
                        if (i < original.length) {
                            result += '<span class="diff-deleted">' + escapeHtml(original[i]) + '</span>';
                        }
                        if (i < verified.length) {
                            result += '<span class="diff-added">' + escapeHtml(verified[i]) + '</span>';
                        }
                    }
                }
                
                return result;
            }
        };
    }
    
    function getVerifiedValue(originalElement) {
        const key = originalElement.closest('.tpak-tree-node').data('key');
        const verifiedElement = $(`#verified-data .tpak-tree-node[data-key="${key}"] .tpak-node-value`);
        return verifiedElement.data('value') || '';
    }
    
    function initializeInlineComments() {
        // Allow users to add inline comments
        $(document).on('click', '.tpak-node-value', function() {
            const nodeId = $(this).closest('.tpak-tree-node').data('node-id');
            showInlineCommentDialog(nodeId, $(this).data('value'));
        });
    }
    
    function showInlineCommentDialog(nodeId, value) {
        const comment = prompt('<?php _e('Enter your comment for this value:', 'tpak-dq-system'); ?>', '');
        if (comment) {
            addInlineComment(nodeId, value, comment);
        }
    }
    
    function addInlineComment(nodeId, value, comment) {
        const commentData = {
            node_id: nodeId,
            value: value,
            comment: comment,
            user_id: <?php echo get_current_user_id(); ?>,
            timestamp: new Date().toISOString()
        };
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_add_inline_comment',
                comment_data: commentData,
                response_id: '<?php echo esc_js($response_id); ?>',
                batch_id: <?php echo $batch_id; ?>,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e('Comment added successfully.', 'tpak-dq-system'); ?>', 'success');
                    displayInlineComment(nodeId, commentData);
                } else {
                    showNotification('<?php _e('Failed to add comment.', 'tpak-dq-system'); ?>', 'error');
                }
            }
        });
    }
    
    function displayInlineComment(nodeId, commentData) {
        const commentHtml = `
            <div class="tpak-inline-comment" data-node-id="${nodeId}">
                <div class="tpak-comment-bubble">
                    <div class="tpak-comment-content">${escapeHtml(commentData.comment)}</div>
                    <div class="tpak-comment-meta">
                        <span class="tpak-comment-author">${commentData.user_name}</span>
                        <span class="tpak-comment-time">${new Date(commentData.timestamp).toLocaleString()}</span>
                    </div>
                </div>
            </div>
        `;
        
        $(`[data-node-id="${nodeId}"]`).append(commentHtml);
    }
    
    // Control event handlers
    $('#diff-mode').on('change', function() {
        window.diffHighlight.setMode($(this).val());
    });
    
    $('#highlight-mode').on('change', function() {
        window.diffHighlight.setHighlightMode($(this).val());
    });
    
    $('#toggle-comments').on('click', function() {
        $('.tpak-inline-comment').toggle();
    });
    
    // Action button handlers
    $('#approve-data').on('click', function() {
        performAction('approve');
    });
    
    $('#reject-data').on('click', function() {
        performAction('reject');
    });
    
    $('#request-revision').on('click', function() {
        performAction('request_revision');
    });
    
    $('#save-draft').on('click', function() {
        saveDraft();
    });
    
    function performAction(action) {
        const comment = $('#new-comment').val();
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_perform_verification_action',
                verification_action: action,
                response_id: '<?php echo esc_js($response_id); ?>',
                batch_id: <?php echo $batch_id; ?>,
                comment: comment,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=tpak-dq-verification-queue'); ?>';
                    }, 2000);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    }
    
    function saveDraft() {
        const comment = $('#new-comment').val();
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_save_verification_draft',
                response_id: '<?php echo esc_js($response_id); ?>',
                batch_id: <?php echo $batch_id; ?>,
                comment: comment,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e('Draft saved successfully.', 'tpak-dq-system'); ?>', 'success');
                } else {
                    showNotification('<?php _e('Failed to save draft.', 'tpak-dq-system'); ?>', 'error');
                }
            }
        });
    }
    
    function showNotification(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script> 