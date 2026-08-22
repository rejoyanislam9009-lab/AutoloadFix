<?php
/**
 * Autoload option scanner.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Scanner {
	/**
	 * Cached plugin inventory used by ownership heuristics.
	 *
	 * @var array<string,mixed>|null
	 */
	private $plugin_inventory = null;

	/**
	 * Core option names that should never be suggested for changes.
	 *
	 * @var string[]
	 */
	private $protected_exact = array(
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'users_can_register',
		'admin_email',
		'start_of_week',
		'use_balanceTags',
		'use_smilies',
		'require_name_email',
		'comments_notify',
		'posts_per_rss',
		'rss_use_excerpt',
		'mailserver_url',
		'mailserver_login',
		'mailserver_pass',
		'mailserver_port',
		'default_category',
		'default_comment_status',
		'default_ping_status',
		'default_pingback_flag',
		'posts_per_page',
		'date_format',
		'time_format',
		'links_updated_date_format',
		'comment_moderation',
		'moderation_notify',
		'permalink_structure',
		'rewrite_rules',
		'hack_file',
		'blog_charset',
		'moderation_keys',
		'active_plugins',
		'category_base',
		'ping_sites',
		'comment_max_links',
		'gmt_offset',
		'default_email_category',
		'recently_edited',
		'template',
		'stylesheet',
		'comment_registration',
		'html_type',
		'use_trackback',
		'default_role',
		'db_version',
		'uploads_use_yearmonth_folders',
		'upload_path',
		'blog_public',
		'default_link_category',
		'show_on_front',
		'tag_base',
		'show_avatars',
		'avatar_rating',
		'upload_url_path',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'thumbnail_crop',
		'medium_size_w',
		'medium_size_h',
		'avatar_default',
		'large_size_w',
		'large_size_h',
		'image_default_link_type',
		'image_default_size',
		'image_default_align',
		'close_comments_for_old_posts',
		'close_comments_days_old',
		'thread_comments',
		'thread_comments_depth',
		'page_comments',
		'comments_per_page',
		'default_comments_page',
		'comment_order',
		'sticky_posts',
		'widget_categories',
		'widget_text',
		'widget_rss',
		'uninstall_plugins',
		'timezone_string',
		'page_for_posts',
		'page_on_front',
		'default_post_format',
		'link_manager_enabled',
		'initial_db_version',
		'finished_splitting_shared_terms',
		'wp_page_for_privacy_policy',
		'cron',
		'sidebars_widgets',
		'can_compress_scripts',
		'alloptions',
		'notoptions',
	);

	/**
	 * Protected prefixes.
	 *
	 * @var string[]
	 */
	private $protected_prefixes = array(
		'theme_mods_',
		'widget_',
		'nav_menu_options',
		'auto_core_update_',
		'core_updater.',
		'fresh_site',
		'wp_force_deactivated_plugins',
		'recovery_mode_',
	);

	/**
	 * Get summary metrics.
	 *
	 * @return array<string,int>
	 */
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

		return array(
			'total_size'   => $total_size,
			'option_count' => $count,
			'large_count'  => $large,
			'health_limit' => $limit,
			'score'        => $score,
		);
	}

	/**
	 * Get largest autoloaded options.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_largest_options( $limit = 100 ) {
		global $wpdb;

		$limit           = max( 1, min( 500, (int) $limit ) );
		$autoload_values = $this->get_autoload_values();
		$placeholders    = implode( ', ', array_fill( 0, count( $autoload_values ), '%s' ) );
		$args            = $autoload_values;
		$args[]          = $limit;
		$sql             = "SELECT option_name, autoload, LENGTH(option_value) AS option_size FROM {$wpdb->options} WHERE autoload IN ({$placeholders}) ORDER BY option_size DESC LIMIT %d";
		$prepared        = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows            = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$settings        = $this->get_settings();
		$threshold       = (int) $settings['large_option_threshold'];

		foreach ( $rows as &$row ) {
			$row['option_size'] = (int) $row['option_size'];
			$row['owner']       = $this->detect_owner( $row['option_name'] );
			$row['risk']        = $this->classify_option( $row['option_name'], $row['option_size'], $row['owner'], $threshold );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Inspect one option by name.
	 *
	 * @param string $option_name Option name.
	 * @return array<string,mixed>|null
	 */
	public function get_option_record( $option_name ) {
		global $wpdb;

		$option_name = sanitize_text_field( $option_name );
		if ( '' === $option_name ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT option_name, autoload, LENGTH(option_value) AS option_size FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option_name
		);
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row['option_size'] = (int) $row['option_size'];
		$row['owner']       = $this->detect_owner( $row['option_name'] );
		$settings           = $this->get_settings();
		$row['risk']        = $this->classify_option( $row['option_name'], $row['option_size'], $row['owner'], (int) $settings['large_option_threshold'] );

		return $row;
	}

	/**
	 * Whether a name is protected.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	public function is_protected( $option_name ) {
		if ( in_array( $option_name, $this->protected_exact, true ) ) {
			return true;
		}

		foreach ( $this->protected_prefixes as $prefix ) {
			if ( 0 === strpos( $option_name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return accepted autoloading DB values.
	 *
	 * @return string[]
	 */
	public function get_autoload_values() {
		if ( function_exists( 'wp_autoload_values_to_autoload' ) ) {
			$values = wp_autoload_values_to_autoload();
			if ( is_array( $values ) && ! empty( $values ) ) {
				return array_values( array_unique( array_map( 'sanitize_key', $values ) ) );
			}
		}

		return array( 'yes', 'on', 'auto-on', 'auto' );
	}

	/**
	 * Get plugin settings with defaults.
	 *
	 * @return array<string,int>
	 */
	public function get_settings() {
		$defaults = array(
			'large_option_threshold' => 150000,
			'health_limit'           => 800000,
		);
		$saved = get_option( 'autoloadfix_settings', array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Detect likely owner.
	 *
	 * @param string $option_name Option name.
	 * @return array<string,mixed>
	 */
	private function detect_owner( $option_name ) {
		if ( $this->is_protected( $option_name ) ) {
			return array(
				'label'      => __( 'WordPress Core / protected', 'autoloadfix' ),
				'type'       => 'core',
				'status'     => 'protected',
				'confidence' => 100,
			);
		}

		$stylesheet = sanitize_key( get_stylesheet() );
		$template   = sanitize_key( get_template() );
		$theme      = wp_get_theme();
		$theme_name = $theme->exists() ? $theme->get( 'Name' ) : __( 'Active theme', 'autoloadfix' );
		$theme_keys = array_unique( array_filter( array( $stylesheet, $template, str_replace( '-', '_', $stylesheet ), str_replace( '-', '_', $template ) ) ) );
		$name_lower = strtolower( $option_name );

		foreach ( $theme_keys as $theme_key ) {
			if ( strlen( $theme_key ) >= 4 && ( 0 === strpos( $name_lower, strtolower( $theme_key ) . '_' ) || 0 === strpos( $name_lower, strtolower( $theme_key ) . '-' ) ) ) {
				return array(
					'label'      => sprintf( __( 'Theme: %s', 'autoloadfix' ), $theme_name ),
					'type'       => 'theme',
					'status'     => 'active',
					'confidence' => 88,
				);
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

			$candidates = array_unique(
				array_filter(
					array(
						$slug,
						str_replace( '-', '_', $slug ),
						str_replace( '_', '-', $slug ),
						preg_replace( '/[^a-z0-9]/', '', strtolower( $slug ) ),
					)
				)
			);

			foreach ( $candidates as $candidate ) {
				$candidate = strtolower( $candidate );
				$name      = strtolower( $option_name );
				$score     = 0;

				if ( 0 === strpos( $name, $candidate . '_' ) || 0 === strpos( $name, $candidate . '-' ) ) {
					$score = 95;
				} elseif ( 0 === strpos( $name, $candidate ) && strlen( $candidate ) >= 5 ) {
					$score = 85;
				} elseif ( false !== strpos( $name, '_' . $candidate . '_' ) && strlen( $candidate ) >= 5 ) {
					$score = 72;
				}

				if ( $score > 0 && ( null === $best || $score > $best['confidence'] ) ) {
					$best = array(
						'label'      => ! empty( $headers['Name'] ) ? $headers['Name'] : $slug,
						'type'       => 'plugin',
						'status'     => in_array( $basename, $active, true ) ? 'active' : 'inactive',
						'confidence' => $score,
					);
				}
			}
		}

		if ( null !== $best ) {
			return $best;
		}

		return array(
			'label'      => __( 'Unknown source', 'autoloadfix' ),
			'type'       => 'unknown',
			'status'     => 'unknown',
			'confidence' => 0,
		);
	}

	/**
	 * Get cached installed/active plugin inventory.
	 *
	 * @return array<string,mixed>
	 */
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

		$this->plugin_inventory = array(
			'active' => $active,
			'all'    => get_plugins(),
		);

		return $this->plugin_inventory;
	}

	/**
	 * Classify option risk.
	 *
	 * @param string               $option_name Option name.
	 * @param int                  $size Option size.
	 * @param array<string,mixed>  $owner Owner metadata.
	 * @param int                  $threshold Large option threshold.
	 * @return array<string,string>
	 */
	private function classify_option( $option_name, $size, $owner, $threshold ) {
		if ( $this->is_protected( $option_name ) ) {
			return array(
				'level'  => 'protected',
				'label'  => __( 'Protected', 'autoloadfix' ),
				'reason' => __( 'Core or site-critical option. AutoloadFix will not change it.', 'autoloadfix' ),
			);
		}

		if ( 'active' === $owner['status'] ) {
			$reason = 'theme' === $owner['type']
				? __( 'Likely belongs to the active theme. Confirm it is not needed on most requests before disabling autoload.', 'autoloadfix' )
				: __( 'Likely belongs to an active plugin. Confirm with the plugin developer before disabling autoload.', 'autoloadfix' );

			return array(
				'level'  => 'review',
				'label'  => __( 'Review', 'autoloadfix' ),
				'reason' => $reason,
			);
		}

		if ( $size >= $threshold && 'inactive' === $owner['status'] ) {
			return array(
				'level'  => 'candidate',
				'label'  => __( 'Strong candidate', 'autoloadfix' ),
				'reason' => __( 'Large option likely owned by an inactive plugin. Review before applying.', 'autoloadfix' ),
			);
		}

		if ( $size >= $threshold ) {
			return array(
				'level'  => 'candidate',
				'label'  => __( 'Large candidate', 'autoloadfix' ),
				'reason' => __( 'This option is large and loaded on every request. Review its owner and usage before changing it.', 'autoloadfix' ),
			);
		}

		return array(
			'level'  => 'review',
			'label'  => __( 'Review', 'autoloadfix' ),
			'reason' => __( 'No automatic safety assumption is made. Change only if you know the option is not required on most requests.', 'autoloadfix' ),
		);
	}
}
