<?php
/**
 * TPAK DQ System - Reports Admin Page
 * 
 * หน้า admin สำหรับการสร้างและจัดการรายงาน
 */

if (!defined('ABSPATH')) {
    exit;
}

$report_generator = TPAK_Report_Generator::get_instance();
$report_templates = $report_generator->get_report_templates();
$user_roles = TPAK_User_Roles::get_instance();
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System - Reports', 'tpak-dq-system'); ?></h1>
    
    <!-- Report Filters -->
    <div class="tpak-report-filters">
        <h2><?php _e('Generate Report', 'tpak-dq-system'); ?></h2>
        
        <form id="report-form" class="tpak-report-form">
            <div class="tpak-form-row">
                <div class="tpak-form-group">
                    <label for="report-type"><?php _e('Report Type:', 'tpak-dq-system'); ?></label>
                    <select id="report-type" name="report_type" required>
                        <option value=""><?php _e('Select Report Type', 'tpak-dq-system'); ?></option>
                        <?php foreach ($report_templates as $type => $template): ?>
                        <option value="<?php echo esc_attr($type); ?>">
                            <?php echo esc_html($template['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="tpak-form-group">
                    <label for="report-format"><?php _e('Export Format:', 'tpak-dq-system'); ?></label>
                    <select id="report-format" name="format">
                        <option value="html"><?php _e('HTML (View)', 'tpak-dq-system'); ?></option>
                        <option value="csv"><?php _e('CSV (Download)', 'tpak-dq-system'); ?></option>
                        <option value="pdf"><?php _e('PDF (Download)', 'tpak-dq-system'); ?></option>
                        <option value="json"><?php _e('JSON (Download)', 'tpak-dq-system'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- Date Filters -->
            <div class="tpak-form-row">
                <div class="tpak-form-group">
                    <label for="date-from"><?php _e('Date From:', 'tpak-dq-system'); ?></label>
                    <input type="date" id="date-from" name="filters[date_from]" />
                </div>
                
                <div class="tpak-form-group">
                    <label for="date-to"><?php _e('Date To:', 'tpak-dq-system'); ?></label>
                    <input type="date" id="date-to" name="filters[date_to]" />
                </div>
            </div>
            
            <!-- Additional Filters -->
            <div class="tpak-form-row" id="additional-filters" style="display: none;">
                <!-- Dynamic filters will be loaded here -->
            </div>
            
            <div class="tpak-form-actions">
                <button type="submit" class="button button-primary" id="generate-report">
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php _e('Generate Report', 'tpak-dq-system'); ?>
                </button>
                
                <button type="button" class="button" id="preview-report">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Preview', 'tpak-dq-system'); ?>
                </button>
                
                <button type="button" class="button" id="clear-filters">
                    <span class="dashicons dashicons-clear"></span>
                    <?php _e('Clear Filters', 'tpak-dq-system'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <!-- Report Description -->
    <div class="tpak-report-description" id="report-description" style="display: none;">
        <div class="tpak-description-content">
            <h3><?php _e('Report Description', 'tpak-dq-system'); ?></h3>
            <p id="description-text"></p>
        </div>
    </div>
    
    <!-- Report Results -->
    <div class="tpak-report-results" id="report-results" style="display: none;">
        <div class="tpak-results-header">
            <h3><?php _e('Report Results', 'tpak-dq-system'); ?></h3>
            <div class="tpak-results-actions">
                <button class="button" id="export-report">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export', 'tpak-dq-system'); ?>
                </button>
                <button class="button" id="print-report">
                    <span class="dashicons dashicons-printer"></span>
                    <?php _e('Print', 'tpak-dq-system'); ?>
                </button>
            </div>
        </div>
        
        <div class="tpak-results-content" id="results-content">
            <!-- Report content will be loaded here -->
        </div>
    </div>
    
    <!-- Scheduled Reports -->
    <div class="tpak-scheduled-reports">
        <h2><?php _e('Scheduled Reports', 'tpak-dq-system'); ?></h2>
        
        <div class="tpak-schedule-list">
            <?php foreach ($report_templates as $type => $template): ?>
            <div class="tpak-schedule-item">
                <div class="tpak-schedule-info">
                    <h4><?php echo esc_html($template['name']); ?></h4>
                    <p><?php echo esc_html($template['description']); ?></p>
                    <span class="tpak-schedule-frequency">
                        <?php printf(__('Frequency: %s', 'tpak-dq-system'), ucfirst($template['schedule'])); ?>
                    </span>
                </div>
                
                <div class="tpak-schedule-actions">
                    <button class="button tpak-run-now" data-report-type="<?php echo esc_attr($type); ?>">
                        <?php _e('Run Now', 'tpak-dq-system'); ?>
                    </button>
                    <button class="button tpak-view-latest" data-report-type="<?php echo esc_attr($type); ?>">
                        <?php _e('View Latest', 'tpak-dq-system'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Report History -->
    <div class="tpak-report-history">
        <h2><?php _e('Report History', 'tpak-dq-system'); ?></h2>
        
        <div class="tpak-history-filters">
            <select id="history-report-type">
                <option value=""><?php _e('All Report Types', 'tpak-dq-system'); ?></option>
                <?php foreach ($report_templates as $type => $template): ?>
                <option value="<?php echo esc_attr($type); ?>">
                    <?php echo esc_html($template['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" id="history-date-from" placeholder="<?php _e('Date From', 'tpak-dq-system'); ?>" />
            <input type="date" id="history-date-to" placeholder="<?php _e('Date To', 'tpak-dq-system'); ?>" />
            
            <button class="button" id="load-history">
                <?php _e('Load History', 'tpak-dq-system'); ?>
            </button>
        </div>
        
        <div class="tpak-history-list" id="history-list">
            <!-- Report history will be loaded here -->
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let currentReportData = null;
    
    // Report type change handler
    $('#report-type').on('change', function() {
        const reportType = $(this).val();
        if (reportType) {
            loadReportDescription(reportType);
            loadAdditionalFilters(reportType);
        } else {
            $('#report-description').hide();
            $('#additional-filters').hide();
        }
    });
    
    // Load report description
    function loadReportDescription(reportType) {
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_get_report_template',
                report_type: reportType,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#description-text').text(response.data.description);
                    $('#report-description').show();
                }
            }
        });
    }
    
    // Load additional filters
    function loadAdditionalFilters(reportType) {
        const filters = {
            'quality_summary': `
                <div class="tpak-form-group">
                    <label for="questionnaire-id"><?php _e('Questionnaire ID:', 'tpak-dq-system'); ?></label>
                    <input type="number" id="questionnaire-id" name="filters[questionnaire_id]" />
                </div>
            `,
            'workflow_status': `
                <div class="tpak-form-group">
                    <label for="workflow-state"><?php _e('Workflow State:', 'tpak-dq-system'); ?></label>
                    <select id="workflow-state" name="filters[state]">
                        <option value=""><?php _e('All States', 'tpak-dq-system'); ?></option>
                        <option value="pending"><?php _e('Pending', 'tpak-dq-system'); ?></option>
                        <option value="interviewing"><?php _e('Interviewing', 'tpak-dq-system'); ?></option>
                        <option value="supervising"><?php _e('Supervising', 'tpak-dq-system'); ?></option>
                        <option value="examining"><?php _e('Examining', 'tpak-dq-system'); ?></option>
                        <option value="completed"><?php _e('Completed', 'tpak-dq-system'); ?></option>
                    </select>
                </div>
                <div class="tpak-form-group">
                    <label for="assigned-user"><?php _e('Assigned User:', 'tpak-dq-system'); ?></label>
                    <select id="assigned-user" name="filters[assigned_user]">
                        <option value=""><?php _e('All Users', 'tpak-dq-system'); ?></option>
                        <!-- Users will be loaded dynamically -->
                    </select>
                </div>
            `,
            'verification_log': `
                <div class="tpak-form-group">
                    <label for="verifier-id"><?php _e('Verifier:', 'tpak-dq-system'); ?></label>
                    <select id="verifier-id" name="filters[verifier_id]">
                        <option value=""><?php _e('All Verifiers', 'tpak-dq-system'); ?></option>
                        <!-- Verifiers will be loaded dynamically -->
                    </select>
                </div>
                <div class="tpak-form-group">
                    <label for="verification-action"><?php _e('Action:', 'tpak-dq-system'); ?></label>
                    <select id="verification-action" name="filters[action]">
                        <option value=""><?php _e('All Actions', 'tpak-dq-system'); ?></option>
                        <option value="approve"><?php _e('Approve', 'tpak-dq-system'); ?></option>
                        <option value="reject"><?php _e('Reject', 'tpak-dq-system'); ?></option>
                        <option value="request_revision"><?php _e('Request Revision', 'tpak-dq-system'); ?></option>
                    </select>
                </div>
            `
        };
        
        if (filters[reportType]) {
            $('#additional-filters').html(filters[reportType]).show();
        } else {
            $('#additional-filters').hide();
        }
    }
    
    // Generate report
    $('#report-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'tpak_generate_report');
        formData.append('nonce', tpak_dq_ajax.nonce);
        
        $('#generate-report').prop('disabled', true).text('<?php _e('Generating...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    if (response.data.format === 'html') {
                        displayReportResults(response.data.data);
                    } else {
                        // For export formats, trigger download
                        downloadReport(response.data);
                    }
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('<?php _e('Failed to generate report', 'tpak-dq-system'); ?>', 'error');
            },
            complete: function() {
                $('#generate-report').prop('disabled', false).html('<span class="dashicons dashicons-chart-area"></span><?php _e('Generate Report', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Preview report
    $('#preview-report').on('click', function() {
        const reportType = $('#report-type').val();
        if (!reportType) {
            showNotification('<?php _e('Please select a report type', 'tpak-dq-system'); ?>', 'warning');
            return;
        }
        
        const formData = new FormData($('#report-form')[0]);
        formData.append('action', 'tpak_generate_report');
        formData.append('format', 'html');
        formData.append('nonce', tpak_dq_ajax.nonce);
        
        $('#preview-report').prop('disabled', true).text('<?php _e('Loading...', 'tpak-dq-system'); ?>');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayReportResults(response.data.data);
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            complete: function() {
                $('#preview-report').prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span><?php _e('Preview', 'tpak-dq-system'); ?>');
            }
        });
    });
    
    // Display report results
    function displayReportResults(html) {
        $('#results-content').html(html);
        $('#report-results').show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#report-results').offset().top - 50
        }, 500);
    }
    
    // Download report
    function downloadReport(data) {
        const formData = new FormData($('#report-form')[0]);
        formData.append('action', 'tpak_export_report');
        formData.append('nonce', tpak_dq_ajax.nonce);
        
        // Create temporary form for download
        const form = $('<form>', {
            method: 'POST',
            action: tpak_dq_ajax.ajax_url,
            target: '_blank'
        });
        
        formData.forEach(function(value, key) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: value
            }));
        });
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    // Export report
    $('#export-report').on('click', function() {
        if (!currentReportData) {
            showNotification('<?php _e('No report data to export', 'tpak-dq-system'); ?>', 'warning');
            return;
        }
        
        const format = $('#report-format').val();
        downloadReport({ format: format });
    });
    
    // Print report
    $('#print-report').on('click', function() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title><?php _e('TPAK DQ System Report', 'tpak-dq-system'); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .report-header { text-align: center; margin-bottom: 20px; }
                        @media print { .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1><?php _e('TPAK DQ System Report', 'tpak-dq-system'); ?></h1>
                        <p><?php echo current_time('Y-m-d H:i:s'); ?></p>
                    </div>
                    ${$('#results-content').html()}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    });
    
    // Clear filters
    $('#clear-filters').on('click', function() {
        $('#report-form')[0].reset();
        $('#report-description').hide();
        $('#additional-filters').hide();
        $('#report-results').hide();
    });
    
    // Run scheduled report now
    $('.tpak-run-now').on('click', function() {
        const reportType = $(this).data('report-type');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_run_scheduled_report',
                report_type: reportType,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e('Report generated successfully', 'tpak-dq-system'); ?>', 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    });
    
    // View latest report
    $('.tpak-view-latest').on('click', function() {
        const reportType = $(this).data('report-type');
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_get_latest_report',
                report_type: reportType,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayReportResults(response.data.html);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    });
    
    // Load report history
    $('#load-history').on('click', function() {
        const reportType = $('#history-report-type').val();
        const dateFrom = $('#history-date-from').val();
        const dateTo = $('#history-date-to').val();
        
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_get_report_history',
                report_type: reportType,
                date_from: dateFrom,
                date_to: dateTo,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayReportHistory(response.data.history);
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    });
    
    // Display report history
    function displayReportHistory(history) {
        let html = '';
        
        if (history.length === 0) {
            html = '<p class="tpak-no-history"><?php _e('No report history found', 'tpak-dq-system'); ?></p>';
        } else {
            html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th><?php _e('Report Type', 'tpak-dq-system'); ?></th>';
            html += '<th><?php _e('Generated', 'tpak-dq-system'); ?></th>';
            html += '<th><?php _e('Records', 'tpak-dq-system'); ?></th>';
            html += '<th><?php _e('Actions', 'tpak-dq-system'); ?></th>';
            html += '</tr></thead><tbody>';
            
            history.forEach(function(report) {
                html += '<tr>';
                html += '<td>' + report.report_name + '</td>';
                html += '<td>' + report.generated_at + '</td>';
                html += '<td>' + report.record_count + '</td>';
                html += '<td>';
                html += '<button class="button button-small view-report" data-report-id="' + report.id + '"><?php _e('View', 'tpak-dq-system'); ?></button>';
                html += '<button class="button button-small download-report" data-report-id="' + report.id + '"><?php _e('Download', 'tpak-dq-system'); ?></button>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
        }
        
        $('#history-list').html(html);
    }
    
    // Show notification
    function showNotification(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : type === 'error' ? 'notice-error' : 'notice-warning';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
</script> 