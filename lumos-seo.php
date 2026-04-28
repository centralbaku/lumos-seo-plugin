<?php
/**
 * Plugin Name: Lumos SEO
 * Plugin URI:  https://lumosseo.com
 * Description: On-page SEO analysis for WordPress — keyword optimization, readability scoring, snippet preview, and meta management.
 * Version:     1.6.0
 * Author:      Orkhan Hasanov
 * License:     GPL-2.0+
 * Text Domain: lumos-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LUMOS_SEO_VERSION', '1.6.0' );
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
}

register_deactivation_hook( __FILE__, 'lumos_seo_deactivate' );
function lumos_seo_deactivate() {
    flush_rewrite_rules();
}
