<?php
/**
 * Autoload option scanner and classification service.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Scanner {
	/** @var array<string,mixed>|null */
	private $plugin_inventory = null;

	/** @var string[] */
	private $protected_exact = array(
		'siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register', 'admin_email',
		'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'rewrite_rules',
		'active_plugins', 'template', 'stylesheet', 'current_theme', 'default_role', 'db_version', 'initial_db_version',
		'blog_charset', 'timezone_string', 'gmt_offset', 'cron', 'sidebars_widgets', 'sticky_posts',
		'wp_page_for_privacy_policy', 'uninstall_plugins', 'alloptions', 'notoptions',
	);

	/** @var string[] */
	private $protected_prefixes = array(
		'theme_mods_', 'widget_', 'nav_menu_options', 'auto_core_update_', 'core_updater.',
		'recovery_mode_', 'wp_force_deactivated_plugins',
	);

	/** @return array<string,mixed> */
	public function get_settings() {
		$defaults = array(
			'large_option_threshold' => 150000,
			'health_limit'           => 800000,
			'audit_interval'         => 'daily',
			'growth_alert_percent'   => 25,
			'history_retention'      => 30,
			'read_only'              => 0,
			'custom_protected'       => '',
		);
		$saved = get_option( 'autoloadfix_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	/** @return array<string,int> */
	public function get_summary() {
		global $wpdb;
		$settings        = $this->get_settings();
		$threshold       = max( 1, (int) $settings['large_option_threshold'] );
		$autoload_values = $this->get_autoload_values();
		$placeholders    = implode( ', ', array_fill( 0, count( $autoload_values ), '%s' ) );
		$args            = array_merge( array( $threshold ), $autoload_values );
		$sql             = "SELECT COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_value)), 0) AS total_size, COALESCE(SUM(CASE WHEN LENGTH(option_value) >= %d THEN 1 ELSE 0 END), 0) AS large_count FROM {$wpdb->options} WHERE autoload IN ({$placeholders})";
		$prepared        = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row             = $wpdb->get_row( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total_size = isset( $row['total_size'] ) ? (int) $row['total_size'] : 0;
		$count      = isset( $row['option_count'] ) ? (int) $row['option_count'] : 0;
		$large      = isset( $row['large_count'] ) ? (int) $row['large_count'] : 0;
		$limit      = max( 1, (int) $settings['health_limit'] );
		$score      = 100;
		if ( $total_size > $limit ) {
			$over  = $total_size - $limit;
			$score = max( 5, 100 - (int) round( ( $over / $limit ) * 45 ) );
		} elseif ( $total_size > (int) ( $limit * 0.75 ) ) {
			$score = 80;
		} elseif ( $total_size > (int) ( $limit * 0.5 ) ) {
			$score = 92;
		}
		return array( 'total_size' => $total_size, 'option_count' => $count, 'large_count' => $large, 'health_limit' => $limit, 'score' => $score );
	}

	/**
	 * @param int $limit Maximum rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_largest_options( $limit = 250 ) {
		global $wpdb;
		$limit           = max( 1, min( 500, (int) $limit ) );
		$autoload_values = $this->get_autoload_values();
		$placeholders    = implode( ', ', array_fill( 0, count( $autoload_values ), '%s' ) );
		$args            = $autoload_values;
		$args[]          = $limit;
		$sql             = "SELECT option_name, autoload, LENGTH(option_value) AS option_size FROM {$wpdb->options} WHERE autoload IN ({$placeholders}) ORDER BY option_size DESC LIMIT %d";
		$prepared        = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows            = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$summary         = $this->get_summary();
		$settings        = $this->get_settings();
		$threshold       = (int) $settings['large_option_threshold'];
		foreach ( $rows as &$row ) {
			$row['option_size']    = (int) $row['option_size'];
			$row['owner']          = $this->detect_owner( $row['option_name'] );
			$row['ignored']        = $this->is_ignored( $row['option_name'] );
			$row['watched']        = $this->is_watched( $row['option_name'] );
			$row['risk']           = $this->classify_option( $row['option_name'], $row['option_size'], $row['owner'], $threshold, $row['ignored'] );
			$row['impact_percent'] = $summary['total_size'] > 0 ? round( ( $row['option_size'] / $summary['total_size'] ) * 100, 1 ) : 0;
		}
		unset( $row );
		return $rows;
	}

	/** @param string $option_name Option name. @return array<string,mixed>|null */
	public function get_option_record( $option_name ) {
		global $wpdb;
		$option_name = sanitize_text_field( $option_name );
		if ( '' === $option_name ) {
			return null;
		}
		$sql = $wpdb->prepare( "SELECT option_name, autoload, LENGTH(option_value) AS option_size FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option_name );
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['option_size'] = (int) $row['option_size'];
		$row['owner']       = $this->detect_owner( $row['option_name'] );
		$row['ignored']     = $this->is_ignored( $row['option_name'] );
		$row['watched']     = $this->is_watched( $row['option_name'] );
		$settings           = $this->get_settings();
		$row['risk']        = $this->classify_option( $row['option_name'], $row['option_size'], $row['owner'], (int) $settings['large_option_threshold'], $row['ignored'] );
		return $row;
	}

	/** @param string $option_name Option name. @return bool */
	public function is_protected( $option_name ) {
		if ( in_array( $option_name, $this->protected_exact, true ) ) {
			return true;
		}
		if ( strlen( $option_name ) >= 11 && '_user_roles' === substr( $option_name, -11 ) ) {
			return true;
		}
		foreach ( $this->protected_prefixes as $prefix ) {
			if ( 0 === strpos( $option_name, $prefix ) ) {
				return true;
			}
		}
		$settings = $this->get_settings();
		$custom   = preg_split( '/\r\n|\r|\n/', (string) $settings['custom_protected'] );
		if ( is_array( $custom ) ) {
			foreach ( $custom as $name ) {
				$name = sanitize_text_field( trim( $name ) );
				if ( '' !== $name && $name === $option_name ) {
					return true;
				}
			}
		}
		return (bool) apply_filters( 'autoloadfix_is_protected_option', false, $option_name );
	}

	/** @return string[] */
	public function get_autoload_values() {
		if ( function_exists( 'wp_autoload_values_to_autoload' ) ) {
			$values = wp_autoload_values_to_autoload();
			if ( is_array( $values ) && ! empty( $values ) ) {
				return array_values( array_unique( array_map( 'sanitize_key', $values ) ) );
			}
		}
		return array( 'yes', 'on', 'auto-on', 'auto' );
	}

	/** @return array<int,array<string,mixed>> */
	public function get_autoload_breakdown() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT autoload, COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_value)), 0) AS total_size FROM {$wpdb->options} GROUP BY autoload ORDER BY total_size DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string,mixed> */
	public function get_diagnostics() {
		global $wpdb, $wp_version;
		$summary = $this->get_summary();
		return array(
			'wordpress_version' => $wp_version,
			'php_version'       => PHP_VERSION,
			'multisite'         => is_multisite() ? __( 'Yes', 'autoloadfix' ) : __( 'No', 'autoloadfix' ),
			'object_cache'      => wp_using_ext_object_cache() ? __( 'Persistent', 'autoloadfix' ) : __( 'Default', 'autoloadfix' ),
			'db_version'        => $wpdb->db_version(),
			'charset'           => $wpdb->charset,
			'autoload_bytes'    => $summary['total_size'],
			'autoload_count'    => $summary['option_count'],
			'autoload_values'   => implode( ', ', $this->get_autoload_values() ),
		);
	}

	/** @return string[] */
	public function get_ignored_options() {
		$list = get_option( 'autoloadfix_ignored_options', array() );
		return is_array( $list ) ? array_values( array_unique( array_map( 'sanitize_text_field', $list ) ) ) : array();
	}

	/** @return string[] */
	public function get_watched_options() {
		$list = get_option( 'autoloadfix_watched_options', array() );
		return is_array( $list ) ? array_values( array_unique( array_map( 'sanitize_text_field', $list ) ) ) : array();
	}

	/** @param string $option_name Option name. @return bool */
	public function is_ignored( $option_name ) {
		return in_array( $option_name, $this->get_ignored_options(), true );
	}

	/** @param string $option_name Option name. @return bool */
	public function is_watched( $option_name ) {
		return in_array( $option_name, $this->get_watched_options(), true );
	}

	/** @param string $option_name Option name. @param bool $ignored State. */
	public function set_ignored( $option_name, $ignored ) {
		$this->set_list_state( 'autoloadfix_ignored_options', $option_name, $ignored );
	}

	/** @param string $option_name Option name. @param bool $watched State. */
	public function set_watched( $option_name, $watched ) {
		$this->set_list_state( 'autoloadfix_watched_options', $option_name, $watched );
	}

	/** @param string $option_name Option name. @return array<string,mixed> */
	public function detect_owner( $option_name ) {
		if ( $this->is_protected( $option_name ) ) {
			return array( 'label' => __( 'WordPress Core / protected', 'autoloadfix' ), 'type' => 'core', 'status' => 'protected', 'confidence' => 100 );
		}
		$stylesheet = sanitize_key( get_stylesheet() );
		$template   = sanitize_key( get_template() );
		$theme      = wp_get_theme();
		$theme_name = $theme->exists() ? $theme->get( 'Name' ) : __( 'Active theme', 'autoloadfix' );
		$theme_keys = array_unique( array_filter( array( $stylesheet, $template, str_replace( '-', '_', $stylesheet ), str_replace( '-', '_', $template ) ) ) );
		$name_lower = strtolower( $option_name );
		foreach ( $theme_keys as $theme_key ) {
			if ( strlen( $theme_key ) >= 4 && ( 0 === strpos( $name_lower, strtolower( $theme_key ) . '_' ) || 0 === strpos( $name_lower, strtolower( $theme_key ) . '-' ) ) ) {
				return array( 'label' => sprintf( __( 'Theme: %s', 'autoloadfix' ), $theme_name ), 'type' => 'theme', 'status' => 'active', 'confidence' => 88 );
			}
		}
		$inventory = $this->get_plugin_inventory();
		$active    = $inventory['active'];
		$all       = $inventory['all'];
		$best      = null;
		foreach ( $all as $basename => $headers ) {
			$slug = dirname( $basename );
			if ( '.' === $slug ) {
				$slug = basename( $basename, '.php' );
			}
			$candidates = array_unique( array_filter( array( $slug, str_replace( '-', '_', $slug ), str_replace( '_', '-', $slug ), preg_replace( '/[^a-z0-9]/', '', strtolower( $slug ) ) ) ) );
			foreach ( $candidates as $candidate ) {
				$candidate = strtolower( $candidate );
				$score     = 0;
				if ( 0 === strpos( $name_lower, $candidate . '_' ) || 0 === strpos( $name_lower, $candidate . '-' ) ) {
					$score = 95;
				} elseif ( 0 === strpos( $name_lower, $candidate ) && strlen( $candidate ) >= 5 ) {
					$score = 85;
				} elseif ( false !== strpos( $name_lower, '_' . $candidate . '_' ) && strlen( $candidate ) >= 5 ) {
					$score = 72;
				}
				if ( $score > 0 && ( null === $best || $score > $best['confidence'] ) ) {
					$best = array( 'label' => ! empty( $headers['Name'] ) ? $headers['Name'] : $slug, 'type' => 'plugin', 'status' => in_array( $basename, $active, true ) ? 'active' : 'inactive', 'confidence' => $score );
				}
			}
		}
		return null !== $best ? $best : array( 'label' => __( 'Unknown source', 'autoloadfix' ), 'type' => 'unknown', 'status' => 'unknown', 'confidence' => 0 );
	}

	/** @return array<string,mixed> */
	private function get_plugin_inventory() {
		if ( null !== $this->plugin_inventory ) {
			return $this->plugin_inventory;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$network_active = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$active         = array_values( array_unique( array_merge( $active, $network_active ) ) );
		}
		$this->plugin_inventory = array( 'active' => $active, 'all' => get_plugins() );
		return $this->plugin_inventory;
	}

	/**
	 * @param string $option_name Option name.
	 * @param int $size Bytes.
	 * @param array<string,mixed> $owner Owner.
	 * @param int $threshold Threshold.
	 * @param bool $ignored Ignored.
	 * @return array<string,string>
	 */
	private function classify_option( $option_name, $size, $owner, $threshold, $ignored ) {
		if ( $this->is_protected( $option_name ) ) {
			return array( 'level' => 'protected', 'label' => __( 'Protected', 'autoloadfix' ), 'reason' => __( 'Core, site-critical, or administrator-protected option. AutoloadFix will not change it.', 'autoloadfix' ) );
		}
		if ( $ignored ) {
			return array( 'level' => 'ignored', 'label' => __( 'Ignored', 'autoloadfix' ), 'reason' => __( 'You chose to ignore this option in AutoloadFix recommendations.', 'autoloadfix' ) );
		}
		if ( 'active' === $owner['status'] ) {
			$reason = 'theme' === $owner['type'] ? __( 'Likely belongs to the active theme. Confirm usage before changing autoload behavior.', 'autoloadfix' ) : __( 'Likely belongs to an active plugin. Confirm usage or consult its developer before changing autoload behavior.', 'autoloadfix' );
			return array( 'level' => 'review', 'label' => __( 'Review', 'autoloadfix' ), 'reason' => $reason );
		}
		if ( $size >= $threshold && 'inactive' === $owner['status'] ) {
			return array( 'level' => 'candidate', 'label' => __( 'Strong candidate', 'autoloadfix' ), 'reason' => __( 'Large option likely owned by an inactive plugin. Review before applying.', 'autoloadfix' ) );
		}
		if ( $size >= $threshold ) {
			return array( 'level' => 'candidate', 'label' => __( 'Large candidate', 'autoloadfix' ), 'reason' => __( 'This option is large and autoloaded. Review ownership and request-time usage before changing it.', 'autoloadfix' ) );
		}
		return array( 'level' => 'review', 'label' => __( 'Review', 'autoloadfix' ), 'reason' => __( 'No automatic safety assumption is made. Change only when you understand how the option is used.', 'autoloadfix' ) );
	}

	/** @param string $storage_key Option. @param string $option_name Name. @param bool $enabled State. */
	private function set_list_state( $storage_key, $option_name, $enabled ) {
		$option_name = sanitize_text_field( $option_name );
		if ( '' === $option_name ) {
			return;
		}
		$list = get_option( $storage_key, array() );
		$list = is_array( $list ) ? array_values( array_unique( array_map( 'sanitize_text_field', $list ) ) ) : array();
		if ( $enabled ) {
			if ( ! in_array( $option_name, $list, true ) ) {
				$list[] = $option_name;
			}
		} else {
			$list = array_values( array_diff( $list, array( $option_name ) ) );
		}
		update_option( $storage_key, $list, false );
	}
}
