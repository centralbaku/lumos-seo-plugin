<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumo_SEO_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        // SEO score column in post list
        add_filter( 'manage_posts_columns',       [ $this, 'add_column' ] );
        add_filter( 'manage_pages_columns',       [ $this, 'add_column' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'manage_pages_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    public function add_menu() {
        add_menu_page(
            'Lumo SEO',
            'Lumo SEO',
            'manage_options',
            'lumo-seo',
            [ $this, 'settings_page' ],
            'dashicons-chart-line',
            80
        );
    }

    public function register_settings() {
        register_setting( 'lumo_seo_settings', 'lumo_seo_separator', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '|' ] );
        register_setting( 'lumo_seo_settings', 'lumo_seo_site_name', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lumo_seo_settings', 'lumo_seo_default_og_image', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'lumo_seo_settings', 'lumo_seo_noindex_archives', [ 'sanitize_callback' => 'absint', 'default' => 0 ] );
        register_setting( 'lumo_seo_settings', 'lumo_seo_google_site_verification', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }

    public function settings_page() {
        ?>
        <div class="wrap lumo-seo-settings">
            <h1>Lumo SEO Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lumo_seo_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Title Separator</th>
                        <td>
                            <input type="text" name="lumo_seo_separator" value="<?php echo esc_attr( get_option( 'lumo_seo_separator', '|' ) ); ?>" size="4">
                            <p class="description">Separator between post title and site name in SEO title (e.g. | – •)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Site Name in Title</th>
                        <td>
                            <input type="text" name="lumo_seo_site_name" value="<?php echo esc_attr( get_option( 'lumo_seo_site_name', get_bloginfo( 'name' ) ) ); ?>">
                            <p class="description">Appended to SEO titles when no custom title is set.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Default OG Image</th>
                        <td>
                            <?php
                            $img_id  = get_option( 'lumo_seo_default_og_image' );
                            $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
                            ?>
                            <input type="hidden" name="lumo_seo_default_og_image" id="lumo_og_image_id" value="<?php echo esc_attr( $img_id ); ?>">
                            <div id="lumo_og_preview"><?php if ( $img_url ) echo '<img src="' . esc_url( $img_url ) . '" style="max-width:150px">'; ?></div>
                            <button type="button" class="button" onclick="lumoPickOGImage()">Choose Image</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Noindex Archive Pages</th>
                        <td>
                            <label>
                                <input type="checkbox" name="lumo_seo_noindex_archives" value="1" <?php checked( get_option( 'lumo_seo_noindex_archives' ), 1 ); ?>>
                                Add noindex to category, tag, and author archive pages
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Google Site Verification</th>
                        <td>
                            <input type="text" name="lumo_seo_google_site_verification" value="<?php echo esc_attr( get_option( 'lumo_seo_google_site_verification' ) ); ?>" class="regular-text">
                            <p class="description">Content value from Google Search Console verification meta tag.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function add_column( $columns ) {
        $columns['lumo_seo_score'] = 'SEO Score';
        return $columns;
    }

    public function render_column( $column, $post_id ) {
        if ( $column !== 'lumo_seo_score' ) return;
        $analyzer = new Lumo_SEO_Analyzer();
        $result   = $analyzer->analyze( $post_id );
        $score    = $result['score'];
        $color    = $score >= 70 ? '#46b450' : ( $score >= 40 ? '#ffb900' : '#dc3232' );
        printf(
            '<span style="display:inline-block;background:%s;color:#fff;border-radius:50%%;width:36px;height:36px;line-height:36px;text-align:center;font-weight:700">%d</span>',
            esc_attr( $color ),
            intval( $score )
        );
    }
}
