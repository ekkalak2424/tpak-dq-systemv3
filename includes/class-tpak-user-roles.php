<?php
/**
 * TPAK DQ System - User Roles & Permissions Management
 * 
 * จัดการ User Roles และ Permissions สำหรับระบบ TPAK DQ System
 * Phase 2: User Roles & Permissions (1 สัปดาห์)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_User_Roles {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
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
        add_action('init', array($this, 'register_custom_roles'));
        add_action('admin_init', array($this, 'add_custom_capabilities'));
        add_action('wp_ajax_tpak_switch_role', array($this, 'handle_role_switching'));
        add_action('wp_ajax_nopriv_tpak_switch_role', array($this, 'handle_role_switching'));
    }
    
    /**
     * ลงทะเบียน Custom Roles
     */
    public function register_custom_roles() {
        // Role A: Data Verifier
        add_role('tpak_verifier', __('TPAK Data Verifier', 'tpak-dq-system'), array(
            'read' => true,
            'tpak_view_dashboard' => true,
            'tpak_verify_data' => true,
        ));
        
        // Role B: Data Approver
        add_role('tpak_approver', __('TPAK Data Approver', 'tpak-dq-system'), array(
            'read' => true,
            'tpak_view_dashboard' => true,
            'tpak_verify_data' => true,
            'tpak_approve_data' => true,
        ));
        
        // Role C: Data Examiner
        add_role('tpak_examiner', __('TPAK Data Examiner', 'tpak-dq-system'), array(
            'read' => true,
            'tpak_view_dashboard' => true,
            'tpak_verify_data' => true,
            'tpak_approve_data' => true,
            'tpak_examine_data' => true,
        ));
        
        // Admin Role: System Manager
        add_role('tpak_manager', __('TPAK System Manager', 'tpak-dq-system'), array(
            'read' => true,
            'tpak_view_dashboard' => true,
            'tpak_verify_data' => true,
            'tpak_approve_data' => true,
            'tpak_examine_data' => true,
            'tpak_manage_system' => true,
        ));
    }
    
    /**
     * เพิ่ม Custom Capabilities ให้กับ Administrator
     */
    public function add_custom_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('tpak_view_dashboard');
            $admin_role->add_cap('tpak_verify_data');
            $admin_role->add_cap('tpak_approve_data');
            $admin_role->add_cap('tpak_examine_data');
            $admin_role->add_cap('tpak_manage_system');
        }
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้มี capability หรือไม่
     */
    public function user_can($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, $capability);
    }
    
    /**
     * รับ role ของผู้ใช้
     */
    public function get_user_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $roles = $user->roles;
        return !empty($roles) ? $roles[0] : false;
    }
    
    /**
     * รับ TPAK role ของผู้ใช้
     */
    public function get_tpak_role($user_id = null) {
        $role = $this->get_user_role($user_id);
        
        $tpak_roles = array(
            'tpak_verifier' => 'verifier',
            'tpak_approver' => 'approver',
            'tpak_examiner' => 'examiner',
            'tpak_manager' => 'manager',
            'administrator' => 'manager'
        );
        
        return isset($tpak_roles[$role]) ? $tpak_roles[$role] : 'guest';
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถเข้าถึง workflow state ได้หรือไม่
     */
    public function can_access_workflow_state($state, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user_role = $this->get_tpak_role($user_id);
        
        $state_permissions = array(
            'pending' => array('verifier', 'approver', 'examiner', 'manager'),
            'interviewing' => array('verifier', 'approver', 'examiner', 'manager'),
            'supervising' => array('approver', 'examiner', 'manager'),
            'examining' => array('examiner', 'manager'),
            'completed' => array('verifier', 'approver', 'examiner', 'manager')
        );
        
        return isset($state_permissions[$state]) && in_array($user_role, $state_permissions[$state]);
    }
    
    /**
     * รับรายการผู้ใช้ตาม role
     */
    public function get_users_by_role($role) {
        $args = array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        return get_users($args);
    }
    
    /**
     * รับรายการผู้ใช้ที่สามารถทำงานได้
     */
    public function get_available_users($capability = null) {
        $roles = array('tpak_verifier', 'tpak_approver', 'tpak_examiner', 'tpak_manager', 'administrator');
        $users = array();
        
        foreach ($roles as $role) {
            $role_users = $this->get_users_by_role($role);
            foreach ($role_users as $user) {
                if (!$capability || $this->user_can($capability, $user->ID)) {
                    $users[] = $user;
                }
            }
        }
        
        return $users;
    }
    
    /**
     * จัดการ role switching
     */
    public function handle_role_switching() {
        // ตรวจสอบ nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_role_switch')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $user_id = get_current_user_id();
        $new_role = sanitize_text_field($_POST['new_role']);
        
        // ตรวจสอบว่าผู้ใช้มีสิทธิ์เปลี่ยน role หรือไม่
        if (!$this->user_can('tpak_manage_system') && !$this->user_can('tpak_examine_data')) {
            wp_die(__('Insufficient permissions', 'tpak-dq-system'));
        }
        
        // ตรวจสอบว่า role ใหม่ถูกต้องหรือไม่
        $valid_roles = array('tpak_verifier', 'tpak_approver', 'tpak_examiner', 'tpak_manager');
        if (!in_array($new_role, $valid_roles)) {
            wp_die(__('Invalid role', 'tpak-dq-system'));
        }
        
        // เปลี่ยน role
        $user = get_userdata($user_id);
        $user->set_role($new_role);
        
        // บันทึก log
        $this->log_role_change($user_id, $new_role);
        
        wp_send_json_success(array(
            'message' => __('Role changed successfully', 'tpak-dq-system'),
            'new_role' => $new_role
        ));
    }
    
    /**
     * บันทึก log การเปลี่ยน role
     */
    private function log_role_change($user_id, $new_role) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_verification_logs';
        
        $wpdb->insert($table, array(
            'batch_id' => 0,
            'response_id' => 'role_change',
            'verifier_id' => $user_id,
            'verification_action' => 'role_switch',
            'verification_status' => 'completed',
            'verification_notes' => sprintf(__('Role changed to: %s', 'tpak-dq-system'), $new_role),
            'verification_date' => current_time('mysql')
        ));
    }
    
    /**
     * รับรายการ capabilities ทั้งหมด
     */
    public function get_all_capabilities() {
        return array(
            'tpak_view_dashboard' => __('View Dashboard', 'tpak-dq-system'),
            'tpak_verify_data' => __('Verify Data', 'tpak-dq-system'),
            'tpak_approve_data' => __('Approve Data', 'tpak-dq-system'),
            'tpak_examine_data' => __('Examine Data', 'tpak-dq-system'),
            'tpak_manage_system' => __('Manage System', 'tpak-dq-system')
        );
    }
    
    /**
     * รับรายการ roles ทั้งหมด
     */
    public function get_all_roles() {
        return array(
            'tpak_verifier' => __('TPAK Data Verifier', 'tpak-dq-system'),
            'tpak_approver' => __('TPAK Data Approver', 'tpak-dq-system'),
            'tpak_examiner' => __('TPAK Data Examiner', 'tpak-dq-system'),
            'tpak_manager' => __('TPAK System Manager', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถจัดการระบบได้หรือไม่
     */
    public function is_system_manager($user_id = null) {
        return $this->user_can('tpak_manage_system', $user_id);
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถตรวจสอบข้อมูลได้หรือไม่
     */
    public function can_verify_data($user_id = null) {
        return $this->user_can('tpak_verify_data', $user_id);
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถอนุมัติข้อมูลได้หรือไม่
     */
    public function can_approve_data($user_id = null) {
        return $this->user_can('tpak_approve_data', $user_id);
    }
    
    /**
     * ตรวจสอบว่าผู้ใช้สามารถตรวจสอบข้อมูลได้หรือไม่
     */
    public function can_examine_data($user_id = null) {
        return $this->user_can('tpak_examine_data', $user_id);
    }
} 