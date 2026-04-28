<?php
/**
 * Gutenberg block registration and server-side rendering for Lexical Lode.
 */
class Lexical_Lode_Block {

	/**
	 * Block name as registered in block.json. Used to derive script handles.
	 */
	const BLOCK_NAME = 'lexical-lode/lexical-lode-block';

	/**
	 * Option name storing post IDs referenced by live-mode blocks site-wide.
	 * Persisted (not a transient) so cached pages continue to work.
	 */
	const LIVE_IDS_OPTION = 'lexical_lode_live_ids';

	public static function register() {
		register_block_type( LEXICAL_LODE_PLUGIN_DIR . 'build', array(
			'render_callback' => array( __CLASS__, 'render' ),
		) );

		// Pass settings data to the block editor.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_data' ) );

		// Rebuild the live-ID registry when posts change — keeps it accurate
		// and pruned regardless of whether the block's render callback runs.
		add_action( 'save_post', array( __CLASS__, 'sync_live_ids_on_save' ), 10, 2 );
		add_action( 'delete_post', array( __CLASS__, 'prune_live_ids_on_delete' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'prune_live_ids_on_unpublish' ), 10, 3 );
	}

	/**
	 * Get the handle WordPress generates for the block's editor script.
	 * WordPress takes the block name, replaces the '/' with '-', and appends '-editor-script'.
	 */
	private static function get_editor_script_handle() {
		return str_replace( '/', '-', self::BLOCK_NAME ) . '-editor-script';
	}

	/**
	 * Make plugin settings available to the block editor JS.
	 */
	public static function enqueue_editor_data() {
		$formats = Lexical_Lode_Settings::get_enabled_formats();
		wp_add_inline_script(
			self::get_editor_script_handle(),
			'window.lexicalLodeData = ' . wp_json_encode( array(
				'formats'       => $formats,
				'restUrl'       => rest_url( Lexical_Lode_REST_API::REST_NAMESPACE ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'allowLiveMode' => Lexical_Lode_Settings::is_live_mode_allowed(),
			) ) . ';',
			'before'
		);
	}

	/**
	 * Server-side render for the frontend.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content (unused — block has no inner blocks).
	 * @param WP_Block $block      Block instance (unused).
	 */
	public static function render( $attributes, $content = '', $block = null ) {
		$lines       = $attributes['lines'] ?? array();
		$format      = $attributes['format'] ?? 'free_verse';
		$mode        = $attributes['mode'] ?? 'locked';
		$attribution = $attributes['attribution'] ?? 'hidden';

		// Normalize lines — enforce types and filter out invalid entries.
		$lines = array_map( function( $line ) {
			return array(
				'post_id' => isset( $line['post_id'] ) ? (int) $line['post_id'] : 0,
				'phrase'  => isset( $line['phrase'] ) ? (string) $line['phrase'] : '',
			);
		}, $lines );
		$lines = array_values( array_filter( $lines, fn( $l ) => $l['post_id'] > 0 && '' !== $l['phrase'] ) );

		// Validate format against allowed list.
		$valid_formats = array_keys( Lexical_Lode_Settings::FORMATS );
		if ( ! in_array( $format, $valid_formats, true ) ) {
			$format = 'free_verse';
		}

		// Validate mode and attribution against allowed values.
		if ( ! in_array( $mode, array( 'locked', 'live' ), true ) ) {
			$mode = 'locked';
		}
		if ( ! in_array( $attribution, array( 'hidden', 'hover', 'footnotes' ), true ) ) {
			$attribution = 'hidden';
		}

		// Enforce the site-wide live-mode setting: if disallowed, force to locked
		// even if the block was saved as live (e.g. setting was turned off after).
		if ( 'live' === $mode && ! Lexical_Lode_Settings::is_live_mode_allowed() ) {
			$mode = 'locked';
		}

		if ( empty( $lines ) ) {
			return '';
		}

		// Live-ID registry is maintained via the save_post hook
		// (sync_live_ids_on_save), not at render time.

		$wrapper_attrs = get_block_wrapper_attributes( array(
			'class'          => 'lexical-lode-block lexical-lode-format-' . esc_attr( $format ),
			'data-mode'      => esc_attr( $mode ),
			'data-format'    => esc_attr( $format ),
		) );

		ob_start();
		// $wrapper_attrs is returned pre-escaped by get_block_wrapper_attributes().
		echo '<div ' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 'prose' === $format ) {
			self::render_prose( $lines, $mode, $attribution );
		} elseif ( 'list' === $format ) {
			self::render_list( $lines, $mode, $attribution );
		} else {
			self::render_lines( $lines, $format, $mode, $attribution );
		}

		if ( 'footnotes' === $attribution ) {
			self::render_attribution_footnotes( $lines );
		}

		echo '</div>';

		if ( 'live' === $mode || 'hover' === $attribution ) {
			self::enqueue_frontend_script();
		}

		return ob_get_clean();
	}

	/**
	 * Get the title of a post, falling back to a placeholder for untitled posts.
	 *
	 * @param int $post_id The post ID.
	 * @return string The post title or a placeholder string.
	 */
	private static function get_post_title( $post_id ) {
		$title = get_the_title( $post_id );
		if ( '' === $title ) {
			$title = __( '[No Title]', 'lexical-lode' );
		}
		return $title;
	}

	/**
	 * Render as line-broken verse (sonnet, free verse, couplets).
	 */
	private static function render_lines( $lines, $format, $mode, $attribution ) {
		$stanza_break = ( 'couplets' === $format ) ? 2 : 0;

		echo '<div class="lexical-lode-lines">';
		foreach ( $lines as $index => $line ) {
			if ( $stanza_break > 0 && $index > 0 && 0 === $index % $stanza_break ) {
				echo '<div class="lexical-lode-stanza-break"></div>';
			}

			$hover_attr = '';
			if ( 'hover' === $attribution ) {
				$title = self::get_post_title( $line['post_id'] );
				$url   = get_permalink( $line['post_id'] );
				$hover_attr = ' data-post-title="' . esc_attr( $title ) . '" data-post-url="' . esc_attr( $url ) . '"';
			}

			echo '<div class="lexical-lode-line"' . $hover_attr . ' data-post-id="' . esc_attr( $line['post_id'] ) . '">';
			echo '<span class="lexical-lode-phrase">' . esc_html( $line['phrase'] ) . '</span>';

			if ( 'live' === $mode ) {
				echo '<button class="lexical-lode-scramble" aria-label="' . esc_attr__( 'Scramble this line', 'lexical-lode' ) . '">&#x21bb;</button>';
			}

			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render as a prose paragraph.
	 */
	private static function render_prose( $lines, $mode, $attribution ) {
		echo '<p class="lexical-lode-prose">';
		foreach ( $lines as $index => $line ) {
			$hover_attr = '';
			if ( 'hover' === $attribution ) {
				$title = self::get_post_title( $line['post_id'] );
				$url   = get_permalink( $line['post_id'] );
				$hover_attr = ' data-post-title="' . esc_attr( $title ) . '" data-post-url="' . esc_attr( $url ) . '"';
			}

			echo '<span class="lexical-lode-phrase" data-post-id="' . esc_attr( $line['post_id'] ) . '"' . $hover_attr . '>' . esc_html( $line['phrase'] ) . '</span>';

			if ( 'live' === $mode ) {
				echo '<button class="lexical-lode-scramble" aria-label="' . esc_attr__( 'Scramble this phrase', 'lexical-lode' ) . '">&#x21bb;</button>';
			}

			if ( $index < count( $lines ) - 1 ) {
				echo ' ';
			}
		}
		echo '</p>';
	}

	/**
	 * Render as a numbered/bulleted list.
	 */
	private static function render_list( $lines, $mode, $attribution ) {
		echo '<ol class="lexical-lode-list">';
		foreach ( $lines as $line ) {
			$hover_attr = '';
			if ( 'hover' === $attribution ) {
				$title = self::get_post_title( $line['post_id'] );
				$url   = get_permalink( $line['post_id'] );
				$hover_attr = ' data-post-title="' . esc_attr( $title ) . '" data-post-url="' . esc_attr( $url ) . '"';
			}

			echo '<li class="lexical-lode-line"' . $hover_attr . ' data-post-id="' . esc_attr( $line['post_id'] ) . '">';
			echo '<span class="lexical-lode-phrase">' . esc_html( $line['phrase'] ) . '</span>';

			if ( 'live' === $mode ) {
				echo '<button class="lexical-lode-scramble" aria-label="' . esc_attr__( 'Scramble this line', 'lexical-lode' ) . '">&#x21bb;</button>';
			}

			echo '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Render source post titles as footnotes.
	 */
	private static function render_attribution_footnotes( $lines ) {
		$sources = array();
		foreach ( $lines as $line ) {
			$post_id = $line['post_id'];
			if ( ! isset( $sources[ $post_id ] ) ) {
				$sources[ $post_id ] = self::get_post_title( $post_id );
			}
		}

		echo '<footer class="lexical-lode-sources"><p>';
		echo esc_html__( 'Sources: ', 'lexical-lode' );
		$links = array();
		foreach ( $sources as $post_id => $title ) {
			$links[] = '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( $title ) . '</a>';
		}
		echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</p></footer>';
	}

	/**
	 * Rebuild the global live-ID registry from all per-post meta entries.
	 */
	private static function rebuild_live_ids_option() {
		global $wpdb;
		$rows = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_lexical_lode_live_ids'"
		);
		$all = array();
		foreach ( $rows as $row ) {
			$decoded = maybe_unserialize( $row );
			if ( is_array( $decoded ) ) {
				$all = array_merge( $all, $decoded );
			}
		}
		update_option( self::LIVE_IDS_OPTION, array_values( array_unique( array_map( 'intval', $all ) ) ), false );
	}

	/**
	 * Check if a post ID is registered as part of a live-mode block anywhere on the site.
	 *
	 * @param int $post_id The post ID to check.
	 * @return bool True if the post ID is in an active live block.
	 */
	public static function is_live_post_id( $post_id ) {
		$ids = get_option( self::LIVE_IDS_OPTION, array() );
		if ( ! is_array( $ids ) ) {
			return false;
		}
		return in_array( (int) $post_id, $ids, true );
	}

	/**
	 * On save_post, scan the saved post's content for live-mode Lexical Lode
	 * blocks, store the referenced post IDs as post meta, and rebuild the
	 * global live-ID registry. This ensures stale IDs are pruned when blocks
	 * are removed or switched away from live mode.
	 *
	 * @param int     $post_id The saved post ID.
	 * @param WP_Post $post    The post object.
	 */
	public static function sync_live_ids_on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( empty( $post->post_content ) || ! has_blocks( $post->post_content ) ) {
			delete_post_meta( $post_id, '_lexical_lode_live_ids' );
			self::rebuild_live_ids_option();
			return;
		}

		$blocks = parse_blocks( $post->post_content );
		$lines  = self::collect_live_lines_from_blocks( $blocks );
		$ids    = array_values( array_unique( array_map( fn( $l ) => (int) $l['post_id'], $lines ) ) );

		if ( ! empty( $ids ) ) {
			update_post_meta( $post_id, '_lexical_lode_live_ids', $ids );
		} else {
			delete_post_meta( $post_id, '_lexical_lode_live_ids' );
		}

		self::rebuild_live_ids_option();
	}

	/**
	 * When a post is deleted, remove its live-ID meta and rebuild.
	 *
	 * @param int $post_id The deleted post ID.
	 */
	public static function prune_live_ids_on_delete( $post_id ) {
		if ( get_post_meta( $post_id, '_lexical_lode_live_ids', true ) ) {
			delete_post_meta( $post_id, '_lexical_lode_live_ids' );
			self::rebuild_live_ids_option();
		}
	}

	/**
	 * When a post is unpublished, prune its live IDs from the registry.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       The post object.
	 */
	public static function prune_live_ids_on_unpublish( $new_status, $old_status, $post ) {
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			if ( get_post_meta( $post->ID, '_lexical_lode_live_ids', true ) ) {
				delete_post_meta( $post->ID, '_lexical_lode_live_ids' );
				self::rebuild_live_ids_option();
			}
		}
	}

	/**
	 * Recursively walk a parsed block tree and collect lines from
	 * live-mode Lexical Lode blocks.
	 *
	 * @param array $blocks Parsed block array from parse_blocks().
	 * @return array Array of line entries with 'post_id' keys.
	 */
	private static function collect_live_lines_from_blocks( $blocks ) {
		$collected = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if (
				isset( $block['blockName'] ) &&
				self::BLOCK_NAME === $block['blockName'] &&
				isset( $block['attrs']['mode'] ) &&
				'live' === $block['attrs']['mode'] &&
				! empty( $block['attrs']['lines'] ) &&
				is_array( $block['attrs']['lines'] )
			) {
				foreach ( $block['attrs']['lines'] as $line ) {
					if ( isset( $line['post_id'] ) ) {
						$collected[] = array( 'post_id' => (int) $line['post_id'] );
					}
				}
			}

			// Recurse into inner blocks if any.
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$collected = array_merge(
					$collected,
					self::collect_live_lines_from_blocks( $block['innerBlocks'] )
				);
			}
		}

		return $collected;
	}

	/**
	 * Enqueue the frontend scramble script (only for live mode blocks).
	 */
	private static function enqueue_frontend_script() {
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}

		// Don't attempt to enqueue during AJAX, REST, or cron contexts —
		// scripts enqueued there won't be output anywhere and just waste work.
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$enqueued = true;

		$asset_file = LEXICAL_LODE_PLUGIN_DIR . 'build/view.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => LEXICAL_LODE_VERSION,
		);

		wp_enqueue_script(
			'lexical-lode-view',
			LEXICAL_LODE_PLUGIN_URL . 'build/view.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_add_inline_script(
			'lexical-lode-view',
			'window.lexicalLodeFront = ' . wp_json_encode( array(
				'restUrl' => rest_url( Lexical_Lode_REST_API::REST_NAMESPACE ),
			) ) . ';',
			'before'
		);
	}
}
