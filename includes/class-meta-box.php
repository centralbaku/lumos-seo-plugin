<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumo_SEO_Meta_Box {

    // All meta keys and their sanitization callbacks
    const FIELD_MAP = [
        '_lumo_focus_keyword'      => 'sanitize_text_field',
        '_lumo_meta_title'         => 'sanitize_text_field',
        '_lumo_meta_description'   => 'sanitize_textarea_field',
        // OG core
        '_lumo_og_title'           => 'sanitize_text_field',
        '_lumo_og_description'     => 'sanitize_textarea_field',
        '_lumo_og_image'           => 'esc_url_raw',
        '_lumo_og_url'             => 'esc_url_raw',
        '_lumo_og_type'            => 'sanitize_text_field',
        '_lumo_og_site_name'       => 'sanitize_text_field',
        '_lumo_og_locale'          => 'sanitize_text_field',
        // Twitter
        '_lumo_twitter_card'       => 'sanitize_text_field',
        '_lumo_twitter_title'      => 'sanitize_text_field',
        '_lumo_twitter_description'=> 'sanitize_textarea_field',
        '_lumo_twitter_image'      => 'esc_url_raw',
        // Advanced
        '_lumo_canonical'          => 'esc_url_raw',
        '_lumo_noindex'            => 'sanitize_text_field',
    ];

    public function __construct() {
        add_action( 'init',                        [ $this, 'register_meta' ] );
        add_action( 'add_meta_boxes',              [ $this, 'register_classic_box' ] );
        add_action( 'save_post',                   [ $this, 'save' ] );
        add_action( 'admin_enqueue_scripts',       [ $this, 'enqueue_classic' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block' ] );
        add_action( 'wp_ajax_lumo_seo_analyze',    [ $this, 'ajax_analyze' ] );
        add_action( 'wp_ajax_lumo_seo_save_meta',  [ $this, 'ajax_save_meta' ] );
        add_action( 'wp_ajax_lumo_seo_import_json',[ $this, 'ajax_import_json' ] );
    }

    // ── Meta registration ──────────────────────────────────────────────────
    public function register_meta() {
        foreach ( array_keys( self::FIELD_MAP ) as $key ) {
            register_post_meta( '', $key, [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => fn() => current_user_can( 'edit_posts' ),
            ] );
        }
    }

    // ── Meta box (shows in both classic AND block editor) ──────────────────
    public function register_classic_box() {
        $types = apply_filters( 'lumo_seo_post_types', [ 'post', 'page' ] );
        foreach ( $types as $type ) {
            add_meta_box( 'lumo_seo_box', '🔍 Lumo SEO', [ $this, 'render' ], $type, 'normal', 'high' );
        }
    }

    public function enqueue_classic( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        wp_enqueue_media();
        wp_enqueue_style( 'lumo-seo', LUMO_SEO_URL . 'assets/css/admin.css', [], LUMO_SEO_VERSION );
        wp_enqueue_script( 'lumo-seo', LUMO_SEO_URL . 'assets/js/admin.js', [ 'jquery' ], LUMO_SEO_VERSION, true );
        wp_localize_script( 'lumo-seo', 'lumoSEO', $this->script_data() );
    }

    public function enqueue_block() {
        wp_enqueue_style( 'lumo-seo-block', LUMO_SEO_URL . 'assets/css/sidebar.css', [], LUMO_SEO_VERSION );
        wp_enqueue_script(
            'lumo-seo-block',
            LUMO_SEO_URL . 'assets/js/gutenberg.js',
            [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'jquery' ],
            LUMO_SEO_VERSION,
            true
        );
        wp_localize_script( 'lumo-seo-block', 'lumoSEO', $this->script_data() );
    }

    private function script_data() {
        $post_id = get_the_ID();
        return [
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'lumo_seo_nonce' ),
            'post_id'     => $post_id,
            'siteUrl'     => home_url(),
            'siteName'    => get_option( 'lumo_seo_site_name', get_bloginfo( 'name' ) ),
            'exampleJson' => $this->example_json(),
        ];
    }

    private function example_json() {
        return wp_json_encode( [
            // ── Core SEO ──────────────────────────────────────────────────
            'focus_keyword'        => 'your main keyword here',
            'meta_title'           => 'Page Title With Keyword — Site Name (30–60 chars)',
            'meta_description'     => 'A compelling 120–158 character description that includes your focus keyword and encourages clicks from search results.',
            // ── Open Graph ────────────────────────────────────────────────
            'og_title'             => 'Social share title shown on Facebook / LinkedIn / WhatsApp',
            'og_description'       => 'Short, punchy social description (2–3 sentences max).',
            'og_image'             => 'https://yoursite.com/images/social-banner.jpg',
            'og_url'               => 'https://yoursite.com/page-slug/',
            'og_type'              => 'article',
            'og_site_name'         => 'Your Brand Name',
            'og_locale'            => 'en_US',
            // ── Twitter / X ───────────────────────────────────────────────
            'twitter_card'         => 'summary_large_image',
            'twitter_title'        => 'Twitter-specific title (optional, falls back to og_title)',
            'twitter_description'  => 'Twitter-specific description (optional).',
            'twitter_image'        => 'https://yoursite.com/images/twitter-banner.jpg',
            // ── Advanced ──────────────────────────────────────────────────
            'canonical'            => 'https://yoursite.com/canonical-url/',
            'noindex'              => false,
            // ── Advisory (shown in UI, not saved to meta) ─────────────────
            'related_keywords'     => [ 'keyword variant', 'another phrase' ],
            'suggested_headings'   => [ 'H2 heading idea', 'Another section heading' ],
            'content_notes'        => 'Advisory notes for the writer — not saved to meta.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    // ── Classic editor render ──────────────────────────────────────────────
    public function render( $post ) {
        wp_nonce_field( 'lumo_seo_save', 'lumo_seo_nonce' );
        $m = [];
        foreach ( array_keys( self::FIELD_MAP ) as $key ) {
            $m[ $key ] = get_post_meta( $post->ID, $key, true );
        }
        // Convenience aliases used in template
        extract( $m ); // phpcs:ignore WordPress.PHP.DontExtract
        $focus_kw   = $m['_lumo_focus_keyword'];
        $meta_title = $m['_lumo_meta_title'] ?: get_the_title( $post->ID );
        $meta_desc  = $m['_lumo_meta_description'];
        $noindex    = $m['_lumo_noindex'];
        include LUMO_SEO_DIR . 'templates/meta-box.php';
    }

    // ── Classic editor save ────────────────────────────────────────────────
    public function save( $post_id ) {
        if ( ! isset( $_POST['lumo_seo_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['lumo_seo_nonce'], 'lumo_seo_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        foreach ( self::FIELD_MAP as $key => $cb ) {
            $post_key = ltrim( $key, '_' );           // _lumo_og_title → lumo_og_title
            $post_key = str_replace( 'lumo_', '', $post_key ); // → og_title
            if ( $key === '_lumo_noindex' ) {
                update_post_meta( $post_id, $key, isset( $_POST['noindex'] ) ? '1' : '' );
                continue;
            }
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $key, $cb( $_POST[ $post_key ] ) );
            }
        }
    }

    // ── AJAX: single meta save (Elementor live edits) ──────────────────────
    public function ajax_save_meta() {
        check_ajax_referer( 'lumo_seo_nonce', 'nonce' );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();

        $key = sanitize_key( $_POST['meta_key'] ?? '' );
        if ( ! array_key_exists( $key, self::FIELD_MAP ) ) wp_send_json_error( 'Disallowed key' );

        $cb  = self::FIELD_MAP[ $key ];
        $val = $cb( wp_unslash( $_POST['meta_val'] ?? '' ) );
        update_post_meta( $post_id, $key, $val );
        wp_send_json_success();
    }

    // ── AJAX: bulk JSON import ─────────────────────────────────────────────
    public function ajax_import_json() {
        check_ajax_referer( 'lumo_seo_nonce', 'nonce' );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( 'Permission denied' );

        $data = json_decode( wp_unslash( $_POST['json'] ?? '' ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) wp_send_json_error( 'Invalid JSON: ' . json_last_error_msg() );
        if ( ! is_array( $data ) || isset( $data[0] ) ) wp_send_json_error( 'JSON must be an object.' );

        // Map JSON keys → meta keys
        $json_to_meta = [
            'focus_keyword'         => '_lumo_focus_keyword',
            'meta_title'            => '_lumo_meta_title',
            'meta_description'      => '_lumo_meta_description',
            'og_title'              => '_lumo_og_title',
            'og_description'        => '_lumo_og_description',
            'og_image'              => '_lumo_og_image',
            'og_url'                => '_lumo_og_url',
            'og_type'               => '_lumo_og_type',
            'og_site_name'          => '_lumo_og_site_name',
            'og_locale'             => '_lumo_og_locale',
            'twitter_card'          => '_lumo_twitter_card',
            'twitter_title'         => '_lumo_twitter_title',
            'twitter_description'   => '_lumo_twitter_description',
            'twitter_image'         => '_lumo_twitter_image',
            'canonical'             => '_lumo_canonical',
        ];

        $applied  = [];
        $warnings = [];

        foreach ( $json_to_meta as $json_key => $meta_key ) {
            if ( ! array_key_exists( $json_key, $data ) ) continue;
            $val = $data[ $json_key ];
            if ( ! is_string( $val ) ) {
                $warnings[] = "{$json_key} skipped — expected string, got " . gettype( $val );
                continue;
            }
            $cb = self::FIELD_MAP[ $meta_key ] ?? 'sanitize_text_field';
            // Length warnings
            if ( $json_key === 'meta_title' && ( strlen( $val ) < 30 || strlen( $val ) > 60 ) ) {
                $warnings[] = "meta_title is " . strlen( $val ) . " chars (ideal 30–60)";
            }
            if ( $json_key === 'meta_description' && ( strlen( $val ) < 120 || strlen( $val ) > 158 ) ) {
                $warnings[] = "meta_description is " . strlen( $val ) . " chars (ideal 120–158)";
            }
            update_post_meta( $post_id, $meta_key, $cb( $val ) );
            $applied[] = $json_key;
        }

        if ( array_key_exists( 'noindex', $data ) ) {
            update_post_meta( $post_id, '_lumo_noindex', $data['noindex'] ? '1' : '' );
            $applied[] = 'noindex';
        }

        if ( empty( $applied ) ) wp_send_json_error( 'No recognised SEO fields found in the JSON.' );

        $analyzer = new Lumo_SEO_Analyzer();
        wp_send_json_success( [
            'applied'  => $applied,
            'warnings' => $warnings,
            'analysis' => $analyzer->analyze( $post_id ),
        ] );
    }

    // ── AJAX: analysis ─────────────────────────────────────────────────────
    public function ajax_analyze() {
        check_ajax_referer( 'lumo_seo_nonce', 'nonce' );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error();

        $analyzer = new Lumo_SEO_Analyzer();
        $result   = $analyzer->analyze( $post_id, [
            'focus_keyword'    => sanitize_text_field( $_POST['focus_keyword']    ?? '' ),
            'meta_title'       => sanitize_text_field( $_POST['meta_title']       ?? '' ),
            'meta_description' => sanitize_textarea_field( $_POST['meta_description'] ?? '' ),
            'content'          => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : null,
        ] );
        wp_send_json_success( $result );
    }
}
