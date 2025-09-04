<?php
/**
 * Certificate PDF Generator
 * Generates PDF certificates server-side using Dompdf library
 */

if (!defined('ABSPATH')) {
    exit;
}

// Require Dompdf autoloader
require_once plugin_dir_path(__FILE__) . 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class CAI_Certificate_PDF_Generator {
    
    private $course_data;
    private $settings;
    
    public function __construct() {
        // No specific library loading needed here, Dompdf is autoloaded
    }
    
    /**
     * Generate certificate PDF
     */
    public function generate_certificate($course_id, $post_id, $student_name) {
        // Get course publisher instance from global context
        global $cai_course_publisher;
        if (!$cai_course_publisher) {
            // Create a temporary instance if needed
            require_once plugin_dir_path(__FILE__) . '../creator-ai.php';
        }
        
        // Load course data using direct file access method
        $this->course_data = $this->load_course_file($course_id);
        $this->settings = $this->get_course_appearance_settings();
        
        if (!$this->course_data) {
            wp_die('Course data not found', 'Certificate Generation Error', array('response' => 404));
        }
        
        // Get user data and completion info
        $user_id = get_current_user_id();
        $user_progress = $this->get_user_course_progress_data($user_id, $course_id);
        
        // Verify course completion
        if (!$this->is_course_completed($user_progress)) {
            wp_die('Course not completed', 'Certificate Generation Error', array('response' => 403));
        }
        
        // Generate PDF using HTML/CSS approach
        $this->generate_html_pdf($student_name, $user_progress);
    }
    
    /**
     * Check if course is completed
     */
    private function is_course_completed($user_progress) {
        if (empty($user_progress) || !isset($user_progress['completed_sections'])) {
            return false;
        }
        
        // Count total sections
        $total_sections = $this->count_total_sections($this->course_data);
        $completed_sections = count($user_progress['completed_sections']);
        
        return $completed_sections >= $total_sections;
    }
    
    /**
     * Count total sections in course
     */
    private function count_total_sections($course_data) {
        $count = 0;
        if (isset($course_data['chapters']) && is_array($course_data['chapters'])) {
            foreach ($course_data['chapters'] as $chapter) {
                if (isset($chapter['sections']) && is_array($chapter['sections'])) {
                    $count += count($chapter['sections']);
                }
            }
        }
        return $count;
    }
    
    /**
     * Generate PDF using Dompdf
     */
    private function generate_html_pdf($student_name, $user_progress) {
        // Get completion date
        $completed_date = isset($user_progress['completion_date']) ? $user_progress['completion_date'] : current_time('mysql');
        $formatted_date = date_i18n(get_option('date_format'), strtotime($completed_date));
        
        // Generate certificate ID
        $certificate_id = $this->generate_certificate_id(get_current_user_id(), $this->course_data['id']);
        
        // Get settings
        $settings = $this->settings;
        $course_title = $this->clean_text_content($this->course_data['title']);
        $student_name = $this->clean_text_content($student_name);
        $company_name = $this->clean_text_content(!empty($settings['certificate_company_name']) ? $settings['certificate_company_name'] : get_bloginfo('name'));

        // Prepare image URLs for HTML
        $logo_url = !empty($settings['certificate_logo']) ? $this->get_image_base64($settings['certificate_logo']) : '';
        $signature_url = !empty($settings['certificate_signature_image']) ? $this->get_image_base64($settings['certificate_signature_image']) : '';

        // Prepare styles
        $font_family = esc_attr($settings['certificate_font']);
        $title_size = esc_attr($settings['certificate_title_size']);
        $title_color = esc_attr($settings['certificate_title_color']);
        $border_width = esc_attr($settings['certificate_border_width']);
        $border_color = esc_attr($settings['certificate_border_color']);
        $layout = esc_attr($settings['certificate_layout']); // standard, modern, classic

        // Start output buffering to capture HTML from template
        ob_start();
        include plugin_dir_path(__FILE__) . 'certificate-template.html';
        $html = ob_get_clean();

        // Instantiate and use Dompdf with optimized settings
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Enable remote images if not using base64
        $options->set('defaultFont', $font_family); // Set default font
        $options->set('dpi', 96); // Set DPI for consistent sizing
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('isFontSubsettingEnabled', true); // Enable font subsetting for smaller file size
        $options->set('debugKeepTemp', false); // Don't keep temp files
        $options->set('debugCss', false); // Disable CSS debugging
        $options->set('debugLayout', false); // Disable layout debugging
        $options->set('debugLayoutLines', false); // Disable layout line debugging
        $options->set('debugLayoutBlocks', false); // Disable layout block debugging
        $options->set('debugLayoutInline', false); // Disable layout inline debugging
        $options->set('debugLayoutPaddingBox', false); // Disable padding box debugging

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        
        // Set paper size and orientation (A4 portrait) with proper margins
        $dompdf->setPaper('A4', 'portrait');
        
        // Render the HTML as PDF
        $dompdf->render();
        
        // Output the generated PDF to Browser
        $filename = sanitize_title($this->course_data['title']) . '-certificate.pdf';
        $dompdf->stream($filename, array("Attachment" => true));
        exit;
    }
    
    /**
     * Generate certificate ID
     */
    private function generate_certificate_id($user_id, $course_id) {
        $base = substr(md5($user_id . $course_id . AUTH_SALT), 0, 12);
        return strtoupper(substr($base, 0, 4) . '-' . substr($base, 4, 4) . '-' . substr($base, 8, 4));
    }
    
    /**
     * Load course file data
     */
    private function load_course_file($course_id) {
        $upload_dir = wp_upload_dir();
        $course_file = $upload_dir['basedir'] . '/creator-ai-courses/' . $course_id . '.json';
        
        if (!file_exists($course_file)) {
            return false;
        }
        
        $course_content = file_get_contents($course_file);
        $course_data = json_decode($course_content, true);
        
        return $course_data && json_last_error() === JSON_ERROR_NONE ? $course_data : false;
    }
    
    /**
     * Get course appearance settings
     */
    private function get_course_appearance_settings() {
        $default_settings = array(
            'certificate_font' => 'Arial', // Changed to Arial as a common web-safe font
            'certificate_title_size' => '32',
            'certificate_title_color' => '#333333',
            'certificate_border_width' => '3',
            'certificate_border_color' => '#cccccc',
            'certificate_logo' => '',
            'certificate_signature_image' => '',
            'certificate_company_name' => '',
            'certificate_layout' => 'standard' // Default layout
        );
        
        $saved_settings = get_option('cai_course_appearance_settings', array());
        
        return array_merge($default_settings, $saved_settings);
    }
    
    /**
     * Get user course progress data
     */
    private function get_user_course_progress_data($user_id, $course_id) {
        if (!$user_id) {
            return array();
        }
        
        $progress_key = 'cai_course_progress_' . $course_id;
        $progress = get_user_meta($user_id, $progress_key, true);
        
        if (empty($progress)) {
            $progress = array(
                'course_id' => $course_id,
                'started_date' => current_time('mysql'),
                'completed_sections' => array(),
                'completed' => false
            );
        }
        
        return $progress;
    }

    /**
     * Helper to convert image URL to base64 for embedding in HTML
     */
    private function get_image_base64($image_url) {
        if (empty($image_url)) {
            return '';
        }

        // Check if the URL is local to avoid issues with remote fetching
        // This is a basic check, more robust validation might be needed
        if (strpos($image_url, site_url()) !== false) {
            $image_path = str_replace(site_url(), ABSPATH, $image_url);
            if (file_exists($image_path)) {
                $type = pathinfo($image_path, PATHINFO_EXTENSION);
                $data = file_get_contents($image_path);
                if ($data !== false) {
                    return 'data:image/' . $type . ';base64,' . base64_encode($data);
                }
            }
        }
        // Fallback to original URL if not local or cannot be read
        return $image_url;
    }

    /**
     * Clean and validate text content for PDF generation
     */
    private function clean_text_content($text) {
        if (empty($text)) {
            return '';
        }

        // Remove any potentially problematic characters
        $text = wp_strip_all_tags($text);
        
        // Remove extra whitespace and normalize
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Limit text length to prevent layout issues
        if (strlen($text) > 200) {
            $text = substr($text, 0, 197) . '...';
        }
        
        // Convert special characters to HTML entities for better PDF rendering
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $text;
    }
}