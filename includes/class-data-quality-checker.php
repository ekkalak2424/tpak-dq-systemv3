<?php
/**
 * Data Quality Checker Class สำหรับ TPAK DQ System v3
 * 
 * จัดการการตรวจสอบคุณภาพข้อมูลจากแบบสอบถาม LimeSurvey
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Data_Quality_Checker {
    
    /**
     * LimeSurvey API instance
     */
    private $limesurvey_api;
    
    /**
     * Questionnaire Manager instance
     */
    private $questionnaire_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $core = TPAK_DQ_Core::get_instance();
        $this->limesurvey_api = $core->get_limesurvey_api();
        $this->questionnaire_manager = $core->get_questionnaire_manager();
    }
    
    /**
     * รันการตรวจสอบคุณภาพข้อมูลสำหรับแบบสอบถาม
     */
    public function run_checks_for_questionnaire($questionnaire_id) {
        try {
            $questionnaire = $this->questionnaire_manager->get_questionnaire($questionnaire_id);
            
            if (!$questionnaire) {
                throw new Exception(__('Questionnaire not found.', 'tpak-dq-system'));
            }
            
            // รับการตรวจสอบที่กำหนดไว้
            $checks = $this->get_quality_checks($questionnaire_id);
            
            if (empty($checks)) {
                return array(
                    'success' => true,
                    'message' => __('No quality checks configured for this questionnaire.', 'tpak-dq-system'),
                    'results' => array()
                );
            }
            
            // รับคำตอบของแบบสอบถาม
            $responses = $this->limesurvey_api->get_survey_responses($questionnaire->limesurvey_id);
            
            $results = array();
            $total_checks = 0;
            
            foreach ($responses as $response) {
                foreach ($checks as $check) {
                    $result = $this->run_single_check($check, $response, $questionnaire);
                    
                    if ($result) {
                        $this->save_check_result($questionnaire_id, $check->id, $response['id'], $result);
                        $results[] = $result;
                        $total_checks++;
                    }
                }
            }
            
            return array(
                'success' => true,
                'message' => sprintf(__('Ran %d quality checks for %d responses.', 'tpak-dq-system'), $total_checks, count($responses)),
                'results' => $results,
                'total_checks' => $total_checks,
                'total_responses' => count($responses)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * รันการตรวจสอบเดียว
     */
    private function run_single_check($check, $response, $questionnaire) {
        $check_config = json_decode($check->check_config, true);
        $check_type = $check->check_type;
        
        switch ($check_type) {
            case 'completeness':
                return $this->check_completeness($check_config, $response);
                
            case 'consistency':
                return $this->check_consistency($check_config, $response);
                
            case 'validity':
                return $this->check_validity($check_config, $response);
                
            case 'accuracy':
                return $this->check_accuracy($check_config, $response);
                
            case 'timeliness':
                return $this->check_timeliness($check_config, $response);
                
            case 'uniqueness':
                return $this->check_uniqueness($check_config, $response);
                
            case 'custom':
                return $this->check_custom($check_config, $response);
                
            default:
                return null;
        }
    }
    
    /**
     * ตรวจสอบความสมบูรณ์ของข้อมูล
     */
    private function check_completeness($config, $response) {
        $required_fields = $config['required_fields'] ?? array();
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (!isset($response['answers'][$field]) || empty($response['answers'][$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Missing required fields: %s', 'tpak-dq-system'), implode(', ', $missing_fields)),
                'details' => array(
                    'missing_fields' => $missing_fields,
                    'required_fields' => $required_fields
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All required fields are completed.', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบความสอดคล้องของข้อมูล
     */
    private function check_consistency($config, $response) {
        $consistency_rules = $config['rules'] ?? array();
        $violations = array();
        
        foreach ($consistency_rules as $rule) {
            $field1 = $rule['field1'];
            $field2 = $rule['field2'];
            $operator = $rule['operator'];
            $condition = $rule['condition'];
            
            $value1 = $response['answers'][$field1] ?? null;
            $value2 = $response['answers'][$field2] ?? null;
            
            if (!$this->evaluate_consistency_rule($value1, $value2, $operator, $condition)) {
                $violations[] = array(
                    'rule' => $rule,
                    'value1' => $value1,
                    'value2' => $value2
                );
            }
        }
        
        if (!empty($violations)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Found %d consistency violations.', 'tpak-dq-system'), count($violations)),
                'details' => array(
                    'violations' => $violations
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All consistency rules passed.', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบความถูกต้องของข้อมูล
     */
    private function check_validity($config, $response) {
        $validation_rules = $config['rules'] ?? array();
        $errors = array();
        
        foreach ($validation_rules as $field => $rules) {
            $value = $response['answers'][$field] ?? null;
            
            foreach ($rules as $rule) {
                if (!$this->validate_field($value, $rule)) {
                    $errors[] = array(
                        'field' => $field,
                        'value' => $value,
                        'rule' => $rule,
                        'message' => $this->get_validation_message($rule)
                    );
                }
            }
        }
        
        if (!empty($errors)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Found %d validation errors.', 'tpak-dq-system'), count($errors)),
                'details' => array(
                    'errors' => $errors
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All validation rules passed.', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบความแม่นยำของข้อมูล
     */
    private function check_accuracy($config, $response) {
        $accuracy_rules = $config['rules'] ?? array();
        $issues = array();
        
        foreach ($accuracy_rules as $rule) {
            $field = $rule['field'];
            $expected_range = $rule['expected_range'] ?? null;
            $reference_field = $rule['reference_field'] ?? null;
            
            $value = $response['answers'][$field] ?? null;
            
            if ($expected_range && !$this->is_in_range($value, $expected_range)) {
                $issues[] = array(
                    'field' => $field,
                    'value' => $value,
                    'expected_range' => $expected_range,
                    'type' => 'range_violation'
                );
            }
            
            if ($reference_field) {
                $reference_value = $response['answers'][$reference_field] ?? null;
                if (!$this->is_accurate_compared_to($value, $reference_value, $rule)) {
                    $issues[] = array(
                        'field' => $field,
                        'value' => $value,
                        'reference_field' => $reference_field,
                        'reference_value' => $reference_value,
                        'type' => 'reference_violation'
                    );
                }
            }
        }
        
        if (!empty($issues)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Found %d accuracy issues.', 'tpak-dq-system'), count($issues)),
                'details' => array(
                    'issues' => $issues
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All accuracy checks passed.', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบความทันสมัยของข้อมูล
     */
    private function check_timeliness($config, $response) {
        $timeliness_rules = $config['rules'] ?? array();
        $issues = array();
        
        foreach ($timeliness_rules as $rule) {
            $field = $rule['field'];
            $max_age = $rule['max_age'] ?? null;
            $min_age = $rule['min_age'] ?? null;
            
            $value = $response['answers'][$field] ?? null;
            
            if ($value) {
                $timestamp = strtotime($value);
                $current_time = time();
                $age = $current_time - $timestamp;
                
                if ($max_age && $age > $max_age) {
                    $issues[] = array(
                        'field' => $field,
                        'value' => $value,
                        'age' => $age,
                        'max_age' => $max_age,
                        'type' => 'too_old'
                    );
                }
                
                if ($min_age && $age < $min_age) {
                    $issues[] = array(
                        'field' => $field,
                        'value' => $value,
                        'age' => $age,
                        'min_age' => $min_age,
                        'type' => 'too_recent'
                    );
                }
            }
        }
        
        if (!empty($issues)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Found %d timeliness issues.', 'tpak-dq-system'), count($issues)),
                'details' => array(
                    'issues' => $issues
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All timeliness checks passed.', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบความไม่ซ้ำกันของข้อมูล
     */
    private function check_uniqueness($config, $response) {
        $uniqueness_rules = $config['rules'] ?? array();
        $duplicates = array();
        
        foreach ($uniqueness_rules as $rule) {
            $field = $rule['field'];
            $value = $response['answers'][$field] ?? null;
            
            if ($value && $this->is_duplicate($field, $value, $response['id'])) {
                $duplicates[] = array(
                    'field' => $field,
                    'value' => $value,
                    'type' => 'duplicate'
                );
            }
        }
        
        if (!empty($duplicates)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Found %d duplicate values.', 'tpak-dq-system'), count($duplicates)),
                'details' => array(
                    'duplicates' => $duplicates
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All uniqueness checks passed.', 'tpak-dq-system')
        );
    }
    
    /**
     * ตรวจสอบแบบกำหนดเอง
     */
    private function check_custom($config, $response) {
        $custom_rules = $config['rules'] ?? array();
        $violations = array();
        
        foreach ($custom_rules as $rule) {
            $expression = $rule['expression'] ?? '';
            $description = $rule['description'] ?? '';
            
            if (!$this->evaluate_custom_rule($expression, $response)) {
                $violations[] = array(
                    'rule' => $rule,
                    'description' => $description
                );
            }
        }
        
        if (!empty($violations)) {
            return array(
                'status' => 'failed',
                'message' => sprintf(__('Found %d custom rule violations.', 'tpak-dq-system'), count($violations)),
                'details' => array(
                    'violations' => $violations
                )
            );
        }
        
        return array(
            'status' => 'passed',
            'message' => __('All custom rules passed.', 'tpak-dq-system')
        );
    }
    
    /**
     * รับการตรวจสอบคุณภาพข้อมูล
     */
    private function get_quality_checks($questionnaire_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_quality_checks';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE questionnaire_id = %d AND is_active = 1",
            $questionnaire_id
        ));
    }
    
    /**
     * บันทึกผลการตรวจสอบ
     */
    private function save_check_result($questionnaire_id, $check_id, $response_id, $result) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_check_results';
        
        $wpdb->insert(
            $table_name,
            array(
                'questionnaire_id' => $questionnaire_id,
                'check_id' => $check_id,
                'response_id' => $response_id,
                'result_status' => $result['status'],
                'result_message' => $result['message'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * ประเมินกฎความสอดคล้อง
     */
    private function evaluate_consistency_rule($value1, $value2, $operator, $condition) {
        switch ($operator) {
            case 'equals':
                return $value1 == $value2;
            case 'not_equals':
                return $value1 != $value2;
            case 'greater_than':
                return $value1 > $value2;
            case 'less_than':
                return $value1 < $value2;
            case 'greater_than_or_equal':
                return $value1 >= $value2;
            case 'less_than_or_equal':
                return $value1 <= $value2;
            default:
                return true;
        }
    }
    
    /**
     * ตรวจสอบความถูกต้องของฟิลด์
     */
    private function validate_field($value, $rule) {
        $type = $rule['type'] ?? '';
        
        switch ($type) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'number':
                return is_numeric($value);
            case 'date':
                return strtotime($value) !== false;
            case 'regex':
                $pattern = $rule['pattern'] ?? '';
                return preg_match($pattern, $value);
            default:
                return true;
        }
    }
    
    /**
     * ตรวจสอบว่าค่าอยู่ในช่วงที่กำหนดหรือไม่
     */
    private function is_in_range($value, $range) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $min = $range['min'] ?? null;
        $max = $range['max'] ?? null;
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * ตรวจสอบความแม่นยำเมื่อเทียบกับค่าอ้างอิง
     */
    private function is_accurate_compared_to($value, $reference_value, $rule) {
        $tolerance = $rule['tolerance'] ?? 0;
        $operator = $rule['operator'] ?? 'equals';
        
        switch ($operator) {
            case 'equals':
                return abs($value - $reference_value) <= $tolerance;
            case 'percentage':
                $percentage = $rule['percentage'] ?? 10;
                $diff = abs(($value - $reference_value) / $reference_value * 100);
                return $diff <= $percentage;
            default:
                return true;
        }
    }
    
    /**
     * ตรวจสอบว่าค่าซ้ำหรือไม่
     */
    private function is_duplicate($field, $value, $current_response_id) {
        global $wpdb;
        
        // ตรวจสอบในฐานข้อมูล
        $table_name = $wpdb->prefix . 'tpak_check_results';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE response_id != %s AND result_message LIKE %s",
            $current_response_id,
            '%' . $wpdb->esc_like($value) . '%'
        ));
        
        return $count > 0;
    }
    
    /**
     * ประเมินกฎแบบกำหนดเอง
     */
    private function evaluate_custom_rule($expression, $response) {
        // ตัวอย่างการประเมินกฎแบบกำหนดเอง
        // ในที่นี้จะใช้ eval() อย่างปลอดภัย (ไม่แนะนำใน production)
        // ควรใช้ expression parser ที่ปลอดภัยแทน
        
        try {
            // แทนที่ตัวแปรใน expression ด้วยค่าจริง
            $evaluated_expression = $this->replace_variables($expression, $response);
            
            // ตรวจสอบความปลอดภัยของ expression
            if ($this->is_safe_expression($evaluated_expression)) {
                return eval("return $evaluated_expression;");
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * แทนที่ตัวแปรใน expression
     */
    private function replace_variables($expression, $response) {
        $answers = $response['answers'] ?? array();
        
        foreach ($answers as $field => $value) {
            $expression = str_replace("{{$field}}", $value, $expression);
        }
        
        return $expression;
    }
    
    /**
     * ตรวจสอบความปลอดภัยของ expression
     */
    private function is_safe_expression($expression) {
        // ตรวจสอบว่ามีคำสั่งที่อันตรายหรือไม่
        $dangerous_patterns = array(
            'system\(',
            'exec\(',
            'shell_exec\(',
            'passthru\(',
            'eval\(',
            'file_get_contents\(',
            'fopen\(',
            'include\(',
            'require\('
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match("/$pattern/i", $expression)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * รับข้อความการตรวจสอบ
     */
    private function get_validation_message($rule) {
        $type = $rule['type'] ?? '';
        $custom_message = $rule['message'] ?? '';
        
        if (!empty($custom_message)) {
            return $custom_message;
        }
        
        switch ($type) {
            case 'email':
                return __('Invalid email format.', 'tpak-dq-system');
            case 'url':
                return __('Invalid URL format.', 'tpak-dq-system');
            case 'number':
                return __('Value must be a number.', 'tpak-dq-system');
            case 'date':
                return __('Invalid date format.', 'tpak-dq-system');
            case 'regex':
                return __('Value does not match required pattern.', 'tpak-dq-system');
            default:
                return __('Validation failed.', 'tpak-dq-system');
        }
    }
} 