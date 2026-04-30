<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Updater {

    private $plugin_file;
    private $plugin_slug;   // folder/file.php
    private $plugin_folder; // folder only
    private $version;
    private $repo      = 'centralbaku/lumos-seo-plugin';
    private $branch    = 'main';
    private $cache_key = 'lumos_seo_update_info';
    private $cache_ttl;

    public function __construct( $plugin_file, $version ) {
        $this->plugin_file   = $plugin_file;
        $this->plugin_slug   = plugin_basename( $plugin_file );
        $this->plugin_folder = dirname( $this->plugin_slug );
        $this->version       = $version;
        $this->cache_ttl     = 12 * HOUR_IN_SECONDS;

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update'  ) );
        add_filter( 'plugins_api',                           array( $this, 'plugin_info'    ), 20, 3 );
        add_filter( 'upgrader_source_selection',             array( $this, 'fix_folder'     ), 10, 4 );
        add_action( 'upgrader_process_complete',             array( $this, 'clear_cache'    ), 10, 2 );
        add_filter( 'plugin_action_links_' . $this->plugin_slug, array( $this, 'action_links' ) );

        // Handle manual "Check for updates" click — run on admin_init so WP is fully loaded
        add_action( 'admin_init', array( $this, 'handle_force_check' ) );
    }

    // ── Manual "Check for updates" handler ───────────────────────────────────
    public function handle_force_check() {
        if ( ! isset( $_GET['lumos_seo_force_check'] ) ) return;
        if ( ! current_user_can( 'update_plugins' ) ) return;

        delete_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( admin_url( 'plugins.php?lumos_checked=1' ) );
        exit;
    }

    // ── Fetch update payload (newest of release/tag only) ────────────────────
    private function fetch_update_payload() {
        $cached = get_transient( $this->cache_key );
        if ( $cached !== false ) return $cached ? $cached : null;

        $release_payload = null;
        $tag_payload     = null;

        $release_response = wp_remote_get(
            'https://api.github.com/repos/' . $this->repo . '/releases/latest',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                ),
            )
        );

        $payload = null;
        $release_code = wp_remote_retrieve_response_code( $release_response );
        if ( ! is_wp_error( $release_response ) && $release_code === 200 ) {
            $release = json_decode( wp_remote_retrieve_body( $release_response ) );
            if ( ! empty( $release->tag_name ) ) {
                $release_payload = (object) array(
                    'version'      => ltrim( $release->tag_name, 'v' ),
                    'package'      => isset( $release->zipball_url ) ? $release->zipball_url : '',
                    'changelog'    => isset( $release->body ) ? $release->body : 'See GitHub for release notes.',
                    'last_updated' => isset( $release->published_at ) ? $release->published_at : '',
                );
            }
        }

        $tags_response = wp_remote_get(
            'https://api.github.com/repos/' . $this->repo . '/tags?per_page=1',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                ),
            )
        );
        $tags_code = wp_remote_retrieve_response_code( $tags_response );
        if ( ! is_wp_error( $tags_response ) && $tags_code === 200 ) {
            $tags = json_decode( wp_remote_retrieve_body( $tags_response ) );
            if ( is_array( $tags ) && ! empty( $tags[0]->name ) ) {
                $tag_name    = ltrim( $tags[0]->name, 'v' );
                $tag_payload = (object) array(
                    'version'      => $tag_name,
                    'package'      => 'https://api.github.com/repos/' . $this->repo . '/zipball/refs/tags/' . rawurlencode( $tags[0]->name ),
                    'changelog'    => 'Latest git tag: ' . $tags[0]->name,
                    'last_updated' => '',
                );
            }
        }

        // Choose whichever semantic version is newer between latest release and latest tag.
        if ( $release_payload && $tag_payload ) {
            $payload = version_compare( $release_payload->version, $tag_payload->version, '>=' )
                ? $release_payload
                : $tag_payload;
        } elseif ( $release_payload ) {
            $payload = $release_payload;
        } elseif ( $tag_payload ) {
            $payload = $tag_payload;
        }

        if ( ! $payload ) {
            set_transient( $this->cache_key, false, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( $this->cache_key, $payload, $this->cache_ttl );
        return $payload;
    }

    // ── Inject update into WP transient ──────────────────────────────────────
    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) ) return $transient;
        if ( empty( $transient->checked ) ) return $transient;

        $installed = $this->version;
        if ( isset( $transient->checked[ $this->plugin_slug ] ) && is_string( $transient->checked[ $this->plugin_slug ] ) ) {
            $installed = $transient->checked[ $this->plugin_slug ];
        }

        $payload = $this->fetch_update_payload();
        if ( ! $payload || empty( $payload->version ) || empty( $payload->package ) ) return $transient;

        $remote = $payload->version;

        // Always clear stale entries first; WP may keep previous response values.
        if ( isset( $transient->response[ $this->plugin_slug ] ) ) {
            unset( $transient->response[ $this->plugin_slug ] );
        }
        if ( isset( $transient->no_update[ $this->plugin_slug ] ) ) {
            unset( $transient->no_update[ $this->plugin_slug ] );
        }

        if ( version_compare( $installed, $remote, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'id'           => 'github.com/' . $this->repo,
                'slug'         => $this->plugin_folder,
                'plugin'       => $this->plugin_slug,
                'new_version'  => $remote,
                'url'          => 'https://github.com/' . $this->repo,
                'package'      => $payload->package,
                'icons'        => array(),
                'banners'      => array(),
                'tested'       => '6.8',
                'requires_php' => '7.2',
            );
        } else {
            // Defensive cleanup: never keep a same-version update response.
            if ( isset( $transient->response[ $this->plugin_slug ] ) ) {
                unset( $transient->response[ $this->plugin_slug ] );
            }
            $transient->no_update[ $this->plugin_slug ] = (object) array(
                'id'          => 'github.com/' . $this->repo,
                'slug'        => $this->plugin_folder,
                'plugin'      => $this->plugin_slug,
                'new_version' => $installed,
                'url'         => 'https://github.com/' . $this->repo,
                'package'     => '',
            );
        }

        return $transient;
    }

    // ── Plugin info popup ─────────────────────────────────────────────────────
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! is_object( $args ) ) return $result;
        if ( empty( $args->slug ) || $args->slug !== $this->plugin_folder ) return $result;

        $payload = $this->fetch_update_payload();
        if ( ! $payload ) return $result;

        $changelog = nl2br( esc_html( isset( $payload->changelog ) ? $payload->changelog : 'See GitHub for release notes.' ) );

        return (object) array(
            'name'              => 'Lumos SEO',
            'slug'              => $this->plugin_folder,
            'version'           => isset( $payload->version ) ? $payload->version : $this->version,
            'author'            => '<a href="https://github.com/centralbaku">Orkhan Hasanov</a>',
            'author_profile'    => 'https://github.com/centralbaku',
            'homepage'          => 'https://github.com/' . $this->repo,
            'requires'          => '6.0',
            'tested'            => '6.8',
            'requires_php'      => '7.2',
            'last_updated'      => isset( $payload->last_updated ) ? $payload->last_updated : '',
            'download_link'     => isset( $payload->package ) ? $payload->package : '',
            'short_description' => 'On-page SEO analysis, OG/Twitter meta, AI JSON import, Gutenberg sidebar.',
            'sections'          => array(
                'description' => '<p>Lumos SEO — lightweight WordPress SEO plugin with on-page analysis, Open Graph / Twitter Cards, AI JSON import, and XML sitemap.</p>',
                'changelog'   => $changelog,
            ),
            'banners' => array(),
            'icons'   => array(),
        );
    }

    // ── Rename GitHub zip folder to match installed folder ────────────────────
    public function fix_folder( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $source;
        }

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
    public function clear_cache( $upgrader, $hook_extra ) {
        if ( isset( $hook_extra['action'] ) && $hook_extra['action'] === 'update'
            && isset( $hook_extra['type'] ) && $hook_extra['type'] === 'plugin'
            && isset( $hook_extra['plugins'] ) && in_array( $this->plugin_slug, (array) $hook_extra['plugins'], true )
        ) {
            delete_transient( $this->cache_key );
            delete_site_transient( 'update_plugins' );
        }
    }

    // ── "Check for updates" link in plugin list ───────────────────────────────
    public function action_links( $links ) {
        $check_url = admin_url( 'plugins.php?lumos_seo_force_check=1' );
        $is_checked = isset( $_GET['lumos_checked'] ) && $_GET['lumos_checked'] === '1';

        if ( $is_checked ) {
            $label = '<span class="lumos-updater-checked" style="color:#46b450">&#10003; Checked</span>';
            $script = '<script>(function(){try{var u=new URL(window.location.href);if(u.searchParams.has("lumos_checked")){u.searchParams.delete("lumos_checked");window.history.replaceState({},document.title,u.pathname+(u.search?u.search:"")+(u.hash?u.hash:""));}}catch(e){}setTimeout(function(){var els=document.querySelectorAll(".lumos-updater-checked");for(var i=0;i<els.length;i++){els[i].textContent="Check for updates";els[i].style.color="";els[i].classList.remove("lumos-updater-checked");}},15000);}());</script>';
            array_unshift( $links, '<a href="' . esc_url( $check_url ) . '">' . $label . '</a>' . $script );
            return $links;
        }

        array_unshift( $links, '<a href="' . esc_url( $check_url ) . '">Check for updates</a>' );
        return $links;
    }
}
