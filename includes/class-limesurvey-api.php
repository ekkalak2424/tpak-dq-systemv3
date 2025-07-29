<?php
/**
 * LimeSurvey API Class สำหรับ TPAK DQ System v3
 * 
 * จัดการการเชื่อมต่อและเรียกใช้ LimeSurvey API
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_LimeSurvey_API {
    
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
     * Constructor
     */
    public function __construct() {
        $core = TPAK_DQ_Core::get_instance();
        $this->api_url = $core->get_setting('limesurvey_api_url');
        $this->username = $core->get_setting('limesurvey_username');
        $this->password = $core->get_setting('limesurvey_password');
    }
    
    /**
     * ทดสอบการเชื่อมต่อ
     */
    public function test_connection() {
        try {
            $this->authenticate();
            return array(
                'success' => true,
                'message' => __('Connection to LimeSurvey API successful.', 'tpak-dq-system')
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * เข้าสู่ระบบ LimeSurvey API
     */
    private function authenticate() {
        if (empty($this->api_url) || empty($this->username) || empty($this->password)) {
            throw new Exception(__('LimeSurvey API credentials are not configured.', 'tpak-dq-system'));
        }
        
        $response = $this->make_request('get_session_key', array(
            'username' => $this->username,
            'password' => $this->password
        ));
        
        if (isset($response['result'])) {
            $this->session_key = $response['result'];
            return true;
        } else {
            throw new Exception(__('Failed to authenticate with LimeSurvey API.', 'tpak-dq-system'));
        }
    }
    
    /**
     * รับรายการแบบสอบถาม
     */
    public function get_surveys() {
        $this->ensure_authenticated();
        
        $response = $this->make_request('list_surveys', array(
            'sSessionKey' => $this->session_key
        ));
        
        if (isset($response['result'])) {
            return $this->format_surveys($response['result']);
        } else {
            throw new Exception(__('Failed to retrieve surveys from LimeSurvey.', 'tpak-dq-system'));
        }
    }
    
    /**
     * Sync แบบสอบถามทั้งหมด
     */
    public function sync_all_surveys() {
        try {
            $surveys = $this->get_surveys();
            $synced_count = 0;
            
            foreach ($surveys as $survey) {
                if ($this->sync_single_survey($survey['sid'])) {
                    $synced_count++;
                }
            }
            
            return array(
                'success' => true,
                'message' => sprintf(__('Successfully synced %d surveys.', 'tpak-dq-system'), $synced_count),
                'count' => $synced_count
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Sync แบบสอบถามเดี่ยว
     */
    public function sync_single_survey($survey_id) {
        try {
            $this->ensure_authenticated();
            
            // รับรายละเอียดแบบสอบถาม
            $survey_details = $this->get_survey_details($survey_id);
            
            // บันทึกลงฐานข้อมูล
            global $wpdb;
            $table = $wpdb->prefix . 'tpak_questionnaires';
            
            $data = array(
                'limesurvey_id' => $survey_id,
                'title' => $survey_details['title'],
                'description' => $survey_details['description'] ?? '',
                'status' => $survey_details['active'] ? 'active' : 'inactive',
                'updated_at' => current_time('mysql')
            );
            
            // ตรวจสอบว่ามีอยู่แล้วหรือไม่
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE limesurvey_id = %s",
                $survey_id
            ));
            
            if ($existing) {
                // อัปเดตข้อมูลที่มีอยู่
                $result = $wpdb->update($table, $data, array('limesurvey_id' => $survey_id));
            } else {
                // เพิ่มข้อมูลใหม่
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table, $data);
            }
            
            if ($result === false) {
                error_log("TPAK DQ System: Failed to sync survey $survey_id: " . $wpdb->last_error);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("TPAK DQ System: Error syncing survey $survey_id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * รับรายละเอียดแบบสอบถาม
     */
    public function get_survey_details($survey_id) {
        $this->ensure_authenticated();
        
        $response = $this->make_request('get_survey_properties', array(
            'sSessionKey' => $this->session_key,
            'iSurveyID' => $survey_id
        ));
        
        if (isset($response['result'])) {
            return $this->format_survey_details($response['result'], $survey_id);
        } else {
            throw new Exception(__('Failed to retrieve survey details from LimeSurvey.', 'tpak-dq-system'));
        }
    }
    
    /**
     * รับคำถามของแบบสอบถาม
     */
    public function get_survey_questions($survey_id) {
        $this->ensure_authenticated();
        
        $response = $this->make_request('list_questions', array(
            'sSessionKey' => $this->session_key,
            'iSurveyID' => $survey_id
        ));
        
        if (isset($response['result'])) {
            return $this->format_questions($response['result']);
        } else {
            throw new Exception(__('Failed to retrieve questions from LimeSurvey.', 'tpak-dq-system'));
        }
    }
    
    /**
     * รับคำตอบของแบบสอบถาม
     */
    public function get_survey_responses($survey_id, $params = array()) {
        $this->ensure_authenticated();
        
        $default_params = array(
            'sSessionKey' => $this->session_key,
            'iSurveyID' => $survey_id,
            'sDocumentType' => 'json',
            'sLanguageCode' => 'en'
        );
        
        $request_params = array_merge($default_params, $params);
        
        $response = $this->make_request('export_responses', $request_params);
        
        if (isset($response['result'])) {
            return $this->format_responses($response['result']);
        } else {
            throw new Exception(__('Failed to retrieve responses from LimeSurvey.', 'tpak-dq-system'));
        }
    }
    
    /**
     * รับสถิติของแบบสอบถาม
     */
    public function get_survey_statistics($survey_id) {
        $this->ensure_authenticated();
        
        $response = $this->make_request('get_summary', array(
            'sSessionKey' => $this->session_key,
            'iSurveyID' => $survey_id
        ));
        
        if (isset($response['result'])) {
            return $this->format_statistics($response['result']);
        } else {
            throw new Exception(__('Failed to retrieve statistics from LimeSurvey.', 'tpak-dq-system'));
        }
    }
    
    /**
     * ตรวจสอบว่า authenticated หรือไม่
     */
    private function ensure_authenticated() {
        if (empty($this->session_key)) {
            $this->authenticate();
        }
    }
    
    /**
     * ส่ง request ไปยัง LimeSurvey API
     */
    private function make_request($method, $params = array()) {
        $url = rtrim($this->api_url, '/') . '/admin/remotecontrol';
        
        $request_data = array(
            'method' => $method,
            'params' => $params,
            'id' => 1
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'TPAK-DQ-System/3.0.0'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30,
            'sslverify' => false
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP Error: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from LimeSurvey API');
        }
        
        if (isset($data['error'])) {
            throw new Exception('LimeSurvey API Error: ' . $data['error']['message']);
        }
        
        return $data;
    }
    
    /**
     * จัดรูปแบบข้อมูลแบบสอบถาม
     */
    private function format_surveys($surveys) {
        $formatted = array();
        
        foreach ($surveys as $survey) {
            $formatted[] = array(
                'id' => $survey['sid'],
                'title' => $survey['surveyls_title'],
                'description' => $survey['description'] ?? '',
                'status' => $this->map_survey_status($survey['active']),
                'created_at' => $survey['datecreated'] ?? '',
                'updated_at' => $survey['datestamp'] ?? '',
                'language' => $survey['language'] ?? 'en',
                'owner_id' => $survey['owner_id'] ?? 0,
                'expires' => $survey['expires'] ?? null,
                'start_date' => $survey['startdate'] ?? null,
                'end_date' => $survey['expires'] ?? null
            );
        }
        
        return $formatted;
    }
    
    /**
     * จัดรูปแบบรายละเอียดแบบสอบถาม
     */
    private function format_survey_details($details, $survey_id) {
        return array(
            'id' => $survey_id,
            'title' => $details['surveyls_title'] ?? '',
            'description' => $details['description'] ?? '',
            'welcome_text' => $details['surveyls_welcometext'] ?? '',
            'end_text' => $details['surveyls_endtext'] ?? '',
            'admin_email' => $details['adminemail'] ?? '',
            'admin_name' => $details['adminname'] ?? '',
            'bounce_email' => $details['bounce_email'] ?? '',
            'language' => $details['language'] ?? 'en',
            'date_created' => $details['datecreated'] ?? '',
            'date_modified' => $details['datestamp'] ?? '',
            'active' => $details['active'] ?? 'N',
            'expires' => $details['expires'] ?? null,
            'start_date' => $details['startdate'] ?? null,
            'end_date' => $details['expires'] ?? null,
            'format' => $details['format'] ?? 'G',
            'question_index' => $details['questionindex'] ?? '0',
            'nokeyboard' => $details['nokeyboard'] ?? 'N',
            'allowregister' => $details['allowregister'] ?? 'N',
            'allowprev' => $details['allowprev'] ?? 'N',
            'printanswers' => $details['printanswers'] ?? 'N',
            'ipaddr' => $details['ipaddr'] ?? 'N',
            'refurl' => $details['refurl'] ?? 'N',
            'dateformat' => $details['dateformat'] ?? '1',
            'publicstatistics' => $details['publicstatistics'] ?? 'N',
            'publicgraphs' => $details['publicgraphs'] ?? 'N',
            'listpublic' => $details['listpublic'] ?? 'N',
            'usecookie' => $details['usecookie'] ?? 'N',
            'usecaptcha' => $details['usecaptcha'] ?? 'N',
            'usetokens' => $details['usetokens'] ?? 'N',
            'bounceprocessing' => $details['bounceprocessing'] ?? 'N',
            'attributedescriptions' => $details['attributedescriptions'] ?? '',
            'emailresponseto' => $details['emailresponseto'] ?? '',
            'emailnotificationto' => $details['emailnotificationto'] ?? '',
            'tokenlength' => $details['tokenlength'] ?? 15,
            'showxquestions' => $details['showxquestions'] ?? 'Y',
            'showgroupinfo' => $details['showgroupinfo'] ?? 'B',
            'shownoanswer' => $details['shownoanswer'] ?? 'Y',
            'showqnumcode' => $details['showqnumcode'] ?? 'X',
            'showwelcome' => $details['showwelcome'] ?? 'Y',
            'showprogress' => $details['showprogress'] ?? 'Y',
            'questionindex' => $details['questionindex'] ?? 0,
            'navigationdelay' => $details['navigationdelay'] ?? 0,
            'nokeyboard' => $details['nokeyboard'] ?? 'N',
            'alloweditaftercompletion' => $details['alloweditaftercompletion'] ?? 'N',
            'googleanalyticsstyle' => $details['googleanalyticsstyle'] ?? 0,
            'googleanalyticsapikey' => $details['googleanalyticsapikey'] ?? ''
        );
    }
    
    /**
     * จัดรูปแบบคำถาม
     */
    private function format_questions($questions) {
        $formatted = array();
        
        foreach ($questions as $question) {
            $formatted[] = array(
                'id' => $question['qid'],
                'survey_id' => $question['sid'],
                'group_id' => $question['gid'],
                'type' => $question['type'],
                'title' => $question['title'],
                'question' => $question['question'],
                'help' => $question['help'] ?? '',
                'mandatory' => $question['mandatory'] ?? 'N',
                'other' => $question['other'] ?? 'N',
                'position' => $question['position'] ?? 0,
                'scale_id' => $question['scale_id'] ?? 0,
                'same_default' => $question['same_default'] ?? 0,
                'relevance' => $question['relevance'] ?? '',
                'modulename' => $question['modulename'] ?? '',
                'class' => $question['class'] ?? '',
                'preg' => $question['preg'] ?? '',
                'encrypted' => $question['encrypted'] ?? 'N',
                'question_order' => $question['question_order'] ?? 0,
                'parent_qid' => $question['parent_qid'] ?? 0,
                'scale_id' => $question['scale_id'] ?? 0,
                'same_default' => $question['same_default'] ?? 0,
                'relevance' => $question['relevance'] ?? '',
                'modulename' => $question['modulename'] ?? '',
                'class' => $question['class'] ?? '',
                'preg' => $question['preg'] ?? '',
                'encrypted' => $question['encrypted'] ?? 'N',
                'question_order' => $question['question_order'] ?? 0,
                'parent_qid' => $question['parent_qid'] ?? 0
            );
        }
        
        return $formatted;
    }
    
    /**
     * จัดรูปแบบคำตอบ
     */
    private function format_responses($responses) {
        // LimeSurvey ส่งข้อมูลกลับมาเป็น JSON string
        if (is_string($responses)) {
            $responses = json_decode($responses, true);
        }
        
        if (!is_array($responses)) {
            return array();
        }
        
        $formatted = array();
        
        foreach ($responses as $response) {
            $formatted[] = array(
                'id' => $response['id'] ?? '',
                'submitdate' => $response['submitdate'] ?? '',
                'lastpage' => $response['lastpage'] ?? 0,
                'startlanguage' => $response['startlanguage'] ?? 'en',
                'token' => $response['token'] ?? '',
                'datestamp' => $response['datestamp'] ?? '',
                'ipaddr' => $response['ipaddr'] ?? '',
                'refurl' => $response['refurl'] ?? '',
                'answers' => $response['answers'] ?? array()
            );
        }
        
        return $formatted;
    }
    
    /**
     * จัดรูปแบบสถิติ
     */
    private function format_statistics($stats) {
        return array(
            'total_responses' => $stats['total_responses'] ?? 0,
            'completed_responses' => $stats['completed_responses'] ?? 0,
            'incomplete_responses' => $stats['incomplete_responses'] ?? 0,
            'total_questions' => $stats['total_questions'] ?? 0,
            'total_groups' => $stats['total_groups'] ?? 0,
            'start_date' => $stats['start_date'] ?? '',
            'end_date' => $stats['end_date'] ?? '',
            'average_time' => $stats['average_time'] ?? 0,
            'median_time' => $stats['median_time'] ?? 0,
            'min_time' => $stats['min_time'] ?? 0,
            'max_time' => $stats['max_time'] ?? 0
        );
    }
    
    /**
     * แปลงสถานะแบบสอบถาม
     */
    private function map_survey_status($active) {
        switch ($active) {
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
     * ออกจากระบบ
     */
    public function logout() {
        if (!empty($this->session_key)) {
            try {
                $this->make_request('release_session_key', array(
                    'sSessionKey' => $this->session_key
                ));
            } catch (Exception $e) {
                // Log error but don't throw
                error_log('LimeSurvey logout error: ' . $e->getMessage());
            }
            
            $this->session_key = null;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->logout();
    }
} 