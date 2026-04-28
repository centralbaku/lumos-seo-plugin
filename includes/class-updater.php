<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Updater {

    private string $plugin_file;   // absolute path to main plugin file
    private string $plugin_slug;   // folder/file.php
    private string $plugin_folder; // folder name only
    private string $version;
    private string $repo = 'centralbaku/lumos-seo-plugin';
    private string $cache_key = 'lumos_seo_update_info';
    private int    $cache_ttl;

    public function __construct( string $plugin_file, string $version ) {
        $this->plugin_file   = $plugin_file;
        $this->plugin_slug   = plugin_basename( $plugin_file );          // lumo-seo/lumos-seo.php
        $this->plugin_folder = dirname( $this->plugin_slug );            // lumo-seo
        $this->version       = $version;
        $this->cache_ttl     = 12 * HOUR_IN_SECONDS;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info'   ], 20, 3 );
        add_filter( 'upgrader_source_selection',             [ $this, 'fix_folder'    ], 10, 4 );
        add_action( 'upgrader_process_complete',             [ $this, 'clear_cache'   ], 10, 2 );
    }

    // ── Fetch latest release from GitHub API ──────────────────────────────────
    private function fetch_release(): ?object {
        $cached = get_transient( $this->cache_key );
        if ( $cached !== false ) return $cached ?: null;

        $response = wp_remote_get(
            "https://api.github.com/repos/{$this->repo}/releases/latest",
            [
                'timeout' => 10,
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                ],
            ]
        );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $this->cache_key, false, HOUR_IN_SECONDS ); // back-off on failure
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        set_transient( $this->cache_key, $release, $this->cache_ttl );
        return $release;
    }

    private function remote_version( object $release ): string {
        return ltrim( $release->tag_name, 'v' );
    }

    // ── Hook: inject update into WordPress transient ──────────────────────────
    public function inject_update( object $transient ): object {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->fetch_release();
        if ( ! $release ) return $transient;

        $remote = $this->remote_version( $release );

        if ( version_compare( $this->version, $remote, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'id'          => "github.com/{$this->repo}",
                'slug'        => $this->plugin_folder,
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote,
                'url'         => "https://github.com/{$this->repo}",
                'package'     => $release->zipball_url,
                'icons'       => [],
                'banners'     => [],
                'tested'      => '6.8',
                'requires_php'=> '8.0',
            ];
        } else {
            // Tell WP the plugin is up to date (removes stale notices)
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

    // ── Hook: show plugin info in the "View version X details" popup ──────────
    public function plugin_info( mixed $result, string $action, object $args ): mixed {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ( $args->slug ?? '' ) !== $this->plugin_folder ) return $result;

        $release = $this->fetch_release();
        if ( ! $release ) return $result;

        $remote = $this->remote_version( $release );

        // Convert GitHub markdown release notes to basic HTML
        $changelog = esc_html( $release->body ?? '' );
        $changelog = nl2br( $changelog );

        return (object) [
            'name'              => 'Lumos SEO',
            'slug'              => $this->plugin_folder,
            'version'           => $remote,
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
                'description' => '<p>Lumos SEO is a lightweight WordPress SEO plugin with on-page analysis, readability scoring, Open Graph / Twitter Cards, AI JSON import, and XML sitemap.</p>',
                'changelog'   => $changelog,
            ],
            'banners'           => [],
            'icons'             => [],
        ];
    }

    // ── Hook: rename extracted GitHub zip folder to match installed folder ────
    // GitHub zips extract as "centralbaku-lumos-seo-plugin-{hash}/" — WP needs
    // the folder name to match the installed plugin folder exactly.
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

    // ── Clear cached release info after a successful update ───────────────────
    public function clear_cache( object $upgrader, array $hook_extra ): void {
        if ( ( $hook_extra['action'] ?? '' ) === 'update'
            && ( $hook_extra['type'] ?? '' ) === 'plugin'
            && in_array( $this->plugin_slug, (array) ( $hook_extra['plugins'] ?? [] ), true )
        ) {
            delete_transient( $this->cache_key );
        }
    }
}
