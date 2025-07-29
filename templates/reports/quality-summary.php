<?php
/**
 * TPAK DQ System - Quality Summary Report Template
 * 
 * Template สำหรับรายงานสรุปคุณภาพข้อมูล
 */

if (!defined('ABSPATH')) {
    exit;
}

$data = $data ?? array();
$total_records = count($data);
$total_questionnaires = count(array_unique(array_column($data, 'questionnaire_id')));
$total_passed = array_sum(array_column($data, 'passed_checks'));
$total_failed = array_sum(array_column($data, 'failed_checks'));
$total_warnings = array_sum(array_column($data, 'warning_checks'));
$avg_score = array_sum(array_column($data, 'avg_score')) / max(1, count(array_filter(array_column($data, 'avg_score'))));
?>

<div class="tpak-report-container">
    <!-- Report Header -->
    <div class="tpak-report-header">
        <h2><?php _e('Quality Summary Report', 'tpak-dq-system'); ?></h2>
        <div class="tpak-report-meta">
            <span><?php printf(__('Generated: %s', 'tpak-dq-system'), current_time('Y-m-d H:i:s')); ?></span>
            <span><?php printf(__('Total Records: %d', 'tpak-dq-system'), $total_records); ?></span>
        </div>
    </div>
    
    <!-- Summary Statistics -->
    <div class="tpak-report-summary">
        <div class="tpak-summary-grid">
            <div class="tpak-summary-card">
                <div class="tpak-summary-icon dashicons dashicons-clipboard"></div>
                <div class="tpak-summary-content">
                    <h3><?php _e('Total Questionnaires', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-summary-number"><?php echo esc_html($total_questionnaires); ?></div>
                </div>
            </div>
            
            <div class="tpak-summary-card">
                <div class="tpak-summary-icon dashicons dashicons-yes-alt"></div>
                <div class="tpak-summary-content">
                    <h3><?php _e('Passed Checks', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-summary-number"><?php echo esc_html($total_passed); ?></div>
                </div>
            </div>
            
            <div class="tpak-summary-card">
                <div class="tpak-summary-icon dashicons dashicons-no-alt"></div>
                <div class="tpak-summary-content">
                    <h3><?php _e('Failed Checks', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-summary-number"><?php echo esc_html($total_failed); ?></div>
                </div>
            </div>
            
            <div class="tpak-summary-card">
                <div class="tpak-summary-icon dashicons dashicons-warning"></div>
                <div class="tpak-summary-content">
                    <h3><?php _e('Warnings', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-summary-number"><?php echo esc_html($total_warnings); ?></div>
                </div>
            </div>
            
            <div class="tpak-summary-card">
                <div class="tpak-summary-icon dashicons dashicons-chart-line"></div>
                <div class="tpak-summary-content">
                    <h3><?php _e('Average Score', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-summary-number"><?php echo number_format($avg_score, 2); ?>%</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quality Score Chart -->
    <div class="tpak-report-chart">
        <h3><?php _e('Quality Score Distribution', 'tpak-dq-system'); ?></h3>
        <div class="tpak-chart-container">
            <canvas id="quality-chart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Detailed Results Table -->
    <div class="tpak-report-table">
        <h3><?php _e('Detailed Results', 'tpak-dq-system'); ?></h3>
        
        <?php if (empty($data)): ?>
        <p class="tpak-no-data"><?php _e('No data available for the selected criteria.', 'tpak-dq-system'); ?></p>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Questionnaire ID', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Title', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Total Checks', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Passed', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Failed', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Warnings', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Score (%)', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Status', 'tpak-dq-system'); ?></th>
                    <th><?php _e('Last Updated', 'tpak-dq-system'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td><?php echo esc_html($row->questionnaire_id); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=tpak-dq-questionnaires&action=view&id=' . $row->questionnaire_id); ?>">
                            <?php echo esc_html($row->questionnaire_title); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($row->total_checks); ?></td>
                    <td>
                        <span class="tpak-status-passed">
                            <?php echo esc_html($row->passed_checks); ?>
                        </span>
                    </td>
                    <td>
                        <span class="tpak-status-failed">
                            <?php echo esc_html($row->failed_checks); ?>
                        </span>
                    </td>
                    <td>
                        <span class="tpak-status-warning">
                            <?php echo esc_html($row->warning_checks); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $score = $row->avg_score ?? 0;
                        $score_class = $score >= 90 ? 'excellent' : ($score >= 70 ? 'good' : ($score >= 50 ? 'fair' : 'poor'));
                        ?>
                        <span class="tpak-score tpak-score-<?php echo $score_class; ?>">
                            <?php echo number_format($score, 1); ?>%
                        </span>
                    </td>
                    <td>
                        <?php
                        $status = 'unknown';
                        if ($row->failed_checks > 0) {
                            $status = 'failed';
                        } elseif ($row->warning_checks > 0) {
                            $status = 'warning';
                        } elseif ($row->passed_checks > 0) {
                            $status = 'passed';
                        }
                        ?>
                        <span class="tpak-status tpak-status-<?php echo $status; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($row->updated_at))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Quality Trends -->
    <div class="tpak-report-trends">
        <h3><?php _e('Quality Trends', 'tpak-dq-system'); ?></h3>
        <div class="tpak-trends-container">
            <div class="tpak-trend-item">
                <h4><?php _e('Pass Rate', 'tpak-dq-system'); ?></h4>
                <div class="tpak-trend-value">
                    <?php 
                    $total_checks = $total_passed + $total_failed + $total_warnings;
                    $pass_rate = $total_checks > 0 ? ($total_passed / $total_checks) * 100 : 0;
                    echo number_format($pass_rate, 1) . '%';
                    ?>
                </div>
            </div>
            
            <div class="tpak-trend-item">
                <h4><?php _e('Fail Rate', 'tpak-dq-system'); ?></h4>
                <div class="tpak-trend-value">
                    <?php 
                    $fail_rate = $total_checks > 0 ? ($total_failed / $total_checks) * 100 : 0;
                    echo number_format($fail_rate, 1) . '%';
                    ?>
                </div>
            </div>
            
            <div class="tpak-trend-item">
                <h4><?php _e('Warning Rate', 'tpak-dq-system'); ?></h4>
                <div class="tpak-trend-value">
                    <?php 
                    $warning_rate = $total_checks > 0 ? ($total_warnings / $total_checks) * 100 : 0;
                    echo number_format($warning_rate, 1) . '%';
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recommendations -->
    <div class="tpak-report-recommendations">
        <h3><?php _e('Recommendations', 'tpak-dq-system'); ?></h3>
        <div class="tpak-recommendations-list">
            <?php if ($total_failed > 0): ?>
            <div class="tpak-recommendation tpak-recommendation-high">
                <span class="dashicons dashicons-warning"></span>
                <div class="tpak-recommendation-content">
                    <h4><?php _e('High Priority', 'tpak-dq-system'); ?></h4>
                    <p><?php printf(__('There are %d failed quality checks that require immediate attention.', 'tpak-dq-system'), $total_failed); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($total_warnings > 0): ?>
            <div class="tpak-recommendation tpak-recommendation-medium">
                <span class="dashicons dashicons-info"></span>
                <div class="tpak-recommendation-content">
                    <h4><?php _e('Medium Priority', 'tpak-dq-system'); ?></h4>
                    <p><?php printf(__('There are %d quality warnings that should be reviewed.', 'tpak-dq-system'), $total_warnings); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($avg_score < 80): ?>
            <div class="tpak-recommendation tpak-recommendation-medium">
                <span class="dashicons dashicons-chart-line"></span>
                <div class="tpak-recommendation-content">
                    <h4><?php _e('Quality Improvement', 'tpak-dq-system'); ?></h4>
                    <p><?php _e('The average quality score is below 80%. Consider reviewing data collection processes.', 'tpak-dq-system'); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($total_passed > 0 && $total_failed == 0 && $total_warnings == 0): ?>
            <div class="tpak-recommendation tpak-recommendation-low">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="tpak-recommendation-content">
                    <h4><?php _e('Excellent Quality', 'tpak-dq-system'); ?></h4>
                    <p><?php _e('All quality checks have passed. Maintain current data quality standards.', 'tpak-dq-system'); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize quality chart
    if (typeof Chart !== 'undefined' && $('#quality-chart').length) {
        const ctx = document.getElementById('quality-chart').getContext('2d');
        
        const chartData = {
            labels: ['<?php _e('Passed', 'tpak-dq-system'); ?>', '<?php _e('Failed', 'tpak-dq-system'); ?>', '<?php _e('Warnings', 'tpak-dq-system'); ?>'],
            datasets: [{
                data: [<?php echo $total_passed; ?>, <?php echo $total_failed; ?>, <?php echo $total_warnings; ?>],
                backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                borderWidth: 1
            }]
        };
        
        new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script> 