<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Updater {

    private string $plugin_file;
    private string $plugin_slug;   // folder/file.php
    private string $plugin_folder; // folder only
    private string $version;
    private string $repo = 'centralbaku/lumos-seo-plugin';
    private string $cache_key = 'lumos_seo_update_info';
    private int    $cache_ttl;

    public function __construct( string $plugin_file, string $version ) {
        $this->plugin_file   = $plugin_file;
        $this->plugin_slug   = plugin_basename( $plugin_file );
        $this->plugin_folder = dirname( $this->plugin_slug );
        $this->version       = $version;
        $this->cache_ttl     = 12 * HOUR_IN_SECONDS;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update'  ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info'    ], 20, 3 );
        add_filter( 'upgrader_source_selection',             [ $this, 'fix_folder'     ], 10, 4 );
        add_action( 'upgrader_process_complete',             [ $this, 'clear_cache'    ], 10, 2 );
        add_filter( 'plugin_action_links_' . $this->plugin_slug, [ $this, 'action_links' ] );

        // Handle manual "Check for updates" click
        if ( isset( $_GET['lumos_seo_force_check'] ) && current_user_can( 'update_plugins' ) ) {
            delete_transient( $this->cache_key );
            delete_site_transient( 'update_plugins' );
            wp_redirect( admin_url( 'plugins.php?lumos_checked=1' ) );
            exit;
        }
    }

    // ── Fetch latest GitHub release ───────────────────────────────────────────
    private function fetch_release(): ?object {
        $cached = get_transient( $this->cache_key );
        if ( $cached !== false ) return $cached ?: null;

        $response = wp_remote_get(
            "https://api.github.com/repos/{$this->repo}/releases/latest",
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                ],
            ]
        );

        $code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) || $code !== 200 ) {
            // 404 = no releases yet; back-off 1 h so we don't spam GitHub
            set_transient( $this->cache_key, false, HOUR_IN_SECONDS );
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );

        // Sanity-check: must have a tag_name
        if ( empty( $release->tag_name ) ) {
            set_transient( $this->cache_key, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( $this->cache_key, $release, $this->cache_ttl );
        return $release;
    }

    private function remote_version( object $release ): string {
        return ltrim( $release->tag_name, 'v' );
    }

    // ── Inject update into WP transient ──────────────────────────────────────
    public function inject_update( object $transient ): object {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->fetch_release();
        if ( ! $release ) return $transient;

        $remote = $this->remote_version( $release );

        if ( version_compare( $this->version, $remote, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'           => "github.com/{$this->repo}",
                'slug'         => $this->plugin_folder,
                'plugin'       => $this->plugin_slug,
                'new_version'  => $remote,
                'url'          => "https://github.com/{$this->repo}",
                'package'      => $release->zipball_url,
                'icons'        => [],
                'banners'      => [],
                'tested'       => '6.8',
                'requires_php' => '8.0',
            ];
        } else {
            $transient->no_update[ $this->plugin_slug ] = (object) [
                'id'          => "github.com/{$this->repo}",
                'slug'        => $this->plugin_folder,
                'plugin'      => $this->plugin_slug,
                'new_version' => $this->version,
                'url'         => "https://github.com/{$this->repo}",
                'package'     => '',
            ];
        }

        return $transient;
    }

    // ── Plugin info popup ─────────────────────────────────────────────────────
    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ( $args->slug ?? '' ) !== $this->plugin_folder ) return $result;

        $release = $this->fetch_release();
        if ( ! $release ) return $result;

        $changelog = nl2br( esc_html( $release->body ?? 'See GitHub for release notes.' ) );

        return (object) [
            'name'              => 'Lumos SEO',
            'slug'              => $this->plugin_folder,
            'version'           => $this->remote_version( $release ),
            'author'            => '<a href="https://github.com/centralbaku">Orkhan Hasanov</a>',
            'author_profile'    => 'https://github.com/centralbaku',
            'homepage'          => "https://github.com/{$this->repo}",
            'requires'          => '6.0',
            'tested'            => '6.8',
            'requires_php'      => '8.0',
            'last_updated'      => $release->published_at ?? '',
            'download_link'     => $release->zipball_url,
            'short_description' => 'On-page SEO analysis, OG/Twitter meta, AI JSON import, Gutenberg sidebar.',
            'sections'          => [
                'description' => '<p>Lumos SEO — lightweight WordPress SEO plugin with on-page analysis, Open Graph / Twitter Cards, AI JSON import, and XML sitemap.</p>',
                'changelog'   => $changelog,
            ],
            'banners' => [],
            'icons'   => [],
        ];
    }

    // ── Rename GitHub zip folder to match installed folder ────────────────────
    public function fix_folder( string $source, string $remote_source, object $upgrader, array $hook_extra ): string {
        if ( ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_slug ) return $source;

        global $wp_filesystem;
        $target = trailingslashit( $remote_source ) . $this->plugin_folder . '/';

        if ( trailingslashit( $source ) !== $target ) {
            if ( $wp_filesystem->exists( $target ) ) {
                $wp_filesystem->delete( $target, true );
            }
            $wp_filesystem->move( $source, $target );
        }

        return $target;
    }

    // ── Clear cache after successful update ───────────────────────────────────
    public function clear_cache( object $upgrader, array $hook_extra ): void {
        if ( ( $hook_extra['action'] ?? '' ) === 'update'
            && ( $hook_extra['type'] ?? '' ) === 'plugin'
            && in_array( $this->plugin_slug, (array) ( $hook_extra['plugins'] ?? [] ), true )
        ) {
            delete_transient( $this->cache_key );
        }
    }

    // ── "Check for updates" link in plugin list ───────────────────────────────
    public function action_links( array $links ): array {
        $check_url = admin_url( 'plugins.php?lumos_seo_force_check=1' );
        $label     = isset( $_GET['lumos_checked'] )
            ? '<span style="color:#46b450">✓ Checked</span>'
            : 'Check for updates';
        array_unshift( $links, '<a href="' . esc_url( $check_url ) . '">' . $label . '</a>' );
        return $links;
    }
}
