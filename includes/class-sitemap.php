<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Sitemap {

    public function __construct() {
        add_action( 'init',           [ $this, 'add_rewrite' ] );
        add_filter( 'query_vars',     [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'handle' ] );
        add_action( 'save_post',      [ $this, 'flush' ] );
    }

    public function add_rewrite() {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?lumo_sitemap=1', 'top' );
    }

    public function add_query_var( $vars ) {
        $vars[] = 'lumo_sitemap';
        return $vars;
    }

    public function flush( $post_id ) {
        if ( ! wp_is_post_revision( $post_id ) ) {
            flush_rewrite_rules();
        }
    }

    public function handle() {
        if ( ! get_query_var( 'lumo_sitemap' ) ) return;

        $posts = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 1000,
            'meta_query'     => [
                [
                    'key'     => '_lumos_noindex',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        header( 'Content-Type: application/xml; charset=UTF-8' );
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        echo "<url>\n<loc>" . esc_url( home_url( '/' ) ) . "</loc>\n<changefreq>daily</changefreq>\n<priority>1.0</priority>\n</url>\n";

        foreach ( $posts as $post ) {
            $modified = get_post_modified_time( 'c', true, $post->ID );
            echo "<url>\n";
            echo '<loc>' . esc_url( get_permalink( $post->ID ) ) . "</loc>\n";
            echo '<lastmod>' . esc_html( $modified ) . "</lastmod>\n";
            echo '<changefreq>weekly</changefreq>' . "\n";
            echo '<priority>' . ( $post->post_type === 'page' ? '0.8' : '0.6' ) . "</priority>\n";
            echo "</url>\n";
        }

        echo '</urlset>';
        exit;
    }
}
