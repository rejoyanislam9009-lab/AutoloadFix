<?php
/**
 * WordPress admin UI and mutation handlers.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Admin {
	/** @var AutoloadFix_Scanner */
	private $scanner;

	/** @var AutoloadFix_Snapshot */
	private $snapshot;

	/**
	 * @param AutoloadFix_Scanner  $scanner Scanner service.
	 * @param AutoloadFix_Snapshot $snapshot Snapshot service.
	 */
	public function __construct( AutoloadFix_Scanner $scanner, AutoloadFix_Snapshot $snapshot ) {
		$this->scanner  = $scanner;
		$this->snapshot = $snapshot;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_autoloadfix_disable', array( $this, 'handle_disable' ) );
		add_action( 'admin_post_autoloadfix_restore', array( $this, 'handle_restore' ) );
		add_action( 'admin_post_autoloadfix_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_autoloadfix_export', array( $this, 'handle_export' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AUTOLOADFIX_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add a shortcut on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=autoloadfix' ) ) . '">' . esc_html__( 'Open AutoloadFix', 'autoloadfix' ) . '</a>'
		);
		return $links;
	}

	/** Register the admin menu. */
	public function register_menu() {
		add_menu_page(
			__( 'AutoloadFix', 'autoloadfix' ),
			__( 'AutoloadFix', 'autoloadfix' ),
			'manage_options',
			'autoloadfix',
			array( $this, 'render_page' ),
			'dashicons-performance',
			81
		);
	}

	/**
	 * Load assets only on the AutoloadFix top-level page.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_autoloadfix' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'autoloadfix-admin', AUTOLOADFIX_URL . 'assets/css/admin.css', array(), AUTOLOADFIX_VERSION );
		wp_enqueue_script( 'autoloadfix-admin', AUTOLOADFIX_URL . 'assets/js/admin.js', array(), AUTOLOADFIX_VERSION, true );
		wp_localize_script(
			'autoloadfix-admin',
			'AutoloadFixAdmin',
			array(
				'confirmDisable' => __( 'Disable autoloading for this option? A snapshot will be created first. Test your site after the change.', 'autoloadfix' ),
				'confirmRestore' => __( 'Restore the autoload behavior saved in this snapshot?', 'autoloadfix' ),
			)
		);
	}

	/** Render the main admin page. */
	public function render_page() {
		$this->require_manage_options();
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
		$view = in_array( $view, array( 'overview', 'history', 'settings' ), true ) ? $view : 'overview';
		$this->render_notice();
		?>
		<div class="wrap autoloadfix-wrap">
			<div class="autoloadfix-header">
				<div>
					<div class="autoloadfix-eyebrow"><?php esc_html_e( 'WORDPRESS PERFORMANCE TOOLKIT', 'autoloadfix' ); ?></div>
					<h1><?php esc_html_e( 'AutoloadFix', 'autoloadfix' ); ?></h1>
					<p><?php esc_html_e( 'Find oversized autoloaded options, understand likely ownership, make cautious changes, and restore them if needed.', 'autoloadfix' ); ?></p>
				</div>
				<div class="autoloadfix-version">v<?php echo esc_html( AUTOLOADFIX_VERSION ); ?></div>
			</div>

			<nav class="nav-tab-wrapper autoloadfix-tabs" aria-label="<?php esc_attr_e( 'AutoloadFix sections', 'autoloadfix' ); ?>">
				<?php $this->nav_tab( 'overview', __( 'Overview', 'autoloadfix' ), $view ); ?>
				<?php $this->nav_tab( 'history', __( 'History & Restore', 'autoloadfix' ), $view ); ?>
				<?php $this->nav_tab( 'settings', __( 'Settings', 'autoloadfix' ), $view ); ?>
			</nav>

			<?php
			if ( 'history' === $view ) {
				$this->render_history();
			} elseif ( 'settings' === $view ) {
				$this->render_settings();
			} else {
				$this->render_overview();
			}
			?>
		</div>
		<?php
	}

	/** Render overview. */
	private function render_overview() {
		$summary    = $this->scanner->get_summary();
		$rows       = $this->scanner->get_largest_options( 100 );
		$settings   = $this->scanner->get_settings();
		$candidates = 0;

		foreach ( $rows as $row ) {
			if ( 'candidate' === $row['risk']['level'] ) {
				++$candidates;
			}
		}

		$status_label = __( 'Healthy', 'autoloadfix' );
		$status_class = 'good';
		if ( $summary['total_size'] > $summary['health_limit'] ) {
			$status_label = __( 'Needs attention', 'autoloadfix' );
			$status_class = 'bad';
		} elseif ( $summary['score'] < 90 ) {
			$status_label = __( 'Watch', 'autoloadfix' );
			$status_class = 'warn';
		}

		/* translators: %d: Autoload health score from 0 to 100. */
		$score_label = sprintf( __( 'Health score %d out of 100', 'autoloadfix' ), $summary['score'] );
		/* translators: %s: Human-readable configured health limit. */
		$health_caption = sprintf( __( 'Health limit: %s', 'autoloadfix' ), size_format( $summary['health_limit'], 1 ) );
		/* translators: %s: Human-readable large-option threshold. */
		$large_caption = sprintf( __( 'At least %s each', 'autoloadfix' ), size_format( (int) $settings['large_option_threshold'], 0 ) );
		?>
		<section class="autoloadfix-score-card">
			<div class="autoloadfix-score-ring" aria-label="<?php echo esc_attr( $score_label ); ?>">
				<strong><?php echo esc_html( $summary['score'] ); ?></strong><span>/100</span>
			</div>
			<div class="autoloadfix-score-copy">
				<span class="autoloadfix-status autoloadfix-status-<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
				<h2><?php esc_html_e( 'Autoload health score', 'autoloadfix' ); ?></h2>
				<p><?php esc_html_e( 'Autoloaded options are loaded on many requests. Large or unnecessary entries can increase memory use and database work.', 'autoloadfix' ); ?></p>
			</div>
		</section>

		<div class="autoloadfix-grid autoloadfix-metrics">
			<?php $this->metric_card( __( 'Total autoload', 'autoloadfix' ), size_format( $summary['total_size'], 1 ), $health_caption ); ?>
			<?php $this->metric_card( __( 'Autoloaded options', 'autoloadfix' ), number_format_i18n( $summary['option_count'] ), __( 'Count loaded by WordPress', 'autoloadfix' ) ); ?>
			<?php $this->metric_card( __( 'Large entries', 'autoloadfix' ), number_format_i18n( $summary['large_count'] ), $large_caption ); ?>
			<?php $this->metric_card( __( 'Review candidates', 'autoloadfix' ), number_format_i18n( $candidates ), __( 'Among the 100 largest scanned', 'autoloadfix' ) ); ?>
		</div>

		<div class="autoloadfix-panel">
			<div class="autoloadfix-panel-head">
				<div>
					<h2><?php esc_html_e( 'Largest autoloaded options', 'autoloadfix' ); ?></h2>
					<p><?php esc_html_e( 'AutoloadFix never changes protected WordPress options and never assumes an unknown option is safe.', 'autoloadfix' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=autoloadfix_export' ), 'autoloadfix_export' ) ); ?>"><?php esc_html_e( 'Export report', 'autoloadfix' ); ?></a>
			</div>

			<div class="autoloadfix-table-wrap">
				<table class="widefat striped autoloadfix-table">
					<thead>
						<tr><th><?php esc_html_e( 'Option', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Size', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Likely owner', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Assessment', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Action', 'autoloadfix' ); ?></th></tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No autoloaded options were found.', 'autoloadfix' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							/* translators: %s: Raw database autoload value for the option. */
							$autoload_caption = sprintf( __( 'DB autoload value: %s', 'autoloadfix' ), $row['autoload'] );
							?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Option', 'autoloadfix' ); ?>"><code><?php echo esc_html( $row['option_name'] ); ?></code><div class="autoloadfix-muted"><?php echo esc_html( $autoload_caption ); ?></div></td>
								<td data-label="<?php esc_attr_e( 'Size', 'autoloadfix' ); ?>"><strong><?php echo esc_html( size_format( $row['option_size'], 1 ) ); ?></strong></td>
								<td data-label="<?php esc_attr_e( 'Likely owner', 'autoloadfix' ); ?>"><?php echo esc_html( $row['owner']['label'] ); ?><div class="autoloadfix-muted"><?php echo esc_html( $this->owner_meta_label( $row['owner'] ) ); ?></div></td>
								<td data-label="<?php esc_attr_e( 'Assessment', 'autoloadfix' ); ?>"><span class="autoloadfix-pill autoloadfix-pill-<?php echo esc_attr( $row['risk']['level'] ); ?>"><?php echo esc_html( $row['risk']['label'] ); ?></span><div class="autoloadfix-reason"><?php echo esc_html( $row['risk']['reason'] ); ?></div></td>
								<td data-label="<?php esc_attr_e( 'Action', 'autoloadfix' ); ?>"><?php $this->render_option_action( $row ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="autoloadfix-safety-note"><strong><?php esc_html_e( 'Safety first:', 'autoloadfix' ); ?></strong> <?php esc_html_e( 'Disabling autoload does not delete the option value. A restore snapshot is saved before a completed change, but you should still test front-end, checkout, forms, scheduled tasks, and admin workflows afterward.', 'autoloadfix' ); ?></div>
		<?php
	}

	/** Render change history. */
	private function render_history() {
		$rows = $this->snapshot->get_recent( 50 );
		?>
		<div class="autoloadfix-panel">
			<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Change history & restore', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Every completed AutoloadFix change starts with a snapshot of the previous autoload behavior.', 'autoloadfix' ); ?></p></div></div>
			<div class="autoloadfix-table-wrap">
				<table class="widefat striped autoloadfix-table">
					<thead><tr><th><?php esc_html_e( 'Date', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Reason', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Changed', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Before', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'After', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Restore', 'autoloadfix' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No changes have been made yet.', 'autoloadfix' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Date', 'autoloadfix' ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Reason', 'autoloadfix' ); ?>"><?php echo esc_html( $row['reason'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Changed', 'autoloadfix' ); ?>"><?php echo esc_html( number_format_i18n( (int) $row['changed_count'] ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Before', 'autoloadfix' ); ?>"><?php echo esc_html( size_format( (int) $row['total_before'], 1 ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'After', 'autoloadfix' ); ?>"><?php echo esc_html( size_format( (int) $row['total_after'], 1 ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Restore', 'autoloadfix' ); ?>">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="autoloadfix-inline-form">
										<input type="hidden" name="action" value="autoloadfix_restore" />
										<input type="hidden" name="snapshot_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>" />
										<?php wp_nonce_field( 'autoloadfix_restore_' . (int) $row['id'] ); ?>
										<button type="submit" class="button autoloadfix-restore-button"><?php echo ! empty( $row['restored_at'] ) ? esc_html__( 'Restore again', 'autoloadfix' ) : esc_html__( 'Restore snapshot', 'autoloadfix' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/** Render scanner settings. */
	private function render_settings() {
		$settings = $this->scanner->get_settings();
		?>
		<div class="autoloadfix-panel autoloadfix-settings-panel">
			<h2><?php esc_html_e( 'Scanner settings', 'autoloadfix' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="autoloadfix_save_settings" />
				<?php wp_nonce_field( 'autoloadfix_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="large_option_threshold"><?php esc_html_e( 'Large option threshold', 'autoloadfix' ); ?></label></th><td><input class="small-text" type="number" min="10000" step="1000" id="large_option_threshold" name="large_option_threshold" value="<?php echo esc_attr( (int) $settings['large_option_threshold'] ); ?>" /> <?php esc_html_e( 'bytes', 'autoloadfix' ); ?><p class="description"><?php esc_html_e( 'Options at or above this size receive additional review attention.', 'autoloadfix' ); ?></p></td></tr>
					<tr><th scope="row"><label for="health_limit"><?php esc_html_e( 'Health limit', 'autoloadfix' ); ?></label></th><td><input class="regular-text" type="number" min="100000" step="10000" id="health_limit" name="health_limit" value="<?php echo esc_attr( (int) $settings['health_limit'] ); ?>" /> <?php esc_html_e( 'bytes', 'autoloadfix' ); ?><p class="description"><?php esc_html_e( 'Used by the AutoloadFix score and Site Health test.', 'autoloadfix' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Save settings', 'autoloadfix' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Handle disable-autoload request. */
	public function handle_disable() {
		$this->require_manage_options();
		$option_name = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
		check_admin_referer( 'autoloadfix_disable_' . $option_name );

		if ( '' === $option_name ) {
			$this->redirect_with_notice( 'invalid_option' );
		}
		if ( $this->scanner->is_protected( $option_name ) ) {
			$this->redirect_with_notice( 'protected' );
		}

		$record = $this->scanner->get_option_record( $option_name );
		if ( ! is_array( $record ) ) {
			$this->redirect_with_notice( 'missing' );
		}

		$autoload_values = $this->scanner->get_autoload_values();
		if ( ! in_array( $record['autoload'], $autoload_values, true ) ) {
			$this->redirect_with_notice( 'already_off' );
		}
		if ( ! function_exists( 'wp_set_option_autoload_values' ) ) {
			$this->redirect_with_notice( 'api_missing' );
		}

		$before = $this->scanner->get_summary();
		/* translators: %s: WordPress option name whose autoload behavior was changed. */
		$reason = sprintf( __( 'Disabled autoload for %s', 'autoloadfix' ), $option_name );
		$snapshot_id = $this->snapshot->create( array( $option_name => $record['autoload'] ), $reason, $before['total_size'] );
		if ( false === $snapshot_id ) {
			$this->redirect_with_notice( 'snapshot_failed' );
		}

		$result       = wp_set_option_autoload_values( array( $option_name => false ) );
		$after_record = $this->scanner->get_option_record( $option_name );
		$changed      = isset( $result[ $option_name ] ) && true === $result[ $option_name ];
		$is_off       = is_array( $after_record ) && ! in_array( $after_record['autoload'], $autoload_values, true );

		if ( ! $changed && ! $is_off ) {
			$this->snapshot->delete( $snapshot_id );
			$this->redirect_with_notice( 'change_failed' );
		}

		$after = $this->scanner->get_summary();
		$this->snapshot->set_total_after( $snapshot_id, $after['total_size'] );
		$this->redirect_with_notice( 'disabled' );
	}

	/** Handle restore request. */
	public function handle_restore() {
		$this->require_manage_options();
		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
		check_admin_referer( 'autoloadfix_restore_' . $snapshot_id );
		if ( ! $snapshot_id ) {
			$this->redirect_with_notice( 'invalid_snapshot', 'history' );
		}
		if ( is_wp_error( $this->snapshot->restore( $snapshot_id ) ) ) {
			$this->redirect_with_notice( 'restore_failed', 'history' );
		}
		$this->redirect_with_notice( 'restored', 'history' );
	}

	/** Save scanner settings. */
	public function handle_save_settings() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_save_settings' );
		$large = isset( $_POST['large_option_threshold'] ) ? absint( $_POST['large_option_threshold'] ) : 150000;
		$limit = isset( $_POST['health_limit'] ) ? absint( $_POST['health_limit'] ) : 800000;

		update_option(
			'autoloadfix_settings',
			array(
				'large_option_threshold' => max( 10000, min( 5000000, $large ) ),
				'health_limit'           => max( 100000, min( 20000000, $limit ) ),
			),
			false
		);
		$this->redirect_with_notice( 'settings_saved', 'settings' );
	}

	/** Export a metadata-only JSON diagnostic report. */
	public function handle_export() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_export' );
		$report = array(
			'plugin'       => 'AutoloadFix',
			'version'      => AUTOLOADFIX_VERSION,
			'generated_at' => current_time( 'mysql' ),
			'site'         => home_url( '/' ),
			'wordpress'    => get_bloginfo( 'version' ),
			'php'          => PHP_VERSION,
			'summary'      => $this->scanner->get_summary(),
			'options'      => $this->scanner->get_largest_options( 500 ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="autoloadfix-report-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoder escapes report data.
		exit;
	}

	/**
	 * Render a navigation tab.
	 *
	 * @param string $view View key.
	 * @param string $label Label.
	 * @param string $current Current view.
	 */
	private function nav_tab( $view, $label, $current ) {
		$class = $view === $current ? 'nav-tab-active' : '';
		printf(
			'<a class="nav-tab %1$s" href="%2$s">%3$s</a>',
			esc_attr( $class ),
			esc_url( admin_url( 'admin.php?page=autoloadfix&view=' . $view ) ),
			esc_html( $label )
		);
	}

	/** @param array<string,mixed> $row Option row. */
	private function render_option_action( $row ) {
		if ( 'protected' === $row['risk']['level'] ) {
			echo '<span class="autoloadfix-muted">' . esc_html__( 'Locked', 'autoloadfix' ) . '</span>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="autoloadfix-inline-form">
			<input type="hidden" name="action" value="autoloadfix_disable" />
			<input type="hidden" name="option_name" value="<?php echo esc_attr( $row['option_name'] ); ?>" />
			<?php wp_nonce_field( 'autoloadfix_disable_' . $row['option_name'] ); ?>
			<button type="submit" class="button autoloadfix-disable-button"><?php esc_html_e( 'Disable autoload', 'autoloadfix' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render a metric card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $caption Caption.
	 */
	private function metric_card( $label, $value, $caption ) {
		echo '<div class="autoloadfix-metric"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong><small>' . esc_html( $caption ) . '</small></div>';
	}

	/**
	 * Format owner metadata.
	 *
	 * @param array<string,mixed> $owner Owner metadata.
	 * @return string
	 */
	private function owner_meta_label( $owner ) {
		$status     = isset( $owner['status'] ) ? $owner['status'] : 'unknown';
		$type       = isset( $owner['type'] ) ? $owner['type'] : 'unknown';
		$confidence = isset( $owner['confidence'] ) ? absint( $owner['confidence'] ) : 0;

		if ( 'protected' === $status ) {
			$label = __( 'Protected', 'autoloadfix' );
		} elseif ( 'theme' === $type && 'active' === $status ) {
			$label = __( 'Active theme', 'autoloadfix' );
		} elseif ( 'plugin' === $type && 'active' === $status ) {
			$label = __( 'Active plugin', 'autoloadfix' );
		} elseif ( 'plugin' === $type && 'inactive' === $status ) {
			$label = __( 'Inactive plugin', 'autoloadfix' );
		} else {
			$label = __( 'Unknown', 'autoloadfix' );
		}

		if ( $confidence > 0 && 'protected' !== $status ) {
			/* translators: 1: Owner status label. 2: Ownership confidence percentage. */
			return sprintf( __( '%1$s · %2$d%% confidence', 'autoloadfix' ), $label, $confidence );
		}
		return $label;
	}

	/** Require administrator capability. */
	private function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AutoloadFix.', 'autoloadfix' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirect to an AutoloadFix screen with a notice code.
	 *
	 * @param string $code Notice code.
	 * @param string $view Target view.
	 */
	private function redirect_with_notice( $code, $view = 'overview' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'autoloadfix',
					'view'            => sanitize_key( $view ),
					'autoloadfix_msg' => sanitize_key( $code ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Render action notice. */
	private function render_notice() {
		$code = isset( $_GET['autoloadfix_msg'] ) ? sanitize_key( wp_unslash( $_GET['autoloadfix_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice code after a nonce-protected redirect.
		$messages = array(
			'disabled'         => array( 'success', __( 'Autoloading was disabled and a restore snapshot was saved.', 'autoloadfix' ) ),
			'restored'         => array( 'success', __( 'Snapshot autoload behavior was restored.', 'autoloadfix' ) ),
			'settings_saved'   => array( 'success', __( 'Settings saved.', 'autoloadfix' ) ),
			'protected'        => array( 'error', __( 'That option is protected and AutoloadFix will not change it.', 'autoloadfix' ) ),
			'missing'          => array( 'error', __( 'The selected option no longer exists.', 'autoloadfix' ) ),
			'already_off'      => array( 'warning', __( 'That option is already not autoloaded.', 'autoloadfix' ) ),
			'snapshot_failed'  => array( 'error', __( 'The safety snapshot could not be created, so no change was made.', 'autoloadfix' ) ),
			'change_failed'    => array( 'error', __( 'WordPress could not update the autoload value.', 'autoloadfix' ) ),
			'api_missing'      => array( 'error', __( 'The required WordPress autoload API is unavailable.', 'autoloadfix' ) ),
			'invalid_option'   => array( 'error', __( 'Invalid option name.', 'autoloadfix' ) ),
			'invalid_snapshot' => array( 'error', __( 'Invalid snapshot.', 'autoloadfix' ) ),
			'restore_failed'   => array( 'error', __( 'The snapshot could not be restored.', 'autoloadfix' ) ),
		);

		if ( isset( $messages[ $code ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $code ][0] ),
				esc_html( $messages[ $code ][1] )
			);
		}
	}
}
