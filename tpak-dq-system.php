<?php
/**
 * Plugin Name: TPAK DQ System v3
 * Plugin URI: https://tpak.org/dq-system
 * Description: ระบบ Data Quality สำหรับเชื่อมต่อกับ LimeSurvey และจัดการแบบสอบถามแบบยืดหยุ่น
 * Version: 3.0.0
 * Author: TPAK Development Team
 * Author URI: https://tpak.org
 * License: GPL v2 or later
 * Text Domain: tpak-dq-system
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// กำหนดค่าคงที่
define('TPAK_DQ_SYSTEM_VERSION', '3.0.0');
define('TPAK_DQ_SYSTEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPAK_DQ_SYSTEM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPAK_DQ_SYSTEM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * ไฟล์หลักของปลั๊กอิน TPAK DQ System v3
 */
class TPAK_DQ_System {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * Admin instance
     */
    private $admin = null;
    
    /**
     * Public instance
     */
    private $public = null;
    
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
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * โหลดไฟล์ dependencies
     */
    private function load_dependencies() {
        // โหลดไฟล์หลัก
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-tpak-dq-core.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-tpak-user-roles.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-tpak-limesurvey-client.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-tpak-workflow.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-tpak-notifications.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-limesurvey-api.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-questionnaire-manager.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-data-quality-checker.php';
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-report-generator.php';
        
        // โหลดไฟล์ admin
        if (is_admin()) {
            require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/class-admin.php';
        }
        
        // โหลดไฟล์ public
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'public/class-public.php';
    }
    
    /**
     * โหลดไฟล์ภาษา
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'tpak-dq-system',
            false,
            dirname(TPAK_DQ_SYSTEM_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * เริ่มต้นปลั๊กอิน
     */
    public function init() {
        // ตรวจสอบ memory limit
        $this->check_memory_limit();
        
        // เริ่มต้น core class (lazy loading)
        TPAK_DQ_Core::get_instance();
        
        // เริ่มต้น admin (ถ้าจำเป็น)
        if (is_admin()) {
            $this->admin = TPAK_DQ_Admin::get_instance();
        }
        
        // เริ่มต้น public (ถ้าจำเป็น)
        $this->public = TPAK_DQ_Public::get_instance();
        
        // ตั้งค่า cron jobs
        $this->setup_cron_jobs();
    }
    
    /**
     * ตรวจสอบ memory limit
     */
    private function check_memory_limit() {
        $current_memory = memory_get_usage(true) / 1024 / 1024; // MB
        $limit = ini_get('memory_limit');
        
        if ($limit !== '-1') {
            $limit_mb = $this->parse_memory_limit($limit);
            if ($current_memory > ($limit_mb * 0.7)) { // 70% of limit
                error_log('TPAK DQ System: High memory usage detected - ' . round($current_memory, 2) . 'MB');
                
                // Force garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
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
     * เมื่อเปิดใช้งานปลั๊กอิน
     */
    public function activate() {
        // สร้างตารางฐานข้อมูล
        $this->create_tables();
        
        // สร้าง default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('TPAK DQ System: Plugin activated successfully');
    }
    
    /**
     * เมื่อปิดใช้งานปลั๊กอิน
     */
    public function deactivate() {
        // ลบ cron jobs
        wp_clear_scheduled_hook('tpak_sync_survey_data');
        wp_clear_scheduled_hook('tpak_generate_reports');
        wp_clear_scheduled_hook('tpak_cleanup_old_data');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * สร้างตารางฐานข้อมูล
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตารางแบบสอบถาม
        $table_questionnaires = $wpdb->prefix . 'tpak_questionnaires';
        $sql_questionnaires = "CREATE TABLE $table_questionnaires (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            limesurvey_id varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY limesurvey_id (limesurvey_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // ตารางการตรวจสอบคุณภาพข้อมูล
        $table_quality_checks = $wpdb->prefix . 'tpak_quality_checks';
        $sql_quality_checks = "CREATE TABLE $table_quality_checks (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            questionnaire_id mediumint(9) NOT NULL,
            check_type varchar(50) NOT NULL,
            check_config text NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY questionnaire_id (questionnaire_id),
            KEY check_type (check_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // ตารางผลการตรวจสอบ
        $table_check_results = $wpdb->prefix . 'tpak_check_results';
        $sql_check_results = "CREATE TABLE $table_check_results (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            questionnaire_id mediumint(9) NOT NULL,
            check_id mediumint(9) NOT NULL,
            response_id varchar(50) NOT NULL,
            result_status varchar(20) NOT NULL,
            result_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY questionnaire_id (questionnaire_id),
            KEY check_id (check_id),
            KEY response_id (response_id),
            KEY result_status (result_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // ตารางชุดข้อมูลตรวจสอบ (Verification Batches)
        $table_verification_batches = $wpdb->prefix . 'tpak_verification_batches';
        $sql_verification_batches = "CREATE TABLE $table_verification_batches (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            batch_name varchar(255) NOT NULL,
            questionnaire_id mediumint(9) NOT NULL,
            batch_type varchar(50) NOT NULL DEFAULT 'manual',
            status varchar(20) NOT NULL DEFAULT 'pending',
            total_records int(11) DEFAULT 0,
            processed_records int(11) DEFAULT 0,
            verified_records int(11) DEFAULT 0,
            rejected_records int(11) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            assigned_to bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY questionnaire_id (questionnaire_id),
            KEY status (status),
            KEY batch_type (batch_type),
            KEY created_by (created_by),
            KEY assigned_to (assigned_to),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // ตารางข้อมูลจาก LimeSurvey (Survey Data)
        $table_survey_data = $wpdb->prefix . 'tpak_survey_data';
        $sql_survey_data = "CREATE TABLE $table_survey_data (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            limesurvey_id varchar(50) NOT NULL,
            response_id varchar(50) NOT NULL,
            questionnaire_id mediumint(9) NOT NULL,
            batch_id mediumint(9) DEFAULT NULL,
            respondent_id varchar(100) DEFAULT NULL,
            response_data longtext NOT NULL,
            response_status varchar(20) DEFAULT 'submitted',
            submission_date datetime DEFAULT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            sync_status varchar(20) DEFAULT 'pending',
            sync_attempts int(11) DEFAULT 0,
            last_sync_attempt datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY response_unique (limesurvey_id, response_id),
            KEY questionnaire_id (questionnaire_id),
            KEY batch_id (batch_id),
            KEY response_status (response_status),
            KEY sync_status (sync_status),
            KEY submission_date (submission_date)
        ) $charset_collate;";
        
        // ตารางประวัติการตรวจสอบ (Verification Logs)
        $table_verification_logs = $wpdb->prefix . 'tpak_verification_logs';
        $sql_verification_logs = "CREATE TABLE $table_verification_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            batch_id mediumint(9) NOT NULL,
            response_id varchar(50) NOT NULL,
            verifier_id bigint(20) unsigned NOT NULL,
            verification_action varchar(50) NOT NULL,
            verification_status varchar(20) NOT NULL,
            verification_notes text,
            verification_data longtext,
            verification_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY response_id (response_id),
            KEY verifier_id (verifier_id),
            KEY verification_action (verification_action),
            KEY verification_status (verification_status),
            KEY verification_date (verification_date)
        ) $charset_collate;";
        
        // ตารางสถานะ workflow (Workflow Status)
        $table_workflow_status = $wpdb->prefix . 'tpak_workflow_status';
        $sql_workflow_status = "CREATE TABLE $table_workflow_status (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            batch_id mediumint(9) NOT NULL,
            current_state varchar(50) NOT NULL DEFAULT 'pending',
            previous_state varchar(50) DEFAULT NULL,
            state_data longtext,
            assigned_role varchar(50) DEFAULT NULL,
            assigned_user bigint(20) unsigned DEFAULT NULL,
            state_entered_at datetime DEFAULT CURRENT_TIMESTAMP,
            state_updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            workflow_completed tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY batch_workflow (batch_id),
            KEY current_state (current_state),
            KEY assigned_role (assigned_role),
            KEY assigned_user (assigned_user),
            KEY workflow_completed (workflow_completed)
        ) $charset_collate;";
        
        // ตาราง notifications
        $table_notifications = $wpdb->prefix . 'tpak_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_questionnaires);
        dbDelta($sql_quality_checks);
        dbDelta($sql_check_results);
        dbDelta($sql_verification_batches);
        dbDelta($sql_survey_data);
        dbDelta($sql_verification_logs);
        dbDelta($sql_workflow_status);
        dbDelta($sql_notifications);
    }
    
    /**
     * ตั้งค่า default options
     */
    private function set_default_options() {
        $default_options = array(
            'limesurvey_api_url' => '',
            'limesurvey_username' => '',
            'limesurvey_password' => '',
            'auto_sync_interval' => 'hourly',
            'quality_check_enabled' => true,
            'report_auto_generate' => false,
            'notification_email' => get_option('admin_email'),
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('tpak_dq_' . $key) === false) {
                update_option('tpak_dq_' . $key, $value);
            }
        }
    }
    
    /**
     * ตั้งค่า cron jobs
     */
    private function setup_cron_jobs() {
        // เพิ่ม custom intervals
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
        
        // ตั้งค่า cron jobs
        if (!wp_next_scheduled('tpak_sync_survey_data')) {
            wp_schedule_event(time(), 'hourly', 'tpak_sync_survey_data');
        }
        
        if (!wp_next_scheduled('tpak_generate_reports')) {
            wp_schedule_event(time(), 'daily', 'tpak_generate_reports');
        }
        
        if (!wp_next_scheduled('tpak_cleanup_old_data')) {
            wp_schedule_event(time(), 'weekly', 'tpak_cleanup_old_data');
        }
    }
    
    /**
     * เพิ่ม custom cron intervals
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['every_15_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'tpak-dq-system')
        );
        
        $schedules['every_30_minutes'] = array(
            'interval' => 1800,
            'display' => __('Every 30 Minutes', 'tpak-dq-system')
        );
        
        $schedules['every_2_hours'] = array(
            'interval' => 7200,
            'display' => __('Every 2 Hours', 'tpak-dq-system')
        );
        
        return $schedules;
    }
}

// เริ่มต้นปลั๊กอิน
function tpak_dq_system() {
    return TPAK_DQ_System::get_instance();
}

// เริ่มต้นเมื่อ WordPress โหลดเสร็จ
add_action('plugins_loaded', 'tpak_dq_system'); 