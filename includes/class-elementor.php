<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumo_SEO_Elementor {

    public function __construct() {
        // Only hook when Elementor is active
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        if ( ! did_action( 'elementor/loaded' ) ) return;
        add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'elementor/editor/after_enqueue_styles',  [ $this, 'enqueue_styles' ] );
        // Add custom document controls tab
        add_action( 'elementor/documents/register_controls', [ $this, 'register_document_controls' ] );
    }

    public function enqueue() {
        wp_enqueue_script(
            'lumo-seo-elementor',
            LUMO_SEO_URL . 'assets/js/elementor.js',
            [ 'jquery', 'elementor-editor' ],
            LUMO_SEO_VERSION,
            true
        );
        wp_localize_script( 'lumo-seo-elementor', 'lumoSEO', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'lumo_seo_nonce' ),
            'post_id' => get_the_ID(),
            'siteUrl' => home_url(),
            'meta'    => [
                'focus_keyword'        => get_post_meta( get_the_ID(), '_lumo_focus_keyword', true ),
                'meta_title'           => get_post_meta( get_the_ID(), '_lumo_meta_title', true ),
                'meta_description'     => get_post_meta( get_the_ID(), '_lumo_meta_description', true ),
                'og_title'             => get_post_meta( get_the_ID(), '_lumo_og_title', true ),
                'og_description'       => get_post_meta( get_the_ID(), '_lumo_og_description', true ),
                'og_image'             => get_post_meta( get_the_ID(), '_lumo_og_image', true ),
                'og_url'               => get_post_meta( get_the_ID(), '_lumo_og_url', true ),
                'og_type'              => get_post_meta( get_the_ID(), '_lumo_og_type', true ),
                'og_site_name'         => get_post_meta( get_the_ID(), '_lumo_og_site_name', true ),
                'og_locale'            => get_post_meta( get_the_ID(), '_lumo_og_locale', true ),
                'twitter_card'         => get_post_meta( get_the_ID(), '_lumo_twitter_card', true ),
                'twitter_title'        => get_post_meta( get_the_ID(), '_lumo_twitter_title', true ),
                'twitter_description'  => get_post_meta( get_the_ID(), '_lumo_twitter_description', true ),
                'twitter_image'        => get_post_meta( get_the_ID(), '_lumo_twitter_image', true ),
                'noindex'              => get_post_meta( get_the_ID(), '_lumo_noindex', true ),
                'canonical'            => get_post_meta( get_the_ID(), '_lumo_canonical', true ),
            ],
        ] );
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'lumo-seo-elementor',
            LUMO_SEO_URL . 'assets/css/elementor.css',
            [],
            LUMO_SEO_VERSION
        );
    }

    public function register_document_controls( $document ) {
        // Only for public document types (page, post, etc.)
        if ( ! $document instanceof \Elementor\Core\DocumentTypes\PageBase &&
             ! $document instanceof \Elementor\Modules\Library\Documents\Page ) {
            // Allow for any document — catch-all
        }
        // Intentionally left minimal — our JS panel handles the full UI
    }
}
