<?php
/**
 * Report Generator Class สำหรับ TPAK DQ System v3
 * 
 * จัดการการสร้างรายงานคุณภาพข้อมูลและสถิติต่างๆ
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Report_Generator {
    
    /**
     * Core instance
     */
    private $core;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->core = TPAK_DQ_Core::get_instance();
    }
    
    /**
     * สร้างรายงานอัตโนมัติ
     */
    public function generate_auto_reports() {
        $reports = array();
        
        // รายงานสรุปประจำวัน
        if ($this->should_generate_daily_report()) {
            $reports[] = $this->generate_daily_summary_report();
        }
        
        // รายงานสรุปประจำสัปดาห์
        if ($this->should_generate_weekly_report()) {
            $reports[] = $this->generate_weekly_summary_report();
        }
        
        // รายงานสรุปประจำเดือน
        if ($this->should_generate_monthly_report()) {
            $reports[] = $this->generate_monthly_summary_report();
        }
        
        return $reports;
    }
    
    /**
     * รับรายงานคุณภาพข้อมูล
     */
    public function get_quality_report($args = array()) {
        $defaults = array(
            'questionnaire_id' => 0,
            'period' => 'month',
            'start_date' => null,
            'end_date' => null,
            'check_types' => array(),
            'status' => array('passed', 'failed')
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // กำหนดช่วงเวลา
        if ($args['start_date'] && $args['end_date']) {
            $start_date = $args['start_date'];
            $end_date = $args['end_date'];
        } else {
            $date_range = $this->get_date_range($args['period']);
            $start_date = $date_range['start'];
            $end_date = $date_range['end'];
        }
        
        // รับข้อมูลการตรวจสอบ
        $check_results = $this->get_check_results($args['questionnaire_id'], $start_date, $end_date, $args['check_types'], $args['status']);
        
        // คำนวณสถิติ
        $statistics = $this->calculate_quality_statistics($check_results);
        
        // สร้างกราฟข้อมูล
        $charts = $this->generate_quality_charts($check_results, $start_date, $end_date);
        
        return array(
            'success' => true,
            'data' => array(
                'statistics' => $statistics,
                'charts' => $charts,
                'check_results' => $check_results,
                'period' => array(
                    'start_date' => $start_date,
                    'end_date' => $end_date
                )
            )
        );
    }
    
    /**
     * สร้างรายงานสรุปประจำวัน
     */
    private function generate_daily_summary_report() {
        $today = current_time('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $report_data = $this->get_quality_report(array(
            'start_date' => $yesterday,
            'end_date' => $today
        ));
        
        $report = array(
            'type' => 'daily_summary',
            'title' => sprintf(__('Daily Quality Report - %s', 'tpak-dq-system'), $today),
            'period' => array(
                'start_date' => $yesterday,
                'end_date' => $today
            ),
            'data' => $report_data['data'],
            'generated_at' => current_time('mysql')
        );
        
        $this->save_report($report);
        $this->send_report_notification($report);
        
        return $report;
    }
    
    /**
     * สร้างรายงานสรุปประจำสัปดาห์
     */
    private function generate_weekly_summary_report() {
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-7 days'));
        
        $report_data = $this->get_quality_report(array(
            'start_date' => $start_date,
            'end_date' => $end_date
        ));
        
        $report = array(
            'type' => 'weekly_summary',
            'title' => sprintf(__('Weekly Quality Report - Week of %s', 'tpak-dq-system'), $start_date),
            'period' => array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ),
            'data' => $report_data['data'],
            'generated_at' => current_time('mysql')
        );
        
        $this->save_report($report);
        $this->send_report_notification($report);
        
        return $report;
    }
    
    /**
     * สร้างรายงานสรุปประจำเดือน
     */
    private function generate_monthly_summary_report() {
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        $report_data = $this->get_quality_report(array(
            'start_date' => $start_date,
            'end_date' => $end_date
        ));
        
        $report = array(
            'type' => 'monthly_summary',
            'title' => sprintf(__('Monthly Quality Report - %s', 'tpak-dq-system'), date('F Y')),
            'period' => array(
                'start_date' => $start_date,
                'end_date' => $end_date
            ),
            'data' => $report_data['data'],
            'generated_at' => current_time('mysql')
        );
        
        $this->save_report($report);
        $this->send_report_notification($report);
        
        return $report;
    }
    
    /**
     * รับผลการตรวจสอบ
     */
    private function get_check_results($questionnaire_id, $start_date, $end_date, $check_types = array(), $status = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_check_results';
        $checks_table = $wpdb->prefix . 'tpak_quality_checks';
        
        $where_conditions = array();
        $where_values = array();
        
        $where_conditions[] = "cr.created_at BETWEEN %s AND %s";
        $where_values[] = $start_date . ' 00:00:00';
        $where_values[] = $end_date . ' 23:59:59';
        
        if ($questionnaire_id > 0) {
            $where_conditions[] = "cr.questionnaire_id = %d";
            $where_values[] = $questionnaire_id;
        }
        
        if (!empty($check_types)) {
            $placeholders = implode(',', array_fill(0, count($check_types), '%s'));
            $where_conditions[] = "c.check_type IN ($placeholders)";
            $where_values = array_merge($where_values, $check_types);
        }
        
        if (!empty($status)) {
            $status_placeholders = implode(',', array_fill(0, count($status), '%s'));
            $where_conditions[] = "cr.result_status IN ($status_placeholders)";
            $where_values = array_merge($where_values, $status);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT cr.*, c.check_type, c.check_config, q.title as questionnaire_title
            FROM {$table_name} cr
            INNER JOIN {$checks_table} c ON cr.check_id = c.id
            INNER JOIN {$wpdb->prefix}tpak_questionnaires q ON cr.questionnaire_id = q.id
            WHERE {$where_clause}
            ORDER BY cr.created_at DESC
        ";
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * คำนวณสถิติคุณภาพข้อมูล
     */
    private function calculate_quality_statistics($check_results) {
        $total_checks = count($check_results);
        $passed_checks = 0;
        $failed_checks = 0;
        $check_types = array();
        $questionnaires = array();
        
        foreach ($check_results as $result) {
            if ($result->result_status === 'passed') {
                $passed_checks++;
            } else {
                $failed_checks++;
            }
            
            // นับตามประเภทการตรวจสอบ
            if (!isset($check_types[$result->check_type])) {
                $check_types[$result->check_type] = array('passed' => 0, 'failed' => 0);
            }
            
            if ($result->result_status === 'passed') {
                $check_types[$result->check_type]['passed']++;
            } else {
                $check_types[$result->check_type]['failed']++;
            }
            
            // นับตามแบบสอบถาม
            if (!isset($questionnaires[$result->questionnaire_id])) {
                $questionnaires[$result->questionnaire_id] = array(
                    'title' => $result->questionnaire_title,
                    'passed' => 0,
                    'failed' => 0
                );
            }
            
            if ($result->result_status === 'passed') {
                $questionnaires[$result->questionnaire_id]['passed']++;
            } else {
                $questionnaires[$result->questionnaire_id]['failed']++;
            }
        }
        
        $pass_rate = $total_checks > 0 ? ($passed_checks / $total_checks) * 100 : 0;
        
        return array(
            'total_checks' => $total_checks,
            'passed_checks' => $passed_checks,
            'failed_checks' => $failed_checks,
            'pass_rate' => round($pass_rate, 2),
            'check_types' => $check_types,
            'questionnaires' => $questionnaires
        );
    }
    
    /**
     * สร้างกราฟข้อมูลคุณภาพ
     */
    private function generate_quality_charts($check_results, $start_date, $end_date) {
        $charts = array();
        
        // กราฟเส้นแสดงแนวโน้มการตรวจสอบ
        $charts['trend'] = $this->generate_trend_chart($check_results, $start_date, $end_date);
        
        // กราฟวงกลมแสดงสัดส่วนประเภทการตรวจสอบ
        $charts['check_types'] = $this->generate_check_types_chart($check_results);
        
        // กราฟแท่งแสดงผลการตรวจสอบตามแบบสอบถาม
        $charts['questionnaires'] = $this->generate_questionnaires_chart($check_results);
        
        return $charts;
    }
    
    /**
     * สร้างกราฟแนวโน้ม
     */
    private function generate_trend_chart($check_results, $start_date, $end_date) {
        $trend_data = array();
        $current_date = $start_date;
        
        while ($current_date <= $end_date) {
            $date_checks = array_filter($check_results, function($result) use ($current_date) {
                return date('Y-m-d', strtotime($result->created_at)) === $current_date;
            });
            
            $passed = 0;
            $failed = 0;
            
            foreach ($date_checks as $check) {
                if ($check->result_status === 'passed') {
                    $passed++;
                } else {
                    $failed++;
                }
            }
            
            $trend_data[] = array(
                'date' => $current_date,
                'passed' => $passed,
                'failed' => $failed,
                'total' => $passed + $failed
            );
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return array(
            'type' => 'line',
            'data' => $trend_data,
            'options' => array(
                'title' => __('Quality Check Trend', 'tpak-dq-system'),
                'x_axis' => 'date',
                'y_axis' => 'count'
            )
        );
    }
    
    /**
     * สร้างกราฟประเภทการตรวจสอบ
     */
    private function generate_check_types_chart($check_results) {
        $check_types_data = array();
        
        foreach ($check_results as $result) {
            if (!isset($check_types_data[$result->check_type])) {
                $check_types_data[$result->check_type] = array('passed' => 0, 'failed' => 0);
            }
            
            if ($result->result_status === 'passed') {
                $check_types_data[$result->check_type]['passed']++;
            } else {
                $check_types_data[$result->check_type]['failed']++;
            }
        }
        
        $chart_data = array();
        foreach ($check_types_data as $type => $counts) {
            $chart_data[] = array(
                'type' => $type,
                'passed' => $counts['passed'],
                'failed' => $counts['failed'],
                'total' => $counts['passed'] + $counts['failed']
            );
        }
        
        return array(
            'type' => 'pie',
            'data' => $chart_data,
            'options' => array(
                'title' => __('Check Types Distribution', 'tpak-dq-system')
            )
        );
    }
    
    /**
     * สร้างกราฟแบบสอบถาม
     */
    private function generate_questionnaires_chart($check_results) {
        $questionnaires_data = array();
        
        foreach ($check_results as $result) {
            if (!isset($questionnaires_data[$result->questionnaire_id])) {
                $questionnaires_data[$result->questionnaire_id] = array(
                    'title' => $result->questionnaire_title,
                    'passed' => 0,
                    'failed' => 0
                );
            }
            
            if ($result->result_status === 'passed') {
                $questionnaires_data[$result->questionnaire_id]['passed']++;
            } else {
                $questionnaires_data[$result->questionnaire_id]['failed']++;
            }
        }
        
        $chart_data = array();
        foreach ($questionnaires_data as $id => $data) {
            $chart_data[] = array(
                'questionnaire_id' => $id,
                'title' => $data['title'],
                'passed' => $data['passed'],
                'failed' => $data['failed'],
                'total' => $data['passed'] + $data['failed']
            );
        }
        
        return array(
            'type' => 'bar',
            'data' => $chart_data,
            'options' => array(
                'title' => __('Quality Results by Questionnaire', 'tpak-dq-system'),
                'x_axis' => 'questionnaire',
                'y_axis' => 'count'
            )
        );
    }
    
    /**
     * รับช่วงวันที่ตามช่วงเวลา
     */
    private function get_date_range($period) {
        $end_date = current_time('Y-m-d');
        
        switch ($period) {
            case 'day':
                $start_date = $end_date;
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'quarter':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'year':
                $start_date = date('Y-m-d', strtotime('-365 days'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        
        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }
    
    /**
     * ตรวจสอบว่าควรสร้างรายงานประจำวันหรือไม่
     */
    private function should_generate_daily_report() {
        $last_daily_report = $this->core->get_setting('last_daily_report');
        $today = current_time('Y-m-d');
        
        if ($last_daily_report !== $today) {
            $this->core->set_setting('last_daily_report', $today);
            return true;
        }
        
        return false;
    }
    
    /**
     * ตรวจสอบว่าควรสร้างรายงานประจำสัปดาห์หรือไม่
     */
    private function should_generate_weekly_report() {
        $last_weekly_report = $this->core->get_setting('last_weekly_report');
        $this_week = date('Y-W');
        
        if ($last_weekly_report !== $this_week) {
            $this->core->set_setting('last_weekly_report', $this_week);
            return true;
        }
        
        return false;
    }
    
    /**
     * ตรวจสอบว่าควรสร้างรายงานประจำเดือนหรือไม่
     */
    private function should_generate_monthly_report() {
        $last_monthly_report = $this->core->get_setting('last_monthly_report');
        $this_month = date('Y-m');
        
        if ($last_monthly_report !== $this_month) {
            $this->core->set_setting('last_monthly_report', $this_month);
            return true;
        }
        
        return false;
    }
    
    /**
     * บันทึกรายงาน
     */
    private function save_report($report) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_reports';
        
        $wpdb->insert(
            $table_name,
            array(
                'report_type' => $report['type'],
                'title' => $report['title'],
                'data' => json_encode($report['data']),
                'period_start' => $report['period']['start_date'],
                'period_end' => $report['period']['end_date'],
                'generated_at' => $report['generated_at']
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * ส่งการแจ้งเตือนรายงาน
     */
    private function send_report_notification($report) {
        $notification_email = $this->core->get_setting('notification_email');
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = sprintf(__('TPAK DQ System - %s', 'tpak-dq-system'), $report['title']);
        
        $message = $this->generate_report_email_content($report);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($notification_email, $subject, $message, $headers);
    }
    
    /**
     * สร้างเนื้อหาอีเมลรายงาน
     */
    private function generate_report_email_content($report) {
        $data = $report['data'];
        $statistics = $data['statistics'];
        
        $content = '<html><body>';
        $content .= '<h2>' . $report['title'] . '</h2>';
        $content .= '<p><strong>' . __('Period:', 'tpak-dq-system') . '</strong> ' . $report['period']['start_date'] . ' - ' . $report['period']['end_date'] . '</p>';
        
        $content .= '<h3>' . __('Summary', 'tpak-dq-system') . '</h3>';
        $content .= '<ul>';
        $content .= '<li>' . __('Total Checks:', 'tpak-dq-system') . ' ' . $statistics['total_checks'] . '</li>';
        $content .= '<li>' . __('Passed:', 'tpak-dq-system') . ' ' . $statistics['passed_checks'] . '</li>';
        $content .= '<li>' . __('Failed:', 'tpak-dq-system') . ' ' . $statistics['failed_checks'] . '</li>';
        $content .= '<li>' . __('Pass Rate:', 'tpak-dq-system') . ' ' . $statistics['pass_rate'] . '%</li>';
        $content .= '</ul>';
        
        $content .= '<p>' . __('This report was automatically generated by TPAK DQ System.', 'tpak-dq-system') . '</p>';
        $content .= '</body></html>';
        
        return $content;
    }
} 