<?php
/**
 * Plugin Name: WC Product Gallery
 * Plugin URI: https://harunstudio.com
 * Description: Advanced product gallery with mobile swipe support and responsive thumbnail navigation
 * Version: 1.0.2
 * Author: Harun Studio
 * Author URI: https://harunstudio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-product-gallery
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_GALLERY_VERSION', '1.0.2');
define('WC_GALLERY_PATH', plugin_dir_path(__FILE__));
define('WC_GALLERY_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class WC_Product_Gallery {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Flag to check if assets are enqueued
     */
    private static $assets_enqueued = false;
    
    /**
     * Get instance
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
        // Only enqueue assets when shortcode is used
        add_shortcode('product_gallery', array($this, 'render_gallery'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Security: Add nonce to admin pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue CSS and JS - ONLY when shortcode is used
     */
    public function enqueue_assets() {
        // Prevent multiple enqueues
        if (self::$assets_enqueued) {
            return;
        }
        
        // CSS - Minified version if available
        $css_file = file_exists(WC_GALLERY_PATH . 'assets/css/gallery.min.css') 
            ? 'gallery.min.css' 
            : 'gallery.css';
            
        wp_enqueue_style(
            'wc-gallery-style',
            WC_GALLERY_URL . 'assets/css/' . $css_file,
            array(),
            WC_GALLERY_VERSION,
            'all'
        );
        
        // JavaScript - Minified version if available
        $js_file = file_exists(WC_GALLERY_PATH . 'assets/js/gallery.min.js') 
            ? 'gallery.min.js' 
            : 'gallery.js';
            
        wp_enqueue_script(
            'wc-gallery-script',
            WC_GALLERY_URL . 'assets/js/' . $js_file,
            array(),
            WC_GALLERY_VERSION,
            true // Load in footer
        );
        
        self::$assets_enqueued = true;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'settings_page_wc-product-gallery') {
            return;
        }
        
        wp_enqueue_style(
            'wc-gallery-admin',
            WC_GALLERY_URL . 'assets/css/admin.css',
            array(),
            WC_GALLERY_VERSION
        );
    }
    
    /**
     * Render Gallery Shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_gallery($atts) {
        // Enqueue assets only when shortcode is used
        $this->enqueue_assets();

        $options = get_option('wc_gallery_options', array());
        $default_field = isset($options['default_field']) && $options['default_field'] !== ''
            ? $options['default_field']
            : 'gallery_produk';
        
        // Parse and sanitize attributes
        $atts = shortcode_atts(array(
            'field' => $default_field,
            'lazy' => 'true', // Enable lazy loading by default
        ), $atts, 'product_gallery');
        
        // Sanitize field name
        $field_name = sanitize_text_field($atts['field']);
        $lazy_load = filter_var($atts['lazy'], FILTER_VALIDATE_BOOLEAN);
        
        // Get gallery from ACF
        if (!function_exists('get_field')) {
            return '<p>' . esc_html__('Advanced Custom Fields plugin is required.', 'wc-product-gallery') . '</p>';
        }
        
        $gallery = get_field($field_name);
        
        // Validate gallery data
        if (!$gallery || !is_array($gallery) || empty($gallery)) {
            return '';
        }
        
        // Limit gallery size for performance
        $max_images = apply_filters('wc_gallery_max_images', 20);
        if (count($gallery) > $max_images) {
            $gallery = array_slice($gallery, 0, $max_images);
        }
        
        // Start output buffering
        ob_start();
        
        // Unique ID for multiple galleries on same page
        $gallery_id = 'wc-gallery-' . uniqid();
        ?>
        <div class="wc-gallery" id="<?php echo esc_attr($gallery_id); ?>" data-gallery-count="<?php echo count($gallery); ?>">
            <!-- Thumbnails -->
            <div class="wc-thumbs" role="tablist" aria-label="<?php esc_attr_e('Product images', 'wc-product-gallery'); ?>">
                <?php foreach ($gallery as $index => $img): ?>
                    <?php
                        // Validate image data
                        if (!isset($img['url']) || empty($img['url'])) {
                            continue;
                        }
                        
                        // Use thumbnail size, fallback to URL
                        $thumb = isset($img['sizes']['thumbnail']) ? $img['sizes']['thumbnail'] : $img['url'];
                        $alt = !empty($img['alt']) ? $img['alt'] : sprintf(__('Product thumbnail %d', 'wc-product-gallery'), $index + 1);
                    ?>
                    <div class="wc-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                         data-index="<?php echo esc_attr($index); ?>"
                         role="tab"
                         aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                         aria-controls="slide-<?php echo esc_attr($index); ?>"
                         tabindex="<?php echo $index === 0 ? '0' : '-1'; ?>">
                        <img src="<?php echo esc_url($thumb); ?>" 
                             alt="<?php echo esc_attr($alt); ?>"
                             <?php echo $lazy_load && $index > 0 ? 'loading="lazy"' : ''; ?>
                             width="70"
                             height="70">
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Main Image Slider -->
            <div class="wc-main" role="region" aria-label="<?php esc_attr_e('Product gallery', 'wc-product-gallery'); ?>">
                <div class="wc-main-track">
                    <?php foreach ($gallery as $index => $img): ?>
                        <?php
                            // Validate image data
                            if (!isset($img['url']) || empty($img['url'])) {
                                continue;
                            }
                            
                            $alt = !empty($img['alt']) ? $img['alt'] : sprintf(__('Product image %d', 'wc-product-gallery'), $index + 1);
                            
                            // Get image dimensions for better performance
                            $width = isset($img['width']) ? intval($img['width']) : '';
                            $height = isset($img['height']) ? intval($img['height']) : '';
                        ?>
                        <div class="wc-slide" 
                             id="slide-<?php echo esc_attr($index); ?>"
                             role="tabpanel"
                             aria-label="<?php echo esc_attr($alt); ?>">
                            <img src="<?php echo esc_url($img['url']); ?>" 
                                 alt="<?php echo esc_attr($alt); ?>"
                                 <?php echo $lazy_load && $index > 0 ? 'loading="lazy"' : ''; ?>
                                 <?php echo $width ? 'width="' . esc_attr($width) . '"' : ''; ?>
                                 <?php echo $height ? 'height="' . esc_attr($height) . '"' : ''; ?>>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('WC Product Gallery Settings', 'wc-product-gallery'),
            __('Product Gallery', 'wc-product-gallery'),
            'manage_options',
            'wc-product-gallery',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wc_gallery_settings',
            'wc_gallery_options',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'wc_gallery_general',
            __('General Settings', 'wc-product-gallery'),
            array($this, 'render_settings_section'),
            'wc-product-gallery'
        );

        add_settings_field(
            'wc_gallery_default_field',
            __('Default ACF field', 'wc-product-gallery'),
            array($this, 'render_default_field_input'),
            'wc-product-gallery',
            'wc_gallery_general'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = get_option('wc_gallery_options', array());
        if (!is_array($sanitized)) {
            $sanitized = array();
        }
        
        if (isset($input['max_images'])) {
            $sanitized['max_images'] = absint($input['max_images']);
        }
        
        if (isset($input['lazy_load'])) {
            $sanitized['lazy_load'] = (bool) $input['lazy_load'];
        }
        
        if (isset($input['default_field'])) {
            $sanitized['default_field'] = sanitize_text_field($input['default_field']);
        }

        return $sanitized;
    }

    /**
     * Settings section description
     */
    public function render_settings_section() {
        echo '<p>' . esc_html__('Configure default behavior for the product gallery shortcode.', 'wc-product-gallery') . '</p>';
    }

    /**
     * Render default field input
     */
    public function render_default_field_input() {
        $options = get_option('wc_gallery_options', array());
        $value = isset($options['default_field']) && $options['default_field'] !== ''
            ? $options['default_field']
            : 'gallery_produk';

        printf(
            '<input type="text" name="wc_gallery_options[default_field]" value="%s" class="regular-text" />',
            esc_attr($value)
        );

        echo '<p class="description">' . esc_html__('Name of the ACF gallery field used by default when the shortcode does not pass the "field" attribute.', 'wc-product-gallery') . '</p>';
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-product-gallery'));
        }
        
        // Get current settings
        $options = get_option('wc_gallery_options', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>

            <form method="post" action="options.php" class="card">
                <?php
                settings_fields('wc_gallery_settings');
                do_settings_sections('wc-product-gallery');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php esc_html_e('Shortcode Usage', 'wc-product-gallery'); ?></h2>
                <p><?php esc_html_e('Use this shortcode in your posts, pages, or templates:', 'wc-product-gallery'); ?></p>
                <code>[product_gallery]</code>
                
                <p><?php esc_html_e('Or with custom ACF field name:', 'wc-product-gallery'); ?></p>
                <code>[product_gallery field="your_custom_field"]</code>
                
                <p><?php esc_html_e('Disable lazy loading:', 'wc-product-gallery'); ?></p>
                <code>[product_gallery lazy="false"]</code>
                
                <h3><?php esc_html_e('Requirements', 'wc-product-gallery'); ?></h3>
                <ul>
                    <li>✅ <?php esc_html_e('Advanced Custom Fields (ACF) plugin installed', 'wc-product-gallery'); ?></li>
                    <li>✅ <?php esc_html_e('Gallery field created in ACF', 'wc-product-gallery'); ?></li>
                    <li>✅ <?php esc_html_e('Gallery field assigned to your post type', 'wc-product-gallery'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Features', 'wc-product-gallery'); ?></h3>
                <ul>
                    <li>📱 <?php esc_html_e('Mobile swipe support', 'wc-product-gallery'); ?></li>
                    <li>🖱️ <?php esc_html_e('Desktop click navigation', 'wc-product-gallery'); ?></li>
                    <li>📸 <?php esc_html_e('Responsive thumbnail grid', 'wc-product-gallery'); ?></li>
                    <li>⚡ <?php esc_html_e('Lazy loading for better performance', 'wc-product-gallery'); ?></li>
                    <li>🔒 <?php esc_html_e('Security hardened', 'wc-product-gallery'); ?></li>
                    <li>♿ <?php esc_html_e('Accessibility ready (ARIA labels)', 'wc-product-gallery'); ?></li>
                    <li>🎨 <?php esc_html_e('Customizable via CSS variables', 'wc-product-gallery'); ?></li>
                </ul>
                
                <h3><?php esc_html_e('Performance Tips', 'wc-product-gallery'); ?></h3>
                <ul>
                    <li>✅ <?php esc_html_e('Assets only load when shortcode is used', 'wc-product-gallery'); ?></li>
                    <li>✅ <?php esc_html_e('Lazy loading enabled by default', 'wc-product-gallery'); ?></li>
                    <li>✅ <?php esc_html_e('Maximum 20 images per gallery (adjustable via filter)', 'wc-product-gallery'); ?></li>
                    <li>✅ <?php esc_html_e('Use minified CSS/JS in production', 'wc-product-gallery'); ?></li>
                </ul>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e('Developer Hooks', 'wc-product-gallery'); ?></h2>
                
                <h3><?php esc_html_e('Filters', 'wc-product-gallery'); ?></h3>
                <pre><code>// Adjust max images per gallery
add_filter('wc_gallery_max_images', function($max) {
    return 30; // Default: 20
});
</code></pre>
            </div>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin
 */
function wc_product_gallery_init() {
    return WC_Product_Gallery::get_instance();
}
add_action('plugins_loaded', 'wc_product_gallery_init');

/**
 * Activation callback
 */
function wc_product_gallery_activate() {
    // Create assets folders if they don't exist
    $dirs = array(
        WC_GALLERY_PATH . 'assets/css',
        WC_GALLERY_PATH . 'assets/js'
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wc_product_gallery_activate');

/**
 * Deactivation callback
 */
function wc_product_gallery_deactivate() {
    // Cleanup
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wc_product_gallery_deactivate');

/**
 * Uninstall callback
 */
function wc_product_gallery_uninstall() {
    // Clean up options
    delete_option('wc_gallery_options');
}
register_uninstall_hook(__FILE__, 'wc_product_gallery_uninstall');