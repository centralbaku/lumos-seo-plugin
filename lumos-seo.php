<?php
/**
 * Plugin Name: Lumos SEO
 * Plugin URI:  https://lumosseo.com
 * Description: On-page SEO analysis for WordPress — keyword optimization, readability scoring, snippet preview, and meta management.
 * Version:     1.10.9
 * Author:      Orkhan Hasanov
 * License:     GPL-2.0+
 * Text Domain: lumos-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LUMOS_SEO_VERSION', '1.10.9' );
define( 'LUMOS_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMOS_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once LUMOS_SEO_DIR . 'includes/class-updater.php';
require_once LUMOS_SEO_DIR . 'includes/class-analyzer.php';
require_once LUMOS_SEO_DIR . 'includes/class-meta-box.php';
require_once LUMOS_SEO_DIR . 'includes/class-admin.php';
require_once LUMOS_SEO_DIR . 'includes/class-front-end.php';
require_once LUMOS_SEO_DIR . 'includes/class-sitemap.php';
require_once LUMOS_SEO_DIR . 'includes/class-elementor.php';

new Lumos_SEO_Updater( __FILE__, LUMOS_SEO_VERSION );
new Lumos_SEO_Admin();
new Lumos_SEO_Meta_Box();
new Lumos_SEO_Front_End();
new Lumos_SEO_Sitemap();
new Lumos_SEO_Elementor();

register_activation_hook( __FILE__, 'lumos_seo_activate' );
function lumos_seo_activate() {
    flush_rewrite_rules();
    lumos_seo_migrate_meta_keys();
}

// One-time migration: copy _lumo_* meta keys → _lumos_* for all posts
// Runs on activation AND once on init (catches in-place upgrades)
add_action( 'init', function () {
    if ( get_option( 'lumos_seo_meta_migrated' ) === LUMOS_SEO_VERSION ) return;
    lumos_seo_migrate_meta_keys();
    update_option( 'lumos_seo_meta_migrated', LUMOS_SEO_VERSION );
} );

function lumos_seo_migrate_meta_keys() {
    global $wpdb;

    $old_prefix = '_lumo_';
    $new_prefix = '_lumos_';

    // Find all _lumo_* meta keys that exist
    $old_keys = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like( $old_prefix ) . '%'
    ) );

    if ( empty( $old_keys ) ) return;

    foreach ( $old_keys as $old_key ) {
        $new_key = $new_prefix . substr( $old_key, strlen( $old_prefix ) );

        // Copy rows where the new key doesn't already exist for that post
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT pm.post_id, %s, pm.meta_value
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = %s
               AND NOT EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} pm2
                   WHERE pm2.post_id = pm.post_id AND pm2.meta_key = %s
               )",
            $new_key, $old_key, $new_key
        ) );
    }
}

register_deactivation_hook( __FILE__, 'lumos_seo_deactivate' );
function lumos_seo_deactivate() {
    flush_rewrite_rules();
}
