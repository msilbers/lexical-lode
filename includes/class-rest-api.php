<?php
/**
 * REST API endpoints for Lexical Lode.
 */
class Lexical_Lode_REST_API {

	const REST_NAMESPACE = 'lexical-lode/v1';

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'line_count'       => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
					'order'            => array(
						'required'          => false,
						'type'              => 'string',
						'enum'              => array( 'random', 'newest', 'oldest' ),
						'default'           => 'random',
						'sanitize_callback' => 'sanitize_key',
					),
					'exclude_post_ids' => array(
						'required' => false,
						'type'     => 'array',
						'default'  => array(),
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/scramble',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'scramble' ),
				'permission_callback' => array( $this, 'scramble_permission_check' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Generate a full set of lines.
	 */
	public function generate( $request ) {
		$line_count = $request->get_param( 'line_count' );
		$order      = $request->get_param( 'order' );
		if ( ! in_array( $order, array( 'random', 'newest', 'oldest' ), true ) ) {
			$order = 'random';
		}
		$exclude = $request->get_param( 'exclude_post_ids' );
		$exclude = is_array( $exclude ) ? array_map( 'absint', $exclude ) : array();
		$lines   = Lexical_Lode_Phrase_Extractor::generate_lines( $line_count, $exclude, $order );

		if ( count( $lines ) < $line_count ) {
			return new WP_REST_Response(
				array(
					'lines'   => $lines,
					'warning' => sprintf(
						/* translators: 1: number of lines found, 2: number requested */
						__( 'Only found %1$d usable posts out of %2$d requested.', 'lexical-lode' ),
						count( $lines ),
						$line_count
					),
				),
				200
			);
		}

		return new WP_REST_Response( array( 'lines' => $lines ), 200 );
	}

	/**
	 * Scramble a single line — get a new phrase from the same post.
	 */
	public function scramble( $request ) {
		$post_id = $request->get_param( 'post_id' );

		// Authenticated editors can scramble any (non-excluded) post — they're in the block editor.
		// Unauthenticated users must pass through the live-mode gate and post-ID allowlist.
		if ( ! current_user_can( 'edit_posts' ) ) {
			// Site-wide kill switch — if live mode is off, reject immediately.
			if ( ! Lexical_Lode_Settings::is_live_mode_allowed() ) {
				return new WP_Error(
					'live_mode_disabled',
					__( 'Live mode is not enabled on this site.', 'lexical-lode' ),
					array( 'status' => 403 )
				);
			}

			if ( ! Lexical_Lode_Block::is_live_post_id( $post_id ) ) {
				return new WP_Error(
					'invalid_post',
					__( 'This post is not available for scrambling.', 'lexical-lode' ),
					array( 'status' => 403 )
				);
			}

			// Soft rate limit for unauthenticated requests — courtesy fallback
			// for hosts without server-level throttling. Not a substitute for
			// Nginx/Cloudflare rate limiting on production sites.
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$key = 'lexical_lode_rate_' . md5( $ip );
			$hits = (int) get_transient( $key );
			if ( $hits >= 30 ) {
				return new WP_Error(
					'rate_limited',
					__( 'Too many requests. Please try again shortly.', 'lexical-lode' ),
					array( 'status' => 429 )
				);
			}
			set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		}

		// Always validate against category/tag exclusions.
		$phrase = Lexical_Lode_Phrase_Extractor::get_random_phrase( $post_id, true );

		if ( null === $phrase ) {
			return new WP_Error(
				'no_phrases',
				__( 'No usable phrases found in this post.', 'lexical-lode' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( array( 'phrase' => $phrase ), 200 );
	}

	/**
	 * Scramble permission check — allow authenticated editors always,
	 * and allow unauthenticated users (further validated in the callback).
	 */
	public function scramble_permission_check( $request ) {
		return true;
	}
}
