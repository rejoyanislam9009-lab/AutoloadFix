<?php
/**
 * Cache and optimization advisor.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Cache_Advisor {
	/** @var AutoloadFix_Scanner */
	private $scanner;

	/**
	 * @param AutoloadFix_Scanner $scanner Scanner service.
	 */
	public function __construct( AutoloadFix_Scanner $scanner ) {
		$this->scanner = $scanner;

		add_action( 'admin_menu', array( $this, 'register_submenu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 30 );
		add_action( 'admin_post_autoloadfix_cache_check', array( $this, 'handle_run_check' ) );
		add_action( 'admin_post_autoloadfix_cache_purge', array( $this, 'handle_purge' ) );
		add_action( 'admin_post_autoloadfix_object_cache_flush', array( $this, 'handle_object_cache_flush' ) );
	}

	/** Register the advisor submenu. */
	public function register_submenu() {
		add_submenu_page(
			'autoloadfix',
			__( 'AutoloadFix Cache & Optimization Advisor', 'autoloadfix' ),
			__( 'Optimization Advisor', 'autoloadfix' ),
			'manage_options',
			'autoloadfix-cache-advisor',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'autoloadfix_page_autoloadfix-cache-advisor' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'autoloadfix-admin', AUTOLOADFIX_URL . 'assets/css/admin.css', array(), AUTOLOADFIX_VERSION );
		wp_enqueue_style( 'autoloadfix-cache-advisor', AUTOLOADFIX_URL . 'assets/css/cache-advisor.css', array( 'autoloadfix-admin' ), AUTOLOADFIX_VERSION );
	}

	/** Run the front-end cache probe. */
	public function handle_run_check() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_cache_check' );

		$result = $this->run_frontend_probe();
		update_option( 'autoloadfix_cache_probe', $result, false );
		$this->redirect( ! empty( $result['error'] ) ? 'check_failed' : 'check_complete' );
	}

	/** Purge supported cache layers, then re-run the probe. */
	public function handle_purge() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_cache_purge' );

		$environment = $this->get_environment();
		$purged      = array();

		foreach ( $environment['integrations'] as $integration ) {
			if ( empty( $integration['active'] ) || empty( $integration['purge'] ) ) {
				continue;
			}

			switch ( $integration['purge'] ) {
				case 'litespeed':
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This hook is defined by LiteSpeed Cache.
					do_action( 'litespeed_purge_all' );
					$purged[] = $integration['name'];
					break;
				case 'wp_rocket':
					if ( function_exists( 'rocket_clean_domain' ) ) {
						rocket_clean_domain();
						$purged[] = $integration['name'];
					}
					break;
				case 'wp_super_cache':
					if ( function_exists( 'wp_cache_clear_cache' ) ) {
						wp_cache_clear_cache();
						$purged[] = $integration['name'];
					}
					break;
				case 'w3tc':
					if ( function_exists( 'w3tc_flush_all' ) ) {
						w3tc_flush_all();
						$purged[] = $integration['name'];
					}
					break;
				case 'autoptimize':
					if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
						autoptimizeCache::clearall();
						$purged[] = $integration['name'];
					}
					break;
			}
		}

		update_option( 'autoloadfix_last_purge', array( 'time' => time(), 'integrations' => $purged ), false );
		$probe = $this->run_frontend_probe();
		update_option( 'autoloadfix_cache_probe', $probe, false );

		if ( ! empty( $probe['error'] ) ) {
			$this->redirect( empty( $purged ) ? 'purge_manual_probe_failed' : 'purge_probe_failed' );
		}

		$this->redirect( empty( $purged ) ? 'purge_manual' : 'purge_complete' );
	}

	/** Flush persistent WordPress object cache after an explicit confirmation. */
	public function handle_object_cache_flush() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_object_cache_flush' );

		if ( ! wp_using_ext_object_cache() ) {
			$this->redirect( 'object_cache_missing' );
		}

		$result = wp_cache_flush();
		$this->redirect( false === $result ? 'object_cache_failed' : 'object_cache_flushed' );
	}

	/** Render the advisor page. */
	public function render_page() {
		$this->require_manage_options();

		$environment     = $this->get_environment();
		$probe           = get_option( 'autoloadfix_cache_probe', array() );
		$probe           = is_array( $probe ) ? $probe : array();
		$recommendations = $this->get_recommendations( $environment, $probe );
		$best_fit        = $this->get_best_fit( $environment );
		$next_action     = $this->get_next_action( $environment, $probe );
		$summary         = $this->scanner->get_summary();
		$last_purge      = get_option( 'autoloadfix_last_purge', array() );
		$last_purge      = is_array( $last_purge ) ? $last_purge : array();

		$this->render_notice();
		?>
		<div class="wrap autoloadfix-wrap autoloadfix-cache-wrap">
			<div class="autoloadfix-header">
				<div>
					<div class="autoloadfix-eyebrow"><?php esc_html_e( 'AUTOLOADFIX PERFORMANCE ADVISOR', 'autoloadfix' ); ?></div>
					<h1><?php esc_html_e( 'Cache & Optimization Advisor', 'autoloadfix' ); ?></h1>
					<p><?php esc_html_e( 'See which cache layers are present, avoid overlapping page caches, get a site-fit recommendation, purge supported caches, and verify whether the public cache warms correctly.', 'autoloadfix' ); ?></p>
				</div>
				<div class="autoloadfix-version">v<?php echo esc_html( AUTOLOADFIX_VERSION ); ?></div>
			</div>

			<div class="autoloadfix-cache-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="autoloadfix_cache_check" />
					<?php wp_nonce_field( 'autoloadfix_cache_check' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Run optimization check', 'autoloadfix' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="autoloadfix_cache_purge" />
					<?php wp_nonce_field( 'autoloadfix_cache_purge' ); ?>
					<button type="submit" class="button"><?php esc_html_e( 'Purge supported caches & re-check', 'autoloadfix' ); ?></button>
				</form>
				<?php if ( $environment['object_cache'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Flush the persistent object cache? This is separate from page-cache purge and may briefly increase server work while the cache warms again.', 'autoloadfix' ) ); ?>');">
						<input type="hidden" name="action" value="autoloadfix_object_cache_flush" />
						<?php wp_nonce_field( 'autoloadfix_object_cache_flush' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Flush object cache', 'autoloadfix' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="autoloadfix-advisor-grid">
				<section class="autoloadfix-advisor-card autoloadfix-advisor-primary">
					<span class="autoloadfix-advisor-kicker"><?php esc_html_e( 'Primary recommendation', 'autoloadfix' ); ?></span>
					<h2><?php echo esc_html( $best_fit['title'] ); ?></h2>
					<p><?php echo esc_html( $best_fit['message'] ); ?></p>
					<?php if ( ! empty( $best_fit['url'] ) ) : ?>
						<a class="button" href="<?php echo esc_url( $best_fit['url'] ); ?>"><?php esc_html_e( 'View plugin options', 'autoloadfix' ); ?></a>
					<?php endif; ?>
				</section>

				<section class="autoloadfix-advisor-card autoloadfix-advisor-primary">
					<span class="autoloadfix-advisor-kicker"><?php esc_html_e( 'Next best action', 'autoloadfix' ); ?></span>
					<h2><?php echo esc_html( $next_action['title'] ); ?></h2>
					<p><?php echo esc_html( $next_action['message'] ); ?></p>
					<?php if ( ! empty( $next_action['url'] ) ) : ?>
						<a class="button" href="<?php echo esc_url( $next_action['url'] ); ?>"><?php esc_html_e( 'Open relevant settings', 'autoloadfix' ); ?></a>
					<?php endif; ?>
				</section>

				<section class="autoloadfix-advisor-card">
					<span class="autoloadfix-advisor-kicker"><?php esc_html_e( 'Autoload health', 'autoloadfix' ); ?></span>
					<h2><?php echo esc_html( size_format( (int) $summary['total_size'], 1 ) ); ?></h2>
					<p>
					<?php
					/* translators: 1: Autoload health score. 2: Health limit. */
					echo esc_html( sprintf( __( 'Score %1$d/100. Current health limit: %2$s.', 'autoloadfix' ), (int) $summary['score'], size_format( (int) $summary['health_limit'], 1 ) ) );
					?>
					</p>
				</section>

				<section class="autoloadfix-advisor-card">
					<span class="autoloadfix-advisor-kicker"><?php esc_html_e( 'Server', 'autoloadfix' ); ?></span>
					<h2><?php echo esc_html( $environment['server_label'] ); ?></h2>
					<p><?php echo esc_html( $environment['server_software'] ); ?></p>
				</section>

				<section class="autoloadfix-advisor-card">
					<span class="autoloadfix-advisor-kicker"><?php esc_html_e( 'Detected cache stack', 'autoloadfix' ); ?></span>
					<h2><?php echo esc_html( number_format_i18n( count( $environment['active_cache_plugins'] ) ) ); ?></h2>
					<p><?php esc_html_e( 'Active cache/optimization plugins detected. Full-page cache overlap is flagged below.', 'autoloadfix' ); ?></p>
				</section>
			</div>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Cache layers', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'AutoloadFix separates page cache, persistent object cache, asset-optimization capability, and public cache/CDN signals so you know what should be reviewed or cleared.', 'autoloadfix' ); ?></p></div></div>
				<div class="autoloadfix-layer-grid">
					<?php $this->render_layer( __( 'Full-page cache', 'autoloadfix' ), ! empty( $environment['active_page_cache_plugins'] ), $this->page_cache_detail( $environment ) ); ?>
					<?php $this->render_layer( __( 'Persistent object cache', 'autoloadfix' ), $environment['object_cache'], $environment['object_cache'] ? __( 'A persistent WordPress object-cache drop-in is active.', 'autoloadfix' ) : __( 'No persistent object cache detected.', 'autoloadfix' ) ); ?>
					<?php $this->render_layer( __( 'Asset optimization capability', 'autoloadfix' ), ! empty( $environment['asset_capable_plugins'] ), $this->asset_cache_detail( $environment ) ); ?>
					<?php $this->render_layer( __( 'Front-end cache/CDN signal', 'autoloadfix' ), ! empty( $probe['cache_layer_detected'] ), $this->probe_signal_detail( $probe ) ); ?>
				</div>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Detected integrations & where to clear cache', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Supported integrations can be purged from this page. Other detected tools show the WordPress menu path for manual clearing.', 'autoloadfix' ); ?></p></div></div>
				<div class="autoloadfix-integration-list">
					<?php
					$has_active = false;
					foreach ( $environment['integrations'] as $integration ) :
						if ( empty( $integration['active'] ) ) {
							continue;
						}
						$has_active = true;
						?>
						<div class="autoloadfix-integration-row">
							<div><strong><?php echo esc_html( $integration['name'] ); ?></strong><span><?php echo esc_html( $integration['type_label'] ); ?></span></div>
							<div>
								<?php if ( ! empty( $integration['purge'] ) ) : ?><span class="autoloadfix-support-badge is-supported"><?php esc_html_e( 'One-click purge supported', 'autoloadfix' ); ?></span><?php else : ?><span class="autoloadfix-support-badge"><?php esc_html_e( 'Manual purge', 'autoloadfix' ); ?></span><?php endif; ?>
								<div class="autoloadfix-manual-path"><?php echo esc_html( $integration['manual_path'] ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
					<?php if ( ! $has_active ) : ?><p><?php esc_html_e( 'No recognized cache or optimization plugin is active.', 'autoloadfix' ); ?></p><?php endif; ?>
				</div>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Optimization checklist', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Recommendations come from the current WordPress environment and the latest two-request public probe. AutoloadFix does not install or reconfigure third-party plugins automatically.', 'autoloadfix' ); ?></p></div></div>
				<div class="autoloadfix-recommendation-list">
					<?php foreach ( $recommendations as $recommendation ) : ?>
						<div class="autoloadfix-recommendation is-<?php echo esc_attr( $recommendation['level'] ); ?>"><span class="autoloadfix-recommendation-status"><?php echo esc_html( $recommendation['label'] ); ?></span><div><strong><?php echo esc_html( $recommendation['title'] ); ?></strong><p><?php echo esc_html( $recommendation['message'] ); ?></p></div></div>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Latest front-end check', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'The probe makes two anonymous requests to your public home URL and stores only status, timing, and selected cache-related response headers. Page content is never stored.', 'autoloadfix' ); ?></p></div></div>
				<?php $this->render_probe( $probe ); ?>
			</section>

			<?php if ( ! empty( $last_purge['time'] ) ) : ?>
				<div class="autoloadfix-safety-note"><strong><?php esc_html_e( 'Last purge:', 'autoloadfix' ); ?></strong>
				<?php
				$purged_names = ! empty( $last_purge['integrations'] ) && is_array( $last_purge['integrations'] ) ? implode( ', ', array_map( 'sanitize_text_field', $last_purge['integrations'] ) ) : __( 'No supported integration was available; manual purge guidance was shown.', 'autoloadfix' );
				/* translators: 1: Date/time. 2: Cache integrations. */
				echo esc_html( sprintf( __( '%1$s — %2$s', 'autoloadfix' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_purge['time'] ), $purged_names ) );
				?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return array<string,mixed> */
	private function get_environment() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'Unknown server', 'autoloadfix' );
		$server_lower    = strtolower( $server_software );
		$server_label    = __( 'Other / not identified', 'autoloadfix' );
		if ( false !== strpos( $server_lower, 'litespeed' ) ) {
			$server_label = __( 'LiteSpeed / OpenLiteSpeed', 'autoloadfix' );
		} elseif ( false !== strpos( $server_lower, 'nginx' ) ) {
			$server_label = __( 'Nginx', 'autoloadfix' );
		} elseif ( false !== strpos( $server_lower, 'apache' ) ) {
			$server_label = __( 'Apache', 'autoloadfix' );
		}

		$definitions  = $this->integration_definitions();
		$integrations = array();
		$active_cache = array();
		$active_page  = array();
		$active_asset = array();
		$asset_capable = array();

		foreach ( $definitions as $definition ) {
			$active               = is_plugin_active( $definition['file'] ) || ( is_multisite() && is_plugin_active_for_network( $definition['file'] ) );
			$definition['active'] = $active;
			$integrations[]       = $definition;
			if ( ! $active ) {
				continue;
			}
			$active_cache[] = $definition['name'];
			if ( 'page' === $definition['type'] ) {
				$active_page[] = $definition['name'];
			} elseif ( 'asset' === $definition['type'] ) {
				$active_asset[] = $definition['name'];
			}
			if ( ! empty( $definition['asset_capable'] ) ) {
				$asset_capable[] = $definition['name'];
			}
		}

		$advanced_cache = file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
		$object_cache   = wp_using_ext_object_cache() || file_exists( WP_CONTENT_DIR . '/object-cache.php' );
		$woocommerce    = class_exists( 'WooCommerce' ) || is_plugin_active( 'woocommerce/woocommerce.php' );

		return array(
			'server_software'           => $server_software,
			'server_label'              => $server_label,
			'is_litespeed'              => false !== strpos( $server_lower, 'litespeed' ),
			'wp_cache'                  => defined( 'WP_CACHE' ) && WP_CACHE,
			'advanced_cache'            => $advanced_cache,
			'object_cache'              => $object_cache,
			'woocommerce'               => $woocommerce,
			'integrations'              => $integrations,
			'active_cache_plugins'      => $active_cache,
			'active_page_cache_plugins' => $active_page,
			'active_asset_plugins'      => $active_asset,
			'asset_capable_plugins'     => array_values( array_unique( $asset_capable ) ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function integration_definitions() {
		return array(
			array( 'file' => 'litespeed-cache/litespeed-cache.php', 'name' => 'LiteSpeed Cache', 'type' => 'page', 'type_label' => __( 'Full-page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => 'litespeed', 'manual_path' => __( 'LiteSpeed Cache > Toolbox > Purge > Purge All', 'autoloadfix' ) ),
			array( 'file' => 'wp-rocket/wp-rocket.php', 'name' => 'WP Rocket', 'type' => 'page', 'type_label' => __( 'Full-page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => 'wp_rocket', 'manual_path' => __( 'Settings > WP Rocket > Dashboard > Clear Cache', 'autoloadfix' ) ),
			array( 'file' => 'wp-super-cache/wp-cache.php', 'name' => 'WP Super Cache', 'type' => 'page', 'type_label' => __( 'Full-page cache', 'autoloadfix' ), 'asset_capable' => false, 'purge' => 'wp_super_cache', 'manual_path' => __( 'Settings > WP Super Cache > Contents > Delete Cache', 'autoloadfix' ) ),
			array( 'file' => 'w3-total-cache/w3-total-cache.php', 'name' => 'W3 Total Cache', 'type' => 'page', 'type_label' => __( 'Full-page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => 'w3tc', 'manual_path' => __( 'Performance > Dashboard > Empty All Caches', 'autoloadfix' ) ),
			array( 'file' => 'wp-fastest-cache/wpFastestCache.php', 'name' => 'WP Fastest Cache', 'type' => 'page', 'type_label' => __( 'Full-page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => '', 'manual_path' => __( 'WP Fastest Cache > Delete Cache', 'autoloadfix' ) ),
			array( 'file' => 'breeze/breeze.php', 'name' => 'Breeze', 'type' => 'page', 'type_label' => __( 'Full-page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => '', 'manual_path' => __( 'Settings > Breeze > Purge All Cache', 'autoloadfix' ) ),
			array( 'file' => 'sg-cachepress/sg-cachepress.php', 'name' => 'Speed Optimizer', 'type' => 'page', 'type_label' => __( 'Host/page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => '', 'manual_path' => __( 'Speed Optimizer > Caching > Flush Cache', 'autoloadfix' ) ),
			array( 'file' => 'wp-optimize/wp-optimize.php', 'name' => 'WP-Optimize', 'type' => 'page', 'type_label' => __( 'Page cache + optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => '', 'manual_path' => __( 'WP-Optimize > Cache > Purge cache', 'autoloadfix' ) ),
			array( 'file' => 'autoptimize/autoptimize.php', 'name' => 'Autoptimize', 'type' => 'asset', 'type_label' => __( 'CSS/JS optimization', 'autoloadfix' ), 'asset_capable' => true, 'purge' => 'autoptimize', 'manual_path' => __( 'Settings > Autoptimize > Delete Cache', 'autoloadfix' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $environment Environment.
	 * @return array<string,string>
	 */
	private function get_best_fit( $environment ) {
		$page_plugins = $environment['active_page_cache_plugins'];
		$count        = count( $page_plugins );
		if ( $count > 1 ) {
			return array( 'title' => __( 'Keep only one primary full-page cache', 'autoloadfix' ), 'message' => __( 'Multiple page-cache-capable plugins are active. They may overlap if their page-cache features are enabled together. Keep one primary page-cache layer after testing.', 'autoloadfix' ), 'url' => admin_url( 'plugins.php' ) );
		}
		if ( 1 === $count ) {
			$active = $page_plugins[0];
			if ( $environment['is_litespeed'] && 'LiteSpeed Cache' === $active ) {
				return array( 'title' => __( 'LiteSpeed Cache matches this server well', 'autoloadfix' ), 'message' => __( 'LiteSpeed Cache is active on a LiteSpeed/OpenLiteSpeed server, so keeping it as the primary page cache is a strong fit. Avoid adding another full-page cache plugin.', 'autoloadfix' ), 'url' => admin_url( 'admin.php?page=litespeed' ) );
			}
			/* translators: %s: Active page-cache plugin name. */
			return array( 'title' => sprintf( __( 'Keep %s as the primary cache if it is stable', 'autoloadfix' ), $active ), 'message' => __( 'A single page-cache-capable plugin is already active. Do not switch only to chase a score; verify public cache behavior, exclusions, and real front-end results first.', 'autoloadfix' ), 'url' => admin_url( 'plugins.php' ) );
		}
		if ( $environment['is_litespeed'] ) {
			return array( 'title' => __( 'Suggested fit: LiteSpeed Cache', 'autoloadfix' ), 'message' => __( 'No recognized full-page cache is active and the server reports LiteSpeed/OpenLiteSpeed. LiteSpeed Cache is the natural server-integrated WordPress.org option.', 'autoloadfix' ), 'url' => self_admin_url( 'plugin-install.php?s=LiteSpeed%20Cache&tab=search&type=term' ) );
		}
		return array( 'title' => __( 'Choose one full-page cache, not several', 'autoloadfix' ), 'message' => __( 'No recognized full-page cache is active. A host-managed cache may already exist; otherwise choose one reputable page-cache solution and verify it rather than stacking several.', 'autoloadfix' ), 'url' => self_admin_url( 'plugin-install.php?s=cache&tab=search&type=term' ) );
	}

	/**
	 * @param array<string,mixed> $environment Environment.
	 * @param array<string,mixed> $probe Probe.
	 * @return array<string,string>
	 */
	private function get_next_action( $environment, $probe ) {
		if ( count( $environment['active_page_cache_plugins'] ) > 1 ) {
			return array( 'title' => __( 'Review overlapping page-cache plugins first', 'autoloadfix' ), 'message' => __( 'Before tuning CSS, object cache, or database settings, decide which plugin owns the primary page cache and disable duplicate page-cache features.', 'autoloadfix' ), 'url' => admin_url( 'plugins.php' ) );
		}
		if ( ! empty( $probe['warm_verified'] ) ) {
			if ( $environment['woocommerce'] && ! $environment['object_cache'] ) {
				return array( 'title' => __( 'Page cache is verified; object cache is optional', 'autoloadfix' ), 'message' => __( 'Your public cache warmed successfully. For a busy WooCommerce store, ask the host whether Redis or Memcached is available before adding an object-cache plugin.', 'autoloadfix' ), 'url' => '' );
			}
			return array( 'title' => __( 'Cache is warming correctly', 'autoloadfix' ), 'message' => __( 'No urgent cache change is indicated. Focus next on real page performance, images, CSS/JS, and only change settings when a measured problem exists.', 'autoloadfix' ), 'url' => '' );
		}
		if ( $environment['is_litespeed'] && in_array( 'LiteSpeed Cache', $environment['active_page_cache_plugins'], true ) ) {
			return array( 'title' => __( 'Verify LiteSpeed Page Optimization before adding another plugin', 'autoloadfix' ), 'message' => __( 'LiteSpeed Cache already includes CSS/JS optimization capability. Review its Page Optimization settings first; a separate optimization plugin may be unnecessary.', 'autoloadfix' ), 'url' => admin_url( 'admin.php?page=litespeed' ) );
		}
		return array( 'title' => __( 'Run the public cache verification', 'autoloadfix' ), 'message' => __( 'Use the optimization check to make two anonymous requests. AutoloadFix will show whether the second request becomes a cache HIT when the cache exposes a supported status header.', 'autoloadfix' ), 'url' => '' );
	}

	/**
	 * @param array<string,mixed> $environment Environment.
	 * @param array<string,mixed> $probe Probe.
	 * @return array<int,array<string,string>>
	 */
	private function get_recommendations( $environment, $probe ) {
		$items      = array();
		$page_count = count( $environment['active_page_cache_plugins'] );
		if ( $page_count > 1 ) {
			$items[] = $this->recommendation( 'critical', __( 'Action required', 'autoloadfix' ), __( 'Multiple page-cache-capable plugins are active', 'autoloadfix' ), __( 'Review whether more than one of them has page caching enabled. Keep one primary page-cache layer; asset-only features can still be complementary when configured carefully.', 'autoloadfix' ) );
		} elseif ( 1 === $page_count ) {
			$items[] = $this->recommendation( 'good', __( 'Good', 'autoloadfix' ), __( 'One primary page-cache-capable plugin detected', 'autoloadfix' ), __( 'This is the preferred topology. Use the purge guidance above when changing themes, CSS/JS, templates, or cache-sensitive settings.', 'autoloadfix' ) );
		} else {
			$items[] = $this->recommendation( 'warning', __( 'Review', 'autoloadfix' ), __( 'No recognized full-page cache detected', 'autoloadfix' ), __( 'A public site usually benefits from a single page-cache layer unless the host or CDN already handles full-page caching outside WordPress.', 'autoloadfix' ) );
		}
		if ( $environment['advanced_cache'] && 0 === $page_count ) {
			$items[] = $this->recommendation( 'warning', __( 'Review', 'autoloadfix' ), __( 'advanced-cache.php exists without a recognized cache plugin', 'autoloadfix' ), __( 'This can be a host cache or a leftover drop-in. Confirm its owner before deleting anything.', 'autoloadfix' ) );
		}
		if ( ! empty( $environment['asset_capable_plugins'] ) ) {
			$items[] = $this->recommendation( 'info', __( 'Available', 'autoloadfix' ), __( 'Asset optimization capability is already present', 'autoloadfix' ), __( 'At least one active plugin can provide CSS/JS optimization. This does not prove those features are enabled; review the existing plugin before installing another optimization tool.', 'autoloadfix' ) );
		}
		if ( $environment['woocommerce'] ) {
			$items[] = $this->recommendation( 'info', __( 'WooCommerce', 'autoloadfix' ), __( 'Protect dynamic commerce pages', 'autoloadfix' ), __( 'Confirm that Cart, Checkout, My Account, customer sessions, and personalized fragments are excluded or correctly handled by the cache solution.', 'autoloadfix' ) );
		}
		if ( $environment['woocommerce'] && ! $environment['object_cache'] ) {
			$items[] = $this->recommendation( 'info', __( 'Optional', 'autoloadfix' ), __( 'Consider persistent object cache for a busy store', 'autoloadfix' ), __( 'If the host offers Redis or Memcached, persistent object cache can reduce repeated database work. Do not install a Redis/Memcached plugin unless the server service is actually available.', 'autoloadfix' ) );
		} elseif ( $environment['object_cache'] ) {
			$items[] = $this->recommendation( 'good', __( 'Detected', 'autoloadfix' ), __( 'Persistent object cache is active', 'autoloadfix' ), __( 'Use the separate object-cache flush only when troubleshooting stale object data or after changes that specifically require it.', 'autoloadfix' ) );
		}
		if ( ! empty( $probe['error'] ) ) {
			$items[] = $this->recommendation( 'warning', __( 'Probe', 'autoloadfix' ), __( 'Front-end probe could not complete', 'autoloadfix' ), __( 'A firewall, loopback restriction, DNS issue, maintenance mode, or authentication rule can block the server from requesting its own public URL.', 'autoloadfix' ) );
		} elseif ( ! empty( $probe['checked_at'] ) ) {
			if ( ! empty( $probe['warm_verified'] ) ) {
				$items[] = $this->recommendation( 'good', __( 'Verified', 'autoloadfix' ), __( 'Warm-cache HIT verified', 'autoloadfix' ), __( 'The second anonymous request returned a cache HIT from a supported cache-status header. This is strong evidence that the public page cache is serving warmed content.', 'autoloadfix' ) );
			} elseif ( ! empty( $probe['cache_layer_detected'] ) ) {
				$items[] = $this->recommendation( 'info', __( 'Detected', 'autoloadfix' ), __( 'Cache/CDN layer detected, but HIT was not verified', 'autoloadfix' ), __( 'The cache layer exposed a recognized signal, but the second request was still MISS, BYPASS, STALE, or an unclassified cache response. Review exclusions and cache settings if this persists.', 'autoloadfix' ) );
			} else {
				$items[] = $this->recommendation( 'info', __( 'Check', 'autoloadfix' ), __( 'No obvious cache header was detected', 'autoloadfix' ), __( 'Not every cache exposes a public HIT/MISS header. Confirm with the cache plugin dashboard or host/CDN tools before concluding that caching is disabled.', 'autoloadfix' ) );
			}
		}
		return $items;
	}

	/** @return array<string,string> */
	private function recommendation( $level, $label, $title, $message ) {
		return array( 'level' => $level, 'label' => $label, 'title' => $title, 'message' => $message );
	}

	/**
	 * Make two anonymous requests so a MISS-to-HIT transition can be observed when exposed by headers.
	 *
	 * @return array<string,mixed>
	 */
	private function run_frontend_probe() {
		$first = $this->perform_probe_request();
		if ( ! empty( $first['error'] ) ) {
			return array( 'checked_at' => time(), 'error' => $first['error'] );
		}
		$second = $this->perform_probe_request();
		if ( ! empty( $second['error'] ) ) {
			return array( 'checked_at' => time(), 'error' => $second['error'], 'first_request' => $first );
		}

		$warm_verified = 'hit' === $second['cache_status'];
		return array(
			'checked_at'           => time(),
			'status_code'          => $second['status_code'],
			'latency_ms'           => $second['latency_ms'],
			'headers'              => $second['headers'],
			'cache_layer_detected' => ! empty( $first['cache_layer_detected'] ) || ! empty( $second['cache_layer_detected'] ),
			'cache_status'         => $second['cache_status'],
			'warm_verified'        => $warm_verified,
			'first_request'        => $first,
			'second_request'       => $second,
		);
	}

	/** @return array<string,mixed> */
	private function perform_probe_request() {
		$start = microtime( true );
		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'user-agent'  => 'AutoloadFix/' . AUTOLOADFIX_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => sanitize_text_field( $response->get_error_message() ) );
		}

		$names   = array( 'cache-control', 'age', 'x-cache', 'x-litespeed-cache', 'cf-cache-status', 'x-proxy-cache', 'x-kinsta-cache', 'x-wp-cf-super-cache', 'server', 'via' );
		$headers = array();
		foreach ( $names as $name ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( '' === $value || null === $value ) {
				continue;
			}
			$headers[ $name ] = sanitize_text_field( is_array( $value ) ? implode( ', ', $value ) : (string) $value );
		}
		$status = $this->detect_cache_status( $headers );
		return array(
			'status_code'          => (int) wp_remote_retrieve_response_code( $response ),
			'latency_ms'           => (int) round( ( microtime( true ) - $start ) * 1000 ),
			'headers'              => $headers,
			'cache_status'         => $status,
			'cache_layer_detected' => 'none' !== $status || ( isset( $headers['age'] ) && is_numeric( $headers['age'] ) ),
		);
	}

	/**
	 * @param array<string,string> $headers Headers.
	 * @return string
	 */
	private function detect_cache_status( $headers ) {
		$priority = array( 'x-litespeed-cache', 'cf-cache-status', 'x-cache', 'x-proxy-cache', 'x-kinsta-cache', 'x-wp-cf-super-cache' );
		foreach ( $priority as $name ) {
			if ( empty( $headers[ $name ] ) ) {
				continue;
			}
			$value = strtolower( $headers[ $name ] );
			if ( false !== strpos( $value, 'hit' ) || false !== strpos( $value, 'cached' ) ) {
				return 'hit';
			}
			if ( false !== strpos( $value, 'miss' ) ) {
				return 'miss';
			}
			if ( false !== strpos( $value, 'bypass' ) || false !== strpos( $value, 'dynamic' ) || false !== strpos( $value, 'no-cache' ) ) {
				return 'bypass';
			}
			if ( false !== strpos( $value, 'stale' ) ) {
				return 'stale';
			}
			return 'detected';
		}
		if ( isset( $headers['age'] ) && is_numeric( $headers['age'] ) && (int) $headers['age'] > 0 ) {
			return 'hit';
		}
		return 'none';
	}

	private function render_layer( $title, $detected, $detail ) {
		?>
		<div class="autoloadfix-layer-card <?php echo $detected ? 'is-detected' : 'is-neutral'; ?>"><span class="autoloadfix-layer-state"><?php echo esc_html( $detected ? __( 'Detected', 'autoloadfix' ) : __( 'Not detected', 'autoloadfix' ) ); ?></span><strong><?php echo esc_html( $title ); ?></strong><p><?php echo esc_html( $detail ); ?></p></div>
		<?php
	}

	private function page_cache_detail( $environment ) {
		if ( empty( $environment['active_page_cache_plugins'] ) ) {
			return $environment['advanced_cache'] ? __( 'An advanced-cache.php drop-in exists, but no recognized page-cache plugin is active.', 'autoloadfix' ) : __( 'No recognized WordPress full-page cache plugin is active.', 'autoloadfix' );
		}
		return implode( ', ', array_map( 'sanitize_text_field', $environment['active_page_cache_plugins'] ) );
	}

	private function asset_cache_detail( $environment ) {
		if ( empty( $environment['asset_capable_plugins'] ) ) {
			return __( 'No recognized plugin with CSS/JS optimization capability was detected.', 'autoloadfix' );
		}
		/* translators: %s: Comma-separated plugin names. */
		return sprintf( __( 'Available through: %s. Capability does not mean the feature is enabled; review the plugin settings before adding another optimizer.', 'autoloadfix' ), implode( ', ', array_map( 'sanitize_text_field', $environment['asset_capable_plugins'] ) ) );
	}

	private function probe_signal_detail( $probe ) {
		if ( empty( $probe['checked_at'] ) ) {
			return __( 'Run the optimization check to inspect public cache-related response headers.', 'autoloadfix' );
		}
		if ( ! empty( $probe['error'] ) ) {
			return $probe['error'];
		}
		if ( ! empty( $probe['warm_verified'] ) ) {
			return __( 'A warm-cache HIT was verified on the second anonymous request.', 'autoloadfix' );
		}
		if ( ! empty( $probe['cache_layer_detected'] ) ) {
			/* translators: %s: Cache status such as MISS or BYPASS. */
			return sprintf( __( 'A cache/CDN layer was detected. Latest classified status: %s.', 'autoloadfix' ), strtoupper( isset( $probe['cache_status'] ) ? $probe['cache_status'] : 'detected' ) );
		}
		return __( 'No explicit cache/CDN status header was visible in the last two-request probe.', 'autoloadfix' );
	}

	private function render_probe( $probe ) {
		if ( empty( $probe['checked_at'] ) ) {
			?><p><?php esc_html_e( 'No front-end probe has been run yet. Click “Run optimization check” to establish a baseline.', 'autoloadfix' ); ?></p><?php
			return;
		}
		if ( ! empty( $probe['error'] ) ) {
			?><div class="notice notice-warning inline"><p><?php echo esc_html( $probe['error'] ); ?></p></div><?php
			return;
		}
		$cache_status = isset( $probe['cache_status'] ) ? strtoupper( sanitize_text_field( $probe['cache_status'] ) ) : __( 'Not obvious', 'autoloadfix' );
		?>
		<div class="autoloadfix-probe-summary">
			<div><span><?php esc_html_e( 'HTTP status', 'autoloadfix' ); ?></span><strong><?php echo esc_html( (int) $probe['status_code'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'Second response time', 'autoloadfix' ); ?></span><strong><?php echo esc_html( (int) $probe['latency_ms'] ); ?> ms</strong></div>
			<div><span><?php esc_html_e( 'Cache status', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $cache_status ); ?></strong></div>
			<div><span><?php esc_html_e( 'Warm cache', 'autoloadfix' ); ?></span><strong><?php echo esc_html( ! empty( $probe['warm_verified'] ) ? __( 'Verified HIT', 'autoloadfix' ) : __( 'Not verified', 'autoloadfix' ) ); ?></strong></div>
		</div>
		<?php if ( ! empty( $probe['first_request'] ) && ! empty( $probe['second_request'] ) ) : ?>
			<table class="widefat striped autoloadfix-header-table"><thead><tr><th><?php esc_html_e( 'Request', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Status', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Response time', 'autoloadfix' ); ?></th></tr></thead><tbody>
			<tr><td><?php esc_html_e( 'First', 'autoloadfix' ); ?></td><td><?php echo esc_html( strtoupper( sanitize_text_field( $probe['first_request']['cache_status'] ) ) ); ?></td><td><?php echo esc_html( (int) $probe['first_request']['latency_ms'] ); ?> ms</td></tr>
			<tr><td><?php esc_html_e( 'Second', 'autoloadfix' ); ?></td><td><?php echo esc_html( strtoupper( sanitize_text_field( $probe['second_request']['cache_status'] ) ) ); ?></td><td><?php echo esc_html( (int) $probe['second_request']['latency_ms'] ); ?> ms</td></tr>
			</tbody></table>
		<?php endif; ?>
		<?php if ( ! empty( $probe['headers'] ) && is_array( $probe['headers'] ) ) : ?>
			<table class="widefat striped autoloadfix-header-table"><thead><tr><th><?php esc_html_e( 'Header', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Second request value', 'autoloadfix' ); ?></th></tr></thead><tbody>
			<?php foreach ( $probe['headers'] as $name => $value ) : ?><tr><td><code><?php echo esc_html( $name ); ?></code></td><td><?php echo esc_html( $value ); ?></td></tr><?php endforeach; ?>
			</tbody></table>
		<?php else : ?><p><?php esc_html_e( 'The response did not expose any of the cache-related headers AutoloadFix inspects.', 'autoloadfix' ); ?></p><?php endif; ?>
		<?php
	}

	/** Render action notices. */
	private function render_notice() {
		$key = isset( $_GET['af_cache_notice'] ) ? sanitize_key( wp_unslash( $_GET['af_cache_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map = array(
			'check_complete'             => array( 'success', __( 'Optimization check complete. Two anonymous requests were compared for cache status and warm-cache behavior.', 'autoloadfix' ) ),
			'check_failed'               => array( 'warning', __( 'The front-end probe could not complete. Local environment checks are still available.', 'autoloadfix' ) ),
			'purge_complete'             => array( 'success', __( 'Supported cache integrations were purged and the two-request front-end verification completed.', 'autoloadfix' ) ),
			'purge_probe_failed'         => array( 'warning', __( 'The supported cache purge ran, but the front-end verification could not complete.', 'autoloadfix' ) ),
			'purge_manual'               => array( 'warning', __( 'No supported one-click purge API was available. Use the manual cache path shown for the detected plugin, then run the check again.', 'autoloadfix' ) ),
			'purge_manual_probe_failed'  => array( 'warning', __( 'No one-click purge API was available and the front-end verification also failed. Use the manual purge path, then retry the check.', 'autoloadfix' ) ),
			'object_cache_flushed'       => array( 'success', __( 'Persistent object cache flushed. Allow it to warm again under normal traffic.', 'autoloadfix' ) ),
			'object_cache_failed'        => array( 'error', __( 'WordPress reported that the object-cache flush did not complete.', 'autoloadfix' ) ),
			'object_cache_missing'       => array( 'warning', __( 'No persistent object cache is currently detected.', 'autoloadfix' ) ),
		);
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}
	}

	private function redirect( $notice ) {
		$url = add_query_arg( array( 'page' => 'autoloadfix-cache-advisor', 'af_cache_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AutoloadFix.', 'autoloadfix' ) );
		}
	}
}
