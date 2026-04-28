<?php
/**
 * Plugin Name: Lumo SEO
 * Plugin URI:  https://lumoseo.com
 * Description: On-page SEO analysis for WordPress — keyword optimization, readability scoring, snippet preview, and meta management.
 * Version:     1.5.0
 * Author:      Orkhan Hasanov
 * License:     GPL-2.0+
 * Text Domain: lumo-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LUMO_SEO_VERSION', '1.5.0' );
define( 'LUMO_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUMO_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once LUMO_SEO_DIR . 'includes/class-analyzer.php';
require_once LUMO_SEO_DIR . 'includes/class-meta-box.php';
require_once LUMO_SEO_DIR . 'includes/class-admin.php';
require_once LUMO_SEO_DIR . 'includes/class-front-end.php';
require_once LUMO_SEO_DIR . 'includes/class-sitemap.php';
require_once LUMO_SEO_DIR . 'includes/class-elementor.php';

new Lumo_SEO_Admin();
new Lumo_SEO_Meta_Box();
new Lumo_SEO_Front_End();
new Lumo_SEO_Sitemap();
new Lumo_SEO_Elementor();

register_activation_hook( __FILE__, 'lumo_seo_activate' );
function lumo_seo_activate() {
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'lumo_seo_deactivate' );
function lumo_seo_deactivate() {
    flush_rewrite_rules();
}
