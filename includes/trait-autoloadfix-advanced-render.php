<?php
/**
 * Rendering methods for the professional AutoloadFix tools.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AutoloadFix_Advanced_Render {
	/** Render professional tools page. */
	public function render_page() {
		$this->require_manage_options();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector; no data is changed.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'monitor';
		$section = in_array( $section, array( 'monitor', 'diagnostics', 'settings' ), true ) ? $section : 'monitor';
		?>
		<div class="wrap autoloadfix-wrap autoloadfix-advanced-wrap">
			<div class="autoloadfix-header">
				<div><div class="autoloadfix-eyebrow"><?php esc_html_e( 'AUTOLOADFIX PROFESSIONAL TOOLKIT', 'autoloadfix' ); ?></div><h1><?php esc_html_e( 'Monitor & Tools', 'autoloadfix' ); ?></h1><p><?php esc_html_e( 'Track autoload growth, search and classify entries, maintain watch/ignore lists, and configure safety controls.', 'autoloadfix' ); ?></p></div>
				<div class="autoloadfix-version">v<?php echo esc_html( AUTOLOADFIX_VERSION ); ?></div>
			</div>
			<?php $this->render_notice(); ?>
			<?php $this->render_growth_notice(); ?>
			<nav class="nav-tab-wrapper autoloadfix-tabs" aria-label="<?php esc_attr_e( 'AutoloadFix professional sections', 'autoloadfix' ); ?>">
				<?php $this->tab( 'monitor', __( 'Monitor & Trends', 'autoloadfix' ), $section ); ?>
				<?php $this->tab( 'diagnostics', __( 'Diagnostics', 'autoloadfix' ), $section ); ?>
				<?php $this->tab( 'settings', __( 'Safety Settings', 'autoloadfix' ), $section ); ?>
			</nav>
			<?php
			if ( 'diagnostics' === $section ) {
				$this->render_diagnostics();
			} elseif ( 'settings' === $section ) {
				$this->render_advanced_settings();
			} else {
				$this->render_monitor();
			}
			?>
		</div>
		<?php
	}

	/** Render audit trend and searchable review workspace. */
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

		/* translators: %d: Autoload health score out of 100. */
		$current_score_text = sprintf( __( 'Score %d/100', 'autoloadfix' ), $summary['score'] );
		$delta_context      = __( 'No baseline yet', 'autoloadfix' );
		if ( $latest ) {
			/* translators: %s: Percentage change from the previous audit. */
			$delta_context = sprintf( __( '%s%% from previous audit', 'autoloadfix' ), number_format_i18n( (float) $latest['delta_percent'], 2 ) );
		}
		?>
		<div class="autoloadfix-grid autoloadfix-metrics">
			<?php $this->metric( __( 'Current autoload', 'autoloadfix' ), size_format( $summary['total_size'], 1 ), $current_score_text ); ?>
			<?php $this->metric( __( 'Last audit delta', 'autoloadfix' ), $latest ? $this->delta( (int) $latest['delta_bytes'] ) : '—', $delta_context ); ?>
			<?php $this->metric( __( 'Watched options', 'autoloadfix' ), number_format_i18n( count( $this->scanner->get_watched_options() ) ), __( 'Tracked for review', 'autoloadfix' ) ); ?>
			<?php $this->metric( __( 'Next audit', 'autoloadfix' ), $next ? wp_date( get_option( 'date_format' ), $next ) : __( 'Disabled', 'autoloadfix' ), $next ? wp_date( get_option( 'time_format' ), $next ) : __( 'Configure scheduling', 'autoloadfix' ) ); ?>
		</div>

		<div class="autoloadfix-panel">
			<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Autoload trend', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Audit points store totals and counts only. Option values are never stored.', 'autoloadfix' ); ?></p></div><?php $this->audit_form(); ?></div>
			<?php if ( $audits ) : ?>
				<div class="autoloadfix-advanced-trend" aria-label="<?php esc_attr_e( 'Autoload size trend', 'autoloadfix' ); ?>"><?php foreach ( array_reverse( $audits ) as $audit ) : $height = max( 4, (int) round( ( (int) $audit['total_size'] / $max ) * 100 ) ); ?><span style="height:<?php echo esc_attr( $height ); ?>%" title="<?php echo esc_attr( mysql2date( 'Y-m-d H:i', $audit['created_at'] ) . ' · ' . size_format( (int) $audit['total_size'], 1 ) ); ?>"></span><?php endforeach; ?></div>
			<?php else : ?><p><?php esc_html_e( 'Run an audit to establish the first baseline.', 'autoloadfix' ); ?></p><?php endif; ?>
			<div class="autoloadfix-table-wrap"><table class="widefat striped autoloadfix-table"><thead><tr><th><?php esc_html_e( 'Date', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Trigger', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Total', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Score', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Delta', 'autoloadfix' ); ?></th></tr></thead><tbody>
			<?php if ( ! $audits ) : ?><tr><td colspan="5"><?php esc_html_e( 'No audit history yet.', 'autoloadfix' ); ?></td></tr><?php else : foreach ( $audits as $audit ) : ?><tr><td data-label="<?php esc_attr_e( 'Date', 'autoloadfix' ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $audit['created_at'] ) ); ?></td><td data-label="<?php esc_attr_e( 'Trigger', 'autoloadfix' ); ?>"><?php echo esc_html( ucfirst( $audit['trigger_type'] ) ); ?></td><td data-label="<?php esc_attr_e( 'Total', 'autoloadfix' ); ?>"><?php echo esc_html( size_format( (int) $audit['total_size'], 1 ) ); ?></td><td data-label="<?php esc_attr_e( 'Score', 'autoloadfix' ); ?>"><?php echo esc_html( (int) $audit['score'] ); ?>/100</td><td data-label="<?php esc_attr_e( 'Delta', 'autoloadfix' ); ?>"><?php echo esc_html( $this->delta( (int) $audit['delta_bytes'] ) ); ?> <span class="autoloadfix-muted"><?php echo esc_html( number_format_i18n( (float) $audit['delta_percent'], 2 ) ); ?>%</span></td></tr><?php endforeach; endif; ?>
			</tbody></table></div>
		</div>

		<div class="autoloadfix-panel">
			<div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Option review workspace', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Search the largest 250 autoloaded entries. Watch important entries or ignore known noise. Use Overview for snapshot-backed autoload changes.', 'autoloadfix' ); ?></p></div><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=autoloadfix_export_csv' ), 'autoloadfix_export_csv' ) ); ?>"><?php esc_html_e( 'Export CSV', 'autoloadfix' ); ?></a></div>
			<div class="autoloadfix-advanced-toolbar"><input type="search" id="autoloadfix-advanced-search" placeholder="<?php esc_attr_e( 'Search option or owner…', 'autoloadfix' ); ?>" /><select id="autoloadfix-advanced-risk"><option value="all"><?php esc_html_e( 'All assessments', 'autoloadfix' ); ?></option><option value="candidate"><?php esc_html_e( 'Candidates', 'autoloadfix' ); ?></option><option value="review"><?php esc_html_e( 'Review', 'autoloadfix' ); ?></option><option value="protected"><?php esc_html_e( 'Protected', 'autoloadfix' ); ?></option><option value="ignored"><?php esc_html_e( 'Ignored', 'autoloadfix' ); ?></option></select><label><input type="checkbox" id="autoloadfix-advanced-watched" /> <?php esc_html_e( 'Watched only', 'autoloadfix' ); ?></label><span id="autoloadfix-advanced-count" class="autoloadfix-muted"></span></div>
			<div class="autoloadfix-table-wrap"><table id="autoloadfix-advanced-table" class="widefat striped autoloadfix-table"><thead><tr><th><?php esc_html_e( 'Option', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Size / impact', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Likely owner', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Assessment', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Review tools', 'autoloadfix' ); ?></th></tr></thead><tbody>
			<?php foreach ( $options as $row ) : ?>
				<?php
				$search = strtolower( $row['option_name'] . ' ' . $row['owner']['label'] );
				/* translators: %s: Raw WordPress database autoload value for this option. */
				$db_autoload_label = sprintf( __( 'DB autoload: %s', 'autoloadfix' ), $row['autoload'] );
				?>
				<tr class="autoloadfix-advanced-option" data-search="<?php echo esc_attr( $search ); ?>" data-risk="<?php echo esc_attr( $row['risk']['level'] ); ?>" data-watched="<?php echo $row['watched'] ? '1' : '0'; ?>"><td data-label="<?php esc_attr_e( 'Option', 'autoloadfix' ); ?>"><code><?php echo esc_html( $row['option_name'] ); ?></code><div class="autoloadfix-muted"><?php echo esc_html( $db_autoload_label ); ?></div></td><td data-label="<?php esc_attr_e( 'Size / impact', 'autoloadfix' ); ?>"><strong><?php echo esc_html( size_format( $row['option_size'], 1 ) ); ?></strong><div class="autoloadfix-muted"><?php echo esc_html( number_format_i18n( (float) $row['impact_percent'], 1 ) ); ?>% <?php esc_html_e( 'of current autoload', 'autoloadfix' ); ?></div></td><td data-label="<?php esc_attr_e( 'Likely owner', 'autoloadfix' ); ?>"><?php echo esc_html( $row['owner']['label'] ); ?><div class="autoloadfix-muted"><?php echo esc_html( (int) $row['owner']['confidence'] ); ?>% <?php esc_html_e( 'confidence', 'autoloadfix' ); ?></div></td><td data-label="<?php esc_attr_e( 'Assessment', 'autoloadfix' ); ?>"><span class="autoloadfix-pill autoloadfix-pill-<?php echo esc_attr( $row['risk']['level'] ); ?>"><?php echo esc_html( $row['risk']['label'] ); ?></span><div class="autoloadfix-reason"><?php echo esc_html( $row['risk']['reason'] ); ?></div></td><td data-label="<?php esc_attr_e( 'Review tools', 'autoloadfix' ); ?>"><div class="autoloadfix-advanced-actions"><?php $this->toggle_form( 'autoloadfix_toggle_watch', $row['option_name'], 'watched', $row['watched'], $row['watched'] ? __( 'Unwatch', 'autoloadfix' ) : __( 'Watch', 'autoloadfix' ) ); ?><?php if ( 'protected' !== $row['risk']['level'] ) : $this->toggle_form( 'autoloadfix_toggle_ignore', $row['option_name'], 'ignored', $row['ignored'], $row['ignored'] ? __( 'Unignore', 'autoloadfix' ) : __( 'Ignore', 'autoloadfix' ) ); endif; ?></div></td></tr>
			<?php endforeach; ?><tr id="autoloadfix-advanced-empty" class="autoloadfix-advanced-empty" hidden><td colspan="5"><?php esc_html_e( 'No options match the current filters.', 'autoloadfix' ); ?></td></tr>
			</tbody></table></div>
		</div>
		<?php
	}

	/** Render safe environment diagnostics. */
	private function render_diagnostics() {
		$diag      = $this->scanner->get_diagnostics();
		$breakdown = $this->scanner->get_autoload_breakdown();
		$copy      = wp_json_encode( array( 'plugin' => 'AutoloadFix ' . AUTOLOADFIX_VERSION, 'diagnostics' => $diag ) );
		?>
		<div class="autoloadfix-panel"><div class="autoloadfix-panel-head"><div><h2><?php esc_html_e( 'Environment diagnostics', 'autoloadfix' ); ?></h2><p><?php esc_html_e( 'Safe metadata only; option values are never included.', 'autoloadfix' ); ?></p></div><button type="button" id="autoloadfix-advanced-copy" class="button" data-copy="<?php echo esc_attr( $copy ); ?>"><?php esc_html_e( 'Copy diagnostics', 'autoloadfix' ); ?></button></div><div class="autoloadfix-advanced-diagnostics"><?php foreach ( $diag as $key => $value ) : ?><div><span><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></span><strong><?php echo esc_html( is_numeric( $value ) && false !== strpos( $key, 'bytes' ) ? size_format( (int) $value, 1 ) : $value ); ?></strong></div><?php endforeach; ?></div></div>
		<div class="autoloadfix-panel"><h2><?php esc_html_e( 'Database autoload value breakdown', 'autoloadfix' ); ?></h2><div class="autoloadfix-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Raw value', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Options', 'autoloadfix' ); ?></th><th><?php esc_html_e( 'Data size', 'autoloadfix' ); ?></th></tr></thead><tbody><?php foreach ( $breakdown as $row ) : ?><tr><td><code><?php echo esc_html( $row['autoload'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( (int) $row['option_count'] ) ); ?></td><td><?php echo esc_html( size_format( (int) $row['total_size'], 1 ) ); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
		<div class="autoloadfix-advanced-note"><strong><?php esc_html_e( 'WP-CLI:', 'autoloadfix' ); ?></strong> <code>wp autoloadfix status</code> · <code>wp autoloadfix top --limit=20</code> · <code>wp autoloadfix audit</code></div>
		<?php
	}

	/** Render monitoring and safety settings. */
	private function render_advanced_settings() {
		$s = $this->scanner->get_settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="autoloadfix_save_advanced" /><?php wp_nonce_field( 'autoloadfix_save_advanced' ); ?><div class="autoloadfix-advanced-settings">
			<div class="autoloadfix-panel"><h2><?php esc_html_e( 'Monitoring', 'autoloadfix' ); ?></h2><table class="form-table" role="presentation"><tr><th><label for="audit_interval"><?php esc_html_e( 'Audit schedule', 'autoloadfix' ); ?></label></th><td><select name="audit_interval" id="audit_interval"><option value="daily" <?php selected( $s['audit_interval'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'autoloadfix' ); ?></option><option value="weekly" <?php selected( $s['audit_interval'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'autoloadfix' ); ?></option><option value="disabled" <?php selected( $s['audit_interval'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'autoloadfix' ); ?></option></select></td></tr><tr><th><label for="growth_alert_percent"><?php esc_html_e( 'Growth alert', 'autoloadfix' ); ?></label></th><td><input class="small-text" type="number" min="1" max="500" name="growth_alert_percent" id="growth_alert_percent" value="<?php echo esc_attr( (int) $s['growth_alert_percent'] ); ?>" />%</td></tr><tr><th><label for="history_retention"><?php esc_html_e( 'Audit retention', 'autoloadfix' ); ?></label></th><td><input class="small-text" type="number" min="7" max="365" name="history_retention" id="history_retention" value="<?php echo esc_attr( (int) $s['history_retention'] ); ?>" /> <?php esc_html_e( 'days', 'autoloadfix' ); ?></td></tr></table></div>
			<div class="autoloadfix-panel"><h2><?php esc_html_e( 'Safety', 'autoloadfix' ); ?></h2><p><label><input type="checkbox" name="read_only" value="1" <?php checked( ! empty( $s['read_only'] ) ); ?> /> <strong><?php esc_html_e( 'Read-only safe mode', 'autoloadfix' ); ?></strong></label></p><p class="description"><?php esc_html_e( 'Blocks AutoloadFix disable and restore actions while keeping scans, reports, audits, watch lists, and diagnostics available.', 'autoloadfix' ); ?></p><p><label for="custom_protected"><strong><?php esc_html_e( 'Custom protected options', 'autoloadfix' ); ?></strong></label></p><textarea class="large-text code" rows="8" name="custom_protected" id="custom_protected" placeholder="my_critical_option&#10;another_critical_option"><?php echo esc_textarea( $s['custom_protected'] ); ?></textarea><p class="description"><?php esc_html_e( 'One exact option name per line. Protected options are locked from AutoloadFix changes.', 'autoloadfix' ); ?></p></div>
		</div><?php submit_button( __( 'Save monitoring & safety settings', 'autoloadfix' ) ); ?></form>
		<?php
	}

	/** @param string $action Action. @param string $option_name Option. @param string $field Field. @param bool $current Current. @param string $label Label. */
	private function toggle_form( $action, $option_name, $field, $current, $label ) {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="autoloadfix-inline-form autoloadfix-advanced-inline"><input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" /><input type="hidden" name="option_name" value="<?php echo esc_attr( $option_name ); ?>" /><input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo $current ? '0' : '1'; ?>" /><?php wp_nonce_field( $action . '_' . $option_name ); ?><button type="submit" class="button button-small"><?php echo esc_html( $label ); ?></button></form><?php
	}

	/** Render manual audit form. */
	private function audit_form() {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="autoloadfix-inline-form"><input type="hidden" name="action" value="autoloadfix_run_audit" /><?php wp_nonce_field( 'autoloadfix_run_audit' ); ?><button type="submit" class="button button-primary"><?php esc_html_e( 'Run audit now', 'autoloadfix' ); ?></button></form><?php
	}

	/** @param string $section Section. @param string $label Label. @param string $current Current. */
	private function tab( $section, $label, $current ) {
		$url = add_query_arg( array( 'page' => 'autoloadfix-advanced', 'section' => $section ), admin_url( 'admin.php' ) );
		printf( '<a class="nav-tab %1$s" href="%2$s">%3$s</a>', esc_attr( $current === $section ? 'nav-tab-active' : '' ), esc_url( $url ), esc_html( $label ) );
	}

	/** @param string $title Title. @param string $value Value. @param string $note Note. */
	private function metric( $title, $value, $note ) {
		echo '<div class="autoloadfix-metric"><span>' . esc_html( $title ) . '</span><strong>' . esc_html( $value ) . '</strong><small>' . esc_html( $note ) . '</small></div>';
	}

	/** @param int $bytes Signed bytes. @return string */
	private function delta( $bytes ) {
		if ( 0 === $bytes ) { return '0 B'; }
		return ( $bytes > 0 ? '+' : '-' ) . size_format( abs( $bytes ), 1 );
	}
}
