<?php
/**
 * TPAK DQ System - Notification System
 * 
 * จัดการระบบแจ้งเตือนทั้ง Email และ In-app notifications
 * Phase 7: Notification System (1 สัปดาห์)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Notifications {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * Email templates
     */
    private $email_templates = array();
    
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
        $this->init_email_templates();
        $this->init_hooks();
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_tpak_get_notifications', array($this, 'handle_get_notifications'));
        add_action('wp_ajax_tpak_mark_notification_read', array($this, 'handle_mark_notification_read'));
        add_action('wp_ajax_tpak_delete_notification', array($this, 'handle_delete_notification'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('wp_ajax_tpak_dismiss_notice', array($this, 'handle_dismiss_notice'));
    }
    
    /**
     * เริ่มต้น email templates
     */
    private function init_email_templates() {
        $this->email_templates = array(
            'workflow_state_change' => array(
                'subject' => __('Workflow State Changed - Batch {batch_id}', 'tpak-dq-system'),
                'template' => 'workflow-state-change.html',
                'variables' => array('batch_id', 'new_state', 'user_name', 'timestamp')
            ),
            'task_assigned' => array(
                'subject' => __('New Task Assigned - {task_title}', 'tpak-dq-system'),
                'template' => 'task-assigned.html',
                'variables' => array('task_title', 'batch_id', 'due_date', 'priority')
            ),
            'verification_completed' => array(
                'subject' => __('Verification Completed - Response {response_id}', 'tpak-dq-system'),
                'template' => 'verification-completed.html',
                'variables' => array('response_id', 'batch_id', 'verifier_name', 'result')
            ),
            'quality_check_failed' => array(
                'subject' => __('Quality Check Failed - {check_name}', 'tpak-dq-system'),
                'template' => 'quality-check-failed.html',
                'variables' => array('check_name', 'questionnaire_id', 'response_id', 'error_details')
            ),
            'system_alert' => array(
                'subject' => __('System Alert - {alert_type}', 'tpak-dq-system'),
                'template' => 'system-alert.html',
                'variables' => array('alert_type', 'message', 'timestamp', 'severity')
            ),
            'report_ready' => array(
                'subject' => __('Report Ready - {report_name}', 'tpak-dq-system'),
                'template' => 'report-ready.html',
                'variables' => array('report_name', 'generated_at', 'download_url')
            )
        );
    }
    
    /**
     * ส่ง email notification
     */
    public function send_email_notification($template_key, $recipients, $data = array()) {
        if (!isset($this->email_templates[$template_key])) {
            return array(
                'success' => false,
                'message' => __('Invalid email template', 'tpak-dq-system')
            );
        }
        
        $template = $this->email_templates[$template_key];
        $subject = $this->replace_template_variables($template['subject'], $data);
        $message = $this->get_email_template_content($template['template'], $data);
        
        // ตั้งค่า email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . get_option('admin_email'),
            'X-Mailer: TPAK DQ System v3'
        );
        
        // ส่ง email
        $sent_count = 0;
        foreach ($recipients as $recipient) {
            $result = wp_mail($recipient, $subject, $message, $headers);
            if ($result) {
                $sent_count++;
            }
        }
        
        // บันทึก log
        $this->log_email_notification($template_key, $recipients, $data, $sent_count);
        
        return array(
            'success' => $sent_count > 0,
            'sent_count' => $sent_count,
            'total_recipients' => count($recipients)
        );
    }
    
    /**
     * รับ email template content
     */
    private function get_email_template_content($template_file, $data) {
        $template_path = TPAK_DQ_SYSTEM_PLUGIN_DIR . 'templates/emails/' . $template_file;
        
        if (!file_exists($template_path)) {
            return $this->get_default_email_template($template_file, $data);
        }
        
        ob_start();
        include $template_path;
        $content = ob_get_clean();
        
        return $this->replace_template_variables($content, $data);
    }
    
    /**
     * สร้าง default email template
     */
    private function get_default_email_template($template_name, $data) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPAK DQ System Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; }
        .button { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; }
        .alert { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>TPAK DQ System</h1>
            <p>Data Quality Management System</p>
        </div>
        <div class="content">
            <h2>Notification</h2>
            <p>This is a notification from the TPAK DQ System.</p>
            <div class="alert alert-info">
                <strong>Template:</strong> ' . esc_html($template_name) . '<br>
                <strong>Data:</strong> ' . esc_html(json_encode($data)) . '
            </div>
        </div>
        <div class="footer">
            <p>This email was sent by TPAK DQ System v3</p>
            <p>Generated on: ' . current_time('Y-m-d H:i:s') . '</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * แทนที่ตัวแปรใน template
     */
    private function replace_template_variables($content, $data) {
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        // แทนที่ตัวแปรระบบ
        $system_vars = array(
            'site_name' => get_option('blogname'),
            'site_url' => get_option('home'),
            'admin_email' => get_option('admin_email'),
            'current_time' => current_time('Y-m-d H:i:s'),
            'plugin_version' => TPAK_DQ_SYSTEM_VERSION
        );
        
        foreach ($system_vars as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * ส่ง in-app notification
     */
    public function send_inapp_notification($user_id, $type, $title, $message, $data = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_notifications';
        
        $notification_data = array(
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data),
            'is_read' => 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $notification_data);
        
        if ($result) {
            // ส่ง real-time notification ถ้าเป็นไปได้
            $this->send_realtime_notification($user_id, $notification_data);
            
            return array(
                'success' => true,
                'notification_id' => $wpdb->insert_id
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to create notification', 'tpak-dq-system')
        );
    }
    
    /**
     * ส่ง real-time notification
     */
    private function send_realtime_notification($user_id, $notification_data) {
        // ใช้ WebSocket หรือ Server-Sent Events สำหรับ real-time
        // สำหรับตอนนี้จะใช้ AJAX polling แทน
        
        // บันทึก notification สำหรับ polling
        $this->store_realtime_notification($user_id, $notification_data);
    }
    
    /**
     * บันทึก real-time notification
     */
    private function store_realtime_notification($user_id, $notification_data) {
        $realtime_notifications = get_transient('tpak_realtime_notifications_' . $user_id);
        if (!$realtime_notifications) {
            $realtime_notifications = array();
        }
        
        $realtime_notifications[] = $notification_data;
        set_transient('tpak_realtime_notifications_' . $user_id, $realtime_notifications, 300); // 5 minutes
    }
    
    /**
     * รับ notifications สำหรับ user
     */
    public function get_user_notifications($user_id, $limit = 50, $unread_only = false) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_notifications';
        
        $where_clause = "user_id = %d";
        $params = array($user_id);
        
        if ($unread_only) {
            $where_clause .= " AND is_read = 0";
        }
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d",
            array_merge($params, array($limit))
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * รับ unread notifications count
     */
    public function get_unread_count($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_notifications';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read($notification_id, $user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_notifications';
        
        $result = $wpdb->update(
            $table,
            array('is_read' => 1),
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%d'),
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * ลบ notification
     */
    public function delete_notification($notification_id, $user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_notifications';
        
        $result = $wpdb->delete(
            $table,
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * ส่ง workflow state change notification
     */
    public function send_workflow_notification($batch_id, $new_state, $user_id) {
        $user = get_userdata($user_id);
        $workflow = TPAK_Workflow::get_instance();
        $workflow_data = $workflow->get_workflow_data($batch_id);
        
        $data = array(
            'batch_id' => $batch_id,
            'new_state' => $new_state,
            'user_name' => $user->display_name,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        // ส่ง email notification
        $recipients = $this->get_workflow_notification_recipients($batch_id, $new_state);
        $this->send_email_notification('workflow_state_change', $recipients, $data);
        
        // ส่ง in-app notification
        $this->send_inapp_notification(
            $user_id,
            'workflow_state_change',
            sprintf(__('Workflow State Changed - Batch %d', 'tpak-dq-system'), $batch_id),
            sprintf(__('The workflow state for batch %d has been changed to %s by %s.', 'tpak-dq-system'), $batch_id, $new_state, $user->display_name),
            $data
        );
    }
    
    /**
     * ส่ง task assignment notification
     */
    public function send_task_notification($task_id, $assigned_user_id, $task_data) {
        $assigned_user = get_userdata($assigned_user_id);
        
        $data = array(
            'task_title' => $task_data['title'],
            'batch_id' => $task_data['batch_id'],
            'due_date' => $task_data['due_date'],
            'priority' => $task_data['priority']
        );
        
        // ส่ง email notification
        $this->send_email_notification('task_assigned', array($assigned_user->user_email), $data);
        
        // ส่ง in-app notification
        $this->send_inapp_notification(
            $assigned_user_id,
            'task_assigned',
            sprintf(__('New Task Assigned - %s', 'tpak-dq-system'), $task_data['title']),
            sprintf(__('You have been assigned a new task: %s. Due date: %s', 'tpak-dq-system'), $task_data['title'], $task_data['due_date']),
            $data
        );
    }
    
    /**
     * ส่ง quality check notification
     */
    public function send_quality_check_notification($check_id, $response_id, $result) {
        $data = array(
            'check_name' => $result['check_name'],
            'questionnaire_id' => $result['questionnaire_id'],
            'response_id' => $response_id,
            'error_details' => $result['error_details']
        );
        
        // รับ recipients สำหรับ quality check notifications
        $recipients = $this->get_quality_check_notification_recipients($result['questionnaire_id']);
        
        if ($result['status'] === 'failed') {
            $this->send_email_notification('quality_check_failed', $recipients, $data);
        }
        
        // ส่ง in-app notification ให้กับ system managers
        $managers = $this->get_system_managers();
        foreach ($managers as $manager) {
            $this->send_inapp_notification(
                $manager->ID,
                'quality_check_failed',
                sprintf(__('Quality Check Failed - %s', 'tpak-dq-system'), $result['check_name']),
                sprintf(__('Quality check "%s" failed for response %s', 'tpak-dq-system'), $result['check_name'], $response_id),
                $data
            );
        }
    }
    
    /**
     * ส่ง system alert
     */
    public function send_system_alert($alert_type, $message, $severity = 'info') {
        $data = array(
            'alert_type' => $alert_type,
            'message' => $message,
            'timestamp' => current_time('Y-m-d H:i:s'),
            'severity' => $severity
        );
        
        // ส่ง email ให้ system managers
        $managers = $this->get_system_managers();
        $recipients = array();
        foreach ($managers as $manager) {
            $recipients[] = $manager->user_email;
        }
        
        $this->send_email_notification('system_alert', $recipients, $data);
        
        // ส่ง in-app notification
        foreach ($managers as $manager) {
            $this->send_inapp_notification(
                $manager->ID,
                'system_alert',
                sprintf(__('System Alert - %s', 'tpak-dq-system'), $alert_type),
                $message,
                $data
            );
        }
    }
    
    /**
     * รับ workflow notification recipients
     */
    private function get_workflow_notification_recipients($batch_id, $new_state) {
        $recipients = array();
        
        // รับ system managers
        $managers = $this->get_system_managers();
        foreach ($managers as $manager) {
            $recipients[] = $manager->user_email;
        }
        
        // รับ users ที่เกี่ยวข้องกับ workflow
        $workflow = TPAK_Workflow::get_instance();
        $workflow_data = $workflow->get_workflow_data($batch_id);
        
        if ($workflow_data && $workflow_data['assigned_user']) {
            $assigned_user = get_userdata($workflow_data['assigned_user']);
            if ($assigned_user) {
                $recipients[] = $assigned_user->user_email;
            }
        }
        
        return array_unique($recipients);
    }
    
    /**
     * รับ quality check notification recipients
     */
    private function get_quality_check_notification_recipients($questionnaire_id) {
        $recipients = array();
        
        // รับ system managers
        $managers = $this->get_system_managers();
        foreach ($managers as $manager) {
            $recipients[] = $manager->user_email;
        }
        
        return array_unique($recipients);
    }
    
    /**
     * รับ system managers
     */
    private function get_system_managers() {
        $user_roles = TPAK_User_Roles::get_instance();
        return $user_roles->get_available_users('tpak_manage_system');
    }
    
    /**
     * บันทึก email notification log
     */
    private function log_email_notification($template_key, $recipients, $data, $sent_count) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_verification_logs';
        
        $wpdb->insert($table, array(
            'batch_id' => 0,
            'response_id' => 'email_notification',
            'verifier_id' => get_current_user_id(),
            'verification_action' => 'email_sent',
            'verification_status' => 'completed',
            'verification_notes' => json_encode(array(
                'template' => $template_key,
                'recipients' => $recipients,
                'data' => $data,
                'sent_count' => $sent_count
            )),
            'verification_date' => current_time('mysql')
        ));
    }
    
    /**
     * แสดง admin notices
     */
    public function display_admin_notices() {
        $user_id = get_current_user_id();
        $unread_count = $this->get_unread_count($user_id);
        
        if ($unread_count > 0) {
            $notifications = $this->get_user_notifications($user_id, 5, true);
            
            foreach ($notifications as $notification) {
                $this->display_notification_notice($notification);
            }
        }
    }
    
    /**
     * แสดง notification notice
     */
    private function display_notification_notice($notification) {
        $notice_class = 'notice-info';
        
        switch ($notification->type) {
            case 'workflow_state_change':
                $notice_class = 'notice-success';
                break;
            case 'quality_check_failed':
                $notice_class = 'notice-error';
                break;
            case 'system_alert':
                $notice_class = 'notice-warning';
                break;
        }
        
        echo '<div class="notice ' . $notice_class . ' is-dismissible tpak-notification-notice" data-notification-id="' . $notification->id . '">';
        echo '<p><strong>' . esc_html($notification->title) . '</strong><br>';
        echo esc_html($notification->message) . '</p>';
        echo '</div>';
    }
    
    /**
     * จัดการ AJAX get notifications
     */
    public function handle_get_notifications() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_notifications')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $user_id = get_current_user_id();
        $unread_only = isset($_POST['unread_only']) ? (bool)$_POST['unread_only'] : false;
        $limit = intval($_POST['limit'] ?? 50);
        
        $notifications = $this->get_user_notifications($user_id, $limit, $unread_only);
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'unread_count' => $this->get_unread_count($user_id)
        ));
    }
    
    /**
     * จัดการ AJAX mark notification read
     */
    public function handle_mark_notification_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_notifications')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        $result = $this->mark_notification_read($notification_id, $user_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Notification marked as read', 'tpak-dq-system')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to mark notification as read', 'tpak-dq-system')
            ));
        }
    }
    
    /**
     * จัดการ AJAX delete notification
     */
    public function handle_delete_notification() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_notifications')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        $result = $this->delete_notification($notification_id, $user_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Notification deleted', 'tpak-dq-system')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete notification', 'tpak-dq-system')
            ));
        }
    }
    
    /**
     * จัดการ AJAX dismiss notice
     */
    public function handle_dismiss_notice() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_notifications')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        $this->mark_notification_read($notification_id, $user_id);
        
        wp_send_json_success(array(
            'message' => __('Notice dismissed', 'tpak-dq-system')
        ));
    }
} 