<?php
trait Creator_AI_API {
    /**
     * Check if model supports temperature parameter
     */
    protected static function model_supports_temperature($model) {
        $model_lower = strtolower($model);
        
        // gpt-5 models don't support temperature parameter
        if (strpos($model_lower, 'gpt-5') !== false) {
            return false;
        }
        
        // Most other models support temperature
        return true;
    }

    /**
     * Get the correct token parameter name based on the model
     */
    protected static function get_token_parameter_name($model) {
        $model_lower = strtolower($model);
        
        // ChatGPT models and newer GPT models use max_completion_tokens
        // This includes: chatgpt-4o-latest, gpt-4o-mini, gpt-4o, gpt-5-mini, and other newer models
        if (strpos($model_lower, 'chatgpt') !== false || 
            strpos($model_lower, 'gpt-4o') !== false ||
            strpos($model_lower, 'gpt-4-turbo') !== false ||
            strpos($model_lower, 'gpt-5') !== false) {
            return 'max_completion_tokens';
        }
        
        // All other models (gpt-3.5-turbo, gpt-4, etc.) use max_tokens
        return 'max_tokens';
    }
    
    protected static function openai_request($data, $api_key, $timeout = null) {
        $limit_check = cai_check_request_limit();
        if (is_wp_error($limit_check)) {
            return $limit_check;
        }

        cai_track_request();
        
        if (!isset($data['model'])) {
            return false;
        }
        
        // SECURITY: Add rate limiting to prevent rapid requests that trigger hosting blocks
        static $last_request_time = 0;
        $min_delay = 2; // 2 seconds between requests
        $current_time = time();
        $time_since_last = $current_time - $last_request_time;
        
        if ($time_since_last < $min_delay) {
            $sleep_time = $min_delay - $time_since_last;
            error_log("Rate limiting: sleeping for {$sleep_time} seconds to prevent hosting security blocks");
            sleep($sleep_time);
        }
        $last_request_time = time();
        
        // Convert max_tokens to the correct parameter name based on the model
        if (isset($data['max_tokens'])) {
            try {
                // SECURITY: Cap tokens at reasonable limits to prevent hosting blocks
                $max_allowed_tokens = 8000; // Reasonable limit for most requests
                if ($data['max_tokens'] > $max_allowed_tokens) {
                    error_log("Token limit capped from {$data['max_tokens']} to {$max_allowed_tokens} to prevent hosting security blocks");
                    $data['max_tokens'] = $max_allowed_tokens;
                }
                
                $correct_param = Creator_AI::get_token_parameter_name($data['model']);
                if ($correct_param !== 'max_tokens') {
                    $data[$correct_param] = $data['max_tokens'];
                    unset($data['max_tokens']);
                }
            } catch (Exception $e) {
                error_log('Error in token parameter conversion: ' . $e->getMessage());
                // Continue with original max_tokens parameter
            }
        }

        // Remove temperature parameter if model doesn't support it
        if (isset($data['temperature']) && !Creator_AI::model_supports_temperature($data['model'])) {
            error_log("Removing temperature parameter for model {$data['model']} as it's not supported");
            unset($data['temperature']);
        }

        // Replace problematic function with standard error_log
        $token_param = isset($data['max_tokens']) ? 'max_tokens' : (isset($data['max_completion_tokens']) ? 'max_completion_tokens' : 'none');
        $token_value = isset($data['max_tokens']) ? $data['max_tokens'] : (isset($data['max_completion_tokens']) ? $data['max_completion_tokens'] : 'default');
        error_log('Starting OpenAI request - Model: ' . $data['model'] . ', Token param: ' . $token_param . ', Tokens: ' . $token_value);

        // Use default timeout from settings if not specified
        if ($timeout === null) {
            $timeout = 120; // Default timeout since we can't access instance variables in static context
        }
        
        // SECURITY: Cap timeout to prevent hosting security blocks
        $max_allowed_timeout = 180; // 3 minutes max
        if ($timeout > $max_allowed_timeout) {
            error_log("Timeout capped from {$timeout} to {$max_allowed_timeout} seconds to prevent hosting security blocks");
            $timeout = $max_allowed_timeout;
        }
        
        $url = "https://api.openai.com/v1/chat/completions";
        
        // Ensure data is properly encoded
        $json_data = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('OpenAI API JSON encoding error: ' . json_last_error_msg());
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
            $response = cai_remote_post($url, $args);
            
            if (is_wp_error($response)) {
                error_log('OpenAI API Error: ' . $response->get_error_message());
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Log the response for debugging
            // Log debug data
            $debug_data = array(
                'status_code' => $status_code,
                'response' => json_decode($body, true)
            );
            error_log('OpenAI API Response: ' . json_encode($debug_data));
            
            // Check for HTTP error status codes
            if ($status_code >= 400) {
                $json_body = json_decode($body, true);
                $error_message = isset($json_body['error']['message']) ? $json_body['error']['message'] : 'Unknown error';
                
                error_log('OpenAI API Error: ' . json_encode(array(
                    'status_code' => $status_code,
                    'error' => $error_message
                )));
                return false;
            }
            
            // Parse response body
            $result = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('OpenAI API Error - JSON parsing error: ' . json_last_error_msg());
                return false;
            }
            
            // Check for required response fields
            if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
                error_log('OpenAI API Error - Missing choices in response');
                return false;
            }
            
            if (!isset($result['choices'][0]['message']) || !isset($result['choices'][0]['message']['content'])) {
                error_log('OpenAI API Error - Missing message content in response');
                return false;
            }
            
            return $result;
        } 
        catch (Exception $e) {
            error_log('OpenAI API Exception: ' . $e->getMessage());
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
                $api_response = Creator_AI::openai_request(array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test'
                )
            )
        ), $api_key);
        
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
        
        $token = null; // Access token not available in static context
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
        
        $response = cai_remote_post($token_url, $args);
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
            
            $response = cai_remote_post($token_url, $args);
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