<?php
/**
 * Scanner refinements shared by Site Problem Scanner and Optimization Profiles.
 *
 * @package AutoloadFix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AutoloadFix_Scanner_Refinements {
	/** Register lightweight refinements. */
	public function __construct() {
		add_filter( 'http_response', array( $this, 'normalize_scanner_cache_headers' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'cleanup_legacy_dynamic_info' ), 15 );
	}

	/**
	 * Translate LiteSpeed's authoritative no-cache control header into the
	 * scanner's existing BYPASS vocabulary. This changes only the in-memory
	 * WordPress HTTP response used by AutoloadFix; it does not alter site headers.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param array          $args Request arguments.
	 * @param string         $url Requested URL.
	 * @return array|WP_Error
	 */
	public function normalize_scanner_cache_headers( $response, $args, $url ) {
		unset( $url );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return $response;
		}

		$user_agent = isset( $args['user-agent'] ) ? (string) $args['user-agent'] : '';
		if ( 0 !== strpos( $user_agent, 'AutoloadFix-SiteScanner/' ) ) {
			return $response;
		}

		$existing = strtolower( (string) wp_remote_retrieve_header( $response, 'x-litespeed-cache' ) );
		if ( $existing ) {
			return $response;
		}

		$control = strtolower( (string) wp_remote_retrieve_header( $response, 'x-litespeed-cache-control' ) );
		if ( ! $control ) {
			return $response;
		}

		if ( false === strpos( $control, 'no-cache' ) && false === strpos( $control, 'private' ) ) {
			return $response;
		}

		if ( ! isset( $response['headers'] ) ) {
			return $response;
		}

		if ( is_array( $response['headers'] ) || $response['headers'] instanceof ArrayAccess ) {
			$response['headers']['x-litespeed-cache'] = 'bypass';
		}

		return $response;
	}

	/**
	 * v1.4.0 pre-release builds promoted dynamic UNKNOWN cache states to an
	 * actionable-looking Info result when Optimization Profiles was opened.
	 * UNKNOWN is evidence uncertainty, not a configuration failure, so remove
	 * only that injected issue and restore the result's highest real severity.
	 */
	public function cleanup_legacy_dynamic_info() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$results = get_option( 'autoloadfix_site_scan_results', array() );
		if ( ! is_array( $results ) || ! $results ) {
			return;
		}

		$changed = false;
		foreach ( $results as $key => $result ) {
			if ( empty( $result['dynamic'] ) || empty( $result['issues'] ) || ! is_array( $result['issues'] ) ) {
				continue;
			}

			$kept    = array();
			$removed = false;
			foreach ( $result['issues'] as $issue ) {
				$code = isset( $issue['code'] ) ? sanitize_key( $issue['code'] ) : '';
				if ( 'dynamic_unverified' === $code ) {
					$removed = true;
					continue;
				}
				$kept[] = $issue;
			}

			if ( ! $removed ) {
				continue;
			}

			if ( ! $kept ) {
				$kept[] = array(
					'severity' => 'good',
					'code'     => 'healthy',
					'title'    => __( 'No actionable problem detected in this scan', 'autoloadfix' ),
					'message'  => __( 'The dynamic page is reachable and was not detected as a shared cache HIT. An UNKNOWN cache header alone is not treated as a failure.', 'autoloadfix' ),
					'steps'    => array( __( 'No cache-setting change is required from this result alone.', 'autoloadfix' ) ),
				);
			}

			$severity = 'good';
			foreach ( $kept as $issue ) {
				$issue_severity = isset( $issue['severity'] ) ? sanitize_key( $issue['severity'] ) : 'info';
				if ( $this->severity_rank( $issue_severity ) > $this->severity_rank( $severity ) ) {
					$severity = $issue_severity;
				}
			}

			$results[ $key ]['issues']   = $kept;
			$results[ $key ]['severity'] = $severity;
			$changed                      = true;
		}

		if ( $changed ) {
			update_option( 'autoloadfix_site_scan_results', $results, false );
		}
	}

	/**
	 * @param string $severity Severity.
	 * @return int
	 */
	private function severity_rank( $severity ) {
		$map = array( 'good' => 0, 'info' => 1, 'review' => 2, 'critical' => 3 );
		return isset( $map[ $severity ] ) ? $map[ $severity ] : 1;
	}
}
