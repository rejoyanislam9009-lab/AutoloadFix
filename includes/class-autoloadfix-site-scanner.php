<?php
/**
 * Site-wide page problem scanner and guided verification workflow.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Site_Scanner {
	const BATCH_SIZE = 8;
	const MAX_URLS   = 300;

	/** Register hooks. */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 35 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 35 );
		add_action( 'admin_post_autoloadfix_site_scan_start', array( $this, 'handle_start' ) );
		add_action( 'admin_post_autoloadfix_site_scan_continue', array( $this, 'handle_continue' ) );
		add_action( 'admin_post_autoloadfix_site_scan_recheck', array( $this, 'handle_recheck' ) );
		add_action( 'admin_post_autoloadfix_site_scan_recheck_issues', array( $this, 'handle_recheck_issues' ) );
		add_action( 'admin_post_autoloadfix_site_scan_clear', array( $this, 'handle_clear' ) );
	}

	/** Register submenu. */
	public function register_submenu() {
		add_submenu_page(
			'autoloadfix',
			__( 'AutoloadFix Site Problem Scanner', 'autoloadfix' ),
			__( 'Site Problem Scanner', 'autoloadfix' ),
			'manage_options',
			'autoloadfix-site-scanner',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'autoloadfix_page_autoloadfix-site-scanner' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'autoloadfix-admin', AUTOLOADFIX_URL . 'assets/css/admin.css', array(), AUTOLOADFIX_VERSION );
		wp_enqueue_style( 'autoloadfix-site-scanner', AUTOLOADFIX_URL . 'assets/css/site-scanner.css', array( 'autoloadfix-admin' ), AUTOLOADFIX_VERSION );
	}

	/** Start a new scan and process the first safe batch. */
	public function handle_start() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_site_scan_start' );

		$queue = $this->build_queue();
		$this->save_option_noautoload( 'autoloadfix_site_scan_queue', $queue );
		$this->save_option_noautoload( 'autoloadfix_site_scan_results', array() );
		$this->save_option_noautoload( 'autoloadfix_site_scan_cursor', 0 );
		$this->save_option_noautoload( 'autoloadfix_site_scan_started', time() );
		$this->process_next_batch();
		$this->redirect( 'scan_started' );
	}

	/** Continue the current scan. */
	public function handle_continue() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_site_scan_continue' );
		$this->process_next_batch();
		$this->redirect( 'batch_complete' );
	}

	/** Re-check one previously scanned page by saved result key. */
	public function handle_recheck() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_site_scan_recheck' );

		$key     = isset( $_POST['result_key'] ) ? sanitize_key( wp_unslash( $_POST['result_key'] ) ) : '';
		$results = $this->get_results();
		if ( ! $key || empty( $results[ $key ]['url'] ) || ! $this->is_same_site_url( $results[ $key ]['url'] ) ) {
			$this->redirect( 'invalid_page' );
		}

		$old                      = $results[ $key ];
		$new                      = $this->scan_item( $old );
		$new['previous_severity'] = isset( $old['severity'] ) ? sanitize_key( $old['severity'] ) : 'good';
		$new['fixed']             = $this->severity_rank( $new['severity'] ) < $this->severity_rank( $new['previous_severity'] ) && 0 === $this->severity_rank( $new['severity'] );
		$results[ $key ]          = $new;
		$this->save_option_noautoload( 'autoloadfix_site_scan_results', $results );
		$this->redirect( $new['fixed'] ? 'page_fixed' : 'page_rechecked' );
	}

	/** Re-check a safe batch of pages that still have actionable findings. */
	public function handle_recheck_issues() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_site_scan_recheck_issues' );
		$results = $this->get_results();
		$count   = 0;

		foreach ( $results as $key => $old ) {
			if ( $count >= self::BATCH_SIZE ) {
				break;
			}
			$old_severity = isset( $old['severity'] ) ? $old['severity'] : 'good';
			if ( empty( $old['url'] ) || 0 === $this->severity_rank( $old_severity ) || ! $this->is_same_site_url( $old['url'] ) ) {
				continue;
			}

			$new                      = $this->scan_item( $old );
			$new['previous_severity'] = sanitize_key( $old_severity );
			$new['fixed']             = 0 === $this->severity_rank( $new['severity'] );
			$results[ $key ]          = $new;
			$count++;
		}

		$this->save_option_noautoload( 'autoloadfix_site_scan_results', $results );
		$this->redirect( 'issues_rechecked' );
	}

	/** Clear saved scan data. */
	public function handle_clear() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_site_scan_clear' );
		delete_option( 'autoloadfix_site_scan_queue' );
		delete_option( 'autoloadfix_site_scan_results' );
		delete_option( 'autoloadfix_site_scan_cursor' );
		delete_option( 'autoloadfix_site_scan_started' );
		$this->redirect( 'scan_cleared' );
	}

	/** Render scanner page. */
	public function render_page() {
		$this->require_manage_options();
		$queue    = $this->get_queue();
		$results  = $this->get_results();
		$cursor   = (int) get_option( 'autoloadfix_site_scan_cursor', 0 );
		$total    = count( $queue );
		$complete = $total > 0 && $cursor >= $total;
		$stats    = $this->get_stats( $results );
		$guidance = $this->get_cache_context();
		$progress = $total > 0 ? min( 100, (int) round( ( $cursor / $total ) * 100 ) ) : 0;

		$this->render_notice();
		?>
		<div class="wrap autoloadfix-wrap autoloadfix-site-scan-wrap">
			<div class="autoloadfix-header">
				<div>
					<div class="autoloadfix-eyebrow"><?php esc_html_e( 'AUTOLOADFIX PAGE-BY-PAGE DIAGNOSTICS', 'autoloadfix' ); ?></div>
					<h1><?php esc_html_e( 'Site Problem Scanner', 'autoloadfix' ); ?></h1>
					<p><?php esc_html_e( 'Scan public WordPress pages in safe batches, see page-specific cache and response problems, follow plugin-specific fix steps, then re-check the same pages to verify the result.', 'autoloadfix' ); ?></p>
				</div>
				<div class="autoloadfix-version">v<?php echo esc_html( AUTOLOADFIX_VERSION ); ?></div>
			</div>

			<div class="autoloadfix-scan-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="autoloadfix_site_scan_start" />
					<?php wp_nonce_field( 'autoloadfix_site_scan_start' ); ?>
					<button type="submit" class="button button-primary"><?php echo esc_html( $results ? __( 'Start fresh site scan', 'autoloadfix' ) : __( 'Start site scan', 'autoloadfix' ) ); ?></button>
				</form>
				<?php if ( $total > 0 && ! $complete ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="autoloadfix_site_scan_continue" />
						<?php wp_nonce_field( 'autoloadfix_site_scan_continue' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Scan next batch', 'autoloadfix' ); ?></button>
					</form>
				<?php endif; ?>
				<?php if ( $stats['problems'] > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="autoloadfix_site_scan_recheck_issues" />
						<?php wp_nonce_field( 'autoloadfix_site_scan_recheck_issues' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Re-check problem pages', 'autoloadfix' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="autoloadfix-site-summary">
				<div><span><?php esc_html_e( 'Scanned', 'autoloadfix' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $results ) ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Healthy', 'autoloadfix' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['healthy'] ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Needs review', 'autoloadfix' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['review'] ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Critical', 'autoloadfix' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['critical'] ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Verified fixed', 'autoloadfix' ); ?></span><strong><?php echo esc_html( number_format_i18n( $stats['fixed'] ) ); ?></strong></div>
			</div>

			<?php if ( $total > 0 ) : ?>
				<section class="autoloadfix-panel">
					<div class="autoloadfix-scan-progress-head">
						<strong><?php esc_html_e( 'Scan progress', 'autoloadfix' ); ?></strong>
						<span><?php echo esc_html( sprintf( '%1$d / %2$d (%3$d%%)', min( $cursor, $total ), $total, $progress ) ); ?></span>
					</div>
					<div class="autoloadfix-progress"><span style="width:<?php echo esc_attr( $progress ); ?>%"></span></div>
					<p class="description"><?php echo esc_html( sprintf( __( 'AutoloadFix scans up to %d public URLs per run and processes only a small batch at a time to reduce server load.', 'autoloadfix' ), self::MAX_URLS ) ); ?></p>
				</section>
			<?php endif; ?>

			<section class="autoloadfix-panel autoloadfix-cache-guidance">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Current cache tool guidance', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'When a page has a cache problem, AutoloadFix uses this detected tool to generate exact WordPress menu paths for the fix.', 'autoloadfix' ); ?></p></div></div>
				<div class="autoloadfix-guidance-grid">
					<div><span><?php esc_html_e( 'Detected cache tool', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $guidance['name'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Purge path', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $guidance['purge'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Cache settings', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $guidance['cache'] ); ?></strong></div>
					<div><span><?php esc_html_e( 'Exclusion settings', 'autoloadfix' ); ?></span><strong><?php echo esc_html( $guidance['exclude'] ); ?></strong></div>
				</div>
			</section>

			<section class="autoloadfix-panel">
				<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Page-by-page results', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Open “Fix & verify” on any row to see what is wrong, exactly where to go in the detected cache plugin, and how AutoloadFix will verify the change.', 'autoloadfix' ); ?></p></div></div>
				<?php if ( ! $results ) : ?>
					<p><?php esc_html_e( 'No site scan has been run yet.', 'autoloadfix' ); ?></p>
				<?php else : ?>
					<div class="autoloadfix-site-table-wrap">
						<table class="widefat striped autoloadfix-site-table">
							<thead><tr><th><?php esc_html_e( 'Page', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Response', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Cache', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Status', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Fix & verify', 'autoloadfix' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $results as $key => $result ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $result['title'] ); ?></strong><span class="autoloadfix-page-type"><?php echo esc_html( $result['type'] ); ?></span><code><?php echo esc_html( $this->short_url( $result['url'] ) ); ?></code></td>
									<td><strong><?php echo esc_html( (int) $result['status_code'] ); ?></strong><span><?php echo esc_html( sprintf( '%d ms', (int) $result['latency_ms'] ) ); ?></span></td>
									<td><strong><?php echo esc_html( strtoupper( $result['cache_state'] ) ); ?></strong><span><?php echo esc_html( ! empty( $result['warm_hit'] ) ? __( 'Warm HIT verified', 'autoloadfix' ) : $result['cache_note'] ); ?></span></td>
									<td><span class="autoloadfix-severity is-<?php echo esc_attr( $result['severity'] ); ?>"><?php echo esc_html( $this->severity_label( $result['severity'] ) ); ?></span><?php if ( ! empty( $result['fixed'] ) ) : ?><span class="autoloadfix-fixed"><?php esc_html_e( 'Fixed after re-check', 'autoloadfix' ); ?></span><?php endif; ?></td>
									<td>
										<details class="autoloadfix-fix-details">
											<summary><?php esc_html_e( 'View fix steps', 'autoloadfix' ); ?></summary>
											<?php $this->render_issues( $result ); ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="autoloadfix_site_scan_recheck" />
												<input type="hidden" name="result_key" value="<?php echo esc_attr( $key ); ?>" />
												<?php wp_nonce_field( 'autoloadfix_site_scan_recheck' ); ?>
												<button type="submit" class="button button-small"><?php esc_html_e( 'Re-check this page', 'autoloadfix' ); ?></button>
											</form>
										</details>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>

			<?php if ( $results ) : ?>
				<form class="autoloadfix-clear-scan" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Clear all saved site-scan results?', 'autoloadfix' ) ); ?>');">
					<input type="hidden" name="action" value="autoloadfix_site_scan_clear" />
					<?php wp_nonce_field( 'autoloadfix_site_scan_clear' ); ?>
					<button type="submit" class="button-link-delete"><?php esc_html_e( 'Clear scan results', 'autoloadfix' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Process one safe batch from the saved queue. */
	private function process_next_batch() {
		$queue   = $this->get_queue();
		$results = $this->get_results();
		$cursor  = (int) get_option( 'autoloadfix_site_scan_cursor', 0 );
		$end     = min( count( $queue ), $cursor + self::BATCH_SIZE );

		for ( $i = $cursor; $i < $end; $i++ ) {
			$item = $queue[ $i ];
			if ( empty( $item['url'] ) || ! $this->is_same_site_url( $item['url'] ) ) {
				continue;
			}
			$key             = md5( $item['url'] );
			$results[ $key ] = $this->scan_item( $item );
		}

		$this->save_option_noautoload( 'autoloadfix_site_scan_results', $results );
		$this->save_option_noautoload( 'autoloadfix_site_scan_cursor', $end );
	}

	/**
	 * Build a deduplicated queue of key pages and published public content.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function build_queue() {
		$items = array();
		$this->queue_add( $items, home_url( '/' ), __( 'Home page', 'autoloadfix' ), 'home', false );

		$front_id = (int) get_option( 'page_on_front', 0 );
		$posts_id = (int) get_option( 'page_for_posts', 0 );
		if ( $front_id ) {
			$this->queue_add( $items, get_permalink( $front_id ), get_the_title( $front_id ), 'front-page', false );
		}
		if ( $posts_id ) {
			$this->queue_add( $items, get_permalink( $posts_id ), get_the_title( $posts_id ), 'posts-page', false );
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$this->queue_add( $items, get_permalink( $shop_id ), __( 'WooCommerce Shop', 'autoloadfix' ), 'woocommerce-shop', false );
			}
		}

		$dynamic_ids = $this->get_dynamic_page_ids();
		foreach ( $dynamic_ids as $label => $id ) {
			$this->queue_add( $items, get_permalink( $id ), $label, 'woocommerce-dynamic', true );
		}

		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		foreach ( $types as $type ) {
			$remaining = self::MAX_URLS - count( $items );
			if ( $remaining <= 0 ) {
				break;
			}
			$query = new WP_Query(
				array(
					'post_type'              => $type,
					'post_status'            => 'publish',
					'posts_per_page'         => min( 100, $remaining ),
					'fields'                 => 'ids',
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $query->posts as $post_id ) {
				if ( count( $items ) >= self::MAX_URLS ) {
					break 2;
				}
				$is_dynamic = in_array( (int) $post_id, array_values( $dynamic_ids ), true );
				$this->queue_add( $items, get_permalink( $post_id ), get_the_title( $post_id ), $type, $is_dynamic );
			}
		}

		return array_values( $items );
	}

	/**
	 * @param array<string,array<string,mixed>> $items Queue keyed by URL hash.
	 * @param string $url URL.
	 * @param string $title Title.
	 * @param string $type Type.
	 * @param bool   $dynamic Dynamic page.
	 */
	private function queue_add( &$items, $url, $title, $type, $dynamic ) {
		if ( ! $url || ! $this->is_same_site_url( $url ) ) {
			return;
		}
		$key = md5( $url );
		$items[ $key ] = array(
			'url'     => esc_url_raw( $url ),
			'title'   => $title ? sanitize_text_field( $title ) : sanitize_text_field( $url ),
			'type'    => sanitize_key( $type ),
			'dynamic' => (bool) $dynamic,
		);
	}

	/**
	 * Scan one page with two anonymous same-site requests.
	 *
	 * @param array<string,mixed> $item Queue/result item.
	 * @return array<string,mixed>
	 */
	private function scan_item( $item ) {
		$first = $this->request_page( $item['url'] );
		if ( ! empty( $first['error'] ) ) {
			return $this->build_error_result( $item, $first['error'] );
		}

		usleep( 120000 );
		$second = $this->request_page( $item['url'] );
		if ( ! empty( $second['error'] ) ) {
			$second = $first;
		}

		$assessment = $this->assess_page( $item, $first, $second );
		return array_merge(
			array(
				'url'         => esc_url_raw( $item['url'] ),
				'title'       => sanitize_text_field( $item['title'] ),
				'type'        => sanitize_key( $item['type'] ),
				'dynamic'     => ! empty( $item['dynamic'] ),
				'checked_at'  => time(),
				'status_code' => (int) $second['status_code'],
				'latency_ms'  => (int) $second['latency_ms'],
				'first_cache' => sanitize_key( $first['cache_state'] ),
				'cache_state' => sanitize_key( $second['cache_state'] ),
				'cache_note'  => sanitize_text_field( $second['cache_note'] ),
				'warm_hit'    => 'hit' === $second['cache_state'],
				'headers'     => $second['headers'],
				'fixed'       => false,
			),
			$assessment
		);
	}

	/**
	 * @param string $url Same-site URL.
	 * @return array<string,mixed>
	 */
	private function request_page( $url ) {
		$start    = microtime( true );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 2,
				'user-agent'          => 'AutoloadFix-SiteScanner/' . AUTOLOADFIX_VERSION,
				'limit_response_size' => 65536,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => sanitize_text_field( $response->get_error_message() ) );
		}

		$headers      = array();
		$header_names = array( 'cache-control', 'age', 'x-cache', 'x-litespeed-cache', 'cf-cache-status', 'x-proxy-cache', 'x-kinsta-cache', 'x-wp-cf-super-cache', 'server', 'via', 'location' );
		foreach ( $header_names as $name ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( '' !== $value && null !== $value ) {
				$headers[ $name ] = sanitize_text_field( is_array( $value ) ? implode( ', ', $value ) : (string) $value );
			}
		}

		$cache = $this->classify_cache( $headers );
		return array(
			'error'       => '',
			'status_code' => (int) wp_remote_retrieve_response_code( $response ),
			'latency_ms'  => (int) round( ( microtime( true ) - $start ) * 1000 ),
			'headers'     => $headers,
			'cache_state' => $cache['state'],
			'cache_note'  => $cache['note'],
		);
	}

	/**
	 * @param array<string,string> $headers Headers.
	 * @return array<string,string>
	 */
	private function classify_cache( $headers ) {
		$signals = array();
		foreach ( array( 'x-litespeed-cache', 'x-cache', 'cf-cache-status', 'x-proxy-cache', 'x-kinsta-cache', 'x-wp-cf-super-cache' ) as $name ) {
			if ( ! empty( $headers[ $name ] ) ) {
				$signals[] = strtolower( $headers[ $name ] );
			}
		}
		$text = implode( ' ', $signals );
		if ( false !== strpos( $text, 'hit' ) ) {
			return array( 'state' => 'hit', 'note' => __( 'A cache HIT header was detected.', 'autoloadfix' ) );
		}
		if ( false !== strpos( $text, 'miss' ) ) {
			return array( 'state' => 'miss', 'note' => __( 'A cache MISS header was detected.', 'autoloadfix' ) );
		}
		if ( false !== strpos( $text, 'bypass' ) || false !== strpos( $text, 'dynamic' ) ) {
			return array( 'state' => 'bypass', 'note' => __( 'The response reports a cache bypass/dynamic state.', 'autoloadfix' ) );
		}
		if ( false !== strpos( $text, 'stale' ) ) {
			return array( 'state' => 'stale', 'note' => __( 'The response reports stale cached content.', 'autoloadfix' ) );
		}
		if ( $signals ) {
			return array( 'state' => 'present', 'note' => __( 'A cache-specific header exists, but no HIT/MISS state was recognized.', 'autoloadfix' ) );
		}
		return array( 'state' => 'unknown', 'note' => __( 'No explicit cache HIT/MISS header was exposed.', 'autoloadfix' ) );
	}

	/**
	 * Assess response/cache issues and attach guided fix steps.
	 *
	 * @param array<string,mixed> $item Queue item.
	 * @param array<string,mixed> $first First request.
	 * @param array<string,mixed> $second Second request.
	 * @return array<string,mixed>
	 */
	private function assess_page( $item, $first, $second ) {
		$issues   = array();
		$severity = 'good';
		$status   = (int) $second['status_code'];
		$dynamic  = ! empty( $item['dynamic'] );

		if ( $status >= 500 ) {
			$issues[] = $this->issue( 'critical', 'http_5xx', __( 'Server error on this page', 'autoloadfix' ), __( 'The public URL returned a 5xx response. Fix this before cache tuning because a cache plugin should not hide an application/server failure.', 'autoloadfix' ), $item['url'] );
		} elseif ( $status >= 400 ) {
			$issues[] = $this->issue( 'critical', 'http_4xx', __( 'Broken or blocked public page', 'autoloadfix' ), __( 'The public URL returned a 4xx response. Confirm the page exists, is published, and is not blocked by a maintenance/security rule.', 'autoloadfix' ), $item['url'] );
		} elseif ( $status >= 300 ) {
			$issues[] = $this->issue( 'review', 'redirect', __( 'Page redirects during the scan', 'autoloadfix' ), __( 'The scanned URL redirects. This may be intentional, but review redirect chains and update internal links to the final canonical URL where practical.', 'autoloadfix' ), $item['url'] );
		}

		if ( $dynamic ) {
			if ( 'hit' === $second['cache_state'] ) {
				$issues[] = $this->issue( 'critical', 'dynamic_hit', __( 'Dynamic WooCommerce page appears cached', 'autoloadfix' ), __( 'Cart, Checkout, My Account, and session-sensitive pages generally should not be served as a shared full-page cache HIT.', 'autoloadfix' ), $item['url'] );
			}
		} else {
			if ( 'miss' === $first['cache_state'] && 'miss' === $second['cache_state'] ) {
				$issues[] = $this->issue( 'review', 'not_warming', __( 'Cache is not warming to a HIT', 'autoloadfix' ), __( 'Two anonymous requests still returned MISS. Verify page caching is enabled, check exclusions, purge once, then re-check this page.', 'autoloadfix' ), $item['url'] );
			} elseif ( 'bypass' === $second['cache_state'] ) {
				$issues[] = $this->issue( 'review', 'unexpected_bypass', __( 'Public page is bypassing cache', 'autoloadfix' ), __( 'This page is being bypassed by the detected cache layer. If it is intentionally personalized, keep the bypass; otherwise review cache exclusions and cookies.', 'autoloadfix' ), $item['url'] );
			} elseif ( 'stale' === $second['cache_state'] ) {
				$issues[] = $this->issue( 'review', 'stale', __( 'Stale cache state detected', 'autoloadfix' ), __( 'Purge the active cache, load the page again, and use Re-check this page to confirm a fresh HIT/MISS cycle.', 'autoloadfix' ), $item['url'] );
			} elseif ( 'unknown' === $second['cache_state'] && $this->has_page_cache_plugin() ) {
				$issues[] = $this->issue( 'info', 'no_header', __( 'No explicit cache status header', 'autoloadfix' ), __( 'A recognized cache plugin is active but this response does not expose a clear HIT/MISS header. This is not automatically a failure; verify the cache plugin dashboard and server/host cache status.', 'autoloadfix' ), $item['url'] );
			}

			$cache_control = isset( $second['headers']['cache-control'] ) ? strtolower( $second['headers']['cache-control'] ) : '';
			if ( $cache_control && ( false !== strpos( $cache_control, 'no-store' ) || false !== strpos( $cache_control, 'private' ) ) ) {
				$issues[] = $this->issue( 'review', 'cache_control_private', __( 'Static page sends restrictive Cache-Control', 'autoloadfix' ), __( 'This public page sends private/no-store caching instructions. Confirm that a plugin, security rule, cookie, or custom header is not disabling cache unintentionally.', 'autoloadfix' ), $item['url'] );
			}
		}

		if ( (int) $second['latency_ms'] >= 2500 ) {
			$issues[] = $this->issue( 'critical', 'very_slow', __( 'Very slow server response', 'autoloadfix' ), __( 'The server-side request took at least 2.5 seconds. Check uncached PHP/database work, object cache availability, slow plugins, external API calls, and hosting limits.', 'autoloadfix' ), $item['url'] );
		} elseif ( (int) $second['latency_ms'] >= 1200 ) {
			$issues[] = $this->issue( 'review', 'slow', __( 'Slow server response', 'autoloadfix' ), __( 'The page took at least 1.2 seconds in the server-side probe. If the second request is a cache HIT, investigate network/server load; if not, first fix page-cache warming.', 'autoloadfix' ), $item['url'] );
		}

		foreach ( $issues as $issue ) {
			if ( $this->severity_rank( $issue['severity'] ) > $this->severity_rank( $severity ) ) {
				$severity = $issue['severity'];
			}
		}

		if ( ! $issues ) {
			$issues[] = array(
				'severity' => 'good',
				'code'     => 'healthy',
				'title'    => __( 'No problem detected in this scan', 'autoloadfix' ),
				'message'  => $dynamic ? __( 'The dynamic page is reachable and was not detected as a shared cache HIT.', 'autoloadfix' ) : __( 'The page is reachable and no actionable cache/response issue was detected by this scanner.', 'autoloadfix' ),
				'steps'    => array( __( 'No change is required. Re-check after major cache, theme, or hosting changes.', 'autoloadfix' ) ),
			);
		}

		return array( 'severity' => $severity, 'issues' => $issues );
	}

	/**
	 * @param string $severity Severity.
	 * @param string $code Issue code.
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $url Page URL.
	 * @return array<string,mixed>
	 */
	private function issue( $severity, $code, $title, $message, $url ) {
		return array(
			'severity' => sanitize_key( $severity ),
			'code'     => sanitize_key( $code ),
			'title'    => sanitize_text_field( $title ),
			'message'  => sanitize_text_field( $message ),
			'steps'    => $this->get_fix_steps( $code, $url ),
		);
	}

	/**
	 * Plugin-specific fix instructions.
	 *
	 * @param string $code Issue code.
	 * @param string $url Affected URL.
	 * @return array<int,string>
	 */
	private function get_fix_steps( $code, $url ) {
		$ctx   = $this->get_cache_context();
		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$path  = $path ? $path : '/';
		$steps = array();

		if ( in_array( $code, array( 'not_warming', 'unexpected_bypass', 'cache_control_private', 'no_header' ), true ) ) {
			$steps[] = sprintf( __( 'Open %s and confirm page caching is enabled for public visitors.', 'autoloadfix' ), $ctx['cache'] );
			$steps[] = sprintf( __( 'Review %s and confirm this URL path is not excluded unless it really needs to be: %s', 'autoloadfix' ), $ctx['exclude'], $path );
			$steps[] = sprintf( __( 'Clear the cache from %s, load the public page, then return here and click “Re-check this page”.', 'autoloadfix' ), $ctx['purge'] );
		} elseif ( 'dynamic_hit' === $code ) {
			$steps[] = sprintf( __( 'Open %s and confirm the dynamic path is excluded from shared page cache: %s', 'autoloadfix' ), $ctx['exclude'], $path );
			$steps[] = __( 'For WooCommerce, verify Cart, Checkout, My Account, logged-in/customer-session requests, and personalized fragments are not shared as full-page cache.', 'autoloadfix' );
			$steps[] = sprintf( __( 'Purge from %s, then use “Re-check this page”. A BYPASS/MISS can be correct for this dynamic page; a shared HIT should disappear.', 'autoloadfix' ), $ctx['purge'] );
		} elseif ( 'stale' === $code ) {
			$steps[] = sprintf( __( 'Purge the active cache at %s.', 'autoloadfix' ), $ctx['purge'] );
			$steps[] = __( 'Open the page once in a private/logged-out browser session, then re-check it here.', 'autoloadfix' );
		} elseif ( in_array( $code, array( 'slow', 'very_slow' ), true ) ) {
			$steps[] = __( 'First check whether the second probe request is a cache HIT. If not, fix cache warming before judging uncached performance.', 'autoloadfix' );
			$steps[] = __( 'Check AutoloadFix Overview for autoload growth and Monitor & Tools for recent changes.', 'autoloadfix' );
			$steps[] = __( 'If your host actually provides Redis/Memcached, consider persistent object cache for database-heavy stores; do not install an object-cache plugin without the server service.', 'autoloadfix' );
			$steps[] = __( 'Review slow plugins, external API calls, PHP workers, database load, and hosting resource limits.', 'autoloadfix' );
		} elseif ( in_array( $code, array( 'http_5xx', 'http_4xx', 'redirect', 'probe_error' ), true ) ) {
			$steps[] = __( 'Open the URL in a logged-out/private browser and confirm the same response or redirect behavior.', 'autoloadfix' );
			$steps[] = __( 'Check WordPress permalink/page status, redirect rules, maintenance/security plugins, DNS/loopback restrictions, and server logs as appropriate.', 'autoloadfix' );
			$steps[] = __( 'After correcting the route or application/server problem, return to AutoloadFix and re-check this page.', 'autoloadfix' );
		} else {
			$steps[] = __( 'Review the page in a logged-out session, make only the necessary change, then re-check the same page in AutoloadFix.', 'autoloadfix' );
		}

		return $steps;
	}

	/**
	 * Detect active cache plugin and relevant menu paths.
	 *
	 * @return array<string,mixed>
	 */
	private function get_cache_context() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$defs = array(
			array( 'file' => 'litespeed-cache/litespeed-cache.php', 'name' => 'LiteSpeed Cache', 'purge' => 'LiteSpeed Cache > Toolbox > Purge > Purge All', 'cache' => 'LiteSpeed Cache > Cache > Cache', 'exclude' => 'LiteSpeed Cache > Cache > Excludes' ),
			array( 'file' => 'wp-rocket/wp-rocket.php', 'name' => 'WP Rocket', 'purge' => 'Settings > WP Rocket > Dashboard > Clear Cache', 'cache' => 'Settings > WP Rocket > Cache', 'exclude' => 'Settings > WP Rocket > Advanced Rules > Never Cache URL(s)' ),
			array( 'file' => 'w3-total-cache/w3-total-cache.php', 'name' => 'W3 Total Cache', 'purge' => 'Performance > Dashboard > Empty All Caches', 'cache' => 'Performance > General Settings > Page Cache', 'exclude' => 'Performance > Page Cache > Never cache the following pages' ),
			array( 'file' => 'wp-super-cache/wp-cache.php', 'name' => 'WP Super Cache', 'purge' => 'Settings > WP Super Cache > Contents > Delete Cache', 'cache' => 'Settings > WP Super Cache > Easy/Advanced > Caching On', 'exclude' => 'Settings > WP Super Cache > Advanced > Rejected URL Strings' ),
			array( 'file' => 'wp-fastest-cache/wpFastestCache.php', 'name' => 'WP Fastest Cache', 'purge' => 'WP Fastest Cache > Delete Cache', 'cache' => 'WP Fastest Cache > Settings > Cache System', 'exclude' => 'WP Fastest Cache > Exclude' ),
			array( 'file' => 'breeze/breeze.php', 'name' => 'Breeze', 'purge' => 'Settings > Breeze > Purge All Cache', 'cache' => 'Settings > Breeze > Basic Options > Cache System', 'exclude' => 'Settings > Breeze > Advanced Options > Never Cache URLs' ),
			array( 'file' => 'sg-cachepress/sg-cachepress.php', 'name' => 'Speed Optimizer', 'purge' => 'Speed Optimizer > Caching > Flush Cache', 'cache' => 'Speed Optimizer > Caching', 'exclude' => 'Speed Optimizer > Caching > Exclude URLs' ),
			array( 'file' => 'wp-optimize/wp-optimize.php', 'name' => 'WP-Optimize', 'purge' => 'WP-Optimize > Cache > Purge cache', 'cache' => 'WP-Optimize > Cache > Page cache', 'exclude' => 'WP-Optimize > Cache > Advanced settings > URLs to exclude' ),
		);
		foreach ( $defs as $def ) {
			if ( is_plugin_active( $def['file'] ) || ( is_multisite() && is_plugin_active_for_network( $def['file'] ) ) ) {
				$def['recognized'] = true;
				return $def;
			}
		}
		return array(
			'recognized' => false,
			'name'       => __( 'No recognized WordPress page-cache plugin', 'autoloadfix' ),
			'purge'      => __( 'Use your hosting/CDN cache control if one exists', 'autoloadfix' ),
			'cache'      => __( 'Check your host/server cache settings', 'autoloadfix' ),
			'exclude'    => __( 'Check host/CDN cache exclusion rules', 'autoloadfix' ),
		);
	}

	/** @return bool */
	private function has_page_cache_plugin() {
		$ctx = $this->get_cache_context();
		return ! empty( $ctx['recognized'] );
	}

	/**
	 * Return only WooCommerce pages that should be treated as dynamic/shared-cache-sensitive.
	 * The Shop archive is intentionally excluded because it is normally cacheable.
	 *
	 * @return array<string,int>
	 */
	private function get_dynamic_page_ids() {
		$ids = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			$map = array(
				__( 'WooCommerce Cart', 'autoloadfix' )       => (int) wc_get_page_id( 'cart' ),
				__( 'WooCommerce Checkout', 'autoloadfix' )   => (int) wc_get_page_id( 'checkout' ),
				__( 'WooCommerce My Account', 'autoloadfix' ) => (int) wc_get_page_id( 'myaccount' ),
			);
			foreach ( $map as $label => $id ) {
				if ( $id > 0 ) {
					$ids[ $label ] = $id;
				}
			}
		}
		return $ids;
	}

	/**
	 * @param array<string,mixed> $item Item.
	 * @param string $error Error.
	 * @return array<string,mixed>
	 */
	private function build_error_result( $item, $error ) {
		return array(
			'url'         => esc_url_raw( $item['url'] ),
			'title'       => sanitize_text_field( $item['title'] ),
			'type'        => sanitize_key( $item['type'] ),
			'dynamic'     => ! empty( $item['dynamic'] ),
			'checked_at'  => time(),
			'status_code' => 0,
			'latency_ms'  => 0,
			'first_cache' => 'unknown',
			'cache_state' => 'unknown',
			'cache_note'  => __( 'Probe failed.', 'autoloadfix' ),
			'warm_hit'    => false,
			'headers'     => array(),
			'severity'    => 'critical',
			'fixed'       => false,
			'issues'      => array(
				$this->issue( 'critical', 'probe_error', __( 'AutoloadFix could not request this page', 'autoloadfix' ), sanitize_text_field( $error ), $item['url'] ),
			),
		);
	}

	/**
	 * @param array<string,mixed> $result Result.
	 */
	private function render_issues( $result ) {
		if ( empty( $result['issues'] ) || ! is_array( $result['issues'] ) ) {
			return;
		}
		foreach ( $result['issues'] as $issue ) {
			?>
			<div class="autoloadfix-issue-block is-<?php echo esc_attr( $issue['severity'] ); ?>">
				<strong><?php echo esc_html( $issue['title'] ); ?></strong>
				<p><?php echo esc_html( $issue['message'] ); ?></p>
				<?php if ( ! empty( $issue['steps'] ) && is_array( $issue['steps'] ) ) : ?>
					<ol><?php foreach ( $issue['steps'] as $step ) : ?><li><?php echo esc_html( $step ); ?></li><?php endforeach; ?></ol>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/** @return array<int,array<string,mixed>> */
	private function get_queue() {
		$value = get_option( 'autoloadfix_site_scan_queue', array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return array<string,array<string,mixed>> */
	private function get_results() {
		$value = get_option( 'autoloadfix_site_scan_results', array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param array<string,array<string,mixed>> $results Results.
	 * @return array<string,int>
	 */
	private function get_stats( $results ) {
		$stats = array( 'healthy' => 0, 'review' => 0, 'critical' => 0, 'fixed' => 0, 'problems' => 0 );
		foreach ( $results as $result ) {
			$severity = isset( $result['severity'] ) ? $result['severity'] : 'good';
			if ( 'critical' === $severity ) {
				$stats['critical']++;
				$stats['problems']++;
			} elseif ( in_array( $severity, array( 'review', 'info' ), true ) ) {
				$stats['review']++;
				$stats['problems']++;
			} else {
				$stats['healthy']++;
			}
			if ( ! empty( $result['fixed'] ) ) {
				$stats['fixed']++;
			}
		}
		return $stats;
	}

	/**
	 * @param string $severity Severity.
	 * @return int
	 */
	private function severity_rank( $severity ) {
		$map = array( 'good' => 0, 'info' => 1, 'review' => 2, 'critical' => 3 );
		return isset( $map[ $severity ] ) ? $map[ $severity ] : 1;
	}

	/**
	 * @param string $severity Severity.
	 * @return string
	 */
	private function severity_label( $severity ) {
		$map = array(
			'good'     => __( 'Healthy', 'autoloadfix' ),
			'info'     => __( 'Info', 'autoloadfix' ),
			'review'   => __( 'Review', 'autoloadfix' ),
			'critical' => __( 'Critical', 'autoloadfix' ),
		);
		return isset( $map[ $severity ] ) ? $map[ $severity ] : __( 'Review', 'autoloadfix' );
	}

	/**
	 * Ensure a URL is HTTP(S) and belongs to the same host as the WordPress home URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_same_site_url( $url ) {
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		return $home_host && $url_host && strtolower( $home_host ) === strtolower( $url_host );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private function short_url( $url ) {
		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		$out   = $path ? $path : '/';
		if ( $query ) {
			$out .= '?' . $query;
		}
		return $out;
	}

	/**
	 * Save an option while keeping it non-autoloaded.
	 *
	 * @param string $name Option name.
	 * @param mixed  $value Value.
	 */
	private function save_option_noautoload( $name, $value ) {
		if ( false === get_option( $name, false ) ) {
			add_option( $name, $value, '', false );
		} else {
			update_option( $name, $value, false );
		}
	}

	/** Render action notice. */
	private function render_notice() {
		$key = isset( $_GET['af_site_notice'] ) ? sanitize_key( wp_unslash( $_GET['af_site_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map = array(
			'scan_started'     => array( 'success', __( 'Site scan started and the first safe batch was checked.', 'autoloadfix' ) ),
			'batch_complete'   => array( 'success', __( 'The next site-scan batch was checked.', 'autoloadfix' ) ),
			'page_fixed'       => array( 'success', __( 'Re-check passed: the previously detected problem is no longer present in this scan.', 'autoloadfix' ) ),
			'page_rechecked'   => array( 'info', __( 'Page re-checked. Review the current result and fix steps below.', 'autoloadfix' ) ),
			'issues_rechecked' => array( 'info', __( 'A safe batch of problem pages was re-checked.', 'autoloadfix' ) ),
			'invalid_page'     => array( 'error', __( 'The saved page could not be safely re-checked.', 'autoloadfix' ) ),
			'scan_cleared'     => array( 'success', __( 'Saved site-scan results were cleared.', 'autoloadfix' ) ),
		);
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}
	}

	/**
	 * @param string $notice Notice key.
	 */
	private function redirect( $notice ) {
		$url = add_query_arg( array( 'page' => 'autoloadfix-site-scanner', 'af_site_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/** Require administrator capability. */
	private function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AutoloadFix.', 'autoloadfix' ) );
		}
	}
}
