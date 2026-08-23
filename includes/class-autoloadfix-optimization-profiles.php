<?php
/**
 * Issue-driven optimization profile export for supported cache plugins.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Optimization_Profiles {
	/** Register hooks. */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 40 );
		add_action( 'admin_init', array( $this, 'normalize_dynamic_unknown_results' ), 20 );
		add_action( 'admin_post_autoloadfix_download_optimization_profile', array( $this, 'handle_download' ) );
	}

	/** Register submenu. */
	public function register_submenu() {
		add_submenu_page(
			'autoloadfix',
			__( 'AutoloadFix Optimization Profiles', 'autoloadfix' ),
			__( 'Optimization Profiles', 'autoloadfix' ),
			'manage_options',
			'autoloadfix-optimization-profiles',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'autoloadfix_page_autoloadfix-optimization-profiles' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'autoloadfix-admin', AUTOLOADFIX_URL . 'assets/css/admin.css', array(), AUTOLOADFIX_VERSION );
		wp_enqueue_style( 'autoloadfix-optimization-profiles', AUTOLOADFIX_URL . 'assets/css/optimization-profiles.css', array( 'autoloadfix-admin' ), AUTOLOADFIX_VERSION );
	}

	/**
	 * Dynamic commerce pages with no explicit cache header are not proven healthy.
	 * Promote only previously-good UNKNOWN/PRESENT results to an informational review.
	 */
	public function normalize_dynamic_unknown_results() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$results = get_option( 'autoloadfix_site_scan_results', array() );
		if ( ! is_array( $results ) || ! $results ) {
			return;
		}

		$changed = false;
		foreach ( $results as $key => $result ) {
			if ( empty( $result['dynamic'] ) || 'good' !== ( isset( $result['severity'] ) ? $result['severity'] : 'good' ) ) {
				continue;
			}

			$cache_state = isset( $result['cache_state'] ) ? sanitize_key( $result['cache_state'] ) : 'unknown';
			if ( ! in_array( $cache_state, array( 'unknown', 'present' ), true ) ) {
				continue;
			}

			$issues = isset( $result['issues'] ) && is_array( $result['issues'] ) ? $result['issues'] : array();
			$exists = false;
			foreach ( $issues as $issue ) {
				if ( isset( $issue['code'] ) && 'dynamic_unverified' === $issue['code'] ) {
					$exists = true;
					break;
				}
			}
			if ( $exists ) {
				continue;
			}

			$issues[] = array(
				'severity' => 'info',
				'code'     => 'dynamic_unverified',
				'title'    => __( 'Dynamic cache status could not be verified', 'autoloadfix' ),
				'message'  => __( 'This dynamic commerce page is reachable, but the response did not expose a clear cache HIT/MISS/BYPASS signal. A shared full-page HIT would be unsafe; UNKNOWN alone is not proof of a problem or proof of a correct bypass.', 'autoloadfix' ),
				'steps'    => array(
					__( 'Confirm the active cache plugin treats this page and customer-session traffic as dynamic.', 'autoloadfix' ),
					__( 'Purge the page cache, open the page logged out, then re-check it in AutoloadFix.', 'autoloadfix' ),
					__( 'Do not force this page into shared full-page cache just to obtain a HIT.', 'autoloadfix' ),
				),
			);
			$results[ $key ]['issues']   = $issues;
			$results[ $key ]['severity'] = 'info';
			$changed                      = true;
		}

		if ( $changed ) {
			update_option( 'autoloadfix_site_scan_results', $results, false );
		}
	}

	/** Render profile page. */
	public function render_page() {
		$this->require_manage_options();
		$adapter        = $this->get_adapter();
		$results        = $this->get_results();
		$scan           = $this->get_scan_state();
		$recommendation = $this->build_recommendation( $adapter, $results );
		$ready          = $adapter['export_supported'] && $scan['complete'] && ! empty( $recommendation['changes'] );
		$notice         = isset( $_GET['af_profile_notice'] ) ? sanitize_key( wp_unslash( $_GET['af_profile_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'no_changes' === $notice ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'No safe importable setting change is currently available. Review the manual-only findings instead.', 'autoloadfix' ) . '</p></div>';
		} elseif ( 'scan_incomplete' === $notice ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Complete the Site Problem Scanner before generating a site-specific import profile.', 'autoloadfix' ) . '</p></div>';
		}
		?>
		<div class="wrap autoloadfix-wrap autoloadfix-profile-wrap">
			<div class="autoloadfix-header">
				<div>
					<div class="autoloadfix-eyebrow"><?php esc_html_e( 'AUTOLOADFIX GUIDED CONFIGURATION', 'autoloadfix' ); ?></div>
					<h1><?php esc_html_e( 'Optimization Profiles', 'autoloadfix' ); ?></h1>
					<p><?php esc_html_e( 'Turn verified scanner findings into a conservative, site-specific import file when the detected cache plugin has a known native import format.', 'autoloadfix' ); ?></p>
				</div>
				<div class="autoloadfix-version">v<?php echo esc_html( AUTOLOADFIX_VERSION ); ?></div>
			</div>

			<div class="autoloadfix-profile-summary">
				<div><span><?php esc_html_e( 'Detected cache plugin', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $adapter['name'] ); ?></strong></div>
				<div><span><?php esc_html_e( 'Import format', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $adapter['format_label'] ); ?></strong></div>
				<div><span><?php esc_html_e( 'Scan readiness', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $scan['complete'] ? __( 'Complete', 'autoloadfix' ) : sprintf( __( '%1$d / %2$d pages', 'autoloadfix' ), $scan['cursor'], $scan['total'] ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Safe profile changes', 'autoloadfix' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $recommendation['changes'] ) ) ); ?></strong></div>
			</div>

			<section class="autoloadfix-panel autoloadfix-profile-status <?php echo $ready ? 'is-ready' : 'is-waiting'; ?>">
				<div>
					<h2><?php echo esc_html( $this->get_status_title( $adapter, $scan, $recommendation ) ); ?></h2>
					<p><?php echo esc_html( $this->get_status_message( $adapter, $scan, $recommendation ) ); ?></p>
				</div>
				<div class="autoloadfix-profile-actions">
					<?php if ( $ready ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="autoloadfix_download_optimization_profile" />
							<?php wp_nonce_field( 'autoloadfix_download_optimization_profile' ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Download import profile', 'autoloadfix' ); ?></button>
						</form>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=autoloadfix-site-scanner' ) ); ?>"><?php echo esc_html( $scan['complete'] ? __( 'Open Site Problem Scanner', 'autoloadfix' ) : __( 'Continue site scan', 'autoloadfix' ) ); ?></a>
				</div>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'What the profile would change', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Only high-confidence, issue-linked changes are eligible. AutoloadFix does not turn on aggressive CSS/JS optimization merely because a plugin supports it.', 'autoloadfix' ); ?></p></div></div>
				<?php if ( empty( $recommendation['changes'] ) ) : ?>
					<p><?php esc_html_e( 'No scanner finding currently maps to a safe automatic import change for this cache plugin.', 'autoloadfix' ); ?></p>
				<?php else : ?>
					<div class="autoloadfix-profile-table-wrap"><table class="widefat striped autoloadfix-profile-table"><thead><tr><th><?php esc_html_e( 'Setting', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Current', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Recommended', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Why', 'autoloadfix' ); ?></th></tr></thead><tbody>
					<?php foreach ( $recommendation['changes'] as $change ) : ?>
						<tr><td><code><?php echo esc_html( $change['setting'] ); ?></code></td><td><?php echo esc_html( $change['current'] ); ?></td><td><?php echo esc_html( $change['recommended'] ); ?></td><td><?php echo esc_html( $change['reason'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table></div>
				<?php endif; ?>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Import & verify workflow', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'The generated file is never applied silently. You remain in control of the third-party plugin settings.', 'autoloadfix' ); ?></p></div></div>
				<ol class="autoloadfix-profile-steps">
					<li><?php echo esc_html( sprintf( __( 'Download the AutoloadFix profile after the site scan reaches 100%%.', 'autoloadfix' ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Open %s.', 'autoloadfix' ), $adapter['import_path'] ) ); ?></li>
					<li><?php esc_html_e( 'Import the file, review the cache plugin confirmation, then purge its cache.', 'autoloadfix' ); ?></li>
					<li><?php esc_html_e( 'Return to Site Problem Scanner and use Re-check problem pages. AutoloadFix will test the affected URLs again instead of assuming the import worked.', 'autoloadfix' ); ?></li>
				</ol>
				<?php if ( 'wp_rocket' === $adapter['id'] ) : ?><p class="description"><?php esc_html_e( 'WP Rocket profiles are site-specific JSON settings files. Keep the downloaded file private because it is based on this installation’s current WP Rocket settings.', 'autoloadfix' ); ?></p><?php endif; ?>
				<?php if ( 'litespeed' === $adapter['id'] ) : ?><p class="description"><?php esc_html_e( 'LiteSpeed Cache profiles use its native .data import format. The generated profile contains only the version marker and the settings AutoloadFix intentionally changes; LiteSpeed imports those keys into the existing configuration.', 'autoloadfix' ); ?></p><?php endif; ?>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Findings that still need manual work', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'A settings import cannot safely repair every website problem. Route errors, server latency, unknown cache headers, and application problems remain guided/manual findings.', 'autoloadfix' ); ?></p></div></div>
				<?php $this->render_manual_findings( $recommendation['manual'] ); ?>
			</section>
		</div>
		<?php
	}

	/** Download the generated profile. */
	public function handle_download() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_download_optimization_profile' );

		$adapter = $this->get_adapter();
		$scan    = $this->get_scan_state();
		if ( ! $scan['complete'] ) {
			$this->redirect_notice( 'scan_incomplete' );
		}

		$recommendation = $this->build_recommendation( $adapter, $this->get_results() );
		if ( ! $adapter['export_supported'] || empty( $recommendation['changes'] ) ) {
			$this->redirect_notice( 'no_changes' );
		}

		$file = $this->build_file( $adapter, $recommendation );
		if ( empty( $file['content'] ) ) {
			$this->redirect_notice( 'no_changes' );
		}

		nocache_headers();
		header( 'Content-Type: ' . $file['content_type'] );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file['filename'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $file['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Deliberate file download after capability and nonce checks.
		exit;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_adapter() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) || ( is_multisite() && is_plugin_active_for_network( 'wp-rocket/wp-rocket.php' ) ) ) {
			return array(
				'id'               => 'wp_rocket',
				'name'             => 'WP Rocket',
				'format_label'     => __( 'JSON settings import', 'autoloadfix' ),
				'extension'        => 'json',
				'content_type'     => 'application/json; charset=utf-8',
				'import_path'      => 'Settings > WP Rocket > Tools > Import Settings',
				'export_supported' => true,
			);
		}

		if ( is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) || ( is_multisite() && is_plugin_active_for_network( 'litespeed-cache/litespeed-cache.php' ) ) ) {
			return array(
				'id'               => 'litespeed',
				'name'             => 'LiteSpeed Cache',
				'format_label'     => __( 'Native .data settings import', 'autoloadfix' ),
				'extension'        => 'data',
				'content_type'     => 'application/octet-stream',
				'import_path'      => 'LiteSpeed Cache > Toolbox > Import / Export',
				'export_supported' => true,
			);
		}

		return array(
			'id'               => 'unsupported',
			'name'             => __( 'No supported profile adapter detected', 'autoloadfix' ),
			'format_label'     => __( 'Guided settings only', 'autoloadfix' ),
			'extension'        => '',
			'content_type'     => 'text/plain; charset=utf-8',
			'import_path'      => __( 'Use the detected cache plugin’s settings screen and Site Problem Scanner instructions', 'autoloadfix' ),
			'export_supported' => false,
		);
	}

	/**
	 * @param array<string,mixed> $adapter Adapter.
	 * @param array<string,mixed> $results Scanner results.
	 * @return array<string,mixed>
	 */
	private function build_recommendation( $adapter, $results ) {
		$dynamic_hit_paths = array();
		$manual            = array();

		foreach ( $results as $result ) {
			$issues = isset( $result['issues'] ) && is_array( $result['issues'] ) ? $result['issues'] : array();
			foreach ( $issues as $issue ) {
				$code = isset( $issue['code'] ) ? sanitize_key( $issue['code'] ) : '';
				if ( 'dynamic_hit' === $code && ! empty( $result['url'] ) ) {
					$path = wp_parse_url( $result['url'], PHP_URL_PATH );
					if ( $path ) {
						$dynamic_hit_paths[] = $this->normalize_path( $path );
					}
					continue;
				}
				if ( 'healthy' === $code ) {
					continue;
				}
				$manual[] = array(
					'page'     => isset( $result['title'] ) ? sanitize_text_field( $result['title'] ) : __( 'Page', 'autoloadfix' ),
					'url'      => isset( $result['url'] ) ? esc_url_raw( $result['url'] ) : '',
					'severity' => isset( $issue['severity'] ) ? sanitize_key( $issue['severity'] ) : 'info',
					'title'    => isset( $issue['title'] ) ? sanitize_text_field( $issue['title'] ) : __( 'Review finding', 'autoloadfix' ),
				);
			}
		}
		$dynamic_hit_paths = array_values( array_unique( $dynamic_hit_paths ) );

		$changes = array();
		$payload = array();
		if ( 'wp_rocket' === $adapter['id'] && $dynamic_hit_paths ) {
			$settings = get_option( 'wp_rocket_settings', array() );
			$settings = is_array( $settings ) ? $settings : array();
			$current  = isset( $settings['cache_reject_uri'] ) ? $settings['cache_reject_uri'] : array();
			$current  = is_array( $current ) ? $current : preg_split( '/\r\n|\r|\n/', (string) $current );
			$current  = array_values( array_filter( array_map( 'trim', $current ) ) );
			$merged   = array_values( array_unique( array_merge( $current, $dynamic_hit_paths ) ) );
			if ( $merged !== $current ) {
				$settings['cache_reject_uri'] = $merged;
				$changes[] = array(
					'setting'     => 'cache_reject_uri',
					'current'     => $current ? implode( ', ', $current ) : __( 'No matching explicit exclusion', 'autoloadfix' ),
					'recommended' => implode( ', ', $merged ),
					'reason'      => __( 'Site Problem Scanner detected a shared cache HIT on a dynamic commerce page, so that exact path should not be shared as full-page cache.', 'autoloadfix' ),
				);
			}
			$payload = $settings;
		} elseif ( 'litespeed' === $adapter['id'] && $dynamic_hit_paths ) {
			$current_raw = get_option( 'litespeed.conf.cache-exc', '' );
			$current     = is_array( $current_raw ) ? $current_raw : preg_split( '/\r\n|\r|\n/', (string) $current_raw );
			$current     = array_values( array_filter( array_map( 'trim', $current ) ) );
			$merged      = array_values( array_unique( array_merge( $current, $dynamic_hit_paths ) ) );
			if ( $merged !== $current ) {
				$changes[] = array(
					'setting'     => 'cache-exc',
					'current'     => $current ? implode( ', ', $current ) : __( 'No matching explicit exclusion', 'autoloadfix' ),
					'recommended' => implode( ', ', $merged ),
					'reason'      => __( 'Site Problem Scanner detected a shared cache HIT on a dynamic commerce page. The native LiteSpeed profile adds only those exact dynamic paths to Do Not Cache URIs.', 'autoloadfix' ),
				);
			}
			$payload = array( 'cache-exc' => implode( "\n", $merged ) );
		}

		return array(
			'changes' => $changes,
			'payload' => $payload,
			'manual'  => $this->unique_manual_findings( $manual ),
		);
	}

	/**
	 * @param array<string,mixed> $adapter Adapter.
	 * @param array<string,mixed> $recommendation Recommendation.
	 * @return array<string,string>
	 */
	private function build_file( $adapter, $recommendation ) {
		$stamp = gmdate( 'Ymd-His' );
		if ( 'wp_rocket' === $adapter['id'] ) {
			$content = wp_json_encode( $recommendation['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			return array(
				'filename'     => 'wp-rocket-settings-autoloadfix-' . $stamp . '.json',
				'content_type' => $adapter['content_type'],
				'content'      => $content ? $content : '',
			);
		}

		if ( 'litespeed' === $adapter['id'] ) {
			$version = (string) get_option( 'litespeed.conf._version', '' );
			if ( ! $version && class_exists( '\\LiteSpeed\\Core' ) ) {
				$version = (string) constant( '\\LiteSpeed\\Core::VER' );
			}
			if ( ! $version ) {
				$version = 'unknown';
			}
			$lines   = array( wp_json_encode( array( '_version', $version ) ) );
			$payload = isset( $recommendation['payload'] ) && is_array( $recommendation['payload'] ) ? $recommendation['payload'] : array();
			foreach ( $payload as $key => $value ) {
				$lines[] = wp_json_encode( array( sanitize_key( $key ), $value ) );
			}
			$lines = array_filter( $lines );
			return array(
				'filename'     => 'LSCWP_cfg-autoloadfix-' . $stamp . '.data',
				'content_type' => $adapter['content_type'],
				'content'      => implode( "\n\n", $lines ) . "\n",
			);
		}

		return array( 'filename' => '', 'content_type' => 'text/plain; charset=utf-8', 'content' => '' );
	}

	/** @return array<string,mixed> */
	private function get_scan_state() {
		$queue  = get_option( 'autoloadfix_site_scan_queue', array() );
		$queue  = is_array( $queue ) ? $queue : array();
		$total  = count( $queue );
		$cursor = min( (int) get_option( 'autoloadfix_site_scan_cursor', 0 ), $total );
		return array(
			'total'    => $total,
			'cursor'   => $cursor,
			'complete' => $total > 0 && $cursor >= $total,
		);
	}

	/** @return array<string,mixed> */
	private function get_results() {
		$results = get_option( 'autoloadfix_site_scan_results', array() );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * @param array<string,mixed> $adapter Adapter.
	 * @param array<string,mixed> $scan Scan state.
	 * @param array<string,mixed> $recommendation Recommendation.
	 * @return string
	 */
	private function get_status_title( $adapter, $scan, $recommendation ) {
		if ( ! $adapter['export_supported'] ) {
			return __( 'This cache plugin uses guided settings only', 'autoloadfix' );
		}
		if ( ! $scan['complete'] ) {
			return __( 'Complete the site scan before generating a profile', 'autoloadfix' );
		}
		if ( empty( $recommendation['changes'] ) ) {
			return __( 'No safe import change is currently required', 'autoloadfix' );
		}
		return __( 'A site-specific import profile is ready', 'autoloadfix' );
	}

	/**
	 * @param array<string,mixed> $adapter Adapter.
	 * @param array<string,mixed> $scan Scan state.
	 * @param array<string,mixed> $recommendation Recommendation.
	 * @return string
	 */
	private function get_status_message( $adapter, $scan, $recommendation ) {
		if ( ! $adapter['export_supported'] ) {
			return __( 'AutoloadFix does not know a sufficiently stable native import format for the detected cache tool, so it will not invent a file.', 'autoloadfix' );
		}
		if ( ! $scan['complete'] ) {
			return __( 'A profile based on a partial crawl could miss important dynamic or broken pages. Finish the safe batches first.', 'autoloadfix' );
		}
		if ( empty( $recommendation['changes'] ) ) {
			return __( 'The scanner may still have manual findings, but none currently justify an automatic cache-setting mutation.', 'autoloadfix' );
		}
		return __( 'Download the profile, import it in the detected cache plugin, purge cache, then re-check the affected pages in AutoloadFix.', 'autoloadfix' );
	}

	/**
	 * @param array<int,array<string,string>> $items Manual findings.
	 */
	private function render_manual_findings( $items ) {
		if ( ! $items ) {
			echo '<p>' . esc_html__( 'No manual-only finding is currently stored.', 'autoloadfix' ) . '</p>';
			return;
		}
		echo '<div class="autoloadfix-manual-findings">';
		foreach ( array_slice( $items, 0, 30 ) as $item ) {
			?>
			<div class="autoloadfix-manual-finding is-<?php echo esc_attr( $item['severity'] ); ?>"><div><strong><?php echo esc_html( $item['title'] ); ?></strong><span><?php echo esc_html( $item['page'] ); ?></span></div><?php if ( $item['url'] ) : ?><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open page', 'autoloadfix' ); ?></a><?php endif; ?></div>
			<?php
		}
		echo '</div>';
	}

	/**
	 * @param array<int,array<string,string>> $items Items.
	 * @return array<int,array<string,string>>
	 */
	private function unique_manual_findings( $items ) {
		$out  = array();
		$seen = array();
		foreach ( $items as $item ) {
			$key = md5( $item['url'] . '|' . $item['title'] );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]         = $item;
		}
		return $out;
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );
		return '/' === $path ? $path : trailingslashit( $path );
	}

	/** Redirect with page notice. */
	private function redirect_notice( $notice ) {
		wp_safe_redirect( add_query_arg( 'af_profile_notice', sanitize_key( $notice ), admin_url( 'admin.php?page=autoloadfix-optimization-profiles' ) ) );
		exit;
	}

	/** Require administrator capability. */
	private function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use this tool.', 'autoloadfix' ) );
		}
	}
}
