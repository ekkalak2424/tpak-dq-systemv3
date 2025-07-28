<?php
/**
 * TPAK DQ System - Workflow Engine
 * 
 * จัดการ Workflow Engine และ State Machine
 * Phase 5: Workflow Engine (3 สัปดาห์)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Workflow {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * States ที่กำหนดไว้
     */
    private $states = array(
        'pending' => array(
            'name' => 'Pending',
            'description' => 'รอการตรวจสอบ',
            'allowed_roles' => array('verifier', 'approver', 'examiner', 'manager'),
            'next_states' => array('interviewing', 'completed')
        ),
        'interviewing' => array(
            'name' => 'Interviewing',
            'description' => 'กำลังสัมภาษณ์',
            'allowed_roles' => array('verifier', 'approver', 'examiner', 'manager'),
            'next_states' => array('supervising', 'completed')
        ),
        'supervising' => array(
            'name' => 'Supervising',
            'description' => 'กำลังตรวจสอบ',
            'allowed_roles' => array('approver', 'examiner', 'manager'),
            'next_states' => array('examining', 'completed')
        ),
        'examining' => array(
            'name' => 'Examining',
            'description' => 'กำลังตรวจสอบขั้นสุดท้าย',
            'allowed_roles' => array('examiner', 'manager'),
            'next_states' => array('completed')
        ),
        'completed' => array(
            'name' => 'Completed',
            'description' => 'เสร็จสิ้น',
            'allowed_roles' => array('verifier', 'approver', 'examiner', 'manager'),
            'next_states' => array()
        )
    );
    
    /**
     * Actions ที่กำหนดไว้
     */
    private $actions = array(
        'approve' => array(
            'name' => 'Approve',
            'description' => 'อนุมัติ',
            'allowed_roles' => array('approver', 'examiner', 'manager')
        ),
        'reject' => array(
            'name' => 'Reject',
            'description' => 'ปฏิเสธ',
            'allowed_roles' => array('verifier', 'approver', 'examiner', 'manager')
        ),
        'request_revision' => array(
            'name' => 'Request Revision',
            'description' => 'ขอให้แก้ไข',
            'allowed_roles' => array('verifier', 'approver', 'examiner', 'manager')
        ),
        'complete' => array(
            'name' => 'Complete',
            'description' => 'เสร็จสิ้น',
            'allowed_roles' => array('examiner', 'manager')
        )
    );
    
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
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_tpak_workflow_transition', array($this, 'handle_workflow_transition'));
        add_action('wp_ajax_tpak_workflow_action', array($this, 'handle_workflow_action'));
        add_action('wp_ajax_tpak_get_workflow_status', array($this, 'handle_get_workflow_status'));
    }
    
    /**
     * สร้าง workflow สำหรับ batch
     */
    public function create_workflow($batch_id, $initial_state = 'pending') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_workflow_status';
        
        $result = $wpdb->insert($table, array(
            'batch_id' => $batch_id,
            'current_state' => $initial_state,
            'previous_state' => null,
            'state_data' => json_encode(array()),
            'assigned_role' => $this->get_role_for_state($initial_state),
            'assigned_user' => null,
            'state_entered_at' => current_time('mysql'),
            'workflow_completed' => 0
        ));
        
        if ($result) {
            $this->log_workflow_event($batch_id, 'workflow_created', array(
                'initial_state' => $initial_state
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * เปลี่ยนสถานะ workflow
     */
    public function transition_state($batch_id, $new_state, $user_id = null, $notes = '') {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // ตรวจสอบสิทธิ์
        if (!$this->can_transition_state($batch_id, $new_state, $user_id)) {
            return array(
                'success' => false,
                'message' => __('Insufficient permissions for this transition', 'tpak-dq-system')
            );
        }
        
        // ตรวจสอบว่า state ใหม่ถูกต้องหรือไม่
        if (!isset($this->states[$new_state])) {
            return array(
                'success' => false,
                'message' => __('Invalid state', 'tpak-dq-system')
            );
        }
        
        $table = $wpdb->prefix . 'tpak_workflow_status';
        
        // รับสถานะปัจจุบัน
        $current_workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE batch_id = %d",
            $batch_id
        ));
        
        if (!$current_workflow) {
            return array(
                'success' => false,
                'message' => __('Workflow not found', 'tpak-dq-system')
            );
        }
        
        // ตรวจสอบว่า transition ถูกต้องหรือไม่
        if (!in_array($new_state, $this->states[$current_workflow->current_state]['next_states'])) {
            return array(
                'success' => false,
                'message' => __('Invalid transition', 'tpak-dq-system')
            );
        }
        
        // อัพเดทสถานะ
        $result = $wpdb->update($table, array(
            'previous_state' => $current_workflow->current_state,
            'current_state' => $new_state,
            'state_data' => json_encode(array(
                'transition_notes' => $notes,
                'transition_user' => $user_id,
                'transition_date' => current_time('mysql')
            )),
            'assigned_role' => $this->get_role_for_state($new_state),
            'assigned_user' => $user_id,
            'state_updated_at' => current_time('mysql'),
            'workflow_completed' => ($new_state === 'completed') ? 1 : 0
        ), array(
            'batch_id' => $batch_id
        ));
        
        if ($result !== false) {
            // บันทึก log
            $this->log_workflow_event($batch_id, 'state_transition', array(
                'from_state' => $current_workflow->current_state,
                'to_state' => $new_state,
                'user_id' => $user_id,
                'notes' => $notes
            ));
            
            // ส่ง notification
            $this->send_state_notification($batch_id, $new_state, $user_id);
            
            return array(
                'success' => true,
                'message' => sprintf(__('State changed to: %s', 'tpak-dq-system'), $this->states[$new_state]['name']),
                'new_state' => $new_state
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to update workflow state', 'tpak-dq-system')
        );
    }
    
    /**
     * ดำเนินการ action
     */
    public function perform_action($batch_id, $action, $user_id = null, $action_data = array()) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // ตรวจสอบสิทธิ์
        if (!$this->can_perform_action($batch_id, $action, $user_id)) {
            return array(
                'success' => false,
                'message' => __('Insufficient permissions for this action', 'tpak-dq-system')
            );
        }
        
        // ตรวจสอบว่า action ถูกต้องหรือไม่
        if (!isset($this->actions[$action])) {
            return array(
                'success' => false,
                'message' => __('Invalid action', 'tpak-dq-system')
            );
        }
        
        // ดำเนินการตาม action
        switch ($action) {
            case 'approve':
                return $this->handle_approve_action($batch_id, $user_id, $action_data);
            case 'reject':
                return $this->handle_reject_action($batch_id, $user_id, $action_data);
            case 'request_revision':
                return $this->handle_revision_action($batch_id, $user_id, $action_data);
            case 'complete':
                return $this->handle_complete_action($batch_id, $user_id, $action_data);
            default:
                return array(
                    'success' => false,
                    'message' => __('Unknown action', 'tpak-dq-system')
                );
        }
    }
    
    /**
     * จัดการ approve action
     */
    private function handle_approve_action($batch_id, $user_id, $action_data) {
        $current_state = $this->get_current_state($batch_id);
        $next_state = $this->get_next_state_for_action($current_state, 'approve');
        
        if (!$next_state) {
            return array(
                'success' => false,
                'message' => __('Cannot approve in current state', 'tpak-dq-system')
            );
        }
        
        $result = $this->transition_state($batch_id, $next_state, $user_id, $action_data['notes'] ?? '');
        
        if ($result['success']) {
            // อัพเดท batch status
            $this->update_batch_status($batch_id, 'approved');
        }
        
        return $result;
    }
    
    /**
     * จัดการ reject action
     */
    private function handle_reject_action($batch_id, $user_id, $action_data) {
        $result = $this->transition_state($batch_id, 'pending', $user_id, $action_data['notes'] ?? '');
        
        if ($result['success']) {
            // อัพเดท batch status
            $this->update_batch_status($batch_id, 'rejected');
        }
        
        return $result;
    }
    
    /**
     * จัดการ revision action
     */
    private function handle_revision_action($batch_id, $user_id, $action_data) {
        $current_state = $this->get_current_state($batch_id);
        $next_state = $this->get_next_state_for_action($current_state, 'request_revision');
        
        if (!$next_state) {
            return array(
                'success' => false,
                'message' => __('Cannot request revision in current state', 'tpak-dq-system')
            );
        }
        
        $result = $this->transition_state($batch_id, $next_state, $user_id, $action_data['notes'] ?? '');
        
        if ($result['success']) {
            // อัพเดท batch status
            $this->update_batch_status($batch_id, 'revision_requested');
        }
        
        return $result;
    }
    
    /**
     * จัดการ complete action
     */
    private function handle_complete_action($batch_id, $user_id, $action_data) {
        $result = $this->transition_state($batch_id, 'completed', $user_id, $action_data['notes'] ?? '');
        
        if ($result['success']) {
            // อัพเดท batch status
            $this->update_batch_status($batch_id, 'completed');
        }
        
        return $result;
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถเปลี่ยนสถานะได้หรือไม่
     */
    public function can_transition_state($batch_id, $new_state, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user_roles = TPAK_User_Roles::get_instance();
        $user_role = $user_roles->get_tpak_role($user_id);
        
        return in_array($user_role, $this->states[$new_state]['allowed_roles']);
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถดำเนินการ action ได้หรือไม่
     */
    public function can_perform_action($batch_id, $action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user_roles = TPAK_User_Roles::get_instance();
        $user_role = $user_roles->get_tpak_role($user_id);
        
        return in_array($user_role, $this->actions[$action]['allowed_roles']);
    }
    
    /**
     * รับสถานะปัจจุบัน
     */
    public function get_current_state($batch_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_workflow_status';
        
        $state = $wpdb->get_var($wpdb->prepare(
            "SELECT current_state FROM $table WHERE batch_id = %d",
            $batch_id
        ));
        
        return $state ?: 'pending';
    }
    
    /**
     * รับข้อมูล workflow
     */
    public function get_workflow_data($batch_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_workflow_status';
        
        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE batch_id = %d",
            $batch_id
        ));
        
        if (!$workflow) {
            return null;
        }
        
        return array(
            'batch_id' => $workflow->batch_id,
            'current_state' => $workflow->current_state,
            'previous_state' => $workflow->previous_state,
            'state_data' => json_decode($workflow->state_data, true),
            'assigned_role' => $workflow->assigned_role,
            'assigned_user' => $workflow->assigned_user,
            'state_entered_at' => $workflow->state_entered_at,
            'state_updated_at' => $workflow->state_updated_at,
            'workflow_completed' => $workflow->workflow_completed
        );
    }
    
    /**
     * รับรายการ actions ที่สามารถทำได้
     */
    public function get_available_actions($batch_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $current_state = $this->get_current_state($batch_id);
        $available_actions = array();
        
        foreach ($this->actions as $action_key => $action_data) {
            if ($this->can_perform_action($batch_id, $action_key, $user_id)) {
                $available_actions[$action_key] = $action_data;
            }
        }
        
        return $available_actions;
    }
    
    /**
     * รับรายการ states ที่สามารถเปลี่ยนไปได้
     */
    public function get_available_transitions($batch_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $current_state = $this->get_current_state($batch_id);
        $available_transitions = array();
        
        foreach ($this->states[$current_state]['next_states'] as $next_state) {
            if ($this->can_transition_state($batch_id, $next_state, $user_id)) {
                $available_transitions[$next_state] = $this->states[$next_state];
            }
        }
        
        return $available_transitions;
    }
    
    /**
     * รับ role สำหรับ state
     */
    private function get_role_for_state($state) {
        if (isset($this->states[$state])) {
            return $this->states[$state]['allowed_roles'][0];
        }
        
        return 'verifier';
    }
    
    /**
     * รับ state ถัดไปสำหรับ action
     */
    private function get_next_state_for_action($current_state, $action) {
        $state_transitions = array(
            'pending' => array(
                'approve' => 'interviewing',
                'request_revision' => 'pending'
            ),
            'interviewing' => array(
                'approve' => 'supervising',
                'request_revision' => 'pending'
            ),
            'supervising' => array(
                'approve' => 'examining',
                'request_revision' => 'interviewing'
            ),
            'examining' => array(
                'approve' => 'completed',
                'request_revision' => 'supervising'
            )
        );
        
        return isset($state_transitions[$current_state][$action]) ? $state_transitions[$current_state][$action] : null;
    }
    
    /**
     * อัพเดท batch status
     */
    private function update_batch_status($batch_id, $status) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_verification_batches';
        
        $wpdb->update($table, array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        ), array(
            'id' => $batch_id
        ));
    }
    
    /**
     * บันทึก workflow event
     */
    private function log_workflow_event($batch_id, $event_type, $event_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_verification_logs';
        
        $wpdb->insert($table, array(
            'batch_id' => $batch_id,
            'response_id' => 'workflow_event',
            'verifier_id' => get_current_user_id(),
            'verification_action' => $event_type,
            'verification_status' => 'completed',
            'verification_notes' => json_encode($event_data),
            'verification_date' => current_time('mysql')
        ));
    }
    
    /**
     * ส่ง notification สำหรับ state change
     */
    private function send_state_notification($batch_id, $new_state, $user_id) {
        // ส่ง email notification
        $this->send_email_notification($batch_id, $new_state, $user_id);
        
        // ส่ง in-app notification
        $this->send_inapp_notification($batch_id, $new_state, $user_id);
    }
    
    /**
     * ส่ง email notification
     */
    private function send_email_notification($batch_id, $new_state, $user_id) {
        $workflow_data = $this->get_workflow_data($batch_id);
        $user = get_userdata($user_id);
        
        $subject = sprintf(__('Workflow State Changed - Batch %d', 'tpak-dq-system'), $batch_id);
        $message = sprintf(
            __('The workflow state for batch %d has been changed to %s by %s.', 'tpak-dq-system'),
            $batch_id,
            $this->states[$new_state]['name'],
            $user->display_name
        );
        
        $admin_email = get_option('admin_email');
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * ส่ง in-app notification
     */
    private function send_inapp_notification($batch_id, $new_state, $user_id) {
        // บันทึก notification ในฐานข้อมูล
        $this->save_notification($batch_id, $new_state, $user_id);
    }
    
    /**
     * บันทึก notification
     */
    private function save_notification($batch_id, $new_state, $user_id) {
        // Implementation สำหรับบันทึก notification
        // สามารถเพิ่มตาราง notifications ได้ในอนาคต
    }
    
    /**
     * จัดการ AJAX workflow transition
     */
    public function handle_workflow_transition() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_transition')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $batch_id = intval($_POST['batch_id']);
        $new_state = sanitize_text_field($_POST['new_state']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $result = $this->transition_state($batch_id, $new_state, null, $notes);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * จัดการ AJAX workflow action
     */
    public function handle_workflow_action() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_workflow_action')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $batch_id = intval($_POST['batch_id']);
        $action = sanitize_text_field($_POST['action']);
        $action_data = array(
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        $result = $this->perform_action($batch_id, $action, null, $action_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * จัดการ AJAX get workflow status
     */
    public function handle_get_workflow_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_get_workflow_status')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $batch_id = intval($_POST['batch_id']);
        
        $workflow_data = $this->get_workflow_data($batch_id);
        $available_actions = $this->get_available_actions($batch_id);
        $available_transitions = $this->get_available_transitions($batch_id);
        
        wp_send_json_success(array(
            'workflow_data' => $workflow_data,
            'available_actions' => $available_actions,
            'available_transitions' => $available_transitions
        ));
    }
} 