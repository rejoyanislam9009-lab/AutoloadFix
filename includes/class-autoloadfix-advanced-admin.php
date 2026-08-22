<?php
/**
 * Professional monitoring, diagnostics, and safety controls.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Advanced_Admin {
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
		wp_enqueue_style( 'autoloadfix-advanced', AUTOLOADFIX_URL . 'assets/css/advanced.css', array( 'autoloadfix-admin' ), AUTOLOADFIX_VERSION );
		wp_enqueue_script( 'autoloadfix-advanced', AUTOLOADFIX_URL . 'assets/js/advanced.js', array(), AUTOLOADFIX_VERSION, true );
		$settings = $this->scanner->get_settings();
		wp_localize_script( 'autoloadfix-advanced', 'AutoloadFixAdvanced', array( 'readOnly' => ! empty( $settings['read_only'] ), 'shown' => __( 'shown', 'autoloadfix' ), 'copied' => __( 'Diagnostics copied', 'autoloadfix' ) ) );
	}

	/** Preserve v1.1 keys if the legacy settings screen saves its original fields. */
	public function preserve_advanced_settings( $new_value, $old_value, $option ) {
		if ( 'autoloadfix_settings' !== $option || ! is_array( $new_value ) || ! is_array( $old_value ) ) {
			return $new_value;
		}
		foreach ( array( 'audit_interval', 'growth_alert_percent', 'history_retention', 'read_only', 'custom_protected' ) as $key ) {
			if ( ! array_key_exists( $key, $new_value ) && array_key_exists( $key, $old_value ) ) {
				$new_value[ $key ] = $old_value[ $key ];
			}
		return $new_value;
	}

	/** Render page. */
	public function render_page() {
		$this->require_manage_options();
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'monitor';
		$section = in_array( $section, array( 'monitor', 'diagnostics', 'settings' ), true ) ? $section : 'monitor';
		?>
		<div class="wrap autoloadfix-wrap autoloadfix-advanced-wrap">
			<div class="autoloadfix-header"><div><div class="autoloadfix-eyebrow"><?php esc_html_e( 'AUTOLOADFIX PROFESSIONAL TOOLKIT', 'autoloadfix' ); ?></div><h1><?php esc_html_e( 'Monitor & Tools', 'autoloadfix' ); ?></h1><p><?php esc_html_e( 'Track autoload growth, search and classify entries, maintain watch/ignore lists, and configure safety controls.', 'autoloadfix' ); ?></p></div><div class="autoloadfix-version">v<?php echo esc_html( AUTOLOADFIX_VERSION ); ?></div></div>
			<?php $this->render_notice(); ?>
			<?php $this->render_growth_notice(); ?>
			<nav class="nav-tab-wrapper autoloadfix-tabs"><?php $this->tab( 'monitor', __( 'Monitor & Trends', 'autoloadfix' ), $section ); ?><?php $this->tab( 'diagnostics', __( 'Diagnostics', 'autoloadfix' ), $section ); ?><?php $this->tab( 'settings', __( 'Safety Settings', 'autoloadfix' ), $section ); ?></nav>
			<?php
			if ( 'diagnostics' === $section ) {
				$this->render_diagnostics();
			} elseif ( 'settings' === $section ) {
				$this->render_settings();
			} else {
				$this->render_monitor();
			}
			?>
		</div>
		<?php
	}

	/** Monitor, trend, and review workspace. */
	private function render_monitor() {
		$summary = $this->scanner->get_summary();
		$options = $this->scanner->get_largest_options( 250 );
		$audits  = $this->audit->get_recent( 30 );
		$latest  = $this->audit->get_latest();
		$next    = $this->audit->get_next_scheduled();
		$max     = 1;
		foreach ( $audits as $audit ) {
			$max = max( $max, (int) $audit['total_size'] );
		}
		?>
		<div class="autoloadfix-grid autoloadfix-metrics">
			<?php $this->metric( __( 'Current autoload', 'autoloadfix' ), size_format( $summary['total_size'], 1 ), sprintf( __( 'Score %d/100', 'autoloadfix' ), $summary['score'] ) ); ?>
			<?php $this->metric( __( 'Last audit delta', 'autoloadfix' ), $latest ? $this->delta( (int) $latest['delta_bytes'] ) : '—', $latest ? sprintf( __( '%s%% from previous audit', 'autoloadfix' ), number_format_i18n( (float) $latest['delta_percent'], 2 ) ) : __( 'No baseline yet', 'autoloadfix' ) ); ?>
			<?php $this->metric( __( 'Watched options', 'autoloadfix' ), number_format_i18n( count( $this->scanner->get_watched_options() ) ), __( 'Tracked for review', 'autoloadfix' ) ); ?>
			<?php $this->metric( __( 'Next audit', 'autoloadfix' ), $next ? wp_date( get_option( 'date_format' ), $next ) : __( 'Disabled', 'autoloadfix' ), $next ? wp_date( get_option( 'time_format' ), $next ) : __( 'Configure scheduling', 'autoloadfix' ) ); ?>
		</div>

		<div class="autoloadfix-panel">
			<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Autoload trend', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Audit points store totals and counts only. Option values are never stored.', 'autoloadfix' ); ?></p></div><?php $this->audit_form(); ?></div>
			<?php if ( $audits ) : ?><div class="autoloadfix-advanced-trend"><?php foreach ( array_reverse( $audits ) as $audit ) : $height = max( 4, (int) round( ( (int) $audit['total_size'] / $max ) * 100 ) ); ?><span style="height:<?php echo esc_attr( $height ); ?>%" title="<?php echo esc_attr( mysql2date( 'Y-m-d H:i', $audit['created_at'] ) . ' · ' . size_format( (int) $audit['total_size'], 1 ) ); ?>"></span><?php endforeach; ?></div><?php else : ?><p><?php esc_html_e( 'Run an audit to establish the first baseline.', 'autoloadfix' ); ?></p><?php endif; ?>
			<div class="autoloadfix-table-wrap"><table class="widefat striped autoloadfix-table"><thead><tr><th><?php esc_html_e( 'Date', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Trigger', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Total', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Score', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Delta', 'autoloadfix' ); ?></th></tr></thead><tbody>
			<?php if ( ! $audits ) : ?><tr><td colspan="5"><?php esc_html_e( 'No audit history yet.', 'autoloadfix' ); ?></td></tr><?php else : foreach ( $audits as $audit ) : ?><tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $audit['created_at'] ) ); ?></td><td><?php echo esc_html( ucfirst( $audit['trigger_type'] ) ); ?></td><td><?php echo esc_html( size_format( (int) $audit['total_size'], 1 ) ); ?></td><td><?php echo esc_html( (int) $audit['score'] ); ?>/100</td><td><?php echo esc_html( $this->delta( (int) $audit['delta_bytes'] ) ); ?> <span class="autoloadfix-muted"><?php echo esc_html( number_format_i18n( (float) $audit['delta_percent'], 2 ) ); ?>%</span></td></tr><?php endforeach; endif; ?>
			</tbody></table></div>
		</div>

		<div class="autoloadfix-panel">
			<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Option review workspace', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Search the largest 250 autoloaded entries. Watch important entries or ignore known noise; use Overview for snapshot-backed autoload changes.', 'autoloadfix' ); ?></p></div><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=autoloadfix_export_csv' ), 'autoloadfix_export_csv' ) ); ?>"><?php esc_html_e( 'Export CSV', 'autoloadfix' ); ?></a></div>
			<div class="autoloadfix-advanced-toolbar"><input type="search" id="autoloadfix-advanced-search" placeholder="<?php esc_attr_e( 'Search option or owner…', 'autoloadfix' ); ?>" /><select id="autoloadfix-advanced-risk"><option value="all"><?php esc_html_e( 'All assessments', 'autoloadfix' ); ?></option><option value="candidate"><?php esc_html_e( 'Candidates', 'autoloadfix' ); ?></option><option value="review"><?php esc_html_e( 'Review', 'autoloadfix' ); ?></option><option value="protected"><?php esc_html_e( 'Protected', 'autoloadfix' ); ?></option><option value="ignored"><?php esc_html_e( 'Ignored', 'autoloadfix' ); ?></option></select><label><input type="checkbox" id="autoloadfix-advanced-watched" /> <?php esc_html_e( 'Watched only', 'autoloadfix' ); ?></label><span id="autoloadfix-advanced-count" class="autoloadfix-muted"></span></div>
			<div class="autoloadfix-table-wrap"><table id="autoloadfix-advanced-table" class="widefat striped autoloadfix-table"><thead><tr><th><?php esc_html_e( 'Option', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Size / impact', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Likely owner', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Assessment', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Review tools', 'autoloadfix' ); ?></th></tr></thead><tbody>
			<?php foreach ( $options as $row ) : $search = strtolower( $row['option_name'] . ' ' . $row['owner']['label'] ); ?><tr class="autoloadfix-advanced-option" data-search="<?php echo esc_attr( $search ); ?>" data-risk="<?php echo esc_attr( $row['risk']['level'] ); ?>" data-watched="<?php echo $row['watched'] ? '1' : '0'; ?>"><td><code><?php echo esc_html( $row['option_name'] ); ?></code><div class="autoloadfix-muted"><?php echo esc_html( sprintf( __( 'DB autoload: %s', 'autoloadfix' ), $row['autoload'] ) ); ?></div></td><td><strong><?php echo esc_html( size_format( $row['option_size'], 1 ) ); ?></strong><div class="autoloadfix-muted"><?php echo esc_html( number_format_i18n( (float) $row['impact_percent'], 1 ) ); ?>% <?php esc_html_e( 'of current autoload', 'autoloadfix' ); ?></div></td><td><?php echo esc_html( $row['owner']['label'] ); ?><div class="autoloadfix-muted"><?php echo esc_html( (int) $row['owner']['confidence'] ); ?>% <?php esc_html_e( 'confidence', 'autoloadfix' ); ?></div></td><td><span class="autoloadfix-pill autoloadfix-pill-<?php echo esc_attr( $row['risk']['level'] ); ?>"><?php echo esc_html( $row['risk']['label'] ); ?></span><div class="autoloadfix-reason"><?php echo esc_html( $row['risk']['reason'] ); ?></div></td><td><div class="autoloadfix-advanced-actions"><?php $this->toggle_form( 'autoloadfix_toggle_watch', $row['option_name'], 'watched', $row['watched'], $row['watched'] ? __( 'Unwatch', 'autoloadfix' ) : __( 'Watch', 'autoloadfix' ) ); ?><?php if ( 'protected' !== $row['risk']['level'] ) : $this->toggle_form( 'autoloadfix_toggle_ignore', $row['option_name'], 'ignored', $row['ignored'], $row['ignored'] ? __( 'Unignore', 'autoloadfix' ) : __( 'Ignore', 'autoloadfix' ) ); endif; ?></div></td></tr><?php endforeach; ?>
			<tr id="autoloadfix-advanced-empty" class="autoloadfix-advanced-empty" hidden><td colspan="5"><?php esc_html_e( 'No options match the current filters.', 'autoloadfix' ); ?></td></tr>
			</tbody></table></div>
		</div>
		<?php
	}

	/** Diagnostics page. */
	private function render_diagnostics() {
		$diag      = $this->scanner->get_diagnostics();
		$breakdown = $this->scanner->get_autoload_breakdown();
		$copy      = wp_json_encode( array( 'plugin' => 'AutoloadFix ' . AUTOLOADFIX_VERSION, 'diagnostics' => $diag, 'settings' => $this->scanner->get_settings() ) );
		?>
		<div class="autoloadfix-panel"><div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Environment diagnostics', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Safe metadata only; option values are never included.', 'autoloadfix' ); ?></p></div><button type="button" id="autoloadfix-advanced-copy" class="button" data-copy="<?php echo esc_attr( $copy ); ?>"><?php esc_html_e( 'Copy diagnostics', 'autoloadfix' ); ?></button></div><div class="autoloadfix-advanced-diagnostics"><?php foreach ( $diag as $key => $value ) : ?><div><span><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></span><strong><?php echo esc_html( is_numeric( $value ) && false !== strpos( $key, 'bytes' ) ? size_format( (int) $value, 1 ) : $value ); ?></strong></div><?php endforeach; ?></div></div>
		<div class="autoloadfix-panel"><h2><?php esc_html_e( 'Database autoload value breakdown', 'autoloadfix' ); ?></h2><div class="autoloadfix-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Raw value', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Options', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Data size', 'autoloadfix' ); ?></th></tr></thead><tbody><?php foreach ( $breakdown as $row ) : ?><tr><td><code><?php echo esc_html( $row['autoload'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( (int) $row['option_count'] ) ); ?></td><td><?php echo esc_html( size_format( (int) $row['total_size'], 1 ) ); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
		<div class="autoloadfix-advanced-note"><strong><?php esc_html_e( 'WP-CLI:', 'autoloadfix' ); ?></strong> <code>wp autoloadfix status</code> · <code>wp autoloadfix top --limit=20</code> · <code>wp autoloadfix audit</code></div>
		<?php
	}

	/** Advanced settings. */
	private function render_settings() {
		$s = $this->scanner->get_settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="autoloadfix_save_advanced" /><?php wp_nonce_field( 'autoloadfix_save_advanced' ); ?><div class="autoloadfix-advanced-settings"><div class="autoloadfix-panel"><h2><?php esc_html_e( 'Monitoring', 'autoloadfix' ); ?></h2><table class="form-table" role="presentation"><tr><th><label for="audit_interval"><?php esc_html_e( 'Audit schedule', 'autoloadfix' ); ?></label></th><td><select name="audit_interval" id="audit_interval"><option value="daily" <?php selected( $s['audit_interval'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'autoloadfix' ); ?></option><option value="weekly" <?php selected( $s['audit_interval'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'autoloadfix' ); ?></option><option value="disabled" <?php selected( $s['audit_interval'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'autoloadfix' ); ?></option></select></td></tr><tr><th><label for="growth_alert_percent"><?php esc_html_e( 'Growth alert', 'autoloadfix' ); ?></label></th><td><input class="small-text" type="number" min="1" max="500" name="growth_alert_percent" id="growth_alert_percent" value="<?php echo esc_attr( (int) $s['growth_alert_percent'] ); ?>" />%</td></tr><tr><th><label for="history_retention"><?php esc_html_e( 'Audit retention', 'autoloadfix' ); ?></label></th><td><input class="small-text" type="number" min="7" max="365" name="history_retention" id="history_retention" value="<?php echo esc_attr( (int) $s['history_retention'] ); ?>" /> <?php esc_html_e( 'days', 'autoloadfix' ); ?></td></tr></table></div><div class="autoloadfix-panel"><h2><?php esc_html_e( 'Safety', 'autoloadfix' ); ?></h2><p><label><input type="checkbox" name="read_only" value="1" <?php checked( ! empty( $s['read_only'] ) ); ?> /> <strong><?php esc_html_e( 'Read-only safe mode', 'autoloadfix' ); ?></strong></label></p><p class="description"><?php esc_html_e( 'Blocks AutoloadFix disable and restore actions while keeping scans, reports, audits, watch lists, and diagnostics available.', 'autoloadfix' ); ?></p><p><label for="custom_protected"><strong><?php esc_html_e( 'Custom protected options', 'autoloadfix' ); ?></strong></label></p><textarea class="large-text code" rows="8" name="custom_protected" id="custom_protected" placeholder="my_critical_option&#10;another_critical_option"><?php echo esc_textarea( $s['custom_protected'] ); ?></textarea><p class="description"><?php esc_html_e( 'One exact option name per line. Protected options are locked from AutoloadFix changes.', 'autoloadfix' ); ?></p></div></div><?php submit_button( __( 'Save monitoring & safety settings', 'autoloadfix' ) ); ?></form>
		<?php
	}

	/** @param string $action Action. @param string $option_name Option. @param string $field Field. @param bool $current State. @param string $label Label. */
	private function toggle_form( $action, $option_name, $field, $current, $label ) {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="autoloadfix-inline-form autoloadfix-advanced-inline"><input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" /><input type="hidden" name="option_name" value="<?php echo esc_attr( $option_name ); ?>" /><input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo $current ? '0' : '1'; ?>" /><?php wp_nonce_field( $action . '_' . $option_name ); ?><button type="submit" class="button button-small"><?php echo esc_html( $label ); ?></button></form><?php
	}

	/** Audit button. */
	private function audit_form() {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="autoloadfix-inline-form"><input type="hidden" name="action" value="autoloadfix_run_audit" /><?php wp_nonce_field( 'autoloadfix_run_audit' ); ?><button type="submit" class="button button-primary"><?php esc_html_e( 'Run audit now', 'autoloadfix' ); ?></button></form><?php
	}

	/** Guard old disable action. */
	public function guard_disable() {
		$settings = $this->scanner->get_settings();
		$name = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
		if ( ! empty( $settings['read_only'] ) || $this->scanner->is_ignored( $name ) ) {
			$this->redirect( ! empty( $settings['read_only'] ) ? 'read_only' : 'ignored' );
		}
	}

	/** Guard old restore action. */
	public function guard_restore() {
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

	/** Toggle watch. */
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

	/** Toggle ignore. */
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

	/** Save advanced settings. */
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
				if ( '' !== $line ) { $clean[] = $line; }
			}
		}
		$current['audit_interval'] = $interval;
		$current['growth_alert_percent'] = max( 1, min( 500, $growth ) );
		$current['history_retention'] = max( 7, min( 365, $retention ) );
		$current['read_only'] = isset( $_POST['read_only'] ) ? 1 : 0;
		$current['custom_protected'] = implode( "\n", array_values( array_unique( $clean ) ) );
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

	/** Dismiss growth alert. */
	public function handle_dismiss_growth() {
		$this->require_manage_options();
		check_admin_referer( 'autoloadfix_dismiss_growth' );
		delete_option( 'autoloadfix_growth_notice' );
		$this->redirect( 'dismissed' );
	}

	/** Render growth alert. */
	private function render_growth_notice() {
		$notice = get_option( 'autoloadfix_growth_notice', array() );
		if ( ! is_array( $notice ) || empty( $notice['delta_percent'] ) ) { return; }
		?><div class="notice notice-warning"><p><strong><?php esc_html_e( 'Autoload growth detected:', 'autoloadfix' ); ?></strong> <?php echo esc_html( sprintf( __( '%1$s%% increase (%2$s) since the previous audit.', 'autoloadfix' ), number_format_i18n( (float) $notice['delta_percent'], 2 ), size_format( (int) $notice['delta_bytes'], 1 ) ) ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="autoloadfix_dismiss_growth" /><?php wp_nonce_field( 'autoloadfix_dismiss_growth' ); ?><button class="button-link" type="submit"><?php esc_html_e( 'Dismiss', 'autoloadfix' ); ?></button></form></div><?php
	}

	/** Render notice. */
	private function render_notice() {
		$key = isset( $_GET['af_adv_notice'] ) ? sanitize_key( wp_unslash( $_GET['af_adv_notice'] ) ) : '';
		$map = array( 'audit_saved' => array( 'success', __( 'Audit point saved.', 'autoloadfix' ) ), 'audit_failed' => array( 'error', __( 'Audit point could not be saved.', 'autoloadfix' ) ), 'watched' => array( 'success', __( 'Option added to the watch list.', 'autoloadfix' ) ), 'unwatched' => array( 'success', __( 'Option removed from the watch list.', 'autoloadfix' ) ), 'ignored' => array( 'success', __( 'Option is now ignored in recommendations.', 'autoloadfix' ) ), 'unignored' => array( 'success', __( 'Option returned to recommendations.', 'autoloadfix' ) ), 'settings_saved' => array( 'success', __( 'Monitoring settings saved.', 'autoloadfix' ) ), 'read_only' => array( 'warning', __( 'Read-only safe mode blocked that autoload change.', 'autoloadfix' ) ), 'invalid' => array( 'error', __( 'That option cannot be changed in this review list.', 'autoloadfix' ) ), 'dismissed' => array( 'success', __( 'Growth alert dismissed.', 'autoloadfix' ) ) );
		if ( isset( $map[ $key ] ) ) { printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $key ][0] ), esc_html( $map[ $key ][1] ) ); }
	}

	/** @param string $section Section. @param string $label Label. @param string $current Current. */
	private function tab( $section, $label, $current ) {
		$url = add_query_arg( array( 'page' => 'autoloadfix-advanced', 'section' => $section ), admin_url( 'admin.php' ) );
		printf( '<a class="nav-tab %1$s" href="%2$s">%3$s</a>', $current === $section ? 'nav-tab-active' : '', esc_url( $url ), esc_html( $label ) );
	}

	/** @param string $title Title. @param string $value Value. @param string $note Note. */
	private function metric( $title, $value, $note ) { echo '<div class="autoloadfix-metric"><span>' . esc_html( $title ) . '</span><strong>' . esc_html( $value ) . '</strong><small>' . esc_html( $note ) . '</small></div>'; }

	/** @param int $bytes Signed bytes. @return string */
	private function delta( $bytes ) { return 0 === $bytes ? '0 B' : ( $bytes > 0 ? '+' : '-' ) . size_format( abs( $bytes ), 1 ); }

	/** @param string $notice Notice. @param string $section Section. */
	private function redirect( $notice, $section = 'monitor' ) {
		$url = add_query_arg( array( 'page' => 'autoloadfix-advanced', 'section' => sanitize_key( $section ), 'af_adv_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/** Require capability. */
	private function require_manage_options() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to manage AutoloadFix.', 'autoloadfix' ), '', array( 'response' => 403 ) ); }
	}
}
