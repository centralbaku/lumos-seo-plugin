<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        // SEO score column in post list
        add_filter( 'manage_posts_columns',       [ $this, 'add_column' ] );
        add_filter( 'manage_pages_columns',       [ $this, 'add_column' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'manage_pages_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    public function add_menu() {
        add_menu_page(
            'Lumos SEO',
            'Lumos SEO',
            'manage_options',
            'lumos-seo-dashboard',
            [ $this, 'dashboard_page' ],
            'dashicons-chart-line',
            80
        );

        add_submenu_page(
            'lumos-seo-dashboard',
            'SEO Dashboard',
            'Dashboard',
            'manage_options',
            'lumos-seo-dashboard',
            [ $this, 'dashboard_page' ]
        );

        add_submenu_page(
            'lumos-seo-dashboard',
            'Lumos SEO Settings',
            'Settings',
            'manage_options',
            'lumos-seo',
            [ $this, 'settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'lumos_seo_settings', 'lumos_seo_separator', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '|' ] );
        register_setting( 'lumos_seo_settings', 'lumos_seo_site_name', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'lumos_seo_settings', 'lumos_seo_default_og_image', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'lumos_seo_settings', 'lumos_seo_noindex_archives', [ 'sanitize_callback' => 'absint', 'default' => 0 ] );
        register_setting( 'lumos_seo_settings', 'lumos_seo_google_site_verification', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting(
            'lumos_seo_settings',
            'lumos_seo_dashboard_data',
            [ 'sanitize_callback' => [ $this, 'sanitize_dashboard_data' ] ]
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook === 'toplevel_page_lumos-seo-dashboard' || $hook === 'lumos-seo_page_lumos-seo-dashboard' ) {
            wp_enqueue_style( 'lumos-seo-dashboard', LUMOS_SEO_URL . 'assets/css/dashboard.css', [], LUMOS_SEO_VERSION );
            wp_enqueue_script( 'lumos-seo-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', [], '4.4.3', true );
            wp_enqueue_script( 'lumos-seo-dashboard', LUMOS_SEO_URL . 'assets/js/dashboard.js', [ 'jquery', 'lumos-seo-chartjs' ], LUMOS_SEO_VERSION, true );
            wp_localize_script( 'lumos-seo-dashboard', 'lumosDashboard', [
                'data'       => $this->get_dashboard_data(),
                'overview'   => $this->get_overview_stats(),
                'sitePages'  => $this->get_site_pages_for_tracker(),
                'sitemapUrl' => home_url( '/sitemap.xml' ),
            ] );
        }

        if ( $hook === 'lumos-seo-dashboard_page_lumos-seo' ) {
            wp_enqueue_media();
        }
    }

    public function dashboard_page() {
        ?>
        <div class="wrap lumos-dashboard-wrap">
            <h1>Lumos SEO Dashboard</h1>
            <p class="description">Track SEO performance, keyword strategy, rankings, SERP insights, sitemap optimization, and implementation tasks.</p>

            <form method="post" action="options.php" id="lumos-dashboard-form">
                <?php settings_fields( 'lumos_seo_settings' ); ?>
                <textarea id="lumos-dashboard-data-field" name="lumos_seo_dashboard_data" hidden><?php echo esc_textarea( wp_json_encode( $this->get_dashboard_data() ) ); ?></textarea>

                <div class="lumos-dash-tabs">
                    <button type="button" class="lumos-dash-tab active" data-tab="overview">Overview</button>
                    <button type="button" class="lumos-dash-tab" data-tab="keywords">Keyword Research & Strategy</button>
                    <button type="button" class="lumos-dash-tab" data-tab="tracking">Keyword Ranking Tracker</button>
                    <button type="button" class="lumos-dash-tab" data-tab="serp">SERP Analysis</button>
                    <button type="button" class="lumos-dash-tab" data-tab="sitemap">Sitemap Tracker</button>
                    <button type="button" class="lumos-dash-tab" data-tab="checklist">Implementation Checklist</button>
                </div>

                <section id="lumos-tab-overview" class="lumos-dash-panel active">
                    <div id="lumos-overview-cards" class="lumos-overview-cards"></div>
                    <div class="lumos-chart-grid">
                        <div class="lumos-chart-card"><canvas id="lumos-traffic-chart"></canvas></div>
                        <div class="lumos-chart-card"><canvas id="lumos-rating-chart"></canvas></div>
                    </div>
                </section>

                <section id="lumos-tab-keywords" class="lumos-dash-panel">
                    <div class="lumos-actions-row">
                        <button type="button" class="button button-primary" id="lumos-add-keyword">Add keyword</button>
                        <input type="search" id="lumos-keyword-search" placeholder="Search keywords...">
                    </div>
                    <div class="lumos-table-wrap"><table class="widefat fixed striped"><thead><tr><th>Keyword</th><th>Category</th><th>Volume</th><th>Difficulty</th><th>Intent</th><th>Tags</th><th>Target URL</th><th>Priority</th><th></th></tr></thead><tbody id="lumos-keywords-body"></tbody></table></div>
                </section>

                <section id="lumos-tab-tracking" class="lumos-dash-panel">
                    <div class="lumos-actions-row">
                        <button type="button" class="button button-primary" id="lumos-add-tracking">Add ranking row</button>
                        <input type="search" id="lumos-tracking-search" placeholder="Search keyword...">
                        <input type="search" id="lumos-tag-filter" placeholder="Filter by tag (e.g. branded)">
                    </div>
                    <div class="lumos-table-wrap"><table class="widefat fixed striped"><thead><tr><th>Keyword</th><th>Current Rank</th><th>Target Rank</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Status</th><th>Tags</th><th></th></tr></thead><tbody id="lumos-tracking-body"></tbody></table></div>
                </section>

                <section id="lumos-tab-serp" class="lumos-dash-panel">
                    <div class="lumos-actions-row">
                        <button type="button" class="button button-primary" id="lumos-add-serp">Add SERP row</button>
                    </div>
                    <div class="lumos-table-wrap"><table class="widefat fixed striped"><thead><tr><th>Keyword</th><th>Top Competitor</th><th>Our Rank</th><th>Title Quality</th><th>Description Quality</th><th>Opportunity</th><th></th></tr></thead><tbody id="lumos-serp-body"></tbody></table></div>
                </section>

                <section id="lumos-tab-sitemap" class="lumos-dash-panel">
                    <div class="lumos-actions-row">
                        <button type="button" class="button" id="lumos-add-sitemap-item">Add URL row</button>
                        <a class="button" href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" rel="noopener">Open sitemap.xml</a>
                    </div>
                    <div id="lumos-sitemap-stats" class="lumos-overview-cards"></div>
                    <div class="lumos-table-wrap"><table class="widefat fixed striped"><thead><tr><th>Done</th><th>URL</th><th>Priority</th><th>Changefreq</th><th>Notes</th><th></th></tr></thead><tbody id="lumos-sitemap-body"></tbody></table></div>
                </section>

                <section id="lumos-tab-checklist" class="lumos-dash-panel">
                    <div class="lumos-actions-row">
                        <button type="button" class="button button-primary" id="lumos-add-check-item">Add task</button>
                    </div>
                    <div class="lumos-table-wrap"><table class="widefat fixed striped"><thead><tr><th>Done</th><th>Task</th><th>Category</th><th>Suggestion</th><th></th></tr></thead><tbody id="lumos-checklist-body"></tbody></table></div>
                </section>

                <p class="submit">
                    <button type="button" class="button" id="lumos-reset-dashboard">Reset to sample data</button>
                    <?php submit_button( 'Save Dashboard Data', 'primary', 'submit', false ); ?>
                </p>
            </form>
        </div>
        <?php
    }

    public function settings_page() {
        ?>
        <div class="wrap lumos-seo-settings">
            <h1>Lumos SEO Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'lumos_seo_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Title Separator</th>
                        <td>
                            <input type="text" name="lumos_seo_separator" value="<?php echo esc_attr( get_option( 'lumos_seo_separator', '|' ) ); ?>" size="4">
                            <p class="description">Separator between post title and site name in SEO title (e.g. | – •)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Site Name in Title</th>
                        <td>
                            <input type="text" name="lumos_seo_site_name" value="<?php echo esc_attr( get_option( 'lumos_seo_site_name', get_bloginfo( 'name' ) ) ); ?>">
                            <p class="description">Appended to SEO titles when no custom title is set.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Default OG Image</th>
                        <td>
                            <?php
                            $img_id  = get_option( 'lumos_seo_default_og_image' );
                            $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
                            ?>
                            <input type="hidden" name="lumos_seo_default_og_image" id="lumo_og_image_id" value="<?php echo esc_attr( $img_id ); ?>">
                            <div id="lumo_og_preview"><?php if ( $img_url ) echo '<img src="' . esc_url( $img_url ) . '" style="max-width:150px">'; ?></div>
                            <button type="button" class="button" onclick="lumoPickOGImage()">Choose Image</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Noindex Archive Pages</th>
                        <td>
                            <label>
                                <input type="checkbox" name="lumos_seo_noindex_archives" value="1" <?php checked( get_option( 'lumos_seo_noindex_archives' ), 1 ); ?>>
                                Add noindex to category, tag, and author archive pages
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Google Site Verification</th>
                        <td>
                            <input type="text" name="lumos_seo_google_site_verification" value="<?php echo esc_attr( get_option( 'lumos_seo_google_site_verification' ) ); ?>" class="regular-text">
                            <p class="description">Content value from Google Search Console verification meta tag.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function sanitize_dashboard_data( $value ) {
        $decoded = json_decode( wp_unslash( (string) $value ), true );
        if ( ! is_array( $decoded ) ) {
            return wp_json_encode( $this->default_dashboard_data() );
        }
        return wp_json_encode( $decoded );
    }

    private function get_dashboard_data() {
        $raw = get_option( 'lumos_seo_dashboard_data', '' );
        if ( ! $raw ) {
            return $this->default_dashboard_data();
        }
        $decoded = json_decode( (string) $raw, true );
        if ( ! is_array( $decoded ) ) {
            return $this->default_dashboard_data();
        }
        return wp_parse_args( $decoded, $this->default_dashboard_data() );
    }

    private function default_dashboard_data() {
        return [
            'traffic' => [
                [ 'label' => 'Apr', 'value' => 3240 ],
                [ 'label' => 'May (Proj)', 'value' => 4120 ],
                [ 'label' => 'Jun (Proj)', 'value' => 5800 ],
                [ 'label' => 'Jul (Proj)', 'value' => 7200 ],
                [ 'label' => 'Aug (Proj)', 'value' => 8900 ],
                [ 'label' => 'Sep (Proj)', 'value' => 10200 ],
            ],
            'keywords' => [
                [ 'keyword' => 'middle corridor logistics', 'category' => 'Primary', 'volume' => 1200, 'difficulty' => 45, 'intent' => 'Commercial', 'tags' => [ 'priority', 'corridor' ], 'url' => '/', 'priority' => 'Critical' ],
                [ 'keyword' => 'rail freight azerbaijan', 'category' => 'Primary', 'volume' => 420, 'difficulty' => 35, 'intent' => 'Commercial', 'tags' => [ 'rail' ], 'url' => '/services/rail-freight', 'priority' => 'High' ],
                [ 'keyword' => 'alliance multimodal', 'category' => 'Branded', 'volume' => 850, 'difficulty' => 5, 'intent' => 'Commercial', 'tags' => [ 'brand' ], 'url' => '/', 'priority' => 'Critical' ],
            ],
            'tracking' => [
                [ 'keyword' => 'middle corridor logistics', 'currentRank' => 8, 'targetRank' => 1, 'impressions' => 1240, 'clicks' => 45, 'status' => 'In Progress', 'tags' => [ 'priority', 'corridor' ] ],
                [ 'keyword' => 'rail freight azerbaijan', 'currentRank' => 4, 'targetRank' => 1, 'impressions' => 680, 'clicks' => 42, 'status' => 'Good CTR', 'tags' => [ 'rail' ] ],
                [ 'keyword' => 'alliance multimodal', 'currentRank' => 2, 'targetRank' => 1, 'impressions' => 2100, 'clicks' => 385, 'status' => 'Excellent', 'tags' => [ 'brand' ] ],
            ],
            'serp' => [
                [ 'keyword' => 'middle corridor logistics', 'competitor' => 'DHL Supply Chain', 'ourRank' => 8, 'titleQuality' => 5, 'descriptionQuality' => 6, 'opportunity' => 'High' ],
                [ 'keyword' => 'rail freight azerbaijan', 'competitor' => 'Railfreight.com', 'ourRank' => 4, 'titleQuality' => 6, 'descriptionQuality' => 7, 'opportunity' => 'High' ],
                [ 'keyword' => 'alliance multimodal', 'competitor' => 'Alliance (US)', 'ourRank' => 2, 'titleQuality' => 8, 'descriptionQuality' => 8, 'opportunity' => 'Medium' ],
            ],
            'sitemap' => [],
            'checklist' => [
                [ 'done' => true,  'task' => 'Submit XML sitemap to Google Search Console', 'category' => 'Technical', 'suggestion' => 'Resubmit when major URL structure changes happen.' ],
                [ 'done' => false, 'task' => 'Audit and optimize title tags on top pages', 'category' => 'On-Page', 'suggestion' => 'Put focus keyword earlier in titles for pages ranking 4-10.' ],
                [ 'done' => false, 'task' => 'Create 2 new informational articles', 'category' => 'Content', 'suggestion' => 'Cover "what is middle corridor" and "customs process" first.' ],
            ],
        ];
    }

    private function get_site_pages_for_tracker() {
        $items = [];
        $items[] = [
            'url'        => home_url( '/' ),
            'priority'   => '1.0',
            'changefreq' => 'daily',
            'title'      => 'Homepage',
        ];

        $posts = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        foreach ( $posts as $post ) {
            $items[] = [
                'url'        => get_permalink( $post->ID ),
                'priority'   => $post->post_type === 'page' ? '0.8' : '0.6',
                'changefreq' => 'weekly',
                'title'      => get_the_title( $post->ID ),
            ];
        }
        return $items;
    }

    private function get_overview_stats() {
        $ids = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 150,
            'fields'         => 'ids',
        ] );

        $total  = count( $ids );
        $scores = [];
        $good   = 0;
        $mid    = 0;
        $poor   = 0;
        $analyzer = new Lumos_SEO_Analyzer();

        foreach ( $ids as $id ) {
            $result = $analyzer->analyze( (int) $id );
            $score  = isset( $result['score'] ) ? (int) $result['score'] : 0;
            $scores[] = $score;
            if ( $score >= 70 ) {
                $good++;
            } elseif ( $score >= 40 ) {
                $mid++;
            } else {
                $poor++;
            }
        }

        $avg = $total ? (int) round( array_sum( $scores ) / $total ) : 0;

        return [
            'totalPages'  => $total,
            'avgScore'    => $avg,
            'goodCount'   => $good,
            'needsCount'  => $mid + $poor,
            'distribution'=> [ $good, $mid, $poor ],
        ];
    }

    public function add_column( $columns ) {
        $columns['lumos_seo_score'] = 'SEO Score';
        return $columns;
    }

    public function render_column( $column, $post_id ) {
        if ( $column !== 'lumos_seo_score' ) return;
        $analyzer = new Lumos_SEO_Analyzer();
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
