<?php
/**
 * Questionnaire Manager Class สำหรับ TPAK DQ System v3
 * 
 * จัดการการ sync และจัดการแบบสอบถามจาก LimeSurvey
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Questionnaire_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * LimeSurvey API instance
     */
    private $limesurvey_api;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $core = TPAK_DQ_Core::get_instance();
        $this->limesurvey_api = $core->get_limesurvey_api();
    }
    
    /**
     * Sync แบบสอบถามจาก LimeSurvey
     */
    public function sync_questionnaires($questionnaires = null) {
        global $wpdb;
        
        if ($questionnaires === null) {
            try {
                $questionnaires = $this->limesurvey_api->get_surveys();
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        $synced_count = 0;
        $updated_count = 0;
        $errors = array();
        
        foreach ($questionnaires as $questionnaire) {
            try {
                // ตรวจสอบว่าแบบสอบถามมีอยู่แล้วหรือไม่
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE limesurvey_id = %s",
                    $questionnaire['id']
                ));
                
                $data = array(
                    'limesurvey_id' => $questionnaire['id'],
                    'title' => $questionnaire['title'],
                    'description' => $questionnaire['description'],
                    'status' => $questionnaire['status'],
                    'updated_at' => current_time('mysql')
                );
                
                if ($existing) {
                    // อัปเดตแบบสอบถามที่มีอยู่
                    $result = $wpdb->update(
                        $table_name,
                        $data,
                        array('id' => $existing->id),
                        array('%s', '%s', '%s', '%s', '%s'),
                        array('%d')
                    );
                    
                    if ($result !== false) {
                        $updated_count++;
                    }
                } else {
                    // เพิ่มแบบสอบถามใหม่
                    $data['created_at'] = current_time('mysql');
                    
                    $result = $wpdb->insert(
                        $table_name,
                        $data,
                        array('%s', '%s', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($result !== false) {
                        $synced_count++;
                    }
                }
                
                if ($result === false) {
                    $errors[] = sprintf(
                        __('Failed to sync questionnaire: %s', 'tpak-dq-system'),
                        $questionnaire['title']
                    );
                }
                
            } catch (Exception $e) {
                $errors[] = sprintf(
                    __('Error syncing questionnaire %s: %s', 'tpak-dq-system'),
                    $questionnaire['title'],
                    $e->getMessage()
                );
            }
        }
        
        return array(
            'success' => empty($errors),
            'message' => sprintf(
                __('Synced %d new questionnaires, updated %d existing questionnaires.', 'tpak-dq-system'),
                $synced_count,
                $updated_count
            ),
            'synced_count' => $synced_count,
            'updated_count' => $updated_count,
            'errors' => $errors
        );
    }
    
    /**
     * Sync แบบสอบถามเดียว
     */
    public function sync_single_questionnaire($questionnaire_id) {
        try {
            $survey_details = $this->limesurvey_api->get_survey_details($questionnaire_id);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'tpak_questionnaires';
            
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE limesurvey_id = %s",
                $questionnaire_id
            ));
            
            $data = array(
                'limesurvey_id' => $survey_details['id'],
                'title' => $survey_details['title'],
                'description' => $survey_details['description'],
                'status' => $this->map_status($survey_details['active']),
                'updated_at' => current_time('mysql')
            );
            
            if ($existing) {
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    array('id' => $existing->id),
                    array('%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
                
                $message = __('Questionnaire updated successfully.', 'tpak-dq-system');
            } else {
                $data['created_at'] = current_time('mysql');
                
                $result = $wpdb->insert(
                    $table_name,
                    $data,
                    array('%s', '%s', '%s', '%s', '%s', '%s')
                );
                
                $message = __('Questionnaire synced successfully.', 'tpak-dq-system');
            }
            
            if ($result === false) {
                return array(
                    'success' => false,
                    'message' => __('Failed to sync questionnaire.', 'tpak-dq-system')
                );
            }
            
            return array(
                'success' => true,
                'message' => $message
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * รับรายการแบบสอบถาม
     */
    public function get_questionnaires($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        $where_clause = "WHERE 1=1";
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clause .= " AND status = %s";
            $where_values[] = $args['status'];
        }
        
        $order_clause = sprintf(
            "ORDER BY %s %s",
            esc_sql($args['orderby']),
            esc_sql($args['order'])
        );
        
        $limit_clause = sprintf(
            "LIMIT %d OFFSET %d",
            intval($args['limit']),
            intval($args['offset'])
        );
        
        $query = "SELECT * FROM {$table_name} {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * รับแบบสอบถามตาม ID
     */
    public function get_questionnaire($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * รับแบบสอบถามตาม LimeSurvey ID
     */
    public function get_questionnaire_by_limesurvey_id($limesurvey_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE limesurvey_id = %s",
            $limesurvey_id
        ));
    }
    
    /**
     * รับแบบสอบถามที่ใช้งานอยู่
     */
    public function get_active_questionnaires() {
        return $this->get_questionnaires(array('status' => 'active'));
    }
    
    /**
     * อัปเดตสถานะแบบสอบถาม
     */
    public function update_questionnaire_status($id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * ลบแบบสอบถาม
     */
    public function delete_questionnaire($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * รับสถิติแบบสอบถาม
     */
    public function get_questionnaire_statistics($questionnaire_id) {
        try {
            $questionnaire = $this->get_questionnaire($questionnaire_id);
            
            if (!$questionnaire) {
                throw new Exception(__('Questionnaire not found.', 'tpak-dq-system'));
            }
            
            $statistics = $this->limesurvey_api->get_survey_statistics($questionnaire->limesurvey_id);
            
            return array(
                'success' => true,
                'data' => $statistics
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * รับคำถามของแบบสอบถาม
     */
    public function get_questionnaire_questions($questionnaire_id) {
        try {
            $questionnaire = $this->get_questionnaire($questionnaire_id);
            
            if (!$questionnaire) {
                throw new Exception(__('Questionnaire not found.', 'tpak-dq-system'));
            }
            
            $questions = $this->limesurvey_api->get_survey_questions($questionnaire->limesurvey_id);
            
            return array(
                'success' => true,
                'data' => $questions
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * รับคำตอบของแบบสอบถาม
     */
    public function get_questionnaire_responses($questionnaire_id, $params = array()) {
        try {
            $questionnaire = $this->get_questionnaire($questionnaire_id);
            
            if (!$questionnaire) {
                throw new Exception(__('Questionnaire not found.', 'tpak-dq-system'));
            }
            
            $responses = $this->limesurvey_api->get_survey_responses($questionnaire->limesurvey_id, $params);
            
            return array(
                'success' => true,
                'data' => $responses
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * รับจำนวนแบบสอบถามทั้งหมด
     */
    public function get_questionnaires_count($status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        $where_clause = "WHERE 1=1";
        $where_values = array();
        
        if ($status !== null) {
            $where_clause .= " AND status = %s";
            $where_values[] = $status;
        }
        
        $query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * แปลงสถานะจาก LimeSurvey เป็นสถานะภายใน
     */
    private function map_status($limesurvey_status) {
        switch ($limesurvey_status) {
            case 'Y':
                return 'active';
            case 'N':
                return 'inactive';
            case 'E':
                return 'expired';
            default:
                return 'unknown';
        }
    }
    
    /**
     * ตรวจสอบว่าแบบสอบถามมีอยู่หรือไม่
     */
    public function questionnaire_exists($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE id = %d",
            $id
        ));
        
        return $count > 0;
    }
    
    /**
     * รับแบบสอบถามที่ต้องการการตรวจสอบคุณภาพข้อมูล
     */
    public function get_questionnaires_for_quality_check() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_questionnaires';
        $checks_table = $wpdb->prefix . 'tpak_quality_checks';
        
        $query = "
            SELECT DISTINCT q.* 
            FROM {$table_name} q
            INNER JOIN {$checks_table} c ON q.id = c.questionnaire_id
            WHERE q.status = 'active' 
            AND c.is_active = 1
        ";
        
        return $wpdb->get_results($query);
    }
} 