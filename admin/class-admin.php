<?php
/**
 * Admin Class สำหรับ TPAK DQ System v3
 * 
 * จัดการ admin panel และการตั้งค่าต่างๆ
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Admin {
    
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
        // เพิ่มเมนู admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // เพิ่ม admin scripts และ styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // จัดการ AJAX requests
        add_action('wp_ajax_tpak_dq_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_tpak_dq_add_quality_check', array($this, 'ajax_add_quality_check'));
        add_action('wp_ajax_tpak_dq_delete_quality_check', array($this, 'ajax_delete_quality_check'));
        add_action('wp_ajax_tpak_dq_export_report', array($this, 'ajax_export_report'));
        
        // เพิ่ม admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * เพิ่มเมนู admin
     */
    public function add_admin_menu() {
        // ตรวจสอบสิทธิ์
        $user_roles = TPAK_User_Roles::get_instance();
        
        add_menu_page(
            __('TPAK DQ System', 'tpak-dq-system'),
            __('TPAK DQ', 'tpak-dq-system'),
            'tpak_view_dashboard',
            'tpak-dq-system',
            array($this, 'admin_page_dashboard'),
            'dashicons-chart-area',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'tpak-dq-system',
            __('Dashboard', 'tpak-dq-system'),
            __('Dashboard', 'tpak-dq-system'),
            'tpak_view_dashboard',
            'tpak-dq-system',
            array($this, 'admin_page_dashboard')
        );
        
        // Data Import
        add_submenu_page(
            'tpak-dq-system',
            __('Data Import', 'tpak-dq-system'),
            __('Data Import', 'tpak-dq-system'),
            'tpak_manage_system',
            'tpak-dq-data-import',
            array($this, 'admin_page_data_import')
        );
        
        // Verification Queue
        add_submenu_page(
            'tpak-dq-system',
            __('Verification Queue', 'tpak-dq-system'),
            __('Verification Queue', 'tpak-dq-system'),
            'tpak_verify_data',
            'tpak-dq-verification-queue',
            array($this, 'admin_page_verification_queue')
        );
        
        // Questionnaires
        add_submenu_page(
            'tpak-dq-system',
            __('Questionnaires', 'tpak-dq-system'),
            __('Questionnaires', 'tpak-dq-system'),
            'tpak_view_dashboard',
            'tpak-dq-questionnaires',
            array($this, 'admin_page_questionnaires')
        );
        
        // Quality Checks
        add_submenu_page(
            'tpak-dq-system',
            __('Quality Checks', 'tpak-dq-system'),
            __('Quality Checks', 'tpak-dq-system'),
            'tpak_manage_system',
            'tpak-dq-quality-checks',
            array($this, 'admin_page_quality_checks')
        );
        
        // Reports
        add_submenu_page(
            'tpak-dq-system',
            __('Reports', 'tpak-dq-system'),
            __('Reports', 'tpak-dq-system'),
            'tpak_view_dashboard',
            'tpak-dq-reports',
            array($this, 'admin_page_reports')
        );
        
        // Settings
        add_submenu_page(
            'tpak-dq-system',
            __('Settings', 'tpak-dq-system'),
            __('Settings', 'tpak-dq-system'),
            'manage_options',
            'tpak-dq-settings',
            array($this, 'admin_page_settings')
        );
    }
    
    /**
     * โหลด admin scripts และ styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'tpak-dq') === false) {
            return;
        }
        
        wp_enqueue_script(
            'tpak-dq-admin',
            TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            TPAK_DQ_SYSTEM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'tpak-dq-admin',
            TPAK_DQ_SYSTEM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TPAK_DQ_SYSTEM_VERSION
        );
        
        wp_localize_script('tpak-dq-admin', 'tpak_dq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpak_dq_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'tpak-dq-system'),
                'saving' => __('Saving...', 'tpak-dq-system'),
                'saved' => __('Saved successfully!', 'tpak-dq-system'),
                'error' => __('An error occurred.', 'tpak-dq-system')
            )
        ));
    }
    
    /**
     * หน้า Dashboard
     */
    public function admin_page_dashboard() {
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        $report_generator = $core->get_report_generator();
        
        // รับสถิติ
        $total_questionnaires = $questionnaire_manager->get_questionnaires_count();
        $active_questionnaires = $questionnaire_manager->get_questionnaires_count('active');
        
        // รับรายงานล่าสุด
        $recent_report = $report_generator->get_quality_report(array('period' => 'week'));
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * หน้า Questionnaires
     */
    public function admin_page_questionnaires() {
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'edit':
                $questionnaire_id = intval($_GET['id'] ?? 0);
                $questionnaire = $questionnaire_manager->get_questionnaire($questionnaire_id);
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/questionnaire-edit.php';
                break;
                
            case 'sync':
                $this->handle_questionnaire_sync();
                break;
                
            default:
                $questionnaires = $questionnaire_manager->get_questionnaires(array('limit' => 50));
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/questionnaires.php';
                break;
        }
    }
    
    /**
     * หน้า Quality Checks
     */
    public function admin_page_quality_checks() {
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                $questionnaires = $questionnaire_manager->get_active_questionnaires();
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/quality-check-add.php';
                break;
                
            case 'edit':
                $check_id = intval($_GET['id'] ?? 0);
                $check = $this->get_quality_check($check_id);
                $questionnaires = $questionnaire_manager->get_active_questionnaires();
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/quality-check-edit.php';
                break;
                
            default:
                $checks = $this->get_quality_checks();
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/quality-checks.php';
                break;
        }
    }
    
    /**
     * หน้า Reports
     */
    public function admin_page_reports() {
        $core = TPAK_DQ_Core::get_instance();
        $report_generator = $core->get_report_generator();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'view':
                $report_id = intval($_GET['id'] ?? 0);
                $report = $this->get_report($report_id);
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/report-view.php';
                break;
                
            default:
                $reports = $this->get_reports();
                $questionnaires = $questionnaire_manager->get_active_questionnaires();
                include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/reports.php';
                break;
        }
    }
    
    /**
     * หน้า Settings
     */
    public function admin_page_settings() {
        $core = TPAK_DQ_Core::get_instance();
        
        if ($_POST && isset($_POST['save_settings'])) {
            $this->save_settings($_POST);
        }
        
        $settings = array(
            'limesurvey_api_url' => $core->get_setting('limesurvey_api_url'),
            'limesurvey_username' => $core->get_setting('limesurvey_username'),
            'limesurvey_password' => $core->get_setting('limesurvey_password'),
            'auto_sync_interval' => $core->get_setting('auto_sync_interval'),
            'quality_check_enabled' => $core->get_setting('quality_check_enabled'),
            'report_auto_generate' => $core->get_setting('report_auto_generate'),
            'notification_email' => $core->get_setting('notification_email')
        );
        
        include TPAK_DQ_SYSTEM_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * หน้า Data Import
     */
    public function admin_page_data_import() {
        ?>
        <div class="wrap">
            <h1><?php _e('Data Import', 'tpak-dq-system'); ?></h1>
            <div id="tpak-data-import">
                <div class="tpak-import-section">
                    <h2><?php _e('LimeSurvey Connection', 'tpak-dq-system'); ?></h2>
                    <div class="tpak-connection-status">
                        <p><?php _e('Connection Status:', 'tpak-dq-system'); ?> <span id="connection-status"><?php _e('Checking...', 'tpak-dq-system'); ?></span></p>
                        <button id="test-connection" class="button"><?php _e('Test Connection', 'tpak-dq-system'); ?></button>
                    </div>
                </div>
                
                <div class="tpak-import-section">
                    <h2><?php _e('Available Surveys', 'tpak-dq-system'); ?></h2>
                    <div id="available-surveys">
                        <p><?php _e('Loading surveys...', 'tpak-dq-system'); ?></p>
                    </div>
                    <button id="sync-all-surveys" class="button button-primary"><?php _e('Sync All Surveys', 'tpak-dq-system'); ?></button>
                </div>
                
                <div class="tpak-import-section">
                    <h2><?php _e('Import History', 'tpak-dq-system'); ?></h2>
                    <div id="import-history">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Survey ID', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('Title', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('Last Sync', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('Status', 'tpak-dq-system'); ?></th>
                                    <th><?php _e('Actions', 'tpak-dq-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="import-history-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * หน้า Verification Queue
     */
    public function admin_page_verification_queue() {
        ?>
        <div class="wrap">
            <h1><?php _e('Verification Queue', 'tpak-dq-system'); ?></h1>
            <div id="tpak-verification-queue">
                <div class="tpak-queue-filters">
                    <select id="status-filter">
                        <option value=""><?php _e('All Statuses', 'tpak-dq-system'); ?></option>
                        <option value="pending"><?php _e('Pending', 'tpak-dq-system'); ?></option>
                        <option value="interviewing"><?php _e('Interviewing', 'tpak-dq-system'); ?></option>
                        <option value="supervising"><?php _e('Supervising', 'tpak-dq-system'); ?></option>
                        <option value="examining"><?php _e('Examining', 'tpak-dq-system'); ?></option>
                        <option value="completed"><?php _e('Completed', 'tpak-dq-system'); ?></option>
                    </select>
                    <select id="batch-filter">
                        <option value=""><?php _e('All Batches', 'tpak-dq-system'); ?></option>
                    </select>
                    <button id="apply-filters" class="button"><?php _e('Apply Filters', 'tpak-dq-system'); ?></button>
                </div>
                
                <div class="tpak-queue-content">
                    <div id="verification-items">
                        <p><?php _e('Loading verification items...', 'tpak-dq-system'); ?></p>
                    </div>
                </div>
                
                <div class="tpak-verification-actions">
                    <button id="bulk-approve" class="button button-primary"><?php _e('Bulk Approve', 'tpak-dq-system'); ?></button>
                    <button id="bulk-reject" class="button button-secondary"><?php _e('Bulk Reject', 'tpak-dq-system'); ?></button>
                    <button id="bulk-request-revision" class="button"><?php _e('Bulk Request Revision', 'tpak-dq-system'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * จัดการการ sync แบบสอบถาม
     */
    private function handle_questionnaire_sync() {
        $core = TPAK_DQ_Core::get_instance();
        $questionnaire_manager = $core->get_questionnaire_manager();
        
        $result = $questionnaire_manager->sync_questionnaires();
        
        if ($result['success']) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . $result['message'] . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error is-dismissible"><p>' . $result['message'] . '</p></div>';
            });
        }
        
        wp_redirect(admin_url('admin.php?page=tpak-dq-questionnaires'));
        exit;
    }
    
    /**
     * บันทึกการตั้งค่า
     */
    private function save_settings($data) {
        $core = TPAK_DQ_Core::get_instance();
        
        $settings = array(
            'limesurvey_api_url' => sanitize_url($data['limesurvey_api_url']),
            'limesurvey_username' => sanitize_text_field($data['limesurvey_username']),
            'limesurvey_password' => sanitize_text_field($data['limesurvey_password']),
            'auto_sync_interval' => sanitize_text_field($data['auto_sync_interval']),
            'quality_check_enabled' => isset($data['quality_check_enabled']),
            'report_auto_generate' => isset($data['report_auto_generate']),
            'notification_email' => sanitize_email($data['notification_email'])
        );
        
        foreach ($settings as $key => $value) {
            $core->set_setting($key, $value);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'tpak-dq-system') . '</p></div>';
        });
    }
    
    /**
     * AJAX: บันทึกการตั้งค่า
     */
    public function ajax_save_settings() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $data = $_POST['settings'] ?? array();
        $this->save_settings($data);
        
        wp_send_json_success(__('Settings saved successfully!', 'tpak-dq-system'));
    }
    
    /**
     * AJAX: เพิ่มการตรวจสอบคุณภาพข้อมูล
     */
    public function ajax_add_quality_check() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $data = $_POST['check'] ?? array();
        
        $result = $this->add_quality_check($data);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: ลบการตรวจสอบคุณภาพข้อมูล
     */
    public function ajax_delete_quality_check() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $check_id = intval($_POST['check_id'] ?? 0);
        
        $result = $this->delete_quality_check($check_id);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Export รายงาน
     */
    public function ajax_export_report() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        $report_id = intval($_POST['report_id'] ?? 0);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $result = $this->export_report($report_id, $format);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * เพิ่มการตรวจสอบคุณภาพข้อมูล
     */
    private function add_quality_check($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_quality_checks';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'questionnaire_id' => intval($data['questionnaire_id']),
                'check_type' => sanitize_text_field($data['check_type']),
                'check_config' => json_encode($data['check_config']),
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('Failed to add quality check.', 'tpak-dq-system')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Quality check added successfully.', 'tpak-dq-system')
        );
    }
    
    /**
     * ลบการตรวจสอบคุณภาพข้อมูล
     */
    private function delete_quality_check($check_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_quality_checks';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $check_id),
            array('%d')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => __('Failed to delete quality check.', 'tpak-dq-system')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Quality check deleted successfully.', 'tpak-dq-system')
        );
    }
    
    /**
     * รับการตรวจสอบคุณภาพข้อมูล
     */
    private function get_quality_checks() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_quality_checks';
        $questionnaires_table = $wpdb->prefix . 'tpak_questionnaires';
        
        $query = "
            SELECT c.*, q.title as questionnaire_title
            FROM {$table_name} c
            INNER JOIN {$questionnaires_table} q ON c.questionnaire_id = q.id
            ORDER BY c.created_at DESC
        ";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * รับการตรวจสอบคุณภาพข้อมูลตาม ID
     */
    private function get_quality_check($check_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_quality_checks';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $check_id
        ));
    }
    
    /**
     * รับรายงาน
     */
    private function get_reports() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_reports';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY generated_at DESC LIMIT 50"
        );
    }
    
    /**
     * รับรายงานตาม ID
     */
    private function get_report($report_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_reports';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $report_id
        ));
    }
    
    /**
     * Export รายงาน
     */
    private function export_report($report_id, $format) {
        $report = $this->get_report($report_id);
        
        if (!$report) {
            return array(
                'success' => false,
                'message' => __('Report not found.', 'tpak-dq-system')
            );
        }
        
        $data = json_decode($report->data, true);
        
        switch ($format) {
            case 'csv':
                return $this->export_report_csv($report, $data);
            case 'json':
                return $this->export_report_json($report, $data);
            case 'pdf':
                return $this->export_report_pdf($report, $data);
            default:
                return array(
                    'success' => false,
                    'message' => __('Unsupported export format.', 'tpak-dq-system')
                );
        }
    }
    
    /**
     * Export รายงานเป็น CSV
     */
    private function export_report_csv($report, $data) {
        $filename = 'tpak-dq-report-' . $report->id . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // เขียน header
        fputcsv($output, array('Report ID', 'Type', 'Title', 'Period Start', 'Period End', 'Generated At'));
        fputcsv($output, array($report->id, $report->report_type, $report->title, $report->period_start, $report->period_end, $report->generated_at));
        
        // เขียนข้อมูลสถิติ
        if (isset($data['statistics'])) {
            fputcsv($output, array(''));
            fputcsv($output, array('Statistics'));
            fputcsv($output, array('Total Checks', 'Passed', 'Failed', 'Pass Rate'));
            fputcsv($output, array(
                $data['statistics']['total_checks'],
                $data['statistics']['passed_checks'],
                $data['statistics']['failed_checks'],
                $data['statistics']['pass_rate'] . '%'
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export รายงานเป็น JSON
     */
    private function export_report_json($report, $data) {
        $filename = 'tpak-dq-report-' . $report->id . '-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Export รายงานเป็น PDF
     */
    private function export_report_pdf($report, $data) {
        // ต้องติดตั้ง library สำหรับสร้าง PDF
        return array(
            'success' => false,
            'message' => __('PDF export not implemented yet.', 'tpak-dq-system')
        );
    }
    
    /**
     * แสดง admin notices
     */
    public function admin_notices() {
        // แสดงการแจ้งเตือนต่างๆ
    }
} 