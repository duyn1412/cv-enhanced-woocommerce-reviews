<?php
/*
Plugin Name: CV Enhanced WooCommerce Reviews
Description: Upgrade the WooCommerce product review system with an interactive average summary, advanced filtering/sorting, and helpful voting via AJAX. Responsive and performance-focused.
Version: 1.1.8
Author: canadavapes dev
Text Domain: cv-enhanced-review
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Main plugin class to bootstrap all enhanced review logic
class CV_Enhanced_Review {
    public $core;
    public function __construct() {
        add_action('plugins_loaded', [ $this, 'load_textdomain' ] );
        $this->include_files();
        $this->core = new CVER_Core();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'init', [ $this, 'register_shortcodes' ] );
    }
    // Load plugin textdomain for translation support
    public function load_textdomain() {
        load_plugin_textdomain( 'cv-enhanced-review', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
    // Safely include plugin classes and helpers
    private function include_files() {
        require_once __DIR__ . '/includes/class-cver-core.php';
    }
    // Enqueue CSS/JS for review features, only on WooCommerce product pages
    public function enqueue_assets() {
        if ( is_product() ) {
            wp_enqueue_style( 'cver-style', plugins_url( 'assets/css/cver-style.css', __FILE__ ), array(), '1.1.8' );
            wp_enqueue_script( 'cver-script', plugins_url( 'assets/js/cver-script.js', __FILE__ ), array('jquery'), '1.1.8', true );
            wp_localize_script( 'cver-script', 'cver_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'rest_url' => rest_url( 'cver/v1/reviews-pagination' ),
                'nonce' => wp_create_nonce( 'cver-nonce' )
            ));
        }
    }
    // Register review system shortcode for use in products, blocks, or templates
    public function register_shortcodes() {
        add_shortcode('cv_woo_reviews', [ $this->core, 'shortcode_review_container' ] );
    }
}
// Bootstrap the plugin after all plugins are loaded
function cv_enhanced_review_load() {
    global $cv_enhanced_review;
    $cv_enhanced_review = new CV_Enhanced_Review();
}
add_action( 'plugins_loaded', 'cv_enhanced_review_load', 30 );
