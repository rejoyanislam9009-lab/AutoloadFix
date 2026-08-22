<?php
/**
 * Professional monitoring actions and safety controls.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Advanced_Admin {
	use AutoloadFix_Advanced_Render;

	/** @var AutoloadFix_Scanner */
	private $scanner;
	/** @var AutoloadFix_Snapshot */
	private $snapshot;
	/** @var AutoloadFix_Audit */
	private $audit;

	/** @param AutoloadFix_Scanner $scanner Scanner. @param AutoloadFix_Snapshot $snapshot Snapshot. @param AutoloadFix_Audit $audit Audit. */
	public function __construct( AutoloadFix_Scanner $scanner, AutoloadFix_Snapshot $snapshot, AutoloadFix_Audit $audit ) {
		$this->scanner  = $scanner;
		$this->snapshot = $snapshot;
		$this->audit    = $audit;
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'admin_post_autoloadfix_run_audit', array( $this, 'handle_run_audit' ) );
		add_action( 'admin_post_autoloadfix_toggle_watch', array( $this, 'handle_toggle_watch' ) );
		add_action( 'admin_post_autoloadfix_toggle_ignore', array( $this, 'handle_toggle_ignore' ) );
		add_action( 'admin_post_autoloadfix_save_advanced', array( $this, 'handle_save_advanced' ) );
		add_action( 'admin_post_autoloadfix_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_autoloadfix_dismiss_growth', array( $this, 'handle_dismiss_growth' ) );
		add_action( 'admin_post_autoloadfix_disable', array( $this, 'guard_disable' ), 1 );
		add_action( 'admin_post_autoloadfix_restore', array( $this, 'guard_restore' ), 1 );
		add_filter( 'pre_update_option_autoloadfix_settings', array( $this, 'preserve_advanced_settings' ), 10, 3 );
	}

	/** Register submenu. */
	public function register_submenu() {
		add_submenu_page( 'autoloadfix', __( 'AutoloadFix Monitor & Tools', 'autoloadfix' ), __( 'Monitor & Tools', 'autoloadfix' ), 'manage_options', 'autoloadfix-advanced', array( $this, 'render_page' ) );
	}

	/** @param string $hook Admin hook. */
	public function enqueue_assets( $hook ) {
		if ( 'autoloadfix_page_autoloadfix-advanced' !== $hook && 'toplevel_page_autoloadfix' !== $hook ) {
			return;
		}
		if ( 'autoloadfix_page_autoloadfix-advanced' === $hook ) {
			wp_enqueue_style( 'autoloadfix-admin', AUTOLOADFIX_URL . 'assets/css/admin.css', array(), AUTOLOADFIX_VERSION );
		}
		wp_enqueue_style( 'autoloadfix-advanced', AUTOLOADFIX_URL . 'assets/css/advanced.css', array( 'autoloadfix-admin' ), AUTOLOADFIX_VERSION );
		wp_enqueue_script( 'autoloadfix-advanced', AUTOLOADFIX_URL . 'assets/js/advanced.js', array(), AUTOLOADFIX_VERSION, true );
		$settings = $this->scanner->get_settings();
		wp_localize_script( 'autoloadfix-advanced', 'AutoloadFixAdvanced', array( 'readOnly' => ! empty( $settings['read_only'] ), 'shown' => __( 'shown', 'autoloadfix' ), 'copied' => __( 'Diagnostics copied', 'autoloadfix' ) ) );
	}

	/** Preserve professional settings when the original settings screen saves. */
	public function preserve_advanced_settings( $new_value, $old_value, $option ) {
		if ( 'autoloadfix_settings' !== $option || ! is_array( $new_value ) || ! is_array( $old_value ) ) {
			return $new_value;
		}
		foreach ( array( 'audit_interval', 'growth_alert_percent', 'history_retention', 'read_only', 'custom_protected' ) as $key ) {
			if ( ! array_key_exists( $key, $new_value ) && array_key_exists( $key, $old_value ) ) {
				$new_value[ $key ] = $old_value[ $key ];
			}
		}
		return $new_value;
	}

	/** Guard the original disable action after validating its nonce. */
	public function guard_disable() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$name  = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $name || '' === $nonce || ! wp_verify_nonce( $nonce, 'autoloadfix_disable_' . $name ) ) {
			return;
		}
		$settings = $this->scanner->get_settings();
		if ( ! empty( $settings['read_only'] ) || $this->scanner->is_ignored( $name ) ) {
			$this->redirect( ! empty( $settings['read_only'] ) ? 'read_only' : 'ignored' );
		}
	}

	/** Guard the original restore action after validating its nonce. */
	public function guard_restore() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
		$nonce       = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! $snapshot_id || '' === $nonce || ! wp_verify_nonce( $nonce, 'autoloadfix_restore_' . $snapshot_id ) ) {
			return;
		}
		$settings = $this->scanner->get_settings();
		if ( ! empty( $settings['read_only'] ) ) {
			$this->redirect( 'read_only' );
		}
	}

	/** Run manual audit. */
	public function handle_run_audit() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_run_audit' );
		$result = $this->audit->record( 'manual' );
		$this->redirect( is_wp_error( $result ) ? 'audit_failed' : 'audit_saved' );
	}

	/** Toggle watched state. */
	public function handle_toggle_watch() {
		$this->require_manage_options();
		$name = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
		check_admin_referer( 'autoloadfix_toggle_watch_' . $name );
		if ( ! $this->scanner->get_option_record( $name ) ) {
			$this->redirect( 'invalid' );
		}
		$state = isset( $_POST['watched'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['watched'] ) );
		$this->scanner->set_watched( $name, $state );
		$this->redirect( $state ? 'watched' : 'unwatched' );
	}

	/** Toggle ignored state. */
	public function handle_toggle_ignore() {
		$this->require_manage_options();
		$name = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
		check_admin_referer( 'autoloadfix_toggle_ignore_' . $name );
		if ( ! $this->scanner->get_option_record( $name ) || $this->scanner->is_protected( $name ) ) {
			$this->redirect( 'invalid' );
		}
		$state = isset( $_POST['ignored'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ignored'] ) );
		$this->scanner->set_ignored( $name, $state );
		$this->redirect( $state ? 'ignored' : 'unignored' );
	}

	/** Save professional settings. */
	public function handle_save_advanced() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_save_advanced' );
		$current   = $this->scanner->get_settings();
		$interval  = isset( $_POST['audit_interval'] ) ? sanitize_key( wp_unslash( $_POST['audit_interval'] ) ) : 'daily';
		$interval  = in_array( $interval, array( 'daily', 'weekly', 'disabled' ), true ) ? $interval : 'daily';
		$growth    = isset( $_POST['growth_alert_percent'] ) ? absint( wp_unslash( $_POST['growth_alert_percent'] ) ) : 25;
		$retention = isset( $_POST['history_retention'] ) ? absint( wp_unslash( $_POST['history_retention'] ) ) : 30;
		$custom    = isset( $_POST['custom_protected'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_protected'] ) ) : '';
		$clean     = array();
		$lines     = preg_split( '/\r\n|\r|\n/', $custom );
		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$line = sanitize_text_field( trim( $line ) );
				if ( '' !== $line ) {
					$clean[] = $line;
				}
			}
		}
		$current['audit_interval']       = $interval;
		$current['growth_alert_percent'] = max( 1, min( 500, $growth ) );
		$current['history_retention']    = max( 7, min( 365, $retention ) );
		$current['read_only']            = isset( $_POST['read_only'] ) ? 1 : 0;
		$current['custom_protected']     = implode( "\n", array_values( array_unique( $clean ) ) );
		update_option( 'autoloadfix_settings', $current, false );
		$this->audit->sync_schedule();
		$this->redirect( 'settings_saved', 'settings' );
	}

	/** Export metadata-only CSV. */
	public function handle_export_csv() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_export_csv' );
		$rows = $this->scanner->get_largest_options( 250 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename="autoloadfix-review-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( $out ) {
			fputcsv( $out, array( 'option', 'bytes', 'impact_percent', 'autoload', 'owner', 'owner_status', 'confidence', 'assessment', 'watched', 'ignored' ) );
			foreach ( $rows as $row ) {
				fputcsv( $out, array( $row['option_name'], $row['option_size'], $row['impact_percent'], $row['autoload'], $row['owner']['label'], $row['owner']['status'], $row['owner']['confidence'], $row['risk']['label'], $row['watched'] ? 'yes' : 'no', $row['ignored'] ? 'yes' : 'no' ) );
			}
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		exit;
	}

	/** Dismiss growth notice. */
	public function handle_dismiss_growth() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_dismiss_growth' );
		delete_option( 'autoloadfix_growth_notice' );
		$this->redirect( 'dismissed' );
	}

	/** Render growth notice. */
	private function render_growth_notice() {
		$notice = get_option( 'autoloadfix_growth_notice', array() );
		if ( ! is_array( $notice ) || empty( $notice['delta_percent'] ) ) {
			return;
		}
		?>
		<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Autoload growth detected:', 'autoloadfix' ); ?></strong>
		<?php
		/* translators: 1: Percentage increase. 2: Human-readable byte increase. */
		echo esc_html( sprintf( __( '%1$s%% increase (%2$s) since the previous audit.', 'autoloadfix' ), number_format_i18n( (float) $notice['delta_percent'], 2 ), size_format( (int) $notice['delta_bytes'], 1 ) ) );
		?>
		</p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="autoloadfix_dismiss_growth" /><?php wp_nonce_field( 'autoloadfix_dismiss_growth' ); ?><button class="button-link" type="submit"><?php esc_html_e( 'Dismiss', 'autoloadfix' ); ?></button></form></div>
		<?php
	}

	/** Render action notice. */
	private function render_notice() {
		$key = isset( $_GET['af_adv_notice'] ) ? sanitize_key( wp_unslash( $_GET['af_adv_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map = array(
			'audit_saved'    => array( 'success', __( 'Audit point saved.', 'autoloadfix' ) ),
			'audit_failed'   => array( 'error', __( 'Audit point could not be saved.', 'autoloadfix' ) ),
			'watched'        => array( 'success', __( 'Option added to the watch list.', 'autoloadfix' ) ),
			'unwatched'      => array( 'success', __( 'Option removed from the watch list.', 'autoloadfix' ) ),
			'ignored'        => array( 'success', __( 'Option is now ignored in recommendations.', 'autoloadfix' ) ),
			'unignored'      => array( 'success', __( 'Option returned to recommendations.', 'autoloadfix' ) ),
			'settings_saved' => array( 'success', __( 'Monitoring settings saved.', 'autoloadfix' ) ),
			'read_only'      => array( 'warning', __( 'Read-only safe mode blocked that autoload change.', 'autoloadfix' ) ),
			'invalid'        => array( 'error', __( 'That option cannot be changed in this review list.', 'autoloadfix' ) ),
			'dismissed'      => array( 'success', __( 'Growth alert dismissed.', 'autoloadfix' ) ),
		);
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) );
		}
	}

	/** @param string $notice Notice. @param string $section Section. */
	private function redirect( $notice, $section = 'monitor' ) {
		$url = add_query_arg( array( 'page' => 'autoloadfix-advanced', 'section' => sanitize_key( $section ), 'af_adv_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/** Require administrator capability. */
	private function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AutoloadFix.', 'autoloadfix' ), '', array( 'response' => 403 ) );
		}
	}
}
