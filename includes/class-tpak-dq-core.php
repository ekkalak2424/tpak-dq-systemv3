<?php
/**
 * Core Class สำหรับ TPAK DQ System v3
 * 
 * จัดการฟังก์ชันหลักของระบบ รวมถึงการเชื่อมต่อ LimeSurvey API,
 * การจัดการแบบสอบถาม และการตรวจสอบคุณภาพข้อมูล
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Core {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * LimeSurvey API instance
     */
    private $limesurvey_api;
    
    /**
     * Questionnaire Manager instance
     */
    private $questionnaire_manager;
    
    /**
     * Data Quality Checker instance
     */
    private $quality_checker;
    
    /**
     * Report Generator instance
     */
    private $report_generator;
    
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
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * เริ่มต้น components
     */
    private function init_components() {
        $this->limesurvey_api = new TPAK_LimeSurvey_API();
        $this->questionnaire_manager = new TPAK_Questionnaire_Manager();
        $this->quality_checker = new TPAK_Data_Quality_Checker();
        $this->report_generator = new TPAK_Report_Generator();
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        // Cron jobs สำหรับการ sync ข้อมูล
        add_action('tpak_dq_sync_questionnaires', array($this, 'sync_questionnaires'));
        add_action('tpak_dq_run_quality_checks', array($this, 'run_quality_checks'));
        add_action('tpak_dq_generate_reports', array($this, 'generate_reports'));
        
        // AJAX handlers
        add_action('wp_ajax_tpak_dq_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_tpak_dq_sync_single_questionnaire', array($this, 'ajax_sync_single_questionnaire'));
        add_action('wp_ajax_tpak_dq_run_quality_check', array($this, 'ajax_run_quality_check'));
        
        // Shortcodes
        add_shortcode('tpak_questionnaire_list', array($this, 'shortcode_questionnaire_list'));
        add_shortcode('tpak_quality_report', array($this, 'shortcode_quality_report'));
    }
    
    /**
     * รับ LimeSurvey API instance
     */
    public function get_limesurvey_api() {
        return $this->limesurvey_api;
    }
    
    /**
     * รับ Questionnaire Manager instance
     */
    public function get_questionnaire_manager() {
        return $this->questionnaire_manager;
    }
    
    /**
     * รับ Quality Checker instance
     */
    public function get_quality_checker() {
        return $this->quality_checker;
    }
    
    /**
     * รับ Report Generator instance
     */
    public function get_report_generator() {
        return $this->report_generator;
    }
    
    /**
     * Sync แบบสอบถามจาก LimeSurvey
     */
    public function sync_questionnaires() {
        try {
            $questionnaires = $this->limesurvey_api->get_surveys();
            $this->questionnaire_manager->sync_questionnaires($questionnaires);
            
            // บันทึก log
            $this->log_activity('questionnaire_sync', 'Synced ' . count($questionnaires) . ' questionnaires from LimeSurvey');
            
            return true;
        } catch (Exception $e) {
            $this->log_activity('questionnaire_sync_error', 'Error syncing questionnaires: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * รันการตรวจสอบคุณภาพข้อมูล
     */
    public function run_quality_checks() {
        try {
            $active_questionnaires = $this->questionnaire_manager->get_active_questionnaires();
            $total_checks = 0;
            
            foreach ($active_questionnaires as $questionnaire) {
                $checks = $this->quality_checker->run_checks_for_questionnaire($questionnaire->id);
                $total_checks += count($checks);
            }
            
            // บันทึก log
            $this->log_activity('quality_check_run', "Ran quality checks for {$total_checks} responses");
            
            return true;
        } catch (Exception $e) {
            $this->log_activity('quality_check_error', 'Error running quality checks: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * สร้างรายงานอัตโนมัติ
     */
    public function generate_reports() {
        try {
            $reports = $this->report_generator->generate_auto_reports();
            
            // บันทึก log
            $this->log_activity('report_generation', 'Generated ' . count($reports) . ' auto reports');
            
            return true;
        } catch (Exception $e) {
            $this->log_activity('report_generation_error', 'Error generating reports: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * AJAX: ทดสอบการเชื่อมต่อ LimeSurvey
     */
    public function ajax_test_connection() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $result = $this->limesurvey_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Sync แบบสอบถามเดียว
     */
    public function ajax_sync_single_questionnaire() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $questionnaire_id = intval($_POST['questionnaire_id']);
        $result = $this->questionnaire_manager->sync_single_questionnaire($questionnaire_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: รันการตรวจสอบคุณภาพข้อมูล
     */
    public function ajax_run_quality_check() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $questionnaire_id = intval($_POST['questionnaire_id']);
        $result = $this->quality_checker->run_checks_for_questionnaire($questionnaire_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Shortcode: แสดงรายการแบบสอบถาม
     */
    public function shortcode_questionnaire_list($atts) {
        $atts = shortcode_atts(array(
            'status' => 'active',
            'limit' => 10,
            'show_description' => true
        ), $atts);
        
        $questionnaires = $this->questionnaire_manager->get_questionnaires($atts);
        
        ob_start();
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/questionnaire-list.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: แสดงรายงานคุณภาพข้อมูล
     */
    public function shortcode_quality_report($atts) {
        $atts = shortcode_atts(array(
            'questionnaire_id' => 0,
            'period' => 'month',
            'show_chart' => true
        ), $atts);
        
        $report_data = $this->report_generator->get_quality_report($atts);
        
        ob_start();
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/quality-report.php';
        return ob_get_clean();
    }
    
    /**
     * บันทึกกิจกรรมลง log
     */
    public function log_activity($action, $message, $data = array()) {
        $log_entry = array(
            'action' => $action,
            'message' => $message,
            'data' => $data,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip()
        );
        
        // บันทึกลงฐานข้อมูล
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_activity_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'action' => $log_entry['action'],
                'message' => $log_entry['message'],
                'data' => json_encode($log_entry['data']),
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['ip_address'],
                'created_at' => $log_entry['timestamp']
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * รับ IP address ของ client
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * รับการตั้งค่าของปลั๊กอิน
     */
    public function get_setting($key, $default = null) {
        $option_key = 'tpak_dq_' . $key;
        $value = get_option($option_key, $default);
        
        return $value;
    }
    
    /**
     * ตั้งค่าของปลั๊กอิน
     */
    public function set_setting($key, $value) {
        $option_key = 'tpak_dq_' . $key;
        return update_option($option_key, $value);
    }
    
    /**
     * ตรวจสอบว่า LimeSurvey API พร้อมใช้งานหรือไม่
     */
    public function is_limesurvey_ready() {
        $api_url = $this->get_setting('limesurvey_api_url');
        $username = $this->get_setting('limesurvey_username');
        $password = $this->get_setting('limesurvey_password');
        
        return !empty($api_url) && !empty($username) && !empty($password);
    }
    
    /**
     * รับ URL สำหรับ admin page
     */
    public function get_admin_url($page = '') {
        return admin_url('admin.php?page=tpak-dq-system' . ($page ? '&tab=' . $page : ''));
    }
    
    /**
     * รับ URL สำหรับ public page
     */
    public function get_public_url($page = '') {
        return home_url('tpak-dq/' . $page);
    }
} 