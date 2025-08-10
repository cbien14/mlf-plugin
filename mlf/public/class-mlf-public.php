<?php
/**
 * The public-facing functionality of the plugin.
 *
 * This class is responsible for handling the public-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Mlf
 * @subpackage Mlf/public
 */

class Mlf_Public {

    /**
     * The constructor for the public-facing class.
     */
    public function __construct() {
        // Initialize public-facing functionality here.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue public styles and scripts.
     */
    public function enqueue_styles() {
        wp_enqueue_style( 'mlf-public-css', plugin_dir_url( __FILE__ ) . 'css/mlf-public.css' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'mlf-public-js', plugin_dir_url( __FILE__ ) . 'js/mlf-public.js', array( 'jquery' ), null, true );
    }

    /**
     * Render public content.
     */
    public function render_content() {
        // Output public-facing content here.
    }
}
?>