<?php
/**
 * TPAK DQ System - Dashboard View
 * 
 * Role-based dashboard with statistics widgets and task queues
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_roles = TPAK_User_Roles::get_instance();
$user_role = $user_roles->get_tpak_role();
$workflow = TPAK_Workflow::get_instance();

// รับสถิติตาม role
$stats = array();
if ($user_roles->can_verify_data()) {
    $stats['pending_verifications'] = $this->get_pending_verifications_count();
    $stats['my_tasks'] = $this->get_my_tasks_count();
}
if ($user_roles->can_approve_data()) {
    $stats['pending_approvals'] = $this->get_pending_approvals_count();
}
if ($user_roles->can_examine_data()) {
    $stats['pending_examinations'] = $this->get_pending_examinations_count();
}
if ($user_roles->is_system_manager()) {
    $stats['total_questionnaires'] = $this->get_total_questionnaires_count();
    $stats['active_quality_checks'] = $this->get_active_quality_checks_count();
    $stats['system_health'] = $this->get_system_health_score();
}
?>

<div class="wrap">
    <h1><?php _e('TPAK DQ System Dashboard', 'tpak-dq-system'); ?></h1>
    
    <!-- Role-based Welcome -->
    <div class="tpak-welcome-section">
        <h2><?php printf(__('Welcome, %s!', 'tpak-dq-system'), wp_get_current_user()->display_name); ?></h2>
        <p><?php printf(__('You are logged in as: %s', 'tpak-dq-system'), ucfirst($user_role)); ?></p>
    </div>

    <!-- Statistics Widgets -->
    <div class="tpak-stats-grid">
        <?php if ($user_roles->is_system_manager()): ?>
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-clipboard"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('Total Questionnaires', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['total_questionnaires'] ?? 0); ?></div>
            </div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-yes-alt"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('Active Quality Checks', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['active_quality_checks'] ?? 0); ?></div>
            </div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-chart-line"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('System Health', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['system_health'] ?? 0); ?>%</div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user_roles->can_verify_data()): ?>
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-clock"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('Pending Verifications', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['pending_verifications'] ?? 0); ?></div>
            </div>
        </div>
        
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-tasks"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('My Tasks', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['my_tasks'] ?? 0); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user_roles->can_approve_data()): ?>
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-thumbs-up"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('Pending Approvals', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['pending_approvals'] ?? 0); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user_roles->can_examine_data()): ?>
        <div class="tpak-stat-card">
            <div class="tpak-stat-icon dashicons dashicons-search"></div>
            <div class="tpak-stat-content">
                <h3><?php _e('Pending Examinations', 'tpak-dq-system'); ?></h3>
                <div class="tpak-stat-number"><?php echo esc_html($stats['pending_examinations'] ?? 0); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Progress Indicators -->
    <div class="tpak-progress-section">
        <h3><?php _e('Workflow Progress', 'tpak-dq-system'); ?></h3>
        <div class="tpak-workflow-progress">
            <div class="tpak-progress-step <?php echo $this->get_current_workflow_step_class(); ?>">
                <div class="tpak-progress-icon">1</div>
                <div class="tpak-progress-label"><?php _e('Pending', 'tpak-dq-system'); ?></div>
            </div>
            <div class="tpak-progress-connector"></div>
            <div class="tpak-progress-step <?php echo $this->get_workflow_step_class('interviewing'); ?>">
                <div class="tpak-progress-icon">2</div>
                <div class="tpak-progress-label"><?php _e('Interviewing', 'tpak-dq-system'); ?></div>
            </div>
            <div class="tpak-progress-connector"></div>
            <div class="tpak-progress-step <?php echo $this->get_workflow_step_class('supervising'); ?>">
                <div class="tpak-progress-icon">3</div>
                <div class="tpak-progress-label"><?php _e('Supervising', 'tpak-dq-system'); ?></div>
            </div>
            <div class="tpak-progress-connector"></div>
            <div class="tpak-progress-step <?php echo $this->get_workflow_step_class('examining'); ?>">
                <div class="tpak-progress-icon">4</div>
                <div class="tpak-progress-label"><?php _e('Examining', 'tpak-dq-system'); ?></div>
            </div>
            <div class="tpak-progress-connector"></div>
            <div class="tpak-progress-step <?php echo $this->get_workflow_step_class('completed'); ?>">
                <div class="tpak-progress-icon">5</div>
                <div class="tpak-progress-label"><?php _e('Completed', 'tpak-dq-system'); ?></div>
            </div>
        </div>
    </div>

    <!-- Task Queue -->
    <div class="tpak-task-queue">
        <h3><?php _e('My Task Queue', 'tpak-dq-system'); ?></h3>
        <div id="my-tasks-list" class="tpak-task-list">
            <div class="tpak-loading"><?php _e('Loading tasks...', 'tpak-dq-system'); ?></div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="tpak-charts-section">
        <div class="tpak-chart-container">
            <h3><?php _e('Quality Trend', 'tpak-dq-system'); ?></h3>
            <div id="quality-trend-chart" class="tpak-chart"></div>
        </div>
        
        <div class="tpak-chart-container">
            <h3><?php _e('Workflow Status', 'tpak-dq-system'); ?></h3>
            <div id="workflow-status-chart" class="tpak-chart"></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="tpak-quick-actions">
        <h3><?php _e('Quick Actions', 'tpak-dq-system'); ?></h3>
        <div class="tpak-action-buttons">
            <?php if ($user_roles->can_verify_data()): ?>
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-verification-queue'); ?>" class="button button-primary">
                <span class="dashicons dashicons-clipboard"></span>
                <?php _e('View Verification Queue', 'tpak-dq-system'); ?>
            </a>
            <?php endif; ?>
            
            <?php if ($user_roles->is_system_manager()): ?>
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-data-import'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Import Data', 'tpak-dq-system'); ?>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-reports'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-chart-area"></span>
                <?php _e('View Reports', 'tpak-dq-system'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-settings'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Settings', 'tpak-dq-system'); ?>
            </a>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load dashboard data
    loadDashboardData();
    
    // Refresh data every 30 seconds
    setInterval(loadDashboardData, 30000);
    
    function loadDashboardData() {
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_load_dashboard_data',
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data.stats);
                    updateTaskQueue(response.data.tasks);
                    updateCharts(response.data.charts);
                }
            }
        });
    }
    
    function updateDashboardStats(stats) {
        // Update statistics numbers
        Object.keys(stats).forEach(function(key) {
            const element = $('.tpak-stat-number[data-stat="' + key + '"]');
            if (element.length) {
                element.text(stats[key]);
            }
        });
    }
    
    function updateTaskQueue(tasks) {
        const taskList = $('#my-tasks-list');
        if (tasks.length === 0) {
            taskList.html('<p class="tpak-no-tasks"><?php _e('No tasks available.', 'tpak-dq-system'); ?></p>');
            return;
        }
        
        let html = '';
        tasks.forEach(function(task) {
            html += `
                <div class="tpak-task-item" data-task-id="${task.id}">
                    <div class="tpak-task-header">
                        <span class="tpak-task-title">${task.title}</span>
                        <span class="tpak-task-priority ${task.priority}">${task.priority}</span>
                    </div>
                    <div class="tpak-task-description">${task.description}</div>
                    <div class="tpak-task-meta">
                        <span class="tpak-task-batch">Batch: ${task.batch_id}</span>
                        <span class="tpak-task-due">Due: ${task.due_date}</span>
                    </div>
                    <div class="tpak-task-actions">
                        <button class="button button-small tpak-task-action" data-action="view" data-task-id="${task.id}">
                            <?php _e('View', 'tpak-dq-system'); ?>
                        </button>
                        <button class="button button-small button-primary tpak-task-action" data-action="approve" data-task-id="${task.id}">
                            <?php _e('Approve', 'tpak-dq-system'); ?>
                        </button>
                        <button class="button button-small button-secondary tpak-task-action" data-action="reject" data-task-id="${task.id}">
                            <?php _e('Reject', 'tpak-dq-system'); ?>
                        </button>
                    </div>
                </div>
            `;
        });
        taskList.html(html);
    }
    
    function updateCharts(charts) {
        // Update quality trend chart
        if (charts.quality_trend && typeof Chart !== 'undefined') {
            updateQualityTrendChart(charts.quality_trend);
        }
        
        // Update workflow status chart
        if (charts.workflow_status && typeof Chart !== 'undefined') {
            updateWorkflowStatusChart(charts.workflow_status);
        }
    }
    
    // Task action handlers
    $(document).on('click', '.tpak-task-action', function() {
        const action = $(this).data('action');
        const taskId = $(this).data('task-id');
        
        if (action === 'view') {
            window.location.href = '<?php echo admin_url('admin.php?page=tpak-dq-verification-queue'); ?>&task_id=' + taskId;
        } else {
            performTaskAction(action, taskId);
        }
    });
    
    function performTaskAction(action, taskId) {
        $.ajax({
            url: tpak_dq_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'tpak_perform_task_action',
                task_action: action,
                task_id: taskId,
                nonce: tpak_dq_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadDashboardData(); // Refresh data
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            }
        });
    }
    
    function showNotification(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});
</script> 