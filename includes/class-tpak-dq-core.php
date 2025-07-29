<?php
/**
 * TPAK DQ System - Core Class
 * 
 * จัดการ core functionality และ components ต่างๆ
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
     * Components (lazy loaded)
     */
    private $limesurvey_api = null;
    private $questionnaire_manager = null;
    private $quality_checker = null;
    private $report_generator = null;
    private $user_roles = null;
    private $workflow = null;
    private $notifications = null;
    
    /**
     * Memory management
     */
    private $memory_limit = 256; // MB
    private $initial_memory = 0;
    
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
        // ตรวจสอบ memory limit
        $this->initial_memory = memory_get_usage(true);
        $this->check_memory_limit();
        
        $this->init_hooks();
    }
    
    /**
     * ตรวจสอบ memory limit
     */
    private function check_memory_limit() {
        $current_memory = memory_get_usage(true) / 1024 / 1024; // MB
        $limit = ini_get('memory_limit');
        
        if ($limit !== '-1') {
            $limit_mb = $this->parse_memory_limit($limit);
            if ($current_memory > ($limit_mb * 0.8)) { // 80% of limit
                error_log('TPAK DQ System: Memory usage high - ' . round($current_memory, 2) . 'MB');
            }
        }
    }
    
    /**
     * Parse memory limit string
     */
    private function parse_memory_limit($limit) {
        $unit = strtolower(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);
        
        switch ($unit) {
            case 'k': return $value / 1024;
            case 'm': return $value;
            case 'g': return $value * 1024;
            default: return $value / 1024 / 1024;
        }
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
     * รับ LimeSurvey API instance (lazy loading)
     */
    public function get_limesurvey_api() {
        if ($this->limesurvey_api === null) {
            $this->check_memory_limit();
            $this->limesurvey_api = new TPAK_LimeSurvey_API();
        }
        return $this->limesurvey_api;
    }
    
    /**
     * รับ Questionnaire Manager instance (lazy loading)
     */
    public function get_questionnaire_manager() {
        if ($this->questionnaire_manager === null) {
            $this->check_memory_limit();
            $this->questionnaire_manager = new TPAK_Questionnaire_Manager();
        }
        return $this->questionnaire_manager;
    }
    
    /**
     * รับ Quality Checker instance (lazy loading)
     */
    public function get_quality_checker() {
        if ($this->quality_checker === null) {
            $this->check_memory_limit();
            $this->quality_checker = new TPAK_Data_Quality_Checker();
        }
        return $this->quality_checker;
    }
    
    /**
     * รับ Report Generator instance (lazy loading)
     */
    public function get_report_generator() {
        if ($this->report_generator === null) {
            $this->check_memory_limit();
            $this->report_generator = new TPAK_Report_Generator();
        }
        return $this->report_generator;
    }
    
    /**
     * รับ User Roles instance (lazy loading)
     */
    public function get_user_roles() {
        if ($this->user_roles === null) {
            $this->check_memory_limit();
            $this->user_roles = TPAK_User_Roles::get_instance();
        }
        return $this->user_roles;
    }
    
    /**
     * รับ Workflow instance (lazy loading)
     */
    public function get_workflow() {
        if ($this->workflow === null) {
            $this->check_memory_limit();
            $this->workflow = TPAK_Workflow::get_instance();
        }
        return $this->workflow;
    }
    
    /**
     * รับ Notifications instance (lazy loading)
     */
    public function get_notifications() {
        if ($this->notifications === null) {
            $this->check_memory_limit();
            $this->notifications = TPAK_Notifications::get_instance();
        }
        return $this->notifications;
    }
    
    /**
     * Sync แบบสอบถามจาก LimeSurvey
     */
    public function sync_questionnaires() {
        try {
            $this->check_memory_limit();
            
            $limesurvey_api = $this->get_limesurvey_api();
            $questionnaire_manager = $this->get_questionnaire_manager();
            
            $questionnaires = $limesurvey_api->get_surveys();
            $questionnaire_manager->sync_questionnaires($questionnaires);
            
            // บันทึก log
            $this->log_activity('questionnaire_sync', 'Synced ' . count($questionnaires) . ' questionnaires from LimeSurvey');
            
            // Clear memory
            $this->clear_memory();
            
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
            $this->check_memory_limit();
            
            $questionnaire_manager = $this->get_questionnaire_manager();
            $quality_checker = $this->get_quality_checker();
            
            $active_questionnaires = $questionnaire_manager->get_active_questionnaires();
            $total_checks = 0;
            
            foreach ($active_questionnaires as $questionnaire) {
                $this->check_memory_limit();
                $checks = $quality_checker->run_checks_for_questionnaire($questionnaire->id);
                $total_checks += count($checks);
                
                // Clear memory after each questionnaire
                if ($total_checks % 10 === 0) {
                    $this->clear_memory();
                }
            }
            
            $this->log_activity('quality_checks', 'Ran ' . $total_checks . ' quality checks');
            
            // Clear memory
            $this->clear_memory();
            
            return true;
        } catch (Exception $e) {
            $this->log_activity('quality_checks_error', 'Error running quality checks: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * สร้างรายงาน
     */
    public function generate_reports() {
        try {
            $this->check_memory_limit();
            
            $report_generator = $this->get_report_generator();
            $report_generator->generate_auto_reports();
            
            $this->log_activity('reports_generated', 'Auto reports generated successfully');
            
            // Clear memory
            $this->clear_memory();
            
            return true;
        } catch (Exception $e) {
            $this->log_activity('reports_error', 'Error generating reports: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear memory
     */
    private function clear_memory() {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Force garbage collection
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_dq_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        try {
            $limesurvey_api = $this->get_limesurvey_api();
            $result = $limesurvey_api->test_connection();
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX sync single questionnaire
     */
    public function ajax_sync_single_questionnaire() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_dq_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        try {
            $questionnaire_id = intval($_POST['questionnaire_id']);
            $questionnaire_manager = $this->get_questionnaire_manager();
            $result = $questionnaire_manager->sync_single_questionnaire($questionnaire_id);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX run quality check
     */
    public function ajax_run_quality_check() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_dq_nonce')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        try {
            $questionnaire_id = intval($_POST['questionnaire_id']);
            $quality_checker = $this->get_quality_checker();
            $result = $quality_checker->run_checks_for_questionnaire($questionnaire_id);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Shortcode สำหรับแสดงรายการแบบสอบถาม
     */
    public function shortcode_questionnaire_list($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'status' => 'active'
        ), $atts);
        
        try {
            $questionnaire_manager = $this->get_questionnaire_manager();
            $questionnaires = $questionnaire_manager->get_questionnaires($atts);
            
            ob_start();
            include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/questionnaire-list.php';
            return ob_get_clean();
        } catch (Exception $e) {
            return '<p class="tpak-error">' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * Shortcode สำหรับแสดงรายงานคุณภาพ
     */
    public function shortcode_quality_report($atts) {
        $atts = shortcode_atts(array(
            'questionnaire_id' => 0,
            'format' => 'summary'
        ), $atts);
        
        try {
            $quality_checker = $this->get_quality_checker();
            $report = $quality_checker->get_quality_report($atts['questionnaire_id']);
            
            ob_start();
            include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/quality-report.php';
            return ob_get_clean();
        } catch (Exception $e) {
            return '<p class="tpak-error">' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * บันทึก activity log
     */
    public function log_activity($action, $message, $data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_activity_log';
        
        $wpdb->insert($table, array(
            'action' => $action,
            'message' => $message,
            'data' => json_encode($data),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * รับ client IP
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
     * รับ setting
     */
    public function get_setting($key, $default = null) {
        return get_option('tpak_dq_' . $key, $default);
    }
    
    /**
     * ตั้งค่า setting
     */
    public function set_setting($key, $value) {
        return update_option('tpak_dq_' . $key, $value);
    }
    
    /**
     * ตรวจสอบว่า LimeSurvey พร้อมใช้งานหรือไม่
     */
    public function is_limesurvey_ready() {
        $api_url = $this->get_setting('limesurvey_api_url');
        $username = $this->get_setting('limesurvey_username');
        $password = $this->get_setting('limesurvey_password');
        
        return !empty($api_url) && !empty($username) && !empty($password);
    }
    
    /**
     * รับ admin URL
     */
    public function get_admin_url($page = '') {
        return admin_url('admin.php?page=tpak-dq-system' . ($page ? '&' . $page : ''));
    }
    
    /**
     * รับ public URL
     */
    public function get_public_url($page = '') {
        return home_url('tpak-dq-system' . ($page ? '/' . $page : ''));
    }
} 