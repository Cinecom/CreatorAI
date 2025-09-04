<?php

if (!defined('ABSPATH')) exit;

class Creator_AI_GitHub_Updater {
    
    private $plugin_slug;
    private $plugin_basename;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    private $github_branch;
    private $version;
    private $plugin_data;
    private $api_url;
    
    public function __construct($plugin_file, $github_username, $github_repo, $github_branch = 'main') {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->github_branch = $github_branch;
        $this->api_url = "https://api.github.com/repos/{$github_username}/{$github_repo}";
        
        $this->set_plugin_properties();
        $this->init_hooks();
    }
    
    private function set_plugin_properties() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $this->plugin_data = get_plugin_data($this->plugin_file);
        $this->version = $this->plugin_data['Version'];
    }
    
    private function init_hooks() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Check for updates on plugin activation
        add_action('wp_loaded', array($this, 'delete_transients'));
        
        // Add daily cron job for checking updates
        add_action('wp', array($this, 'schedule_update_check'));
        add_action('creator_ai_check_for_updates', array($this, 'check_for_updates'));
        
        // Clear transients when plugin is deactivated
        register_deactivation_hook($this->plugin_file, array($this, 'deactivation_cleanup'));
    }
    
    public function schedule_update_check() {
        if (!wp_next_scheduled('creator_ai_check_for_updates')) {
            wp_schedule_event(time(), 'daily', 'creator_ai_check_for_updates');
        }
    }
    
    public function check_for_updates() {
        delete_site_transient('update_plugins');
        delete_transient($this->plugin_slug . '_github_data');
        delete_transient($this->plugin_slug . '_update_data');
    }
    
    public function modify_transient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_remote_version();
        
        if (version_compare($this->version, $this->get_github_version(), '<')) {
            $plugin_data = $this->get_plugin_info();
            
            $transient->response[$this->plugin_basename] = (object) array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $this->get_github_version(),
                'url' => $this->plugin_data['PluginURI'],
                'package' => $this->get_zip_url(),
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => get_bloginfo('version'),
                'requires_php' => false,
                'compatibility' => array()
            );
        }
        
        return $transient;
    }
    
    public function plugin_popup($result, $action, $args) {
        if (!empty($args->slug) && $args->slug == $this->plugin_slug) {
            $this->get_remote_version();
            
            $plugin_data = $this->get_plugin_info();
            
            return (object) array(
                'name' => $this->plugin_data['Name'],
                'slug' => $this->plugin_slug,
                'version' => $this->get_github_version(),
                'author' => $this->plugin_data['AuthorName'],
                'author_profile' => $this->plugin_data['AuthorURI'],
                'last_updated' => $this->get_date(),
                'homepage' => $this->plugin_data['PluginURI'],
                'short_description' => $this->plugin_data['Description'],
                'sections' => array(
                    'Description' => $this->plugin_data['Description'],
                    'Updates' => $this->get_github_changelog(),
                ),
                'download_link' => $this->get_zip_url()
            );
        }
        return $result;
    }
    
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($this->plugin_data['TextDomain']) {
            $locale = apply_filters('theme_locale', get_locale(), $this->plugin_data['TextDomain']);
            load_textdomain($this->plugin_data['TextDomain'], $install_directory . '/languages/' . $this->plugin_data['TextDomain'] . '-' . $locale . '.mo');
        }

        return $result;
    }
    
    private function get_remote_version() {
        $request = $this->remote_get($this->api_url . '/contents/' . basename($this->plugin_file) . '?ref=' . $this->github_branch);
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) == 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            if (isset($data['content'])) {
                $content = base64_decode($data['content']);
                
                if (preg_match('/Version:\s*(.*)/', $content, $matches)) {
                    $version = trim($matches[1]);
                    set_transient($this->plugin_slug . '_github_data', array('version' => $version), HOUR_IN_SECONDS);
                    return $version;
                }
            }
        }
        
        return false;
    }
    
    private function get_github_version() {
        $version = get_transient($this->plugin_slug . '_github_data');
        
        if (empty($version)) {
            $version = $this->get_remote_version();
            return $version;
        }
        
        if (is_array($version) && isset($version['version'])) {
            return $version['version'];
        }
        
        return false;
    }
    
    private function get_zip_url() {
        return "https://github.com/{$this->github_username}/{$this->github_repo}/archive/{$this->github_branch}.zip";
    }
    
    private function get_plugin_info() {
        $request = $this->remote_get($this->api_url);
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) == 200) {
            return json_decode(wp_remote_retrieve_body($request), true);
        }
        
        return false;
    }
    
    private function get_date() {
        $request = $this->remote_get($this->api_url . '/commits?sha=' . $this->github_branch . '&per_page=1');
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) == 200) {
            $commits = json_decode(wp_remote_retrieve_body($request), true);
            if (!empty($commits) && isset($commits[0]['commit']['committer']['date'])) {
                return $commits[0]['commit']['committer']['date'];
            }
        }
        
        return false;
    }
    
    private function get_github_changelog() {
        $request = $this->remote_get($this->api_url . '/commits?sha=' . $this->github_branch . '&per_page=5');
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) == 200) {
            $commits = json_decode(wp_remote_retrieve_body($request), true);
            $changelog = '<ul>';
            
            foreach ($commits as $commit) {
                $message = isset($commit['commit']['message']) ? $commit['commit']['message'] : 'No commit message';
                $date = isset($commit['commit']['committer']['date']) ? date('M j, Y', strtotime($commit['commit']['committer']['date'])) : 'Unknown date';
                $changelog .= '<li><strong>' . $date . '</strong>: ' . esc_html($message) . '</li>';
            }
            
            $changelog .= '</ul>';
            return $changelog;
        }
        
        return '<p>Unable to fetch changelog from GitHub.</p>';
    }
    
    private function remote_get($url) {
        $args = array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/vnd.github.v3+json'
            )
        );
        
        return wp_remote_get($url, $args);
    }
    
    public function delete_transients() {
        delete_site_transient('update_plugins');
        delete_transient($this->plugin_slug . '_github_data');
        delete_transient($this->plugin_slug . '_update_data');
    }
    
    public function deactivation_cleanup() {
        wp_clear_scheduled_hook('creator_ai_check_for_updates');
        $this->delete_transients();
    }
    
    // Admin notice for available updates
    public function add_admin_notices() {
        add_action('admin_notices', array($this, 'show_update_notification'));
    }
    
    public function show_update_notification() {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!in_array($screen->id, array('dashboard', 'plugins'))) {
            return;
        }
        
        $this->get_remote_version();
        
        if (version_compare($this->version, $this->get_github_version(), '<')) {
            $plugin_name = $this->plugin_data['Name'];
            $new_version = $this->get_github_version();
            $current_version = $this->version;
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html($plugin_name) . '</strong>: A new version (' . esc_html($new_version) . ') is available. You are currently running version ' . esc_html($current_version) . '. ';
            echo '<a href="' . admin_url('plugins.php') . '">Update now</a> or <a href="https://github.com/' . esc_html($this->github_username) . '/' . esc_html($this->github_repo) . '" target="_blank">view changelog</a>.</p>';
            echo '</div>';
        }
    }
    
    // Force check for updates (can be called manually)
    public function force_update_check() {
        $this->delete_transients();
        $this->get_remote_version();
        
        if (version_compare($this->version, $this->get_github_version(), '<')) {
            return array(
                'update_available' => true,
                'current_version' => $this->version,
                'new_version' => $this->get_github_version(),
                'download_url' => $this->get_zip_url()
            );
        }
        
        return array('update_available' => false);
    }
}