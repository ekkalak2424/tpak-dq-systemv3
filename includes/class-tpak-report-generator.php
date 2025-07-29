<?php
/**
 * TPAK DQ System - Report Generator
 * 
 * จัดการการสร้างรายงานและการ export ข้อมูล
 * Phase 8: Reporting & Export (1 สัปดาห์)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Report_Generator {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * Report templates
     */
    private $report_templates = array();
    
    /**
     * รับ instance เดียวของ class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * รับ instance เดียวของ class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_report_templates();
        $this->init_hooks();
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_tpak_generate_report', array($this, 'handle_generate_report'));
        add_action('wp_ajax_tpak_export_report', array($this, 'handle_export_report'));
        add_action('wp_ajax_tpak_get_report_data', array($this, 'handle_get_report_data'));
        add_action('tpak_generate_auto_reports', array($this, 'generate_auto_reports'));
    }
    
    /**
     * เริ่มต้น report templates
     */
    private function init_report_templates() {
        $this->report_templates = array(
            'quality_summary' => array(
                'name' => __('Quality Summary Report', 'tpak-dq-system'),
                'description' => __('Summary of data quality checks and results', 'tpak-dq-system'),
                'sql_query' => 'quality_summary_query',
                'export_formats' => array('csv', 'pdf', 'json'),
                'schedule' => 'daily'
            ),
            'workflow_status' => array(
                'name' => __('Workflow Status Report', 'tpak-dq-system'),
                'description' => __('Current status of all workflow batches', 'tpak-dq-system'),
                'sql_query' => 'workflow_status_query',
                'export_formats' => array('csv', 'pdf', 'json'),
                'schedule' => 'daily'
            ),
            'verification_log' => array(
                'name' => __('Verification Log Report', 'tpak-dq-system'),
                'description' => __('Detailed log of all verification activities', 'tpak-dq-system'),
                'sql_query' => 'verification_log_query',
                'export_formats' => array('csv', 'pdf', 'json'),
                'schedule' => 'weekly'
            ),
            'system_performance' => array(
                'name' => __('System Performance Report', 'tpak-dq-system'),
                'description' => __('System performance and health metrics', 'tpak-dq-system'),
                'sql_query' => 'system_performance_query',
                'export_formats' => array('csv', 'pdf', 'json'),
                'schedule' => 'weekly'
            ),
            'user_activity' => array(
                'name' => __('User Activity Report', 'tpak-dq-system'),
                'description' => __('User activity and productivity metrics', 'tpak-dq-system'),
                'sql_query' => 'user_activity_query',
                'export_formats' => array('csv', 'pdf', 'json'),
                'schedule' => 'monthly'
            )
        );
    }
    
    /**
     * สร้างรายงาน
     */
    public function generate_report($report_type, $filters = array(), $format = 'html') {
        if (!isset($this->report_templates[$report_type])) {
            return array(
                'success' => false,
                'message' => __('Invalid report type', 'tpak-dq-system')
            );
        }
        
        $template = $this->report_templates[$report_type];
        $data = $this->get_report_data($report_type, $filters);
        
        if (!$data['success']) {
            return $data;
        }
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data['data'], $template['name']);
            case 'pdf':
                return $this->export_to_pdf($data['data'], $template);
            case 'json':
                return $this->export_to_json($data['data']);
            default:
                return $this->generate_html_report($data['data'], $template);
        }
    }
    
    /**
     * รับข้อมูลรายงาน
     */
    private function get_report_data($report_type, $filters = array()) {
        $method_name = $this->report_templates[$report_type]['sql_query'];
        
        if (!method_exists($this, $method_name)) {
            return array(
                'success' => false,
                'message' => __('Report query method not found', 'tpak-dq-system')
            );
        }
        
        try {
            $data = $this->$method_name($filters);
            return array(
                'success' => true,
                'data' => $data
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Quality Summary Query
     */
    private function quality_summary_query($filters = array()) {
        global $wpdb;
        
        $table_checks = $wpdb->prefix . 'tpak_quality_checks';
        $table_results = $wpdb->prefix . 'tpak_check_results';
        $table_questionnaires = $wpdb->prefix . 'tpak_questionnaires';
        
        $where_clause = "1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $where_clause .= " AND q.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clause .= " AND q.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['questionnaire_id'])) {
            $where_clause .= " AND q.questionnaire_id = %d";
            $params[] = $filters['questionnaire_id'];
        }
        
        $query = $wpdb->prepare(
            "SELECT 
                q.questionnaire_id,
                q.title as questionnaire_title,
                COUNT(cr.id) as total_checks,
                SUM(CASE WHEN cr.status = 'passed' THEN 1 ELSE 0 END) as passed_checks,
                SUM(CASE WHEN cr.status = 'failed' THEN 1 ELSE 0 END) as failed_checks,
                SUM(CASE WHEN cr.status = 'warning' THEN 1 ELSE 0 END) as warning_checks,
                AVG(CASE WHEN cr.score IS NOT NULL THEN cr.score ELSE NULL END) as avg_score,
                q.created_at,
                q.updated_at
            FROM $table_questionnaires q
            LEFT JOIN $table_checks c ON q.questionnaire_id = c.questionnaire_id
            LEFT JOIN $table_results cr ON c.id = cr.check_id
            WHERE $where_clause
            GROUP BY q.questionnaire_id
            ORDER BY q.created_at DESC",
            $params
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Workflow Status Query
     */
    private function workflow_status_query($filters = array()) {
        global $wpdb;
        
        $table_batches = $wpdb->prefix . 'tpak_verification_batches';
        $table_workflow = $wpdb->prefix . 'tpak_workflow_status';
        $table_logs = $wpdb->prefix . 'tpak_verification_logs';
        
        $where_clause = "1=1";
        $params = array();
        
        if (!empty($filters['state'])) {
            $where_clause .= " AND ws.current_state = %s";
            $params[] = $filters['state'];
        }
        
        if (!empty($filters['assigned_user'])) {
            $where_clause .= " AND ws.assigned_user = %d";
            $params[] = $filters['assigned_user'];
        }
        
        $query = $wpdb->prepare(
            "SELECT 
                vb.id as batch_id,
                vb.batch_name,
                vb.questionnaire_id,
                vb.total_responses,
                vb.verified_responses,
                ws.current_state,
                ws.previous_state,
                ws.assigned_role,
                ws.assigned_user,
                ws.state_entered_at,
                ws.workflow_completed,
                COUNT(vl.id) as log_count,
                vb.created_at,
                vb.updated_at
            FROM $table_batches vb
            LEFT JOIN $table_workflow ws ON vb.id = ws.batch_id
            LEFT JOIN $table_logs vl ON vb.id = vl.batch_id
            WHERE $where_clause
            GROUP BY vb.id
            ORDER BY vb.created_at DESC",
            $params
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Verification Log Query
     */
    private function verification_log_query($filters = array()) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'tpak_verification_logs';
        $table_batches = $wpdb->prefix . 'tpak_verification_batches';
        
        $where_clause = "1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $where_clause .= " AND vl.verification_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clause .= " AND vl.verification_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['verifier_id'])) {
            $where_clause .= " AND vl.verifier_id = %d";
            $params[] = $filters['verifier_id'];
        }
        
        if (!empty($filters['action'])) {
            $where_clause .= " AND vl.verification_action = %s";
            $params[] = $filters['action'];
        }
        
        $query = $wpdb->prepare(
            "SELECT 
                vl.id,
                vl.batch_id,
                vl.response_id,
                vl.verifier_id,
                vl.verification_action,
                vl.verification_status,
                vl.verification_notes,
                vl.verification_date,
                vb.batch_name,
                u.display_name as verifier_name
            FROM $table_logs vl
            LEFT JOIN $table_batches vb ON vl.batch_id = vb.id
            LEFT JOIN {$wpdb->users} u ON vl.verifier_id = u.ID
            WHERE $where_clause
            ORDER BY vl.verification_date DESC",
            $params
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * System Performance Query
     */
    private function system_performance_query($filters = array()) {
        global $wpdb;
        
        $table_questionnaires = $wpdb->prefix . 'tpak_questionnaires';
        $table_batches = $wpdb->prefix . 'tpak_verification_batches';
        $table_workflow = $wpdb->prefix . 'tpak_workflow_status';
        $table_logs = $wpdb->prefix . 'tpak_verification_logs';
        
        $data = array();
        
        // System statistics
        $data['total_questionnaires'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_questionnaires");
        $data['active_batches'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_batches WHERE status = 'active'");
        $data['completed_workflows'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_workflow WHERE workflow_completed = 1");
        $data['total_verifications'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_logs");
        
        // Performance metrics
        $data['avg_verification_time'] = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) 
             FROM $table_batches WHERE status = 'completed'"
        );
        
        $data['daily_verifications'] = $wpdb->get_results(
            "SELECT DATE(verification_date) as date, COUNT(*) as count 
             FROM $table_logs 
             WHERE verification_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(verification_date)
             ORDER BY date DESC"
        );
        
        return $data;
    }
    
    /**
     * User Activity Query
     */
    private function user_activity_query($filters = array()) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'tpak_verification_logs';
        $table_workflow = $wpdb->prefix . 'tpak_workflow_status';
        
        $where_clause = "1=1";
        $params = array();
        
        if (!empty($filters['date_from'])) {
            $where_clause .= " AND vl.verification_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clause .= " AND vl.verification_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        $query = $wpdb->prepare(
            "SELECT 
                vl.verifier_id,
                u.display_name,
                u.user_email,
                COUNT(vl.id) as total_verifications,
                SUM(CASE WHEN vl.verification_action = 'approve' THEN 1 ELSE 0 END) as approvals,
                SUM(CASE WHEN vl.verification_action = 'reject' THEN 1 ELSE 0 END) as rejections,
                SUM(CASE WHEN vl.verification_action = 'request_revision' THEN 1 ELSE 0 END) as revisions,
                AVG(CASE WHEN vl.verification_status = 'completed' THEN 1 ELSE 0 END) as completion_rate,
                MIN(vl.verification_date) as first_activity,
                MAX(vl.verification_date) as last_activity
            FROM $table_logs vl
            LEFT JOIN {$wpdb->users} u ON vl.verifier_id = u.ID
            WHERE $where_clause
            GROUP BY vl.verifier_id
            ORDER BY total_verifications DESC",
            $params
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * สร้าง HTML report
     */
    private function generate_html_report($data, $template) {
        ob_start();
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'templates/reports/' . $template['name'] . '.php';
        $html = ob_get_clean();
        
        return array(
            'success' => true,
            'data' => $html,
            'format' => 'html'
        );
    }
    
    /**
     * Export เป็น CSV
     */
    private function export_to_csv($data, $report_name) {
        if (empty($data)) {
            return array(
                'success' => false,
                'message' => __('No data to export', 'tpak-dq-system')
            );
        }
        
        $filename = sanitize_file_name($report_name . '_' . date('Y-m-d_H-i-s') . '.csv');
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write headers
        if (!empty($data)) {
            fputcsv($output, array_keys((array)$data[0]));
        }
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, (array)$row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export เป็น PDF
     */
    private function export_to_pdf($data, $template) {
        // ตรวจสอบว่า TCPDF library มีอยู่หรือไม่
        if (!class_exists('TCPDF')) {
            return array(
                'success' => false,
                'message' => __('TCPDF library not found. Please install TCPDF for PDF generation.', 'tpak-dq-system')
            );
        }
        
        try {
            // สร้าง PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // ตั้งค่า document information
            $pdf->SetCreator('TPAK DQ System');
            $pdf->SetAuthor('TPAK DQ System v3');
            $pdf->SetTitle($template['name']);
            $pdf->SetSubject('Data Quality Report');
            
            // ตั้งค่า margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // ตั้งค่า auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // ตั้งค่า font
            $pdf->SetFont('helvetica', '', 10);
            
            // เพิ่มหน้าแรก
            $pdf->AddPage();
            
            // สร้าง PDF content
            $this->generate_pdf_content($pdf, $data, $template);
            
            // ส่ง PDF
            $filename = sanitize_file_name($template['name'] . '_' . date('Y-m-d_H-i-s') . '.pdf');
            $pdf->Output($filename, 'D');
            exit;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * สร้าง PDF content
     */
    private function generate_pdf_content($pdf, $data, $template) {
        // Header
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $template['name'], 0, 1, 'C');
        $pdf->Ln(5);
        
        // Report info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Generated: ' . current_time('Y-m-d H:i:s'), 0, 1);
        $pdf->Cell(0, 8, 'Total Records: ' . count($data), 0, 1);
        $pdf->Ln(5);
        
        // Data table
        if (!empty($data)) {
            $pdf->SetFont('helvetica', 'B', 10);
            
            // Table headers
            $headers = array_keys((array)$data[0]);
            foreach ($headers as $header) {
                $pdf->Cell(40, 8, $header, 1);
            }
            $pdf->Ln();
            
            // Table data
            $pdf->SetFont('helvetica', '', 9);
            foreach ($data as $row) {
                $row_array = (array)$row;
                foreach ($row_array as $cell) {
                    $pdf->Cell(40, 8, substr($cell, 0, 35), 1);
                }
                $pdf->Ln();
            }
        }
        
        // Footer
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 8, 'Generated by TPAK DQ System v3', 0, 1, 'C');
    }
    
    /**
     * Export เป็น JSON
     */
    private function export_to_json($data) {
        $filename = 'report_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * สร้าง auto reports
     */
    public function generate_auto_reports() {
        $notifications = TPAK_Notifications::get_instance();
        
        foreach ($this->report_templates as $type => $template) {
            if ($template['schedule'] === 'daily') {
                $this->generate_scheduled_report($type, $template);
            }
        }
    }
    
    /**
     * สร้าง scheduled report
     */
    private function generate_scheduled_report($type, $template) {
        $data = $this->get_report_data($type, array());
        
        if ($data['success']) {
            // สร้าง report file
            $report_file = $this->save_report_file($type, $data['data']);
            
            // ส่ง notification
            $notifications = TPAK_Notifications::get_instance();
            $managers = $this->get_system_managers();
            
            foreach ($managers as $manager) {
                $notifications->send_inapp_notification(
                    $manager->ID,
                    'report_ready',
                    sprintf(__('Report Ready - %s', 'tpak-dq-system'), $template['name']),
                    sprintf(__('Daily report "%s" has been generated and is ready for review.', 'tpak-dq-system'), $template['name']),
                    array(
                        'report_type' => $type,
                        'report_name' => $template['name'],
                        'file_path' => $report_file,
                        'generated_at' => current_time('Y-m-d H:i:s')
                    )
                );
            }
        }
    }
    
    /**
     * บันทึก report file
     */
    private function save_report_file($type, $data) {
        $upload_dir = wp_upload_dir();
        $reports_dir = $upload_dir['basedir'] . '/tpak-reports/';
        
        if (!file_exists($reports_dir)) {
            wp_mkdir_p($reports_dir);
        }
        
        $filename = $type . '_' . date('Y-m-d') . '.json';
        $filepath = $reports_dir . $filename;
        
        file_put_contents($filepath, json_encode($data));
        
        return $filepath;
    }
    
    /**
     * รับ system managers
     */
    private function get_system_managers() {
        $user_roles = TPAK_User_Roles::get_instance();
        return $user_roles->get_available_users('tpak_manage_system');
    }
    
    /**
     * จัดการ AJAX generate report
     */
    public function handle_generate_report() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_reports')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $format = sanitize_text_field($_POST['format'] ?? 'html');
        
        $result = $this->generate_report($report_type, $filters, $format);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * จัดการ AJAX export report
     */
    public function handle_export_report() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_reports')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $format = sanitize_text_field($_POST['format']);
        
        $result = $this->generate_report($report_type, $filters, $format);
        
        // สำหรับ export formats จะ exit ไปแล้ว
        wp_send_json_success($result);
    }
    
    /**
     * จัดการ AJAX get report data
     */
    public function handle_get_report_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_reports')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        $result = $this->get_report_data($report_type, $filters);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * รับ report templates
     */
    public function get_report_templates() {
        return $this->report_templates;
    }
    
    /**
     * รับ report template
     */
    public function get_report_template($type) {
        return isset($this->report_templates[$type]) ? $this->report_templates[$type] : null;
    }
} 