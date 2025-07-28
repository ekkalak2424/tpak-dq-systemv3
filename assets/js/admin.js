/**
 * Admin JavaScript สำหรับ TPAK DQ System v3
 */

(function($) {
    'use strict';
    
    // Global variables
    var TPAKDQ = {
        ajax: tpak_dq_ajax,
        strings: tpak_dq_ajax.strings
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TPAKDQ.init();
    });
    
    // Main initialization
    TPAKDQ.init = function() {
        this.initDashboard();
        this.initQuestionnaires();
        this.initQualityChecks();
        this.initReports();
        this.initSettings();
        this.initCharts();
    };
    
    // Dashboard functionality
    TPAKDQ.initDashboard = function() {
        if ($('.tpak-dq-dashboard').length === 0) return;
        
        // Auto refresh dashboard data
        this.refreshDashboardData();
        
        // Setup refresh button
        $('.tpak-dq-refresh-dashboard').on('click', function(e) {
            e.preventDefault();
            TPAKDQ.refreshDashboardData();
        });
    };
    
    TPAKDQ.refreshDashboardData = function() {
        var $dashboard = $('.tpak-dq-dashboard');
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_get_dashboard_data',
                nonce: TPAKDQ.ajax.nonce
            },
            beforeSend: function() {
                $dashboard.addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.updateDashboardStats(response.data);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            },
            complete: function() {
                $dashboard.removeClass('loading');
            }
        });
    };
    
    TPAKDQ.updateDashboardStats = function(data) {
        // Update statistics cards
        if (data.total_questionnaires !== undefined) {
            $('.tpak-dq-stat-total-questionnaires .tpak-dq-stat-number').text(data.total_questionnaires);
        }
        
        if (data.active_questionnaires !== undefined) {
            $('.tpak-dq-stat-active-questionnaires .tpak-dq-stat-number').text(data.active_questionnaires);
        }
        
        if (data.total_checks !== undefined) {
            $('.tpak-dq-stat-total-checks .tpak-dq-stat-number').text(data.total_checks);
        }
        
        if (data.pass_rate !== undefined) {
            $('.tpak-dq-stat-pass-rate .tpak-dq-stat-number').text(data.pass_rate + '%');
        }
        
        // Update charts if they exist
        if (data.charts) {
            TPAKDQ.updateCharts(data.charts);
        }
    };
    
    // Questionnaires functionality
    TPAKDQ.initQuestionnaires = function() {
        if ($('.tpak-dq-questionnaires').length === 0) return;
        
        // Sync questionnaires
        $('.tpak-dq-sync-questionnaires').on('click', function(e) {
            e.preventDefault();
            TPAKDQ.syncQuestionnaires();
        });
        
        // Individual questionnaire actions
        $('.tpak-dq-sync-single').on('click', function(e) {
            e.preventDefault();
            var questionnaireId = $(this).data('id');
            TPAKDQ.syncSingleQuestionnaire(questionnaireId);
        });
        
        $('.tpak-dq-run-checks').on('click', function(e) {
            e.preventDefault();
            var questionnaireId = $(this).data('id');
            TPAKDQ.runQualityChecks(questionnaireId);
        });
        
        $('.tpak-dq-delete-questionnaire').on('click', function(e) {
            e.preventDefault();
            var questionnaireId = $(this).data('id');
            var questionnaireTitle = $(this).data('title');
            TPAKDQ.deleteQuestionnaire(questionnaireId, questionnaireTitle);
        });
    };
    
    TPAKDQ.syncQuestionnaires = function() {
        var $button = $('.tpak-dq-sync-questionnaires');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(TPAKDQ.strings.saving);
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_sync_questionnaires',
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    TPAKDQ.syncSingleQuestionnaire = function(questionnaireId) {
        var $button = $('.tpak-dq-sync-single[data-id="' + questionnaireId + '"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(TPAKDQ.strings.saving);
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_sync_single_questionnaire',
                questionnaire_id: questionnaireId,
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    TPAKDQ.runQualityChecks = function(questionnaireId) {
        var $button = $('.tpak-dq-run-checks[data-id="' + questionnaireId + '"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(TPAKDQ.strings.saving);
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_run_quality_check',
                questionnaire_id: questionnaireId,
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    TPAKDQ.deleteQuestionnaire = function(questionnaireId, questionnaireTitle) {
        if (!confirm(TPAKDQ.strings.confirm_delete)) {
            return;
        }
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_delete_questionnaire',
                questionnaire_id: questionnaireId,
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            }
        });
    };
    
    // Quality Checks functionality
    TPAKDQ.initQualityChecks = function() {
        if ($('.tpak-dq-quality-checks').length === 0) return;
        
        // Add quality check form
        $('.tpak-dq-add-check').on('click', function(e) {
            e.preventDefault();
            TPAKDQ.showAddCheckForm();
        });
        
        // Save quality check
        $('.tpak-dq-save-check').on('click', function(e) {
            e.preventDefault();
            TPAKDQ.saveQualityCheck();
        });
        
        // Delete quality check
        $('.tpak-dq-delete-check').on('click', function(e) {
            e.preventDefault();
            var checkId = $(this).data('id');
            TPAKDQ.deleteQualityCheck(checkId);
        });
        
        // Check type change handler
        $('.tpak-dq-check-type').on('change', function() {
            TPAKDQ.updateCheckConfig($(this).val());
        });
    };
    
    TPAKDQ.showAddCheckForm = function() {
        var $form = $('.tpak-dq-check-form');
        $form.slideDown();
        $('.tpak-dq-add-check').hide();
    };
    
    TPAKDQ.saveQualityCheck = function() {
        var $form = $('.tpak-dq-check-form');
        var formData = $form.serialize();
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=tpak_dq_add_quality_check&nonce=' + TPAKDQ.ajax.nonce,
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            }
        });
    };
    
    TPAKDQ.deleteQualityCheck = function(checkId) {
        if (!confirm(TPAKDQ.strings.confirm_delete)) {
            return;
        }
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_delete_quality_check',
                check_id: checkId,
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            }
        });
    };
    
    TPAKDQ.updateCheckConfig = function(checkType) {
        var $config = $('.tpak-dq-check-config');
        
        // Hide all config sections
        $config.find('.config-section').hide();
        
        // Show relevant config section
        $config.find('.config-' + checkType).show();
    };
    
    // Reports functionality
    TPAKDQ.initReports = function() {
        if ($('.tpak-dq-reports').length === 0) return;
        
        // Export report
        $('.tpak-dq-export-report').on('click', function(e) {
            e.preventDefault();
            var reportId = $(this).data('id');
            var format = $(this).data('format');
            TPAKDQ.exportReport(reportId, format);
        });
        
        // Filter reports
        $('.tpak-dq-filter-form').on('submit', function(e) {
            e.preventDefault();
            TPAKDQ.filterReports();
        });
    };
    
    TPAKDQ.exportReport = function(reportId, format) {
        var $button = $('.tpak-dq-export-report[data-id="' + reportId + '"]');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text(TPAKDQ.strings.saving);
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_export_report',
                report_id: reportId,
                format: format,
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Download file
                    var link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    TPAKDQ.showNotice('success', 'Report exported successfully');
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    TPAKDQ.filterReports = function() {
        var formData = $('.tpak-dq-filter-form').serialize();
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=tpak_dq_filter_reports&nonce=' + TPAKDQ.ajax.nonce,
            success: function(response) {
                if (response.success) {
                    $('.tpak-dq-report-list').html(response.data.html);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            }
        });
    };
    
    // Settings functionality
    TPAKDQ.initSettings = function() {
        if ($('.tpak-dq-settings').length === 0) return;
        
        // Save settings
        $('.tpak-dq-save-settings').on('click', function(e) {
            e.preventDefault();
            TPAKDQ.saveSettings();
        });
        
        // Test connection
        $('.tpak-dq-test-connection').on('click', function(e) {
            e.preventDefault();
            TPAKDQ.testConnection();
        });
    };
    
    TPAKDQ.saveSettings = function() {
        var $form = $('.tpak-dq-settings-form');
        var formData = $form.serialize();
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=tpak_dq_save_settings&nonce=' + TPAKDQ.ajax.nonce,
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            }
        });
    };
    
    TPAKDQ.testConnection = function() {
        var $button = $('.tpak-dq-test-connection');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: TPAKDQ.ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_dq_test_connection',
                nonce: TPAKDQ.ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    TPAKDQ.showNotice('success', response.data);
                } else {
                    TPAKDQ.showNotice('error', response.data);
                }
            },
            error: function() {
                TPAKDQ.showNotice('error', TPAKDQ.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    // Charts functionality
    TPAKDQ.initCharts = function() {
        if ($('.tpak-dq-chart-container').length === 0) return;
        
        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            TPAKDQ.initChartJS();
        }
    };
    
    TPAKDQ.initChartJS = function() {
        $('.tpak-dq-chart-container').each(function() {
            var $container = $(this);
            var chartData = $container.data('chart');
            
            if (chartData) {
                var ctx = $container.find('canvas')[0].getContext('2d');
                new Chart(ctx, chartData);
            }
        });
    };
    
    TPAKDQ.updateCharts = function(chartsData) {
        // Update existing charts with new data
        if (typeof Chart !== 'undefined') {
            // Implementation depends on specific chart requirements
        }
    };
    
    // Utility functions
    TPAKDQ.showNotice = function(type, message) {
        var noticeClass = 'tpak-dq-notice-' + type;
        var $notice = $('<div class="tpak-dq-notice ' + noticeClass + '">' + message + '</div>');
        
        $('.tpak-dq-dashboard, .tpak-dq-questionnaires, .tpak-dq-quality-checks, .tpak-dq-reports, .tpak-dq-settings').prepend($notice);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    };
    
    // Expose to global scope
    window.TPAKDQ = TPAKDQ;
    
})(jQuery); 