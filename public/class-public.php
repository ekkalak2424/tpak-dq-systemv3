<?php
/**
 * Public Class สำหรับ TPAK DQ System v3
 * 
 * จัดการ frontend และ shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Public {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * Core instance
     */
    private $core;
    
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
        $this->core = TPAK_DQ_Core::get_instance();
        $this->init_hooks();
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        // เพิ่ม public scripts และ styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        
        // เพิ่ม shortcodes
        add_shortcode('tpak_questionnaire_list', array($this, 'shortcode_questionnaire_list'));
        add_shortcode('tpak_quality_report', array($this, 'shortcode_quality_report'));
        add_shortcode('tpak_quality_dashboard', array($this, 'shortcode_quality_dashboard'));
        
        // เพิ่ม custom endpoints
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_endpoints'));
        
        // เพิ่ม AJAX handlers สำหรับ public
        add_action('wp_ajax_tpak_dq_get_questionnaire_data', array($this, 'ajax_get_questionnaire_data'));
        add_action('wp_ajax_nopriv_tpak_dq_get_questionnaire_data', array($this, 'ajax_get_questionnaire_data'));
    }
    
    /**
     * โหลด public scripts และ styles
     */
    public function enqueue_public_scripts() {
        // ตรวจสอบว่าหน้าปัจจุบันมี shortcode ของปลั๊กอินหรือไม่
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'tpak_questionnaire_list') ||
            has_shortcode($post->post_content, 'tpak_quality_report') ||
            has_shortcode($post->post_content, 'tpak_quality_dashboard')
        )) {
            wp_enqueue_script(
                'tpak-dq-public',
                TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/public.js',
                array('jquery', 'wp-util'),
                TPAK_DQ_SYSTEM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'tpak-dq-public',
                TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/css/public.css',
                array(),
                TPAK_DQ_SYSTEM_VERSION
            );
            
            wp_localize_script('tpak-dq-public', 'tpak_dq_public', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tpak_dq_public_nonce'),
                'strings' => array(
                    'loading' => __('Loading...', 'tpak-dq-system'),
                    'error' => __('An error occurred.', 'tpak-dq-system'),
                    'no_data' => __('No data available.', 'tpak-dq-system')
                )
            ));
        }
    }
    
    /**
     * Shortcode: แสดงรายการแบบสอบถาม
     */
    public function shortcode_questionnaire_list($atts) {
        $atts = shortcode_atts(array(
            'status' => 'active',
            'limit' => 10,
            'show_description' => 'true',
            'show_statistics' => 'false',
            'template' => 'list'
        ), $atts);
        
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $questionnaires = $questionnaire_manager->get_questionnaires(array(
            'status' => $atts['status'],
            'limit' => intval($atts['limit'])
        ));
        
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
            'show_chart' => 'true',
            'show_details' => 'false',
            'template' => 'default'
        ), $atts);
        
        $core = TPAK_DQ_Core::get_instance();
        $report_generator = $core->get_report_generator();
        
        $report_data = $report_generator->get_quality_report(array(
            'questionnaire_id' => intval($atts['questionnaire_id']),
            'period' => $atts['period']
        ));
        
        ob_start();
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/quality-report.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode: แสดง Dashboard คุณภาพข้อมูล
     */
    public function shortcode_quality_dashboard($atts) {
        $atts = shortcode_atts(array(
            'show_summary' => 'true',
            'show_charts' => 'true',
            'show_recent_checks' => 'true',
            'template' => 'dashboard'
        ), $atts);
        
        $core = TPAK_DQ_Core::get_instance();
        $report_generator = $core->get_report_generator();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        // รับข้อมูลสรุป
        $summary_data = $report_generator->get_quality_report(array('period' => 'week'));
        
        // รับแบบสอบถามล่าสุด
        $recent_questionnaires = $questionnaire_manager->get_questionnaires(array(
            'limit' => 5,
            'orderby' => 'updated_at'
        ));
        
        ob_start();
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/quality-dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * เพิ่ม rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            'tpak-dq/questionnaire/([^/]+)/?$',
            'index.php?tpak_dq_page=questionnaire&tpak_dq_id=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            'tpak-dq/report/([^/]+)/?$',
            'index.php?tpak_dq_page=report&tpak_dq_id=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            'tpak-dq/dashboard/?$',
            'index.php?tpak_dq_page=dashboard',
            'top'
        );
    }
    
    /**
     * เพิ่ม query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'tpak_dq_page';
        $vars[] = 'tpak_dq_id';
        return $vars;
    }
    
    /**
     * จัดการ custom endpoints
     */
    public function handle_custom_endpoints() {
        $page = get_query_var('tpak_dq_page');
        
        if (!$page) {
            return;
        }
        
        switch ($page) {
            case 'questionnaire':
                $this->handle_questionnaire_page();
                break;
                
            case 'report':
                $this->handle_report_page();
                break;
                
            case 'dashboard':
                $this->handle_dashboard_page();
                break;
        }
    }
    
    /**
     * จัดการหน้าคำถาม
     */
    private function handle_questionnaire_page() {
        $questionnaire_id = get_query_var('tpak_dq_id');
        
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $questionnaire = $questionnaire_manager->get_questionnaire($questionnaire_id);
        
        if (!$questionnaire) {
            wp_die(__('Questionnaire not found.', 'tpak-dq-system'));
        }
        
        // รับข้อมูลเพิ่มเติม
        $statistics = $questionnaire_manager->get_questionnaire_statistics($questionnaire_id);
        $questions = $questionnaire_manager->get_questionnaire_questions($questionnaire_id);
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/questionnaire-detail.php';
        exit;
    }
    
    /**
     * จัดการหน้ารายงาน
     */
    private function handle_report_page() {
        $report_id = get_query_var('tpak_dq_id');
        
        $core = TPAK_DQ_Core::get_instance();
        $report_generator = $core->get_report_generator();
        
        $report_data = $report_generator->get_quality_report(array(
            'questionnaire_id' => intval($report_id)
        ));
        
        if (!$report_data['success']) {
            wp_die(__('Report not found.', 'tpak-dq-system'));
        }
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/report-detail.php';
        exit;
    }
    
    /**
     * จัดการหน้า Dashboard
     */
    private function handle_dashboard_page() {
        $core = TPAK_DQ_Core::get_instance();
        $report_generator = $core->get_report_generator();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        // รับข้อมูลสรุป
        $summary_data = $report_generator->get_quality_report(array('period' => 'week'));
        
        // รับแบบสอบถามล่าสุด
        $recent_questionnaires = $questionnaire_manager->get_questionnaires(array(
            'limit' => 10,
            'orderby' => 'updated_at'
        ));
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/views/dashboard.php';
        exit;
    }
    
    /**
     * AJAX: รับข้อมูลแบบสอบถาม
     */
    public function ajax_get_questionnaire_data() {
        check_ajax_referer('tpak_dq_public_nonce', 'nonce');
        
        $questionnaire_id = intval($_POST['questionnaire_id'] ?? 0);
        
        if (!$questionnaire_id) {
            wp_send_json_error(__('Invalid questionnaire ID.', 'tpak-dq-system'));
        }
        
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $questionnaire = $questionnaire_manager->get_questionnaire($questionnaire_id);
        
        if (!$questionnaire) {
            wp_send_json_error(__('Questionnaire not found.', 'tpak-dq-system'));
        }
        
        // รับข้อมูลเพิ่มเติม
        $statistics = $questionnaire_manager->get_questionnaire_statistics($questionnaire_id);
        $questions = $questionnaire_manager->get_questionnaire_questions($questionnaire_id);
        
        $data = array(
            'questionnaire' => $questionnaire,
            'statistics' => $statistics['success'] ? $statistics['data'] : null,
            'questions' => $questions['success'] ? $questions['data'] : null
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * สร้าง URL สำหรับหน้าแบบสอบถาม
     */
    public function get_questionnaire_url($questionnaire_id) {
        return home_url("tpak-dq/questionnaire/{$questionnaire_id}/");
    }
    
    /**
     * สร้าง URL สำหรับหน้ารายงาน
     */
    public function get_report_url($questionnaire_id) {
        return home_url("tpak-dq/report/{$questionnaire_id}/");
    }
    
    /**
     * สร้าง URL สำหรับหน้า Dashboard
     */
    public function get_dashboard_url() {
        return home_url('tpak-dq/dashboard/');
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้มีสิทธิ์เข้าถึงข้อมูลหรือไม่
     */
    public function can_access_data($questionnaire_id = null) {
        // ตรวจสอบสิทธิ์ตามการตั้งค่า
        $public_access = $this->core->get_setting('public_access', false);
        
        if ($public_access) {
            return true;
        }
        
        // ตรวจสอบว่าผู้ใช้ login อยู่หรือไม่
        if (is_user_logged_in()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * แสดงข้อความแจ้งเตือนเมื่อไม่มีสิทธิ์เข้าถึง
     */
    public function get_access_denied_message() {
        if (is_user_logged_in()) {
            return __('You do not have permission to access this data.', 'tpak-dq-system');
        } else {
            return __('Please log in to access this data.', 'tpak-dq-system');
        }
    }
    
    /**
     * จัดรูปแบบวันที่
     */
    public function format_date($date, $format = 'Y-m-d H:i:s') {
        return date($format, strtotime($date));
    }
    
    /**
     * จัดรูปแบบตัวเลข
     */
    public function format_number($number, $decimals = 2) {
        return number_format($number, $decimals);
    }
    
    /**
     * จัดรูปแบบเปอร์เซ็นต์
     */
    public function format_percentage($number, $decimals = 1) {
        return number_format($number, $decimals) . '%';
    }
    
    /**
     * รับสถานะแบบสอบถามเป็นภาษาไทย
     */
    public function get_status_text($status) {
        switch ($status) {
            case 'active':
                return __('Active', 'tpak-dq-system');
            case 'inactive':
                return __('Inactive', 'tpak-dq-system');
            case 'expired':
                return __('Expired', 'tpak-dq-system');
            default:
                return __('Unknown', 'tpak-dq-system');
        }
    }
    
    /**
     * รับสถานะการตรวจสอบเป็นภาษาไทย
     */
    public function get_check_status_text($status) {
        switch ($status) {
            case 'passed':
                return __('Passed', 'tpak-dq-system');
            case 'failed':
                return __('Failed', 'tpak-dq-system');
            case 'warning':
                return __('Warning', 'tpak-dq-system');
            default:
                return __('Unknown', 'tpak-dq-system');
        }
    }
    
    /**
     * รับประเภทการตรวจสอบเป็นภาษาไทย
     */
    public function get_check_type_text($type) {
        switch ($type) {
            case 'completeness':
                return __('Completeness', 'tpak-dq-system');
            case 'consistency':
                return __('Consistency', 'tpak-dq-system');
            case 'validity':
                return __('Validity', 'tpak-dq-system');
            case 'accuracy':
                return __('Accuracy', 'tpak-dq-system');
            case 'timeliness':
                return __('Timeliness', 'tpak-dq-system');
            case 'uniqueness':
                return __('Uniqueness', 'tpak-dq-system');
            case 'custom':
                return __('Custom', 'tpak-dq-system');
            default:
                return __('Unknown', 'tpak-dq-system');
        }
    }
} 