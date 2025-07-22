<?php
/**
 * Simple autoloader stub for Google API Client
 * This replaces the need for Composer for basic functionality
 */

// Simple Google API Client mock for development
if (!class_exists('Google_Client')) {
    
    class Google_Client {
        private $applicationName;
        private $scopes = [];
        private $authConfig;
        
        public function setApplicationName($name) {
            $this->applicationName = $name;
        }
        
        public function setScopes($scopes) {
            $this->scopes = $scopes;
        }
        
        public function setAuthConfig($config) {
            $this->authConfig = $config;
        }
    }
    
    class Google_Service_Sheets {
        const SPREADSHEETS = 'https://www.googleapis.com/auth/spreadsheets';
        
        public $spreadsheets_values;
        
        public function __construct($client) {
            $this->spreadsheets_values = new Google_Service_Sheets_Values();
        }
    }
    
    class Google_Service_Sheets_Values {
        public function append($spreadsheetId, $range, $body, $params) {
            // Mock implementation - in production, use real Google Sheets API
            return ['updatedRows' => 1];
        }
        
        public function get($spreadsheetId, $range) {
            // Mock implementation
            $response = new Google_Service_Sheets_ValueRange();
            return $response;
        }
        
        public function update($spreadsheetId, $range, $body, $params) {
            // Mock implementation
            return ['updatedRows' => 1];
        }
    }
    
    class Google_Service_Sheets_ValueRange {
        private $values = [];
        
        public function setValues($values) {
            $this->values = $values;
        }
        
        public function getValues() {
            return $this->values;
        }
    }
}
?>