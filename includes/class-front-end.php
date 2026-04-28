<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Front_End {

    public function __construct() {
        remove_action( 'wp_head', 'wp_generator' );
        add_action( 'wp_head', [ $this, 'output_meta' ],         1 );
        add_action( 'wp_head', [ $this, 'output_og_tags' ],      2 );
        add_action( 'wp_head', [ $this, 'output_twitter_tags' ], 3 );
        add_action( 'wp_head', [ $this, 'output_robots' ],       4 );
        add_action( 'wp_head', [ $this, 'output_canonical' ],    5 );
        add_action( 'wp_head', [ $this, 'output_verification' ], 6 );
        add_filter( 'pre_get_document_title', [ $this, 'filter_document_title' ] );
    }

    // ── Title ──────────────────────────────────────────────────────────────
    public function filter_document_title( $title ) {
        if ( ! is_singular() ) return $title;
        $post_id    = get_queried_object_id();
        $meta_title = get_post_meta( $post_id, '_lumos_meta_title', true );
        if ( $meta_title ) return $meta_title;
        $sep  = get_option( 'lumos_seo_separator', '|' );
        $site = get_option( 'lumos_seo_site_name', get_bloginfo( 'name' ) );
        return get_the_title( $post_id ) . ' ' . $sep . ' ' . $site;
    }

    // ── Basic meta ──────────────────────────────────────────────────────────
    public function output_meta() {
        if ( ! is_singular() ) return;
        $id   = get_queried_object_id();
        $desc = get_post_meta( $id, '_lumos_meta_description', true );
        $kw   = get_post_meta( $id, '_lumos_focus_keyword', true );
        if ( $desc ) echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        if ( $kw )   echo '<meta name="keywords" content="' . esc_attr( $kw ) . '">' . "\n";
    }

    // ── Open Graph tags ────────────────────────────────────────────────────
    public function output_og_tags() {
        if ( ! is_singular() ) return;
        $id   = get_queried_object_id();
        $post = get_post( $id );

        // Resolve each field with smart fallback chain
        $og_title = $this->first(
            get_post_meta( $id, '_lumos_og_title', true ),
            get_post_meta( $id, '_lumos_meta_title', true ),
            get_the_title( $id )
        );
        $og_desc  = $this->first(
            get_post_meta( $id, '_lumos_og_description', true ),
            get_post_meta( $id, '_lumos_meta_description', true )
        );
        $og_url   = $this->first(
            get_post_meta( $id, '_lumos_og_url', true ),
            get_permalink( $id )
        );
        $og_type  = $this->first(
            get_post_meta( $id, '_lumos_og_type', true ),
            ( $post->post_type === 'post' ? 'article' : 'website' )
        );
        $og_site  = $this->first(
            get_post_meta( $id, '_lumos_og_site_name', true ),
            get_option( 'lumos_seo_site_name', get_bloginfo( 'name' ) )
        );
        $og_locale = $this->first(
            get_post_meta( $id, '_lumos_og_locale', true ),
            'en_US'
        );

        // Image: custom og_image → featured image → global default
        $og_image = get_post_meta( $id, '_lumos_og_image', true );
        if ( ! $og_image ) {
            $thumb_id = get_post_thumbnail_id( $id );
            if ( $thumb_id ) $og_image = wp_get_attachment_image_url( $thumb_id, 'large' );
        }
        if ( ! $og_image ) {
            $default_id = get_option( 'lumos_seo_default_og_image' );
            if ( $default_id ) $og_image = wp_get_attachment_image_url( $default_id, 'large' );
        }

        $this->meta_tag( 'og:type',        $og_type,   'property' );
        $this->meta_tag( 'og:title',       $og_title,  'property' );
        $this->meta_tag( 'og:url',         $og_url,    'property' );
        $this->meta_tag( 'og:site_name',   $og_site,   'property' );
        $this->meta_tag( 'og:locale',      $og_locale, 'property' );
        if ( $og_desc )  $this->meta_tag( 'og:description', $og_desc,  'property' );
        if ( $og_image ) {
            $this->meta_tag( 'og:image', $og_image, 'property' );
            // Image dimensions (helps Facebook renderer)
            $size = @getimagesize( $og_image );
            if ( $size ) {
                $this->meta_tag( 'og:image:width',  $size[0], 'property' );
                $this->meta_tag( 'og:image:height', $size[1], 'property' );
            }
            $this->meta_tag( 'og:image:secure_url', $og_image, 'property' );
        }

        // Article-specific tags
        if ( $og_type === 'article' ) {
            $this->meta_tag( 'article:published_time', get_the_date( 'c', $id ),          'property' );
            $this->meta_tag( 'article:modified_time',  get_the_modified_date( 'c', $id ), 'property' );
            $author = get_the_author_meta( 'display_name', $post->post_author );
            if ( $author ) $this->meta_tag( 'article:author', $author, 'property' );
        }

        // JSON-LD Article / WebPage schema
        $schema = array_filter( [
            '@context'      => 'https://schema.org',
            '@type'         => $og_type === 'article' ? 'Article' : 'WebPage',
            'headline'      => $og_title,
            'url'           => $og_url,
            'description'   => $og_desc ?: null,
            'image'         => $og_image ?: null,
            'datePublished' => get_the_date( 'c', $id ),
            'dateModified'  => get_the_modified_date( 'c', $id ),
            'author'        => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $post->post_author ),
            ],
            'publisher'     => [
                '@type' => 'Organization',
                'name'  => $og_site,
            ],
        ] );
        echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
    }

    // ── Twitter / X tags ──────────────────────────────────────────────────
    public function output_twitter_tags() {
        if ( ! is_singular() ) return;
        $id = get_queried_object_id();

        $card  = $this->first( get_post_meta( $id, '_lumos_twitter_card', true ), 'summary_large_image' );
        $title = $this->first(
            get_post_meta( $id, '_lumos_twitter_title', true ),
            get_post_meta( $id, '_lumos_og_title', true ),
            get_post_meta( $id, '_lumos_meta_title', true ),
            get_the_title( $id )
        );
        $desc  = $this->first(
            get_post_meta( $id, '_lumos_twitter_description', true ),
            get_post_meta( $id, '_lumos_og_description', true ),
            get_post_meta( $id, '_lumos_meta_description', true )
        );
        $image = $this->first(
            get_post_meta( $id, '_lumos_twitter_image', true ),
            get_post_meta( $id, '_lumos_og_image', true )
        );
        if ( ! $image ) {
            $thumb = get_post_thumbnail_id( $id );
            if ( $thumb ) $image = wp_get_attachment_image_url( $thumb, 'large' );
        }

        $this->meta_tag( 'twitter:card',  $card,  'name' );
        $this->meta_tag( 'twitter:title', $title, 'name' );
        if ( $desc )  $this->meta_tag( 'twitter:description', $desc,  'name' );
        if ( $image ) $this->meta_tag( 'twitter:image',       $image, 'name' );
    }

    // ── Robots ─────────────────────────────────────────────────────────────
    public function output_robots() {
        $directives = [];
        if ( is_singular() ) {
            $id = get_queried_object_id();
            if ( get_post_meta( $id, '_lumos_noindex', true ) ) {
                $directives = [ 'noindex', 'nofollow' ];
            }
        }
        if ( get_option( 'lumos_seo_noindex_archives' ) && ( is_category() || is_tag() || is_author() || is_date() ) ) {
            $directives = [ 'noindex', 'follow' ];
        }
        if ( $directives ) {
            echo '<meta name="robots" content="' . esc_attr( implode( ', ', $directives ) ) . '">' . "\n";
        }
    }

    // ── Canonical ──────────────────────────────────────────────────────────
    public function output_canonical() {
        if ( ! is_singular() ) return;
        $id  = get_queried_object_id();
        $url = get_post_meta( $id, '_lumos_canonical', true ) ?: get_permalink( $id );
        echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
    }

    // ── Google verification ────────────────────────────────────────────────
    public function output_verification() {
        $code = get_option( 'lumos_seo_google_site_verification' );
        if ( $code ) echo '<meta name="google-site-verification" content="' . esc_attr( $code ) . '">' . "\n";
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    private function meta_tag( $name, $value, $attr = 'name' ) {
        if ( ! $value && $value !== '0' ) return;
        if ( $attr === 'property' ) {
            echo '<meta property="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '">' . "\n";
        } else {
            echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '">' . "\n";
        }
    }

    /** Returns the first non-empty value from the arguments. */
    private function first( ...$values ) {
        foreach ( $values as $v ) {
            if ( $v !== '' && $v !== null && $v !== false ) return $v;
        }
        return '';
    }
}
