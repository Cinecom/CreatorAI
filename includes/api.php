<?php
trait Creator_AI_API {
    protected function openai_request($data, $api_key, $timeout = null) {

        // Replace problematic function with standard error_log
        error_log('Starting OpenAI request - Model: ' . (isset($data['model']) ? $data['model'] : 'undefined') . 
                  ', Tokens: ' . (isset($data['max_tokens']) ? $data['max_tokens'] : 'default'));

        // Use default timeout from settings if not specified
        if ($timeout === null) {
            $timeout = isset($this->api_settings['openai_timeout']) ? $this->api_settings['openai_timeout'] : 120;
        }
        
        if (!isset($data['model'])) {
            return false;
        }
        
        $url = "https://api.openai.com/v1/chat/completions";
        
        // Ensure data is properly encoded
        $json_data = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->is_debugging_enabled()) {
                $this->log_debug_data('OpenAI API', 'JSON encoding error: ' . json_last_error_msg(), true);
            }
            return false;
        }
        
        $args = array(
            'body' => $json_data,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'Cache-Control' => 'no-cache, no-store',
            ),
            'timeout' => $timeout,
        );
        
        try {
            $response = wp_remote_post($url, $args);
            
            if (is_wp_error($response)) {
                if ($this->is_debugging_enabled()) {
                    $this->log_debug_data('OpenAI API Error', $response->get_error_message(), true);
                }
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Log the response for debugging
            if ($this->is_debugging_enabled()) {
                $debug_data = array(
                    'status_code' => $status_code,
                    'response' => json_decode($body, true)
                );
                $this->log_debug_data('OpenAI API', $debug_data);
            }
            
            // Check for HTTP error status codes
            if ($status_code >= 400) {
                $json_body = json_decode($body, true);
                $error_message = isset($json_body['error']['message']) ? $json_body['error']['message'] : 'Unknown error';
                
                if ($this->is_debugging_enabled()) {
                    $this->log_debug_data('OpenAI API Error', array(
                        'status_code' => $status_code,
                        'error' => $error_message
                    ), true);
                }
                return false;
            }
            
            // Parse response body
            $result = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($this->is_debugging_enabled()) {
                    $this->log_debug_data('OpenAI API Error', 'JSON parsing error: ' . json_last_error_msg(), true);
                }
                return false;
            }
            
            // Check for required response fields
            if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
                if ($this->is_debugging_enabled()) {
                    $this->log_debug_data('OpenAI API Error', 'Missing choices in response', true);
                }
                return false;
            }
            
            if (!isset($result['choices'][0]['message']) || !isset($result['choices'][0]['message']['content'])) {
                if ($this->is_debugging_enabled()) {
                    $this->log_debug_data('OpenAI API Error', 'Missing message content in response', true);
                }
                return false;
            }
            
            return $result;
        } 
        catch (Exception $e) {
            if ($this->is_debugging_enabled()) {
                $this->log_debug_data('OpenAI API Exception', $e->getMessage(), true);
            }

            error_log('OpenAI request failed - Error: ' . $e->getMessage());
            return false;
        }
    }
    public function test_openai_api() {
        // Retrieve the API key and model.
        $api_key = get_option('cai_openai_api_key');
        $model   = get_option('cai_openai_model');
        
        // If necessary, check if these are filled.
        if (empty($api_key) || empty($model)) {
            wp_send_json_error("Missing API key or model.");
        }
        
        // Call the OpenAI API – replace this with your actual API call.
        $api_response = wp_remote_get("https://api.openai.com/v1/models/{$model}", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ));
        
        if (is_wp_error($api_response)) {
            wp_send_json_error($api_response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($api_response);
        $data = json_decode($body, true);
        
        // If the API returns an error, pass it along.
        if (isset($data['error'])) {
            wp_send_json_error($data['error']['message']);
        }
        
        wp_send_json_success("OpenAI API connection successful. Your credentials are working properly.");
    }
    public function test_google_api() {
        check_ajax_referer('cai_nonce', 'nonce');
        
        $token = $this->get_access_token();
        if (!$token) {
            wp_send_json_error("Failed to obtain access token.");
        }
        
        wp_send_json_success("OAuth is working. Token (first 20 chars): " . substr($token, 0, 20) . "...");
    }
    public function google_connect() {
        $client_id = get_option('cai_google_client_id');
        $redirect_uri = admin_url('admin-post.php?action=cai_google_callback');
        
        // Request both the YouTube and Cloud Platform scopes
        $scope = urlencode("https://www.googleapis.com/auth/youtube.force-ssl https://www.googleapis.com/auth/cloud-platform");
        
        $auth_url = "https://accounts.google.com/o/oauth2/auth?response_type=code"
                  . "&client_id=" . urlencode($client_id)
                  . "&redirect_uri=" . urlencode($redirect_uri)
                  . "&scope=" . $scope
                  . "&access_type=offline&prompt=consent";
        
        wp_redirect($auth_url);
        exit;
    }
    public function google_disconnect() {
        delete_option('cai_google_access_token');
        delete_option('cai_google_refresh_token');
        delete_option('cai_google_expires');
        
        wp_redirect(admin_url('admin.php?page=creator-ai-settings'));
        exit;
    }
    public function google_callback() {
        if (!isset($_GET['code'])) {
            wp_die("Authorization failed.");
        }
        
        $code = sanitize_text_field($_GET['code']);
        $client_id = get_option('cai_google_client_id');
        $client_secret = get_option('cai_google_client_secret');
        $redirect_uri = admin_url('admin-post.php?action=cai_google_callback');
        
        $token_url = "https://oauth2.googleapis.com/token";
        $args = array(
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        );
        
        $response = wp_remote_post($token_url, $args);
        if (is_wp_error($response)) {
            wp_die("Token request failed: " . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['access_token'])) {
            wp_die("Failed to get access token.");
        }
        
        update_option('cai_google_access_token', $data['access_token']);
        update_option('cai_google_refresh_token', $data['refresh_token']);
        
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
        update_option('cai_google_expires', time() + $expires_in);
        
        wp_redirect(admin_url('admin.php?page=creator-ai-settings'));
        exit;
    }
    protected function get_access_token() {
        $access_token = get_option('cai_google_access_token');
        $expires = get_option('cai_google_expires');
        $refresh_token = get_option('cai_google_refresh_token');
        
        // Check if token is expired and needs refresh
        if (!$access_token || time() > $expires) {
            if (empty($refresh_token)) {
                return false;
            }
            
            $client_id = get_option('cai_google_client_id');
            $client_secret = get_option('cai_google_client_secret');
            
            $token_url = "https://oauth2.googleapis.com/token";
            $args = array(
                'body' => array(
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type' => 'refresh_token'
                )
            );
            
            $response = wp_remote_post($token_url, $args);
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $token_data = json_decode($body, true);
            
            if (empty($token_data['access_token'])) {
                return false;
            }
            
            $access_token = $token_data['access_token'];
            $expires_in = isset($token_data['expires_in']) ? intval($token_data['expires_in']) : 3600;
            
            update_option('cai_google_access_token', $access_token);
            update_option('cai_google_expires', time() + $expires_in);
        }
        
        return $access_token;
    }
    protected function handle_api_error($response, $error_context = '') {
        if (is_wp_error($response)) {
            return [
                'error' => true, 
                'message' => $error_context . ': ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code >= 400) {
            // Check for quota exceeded errors
            if ($status_code == 403 && !empty($data['error']['errors'])) {
                foreach ($data['error']['errors'] as $error) {
                    if (isset($error['reason']) && $error['reason'] === 'quotaExceeded') {
                        return [
                            'error' => true,
                            'message' => "API quota exceeded. You've reached your daily limit for API requests. Please try again tomorrow when your quota resets."
                        ];
                    }
                }
            }
            
            return [
                'error' => true,
                'message' => $error_context . " error: " . 
                    (isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error') . 
                    " (Status code: " . $status_code . ")"
            ];
        }
        
        return ['error' => false, 'data' => $data];
    }


}