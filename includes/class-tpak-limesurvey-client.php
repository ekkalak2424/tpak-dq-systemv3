<?php
/**
 * TPAK DQ System - Enhanced LimeSurvey API Client
 * 
 * จัดการการเชื่อมต่อกับ LimeSurvey RPC API
 * Phase 3: LimeSurvey Integration (2 สัปดาห์)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_LimeSurvey_Client {
    
    /**
     * Instance ของ class
     */
    private static $instance = null;
    
    /**
     * API URL
     */
    private $api_url;
    
    /**
     * Username
     */
    private $username;
    
    /**
     * Password
     */
    private $password;
    
    /**
     * Session key
     */
    private $session_key;
    
    /**
     * Session expiry
     */
    private $session_expiry;
    
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
        $this->api_url = get_option('tpak_dq_limesurvey_api_url', '');
        $this->username = get_option('tpak_dq_limesurvey_username', '');
        $this->password = get_option('tpak_dq_limesurvey_password', '');
        $this->session_key = null;
        $this->session_expiry = null;
        
        $this->init_hooks();
    }
    
    /**
     * เริ่มต้น hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_tpak_sync_survey', array($this, 'handle_sync_survey'));
        add_action('wp_ajax_tpak_test_connection', array($this, 'handle_test_connection'));
        add_action('tpak_sync_survey_data', array($this, 'sync_survey_data_cron'));
    }
    
    /**
     * ตรวจสอบการเชื่อมต่อ
     */
    public function test_connection() {
        try {
            $this->authenticate();
            return array(
                'success' => true,
                'message' => __('Connection successful', 'tpak-dq-system')
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * รับ session key จาก LimeSurvey
     */
    public function authenticate() {
        if ($this->session_key && $this->session_expiry && time() < $this->session_expiry) {
            return $this->session_key;
        }
        
        $method = 'get_session_key';
        $params = array(
            'username' => $this->username,
            'password' => $this->password
        );
        
        $response = $this->make_request($method, $params, false);
        
        if (isset($response['result'])) {
            $this->session_key = $response['result'];
            $this->session_expiry = time() + 3600; // 1 hour
            return $this->session_key;
        } else {
            throw new Exception(__('Authentication failed', 'tpak-dq-system'));
        }
    }
    
    /**
     * ส่ง request ไปยัง LimeSurvey API
     */
    public function make_request($method, $params = array(), $require_auth = true) {
        if ($require_auth) {
            $this->authenticate();
            $params = array_merge(array('sSessionKey' => $this->session_key), $params);
        }
        
        $request_data = array(
            'method' => $method,
            'params' => $params,
            'id' => 1
        );
        
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'TPAK-DQ-System/3.0.0'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            throw new Exception($data['error']['message']);
        }
        
        return $data;
    }
    
    /**
     * รับรายการ surveys
     */
    public function get_surveys() {
        $method = 'list_surveys';
        $params = array();
        
        $response = $this->make_request($method, $params);
        
        if (isset($response['result'])) {
            return $this->format_surveys($response['result']);
        }
        
        return array();
    }
    
    /**
     * รับรายละเอียด survey
     */
    public function get_survey_details($survey_id) {
        $method = 'get_survey_properties';
        $params = array(
            'iSurveyID' => $survey_id
        );
        
        $response = $this->make_request($method, $params);
        
        if (isset($response['result'])) {
            return $this->format_survey_details($response['result']);
        }
        
        return array();
    }
    
    /**
     * รับคำถามของ survey
     */
    public function get_survey_questions($survey_id) {
        $method = 'list_questions';
        $params = array(
            'iSurveyID' => $survey_id
        );
        
        $response = $this->make_request($method, $params);
        
        if (isset($response['result'])) {
            return $this->format_questions($response['result']);
        }
        
        return array();
    }
    
    /**
     * รับข้อมูล responses
     */
    public function get_survey_responses($survey_id, $start = 0, $limit = 100) {
        $method = 'export_responses';
        $params = array(
            'iSurveyID' => $survey_id,
            'sDocumentType' => 'json',
            'sLanguageCode' => 'en',
            'sCompletionStatus' => 'all',
            'sHeadingType' => 'full',
            'sResponseType' => 'long',
            'iStart' => $start,
            'iLimit' => $limit
        );
        
        $response = $this->make_request($method, $params);
        
        if (isset($response['result'])) {
            return $this->format_responses($response['result']);
        }
        
        return array();
    }
    
    /**
     * รับสถิติของ survey
     */
    public function get_survey_statistics($survey_id) {
        $method = 'get_statistics';
        $params = array(
            'iSurveyID' => $survey_id
        );
        
        $response = $this->make_request($method, $params);
        
        if (isset($response['result'])) {
            return $this->format_statistics($response['result']);
        }
        
        return array();
    }
    
    /**
     * รับข้อมูล survey data
     */
    public function get_survey_data($survey_id, $options = array()) {
        $default_options = array(
            'include_responses' => true,
            'include_questions' => true,
            'include_statistics' => false,
            'start' => 0,
            'limit' => 100
        );
        
        $options = wp_parse_args($options, $default_options);
        
        $data = array(
            'survey_id' => $survey_id,
            'details' => $this->get_survey_details($survey_id)
        );
        
        if ($options['include_questions']) {
            $data['questions'] = $this->get_survey_questions($survey_id);
        }
        
        if ($options['include_responses']) {
            $data['responses'] = $this->get_survey_responses($survey_id, $options['start'], $options['limit']);
        }
        
        if ($options['include_statistics']) {
            $data['statistics'] = $this->get_survey_statistics($survey_id);
        }
        
        return $data;
    }
    
    /**
     * Sync ข้อมูลจาก LimeSurvey
     */
    public function sync_data($survey_id = null) {
        global $wpdb;
        
        try {
            if ($survey_id) {
                return $this->sync_single_survey($survey_id);
            } else {
                return $this->sync_all_surveys();
            }
        } catch (Exception $e) {
            $this->log_error('sync_data', $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Sync survey เดียว
     */
    private function sync_single_survey($survey_id) {
        global $wpdb;
        
        $survey_data = $this->get_survey_data($survey_id, array(
            'include_responses' => true,
            'include_questions' => true,
            'limit' => 1000
        ));
        
        if (empty($survey_data['details'])) {
            throw new Exception(sprintf(__('Survey %s not found', 'tpak-dq-system'), $survey_id));
        }
        
        // บันทึกข้อมูล survey
        $table_questionnaires = $wpdb->prefix . 'tpak_questionnaires';
        $table_survey_data = $wpdb->prefix . 'tpak_survey_data';
        
        // อัพเดทหรือเพิ่ม questionnaire
        $wpdb->replace($table_questionnaires, array(
            'limesurvey_id' => $survey_id,
            'title' => $survey_data['details']['title'],
            'description' => $survey_data['details']['description'],
            'status' => 'active',
            'updated_at' => current_time('mysql')
        ));
        
        $questionnaire_id = $wpdb->insert_id ?: $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_questionnaires WHERE limesurvey_id = %s",
            $survey_id
        ));
        
        // บันทึก responses
        if (!empty($survey_data['responses'])) {
            foreach ($survey_data['responses'] as $response) {
                $wpdb->replace($table_survey_data, array(
                    'limesurvey_id' => $survey_id,
                    'response_id' => $response['id'],
                    'questionnaire_id' => $questionnaire_id,
                    'respondent_id' => $response['respondent_id'],
                    'response_data' => json_encode($response['data']),
                    'response_status' => $response['status'],
                    'submission_date' => $response['submission_date'],
                    'sync_status' => 'synced',
                    'last_updated' => current_time('mysql')
                ));
            }
        }
        
        return array(
            'success' => true,
            'survey_id' => $survey_id,
            'responses_count' => count($survey_data['responses']),
            'message' => sprintf(__('Synced %d responses for survey %s', 'tpak-dq-system'), count($survey_data['responses']), $survey_id)
        );
    }
    
    /**
     * Sync surveys ทั้งหมด
     */
    private function sync_all_surveys() {
        $surveys = $this->get_surveys();
        $results = array();
        
        foreach ($surveys as $survey) {
            try {
                $result = $this->sync_single_survey($survey['id']);
                $results[] = $result;
            } catch (Exception $e) {
                $results[] = array(
                    'success' => false,
                    'survey_id' => $survey['id'],
                    'message' => $e->getMessage()
                );
            }
        }
        
        return array(
            'success' => true,
            'results' => $results,
            'total_surveys' => count($surveys)
        );
    }
    
    /**
     * จัดรูปแบบข้อมูล surveys
     */
    private function format_surveys($surveys) {
        $formatted = array();
        
        foreach ($surveys as $survey) {
            $formatted[] = array(
                'id' => $survey['sid'],
                'title' => $survey['surveyls_title'],
                'language' => $survey['language'],
                'active' => $survey['active'] === 'Y',
                'created' => $survey['created'],
                'expires' => $survey['expires']
            );
        }
        
        return $formatted;
    }
    
    /**
     * จัดรูปแบบข้อมูล survey details
     */
    private function format_survey_details($details) {
        return array(
            'title' => $details['surveyls_title'] ?? '',
            'description' => $details['surveyls_description'] ?? '',
            'language' => $details['language'] ?? 'en',
            'active' => ($details['active'] ?? 'N') === 'Y',
            'created' => $details['created'] ?? '',
            'expires' => $details['expires'] ?? '',
            'admin' => $details['admin'] ?? '',
            'adminemail' => $details['adminemail'] ?? ''
        );
    }
    
    /**
     * จัดรูปแบบข้อมูล questions
     */
    private function format_questions($questions) {
        $formatted = array();
        
        foreach ($questions as $question) {
            $formatted[] = array(
                'id' => $question['qid'],
                'title' => $question['title'],
                'question' => $question['question'],
                'type' => $question['type'],
                'mandatory' => $question['mandatory'] === 'Y',
                'gid' => $question['gid'],
                'sid' => $question['sid']
            );
        }
        
        return $formatted;
    }
    
    /**
     * จัดรูปแบบข้อมูล responses
     */
    private function format_responses($responses) {
        $formatted = array();
        
        foreach ($responses as $response) {
            $formatted[] = array(
                'id' => $response['id'],
                'respondent_id' => $response['submitdate'] ?? '',
                'data' => $response,
                'status' => $this->map_response_status($response['submitdate']),
                'submission_date' => $response['submitdate'] ?? current_time('mysql')
            );
        }
        
        return $formatted;
    }
    
    /**
     * จัดรูปแบบข้อมูล statistics
     */
    private function format_statistics($statistics) {
        return array(
            'total_responses' => $statistics['total_responses'] ?? 0,
            'completed_responses' => $statistics['completed_responses'] ?? 0,
            'incomplete_responses' => $statistics['incomplete_responses'] ?? 0,
            'completion_rate' => $statistics['completion_rate'] ?? 0
        );
    }
    
    /**
     * แปลงสถานะ response
     */
    private function map_response_status($submitdate) {
        if (empty($submitdate)) {
            return 'incomplete';
        }
        
        return 'submitted';
    }
    
    /**
     * จัดการ AJAX sync survey
     */
    public function handle_sync_survey() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_sync_survey')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $survey_id = sanitize_text_field($_POST['survey_id']);
        
        try {
            $result = $this->sync_data($survey_id);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * จัดการ AJAX test connection
     */
    public function handle_test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_test_connection')) {
            wp_die(__('Security check failed', 'tpak-dq-system'));
        }
        
        $result = $this->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Cron job สำหรับ sync ข้อมูล
     */
    public function sync_survey_data_cron() {
        $this->sync_data();
    }
    
    /**
     * บันทึก error log
     */
    private function log_error($action, $message) {
        error_log(sprintf('[TPAK DQ System] %s: %s', $action, $message));
    }
} 