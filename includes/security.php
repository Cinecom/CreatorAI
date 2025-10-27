<?php
/**
 * Creator AI Security Functions
 * Handles secure storage and retrieval of sensitive credentials
 */

trait Creator_AI_Security {
    
    /**
     * Cache for decrypted credentials to avoid repeated decryption
     * @var array
     */
    private static $credential_cache = array();
    
    /**
     * Force clear the entire credential cache
     */
    public static function force_clear_cache() {
        self::$credential_cache = array();
    }
    
    /**
     * Encrypt sensitive credential data
     * Uses WordPress salts for encryption key generation
     */
    private static function encrypt_credential($value) {
        if (empty($value)) {
            return '';
        }
        
        // Use WordPress salts to generate encryption key
        $auth_salt = defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth');
        $secure_salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : wp_salt('secure_auth');
        $key = hash('sha256', $auth_salt . $secure_salt);
        
        // Generate random IV for each encryption
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        // Encrypt the value
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encrypted === false) {
            error_log('Creator AI: Failed to encrypt credential');
            return $value; // Return original value if encryption fails
        }
        
        // Prepend IV to encrypted data and encode
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive credential data
     */
    private static function decrypt_credential($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }
        
        // Check if value is already decrypted (backward compatibility)
        if (strpos($encrypted_value, 'cai_encrypted:') !== 0) {
            // Not encrypted, return as-is for backward compatibility
            return $encrypted_value;
        }
        
        // Remove encryption prefix
        $encrypted_data = substr($encrypted_value, 14); // Remove 'cai_encrypted:' prefix
        $data = base64_decode($encrypted_data);
        
        if ($data === false) {
            error_log('Creator AI: Failed to decode encrypted credential');
            return '';
        }
        
        // Use WordPress salts to generate decryption key
        $auth_salt = defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth');
        $secure_salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : wp_salt('secure_auth');
        $key = hash('sha256', $auth_salt . $secure_salt);
        
        // Extract IV and encrypted data
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        // Decrypt the value
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        if ($decrypted === false) {
            error_log('Creator AI: Failed to decrypt credential');
            return '';
        }
        
        return $decrypted;
    }
    
    /**
     * Securely store a credential (legacy method - now handled by sanitization callbacks)
     */
    public static function store_credential($option_name, $value) {
        if (empty($value)) {
            delete_option($option_name);
            return true;
        }
        
        // For new credentials, let the sanitization callback handle encryption
        // This method is kept for backward compatibility and migration
        return update_option($option_name, $value);
    }
    
    /**
     * Securely retrieve a credential with caching to improve performance
     */
    public static function get_credential($option_name, $default = '') {
        // Check cache first
        if (isset(self::$credential_cache[$option_name])) {
            return self::$credential_cache[$option_name];
        }
        
        $stored_value = get_option($option_name, $default);
        
        if (empty($stored_value)) {
            self::$credential_cache[$option_name] = $default;
            return $default;
        }
        
        // Check for environment variable override (highest security)
        $env_var = strtoupper(str_replace('cai_', 'CAI_', $option_name));
        $env_value = getenv($env_var);
        if (!empty($env_value)) {
            self::$credential_cache[$option_name] = $env_value;
            return $env_value;
        }
        
        // Check if OpenSSL is available for decryption
        if (!function_exists('openssl_decrypt')) {
            error_log('Creator AI: OpenSSL not available, returning stored value as-is');
            self::$credential_cache[$option_name] = $stored_value;
            return $stored_value;
        }
        
        $decrypted_value = self::decrypt_credential($stored_value);
        self::$credential_cache[$option_name] = $decrypted_value;
        
        return $decrypted_value;
    }
    
    /**
     * Clear credential cache (useful after credential updates)
     */
    public static function clear_credential_cache($option_name = null) {
        if ($option_name) {
            unset(self::$credential_cache[$option_name]);
        } else {
            self::$credential_cache = array();
        }
    }
    
    /**
     * Mask credential for display in admin interface
     */
    public static function mask_credential($value, $show_chars = 4) {
        if (empty($value)) {
            return '';
        }
        
        $length = strlen($value);
        if ($length <= $show_chars * 2) {
            return str_repeat('•', $length);
        }
        
        return substr($value, 0, $show_chars) . str_repeat('•', $length - ($show_chars * 2)) . substr($value, -$show_chars);
    }
    
    /**
     * Check if user has permission to access credentials
     */
    public static function can_access_credentials() {
        return current_user_can('manage_options') && 
               (current_user_can('edit_plugins') || current_user_can('install_plugins'));
    }
    
    /**
     * Migrate existing plain text credentials to encrypted storage
     */
    public static function migrate_credentials_to_encrypted() {
        if (!self::can_access_credentials()) {
            return false;
        }
        
        $credentials_to_migrate = [
            'cai_openai_api_key',
            'cai_google_client_secret'
        ];
        
        $migrated = 0;
        foreach ($credentials_to_migrate as $option_name) {
            $current_value = get_option($option_name, '');
            
            // Skip if empty or already encrypted
            if (empty($current_value) || strpos($current_value, 'cai_encrypted:') === 0) {
                continue;
            }
            
            // Encrypt and store
            if (self::store_credential($option_name, $current_value)) {
                $migrated++;
            }
        }
        
        if ($migrated > 0) {
            error_log("Creator AI: Migrated {$migrated} credentials to encrypted storage");
        }
        
        return $migrated;
    }
    
    /**
     * Validate credential format (basic validation)
     */
    public static function validate_credential_format($option_name, $value) {
        if (empty($value)) {
            return true; // Allow empty values
        }
        
        switch ($option_name) {
            case 'cai_openai_api_key':
                // OpenAI API keys typically start with 'sk-' and are 51+ chars
                // Modern keys can contain hyphens and underscores (e.g., sk-proj-..., sk-svcacct-...)
                if (!preg_match('/^sk-[a-zA-Z0-9_-]{20,}$/', $value)) {
                    return new WP_Error('invalid_openai_key', 'Invalid OpenAI API key format. Keys should start with "sk-" followed by alphanumeric characters, hyphens, or underscores.');
                }
                break;
                
            case 'cai_google_client_secret':
                // Google client secrets are typically 24+ character alphanumeric strings
                if (strlen($value) < 24 || !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    return new WP_Error('invalid_google_secret', 'Invalid Google Client Secret format.');
                }
                break;
        }
        
        return true;
    }
}