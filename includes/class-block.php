<?php
/**
 * Gutenberg block registration and server-side rendering for Lexical Lode.
 */
class Lexical_Lode_Block {

	/**
	 * Block name as registered in block.json. Used to derive script handles.
	 */
	const BLOCK_NAME = 'lexical-lode/lexical-lode-block';

	public static function register() {
		register_block_type( LEXICAL_LODE_PLUGIN_DIR . 'build', array(
			'render_callback' => array( __CLASS__, 'render' ),
		) );

		// Pass settings data to the block editor.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_data' ) );
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
				'formats' => $formats,
				'restUrl' => rest_url( Lexical_Lode_REST_API::REST_NAMESPACE ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
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

		if ( ! in_array( $attribution, array( 'hidden', 'hover', 'footnotes' ), true ) ) {
			$attribution = 'hidden';
		}

		if ( empty( $lines ) ) {
			return '';
		}

		$wrapper_attrs = get_block_wrapper_attributes( array(
			'class'          => 'lexical-lode-block lexical-lode-format-' . esc_attr( $format ),
			'data-format'    => esc_attr( $format ),
		) );

		ob_start();
		// $wrapper_attrs is returned pre-escaped by get_block_wrapper_attributes().
		echo '<div ' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 'prose' === $format ) {
			self::render_prose( $lines, $attribution );
		} elseif ( 'list' === $format ) {
			self::render_list( $lines, $attribution );
		} else {
			self::render_lines( $lines, $format, $attribution );
		}

		if ( 'footnotes' === $attribution ) {
			self::render_attribution_footnotes( $lines );
		}

		echo '</div>';

		if ( 'hover' === $attribution ) {
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
	private static function render_lines( $lines, $format, $attribution ) {
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
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render as a prose paragraph.
	 */
	private static function render_prose( $lines, $attribution ) {
		echo '<p class="lexical-lode-prose">';
		foreach ( $lines as $index => $line ) {
			$hover_attr = '';
			if ( 'hover' === $attribution ) {
				$title = self::get_post_title( $line['post_id'] );
				$url   = get_permalink( $line['post_id'] );
				$hover_attr = ' data-post-title="' . esc_attr( $title ) . '" data-post-url="' . esc_attr( $url ) . '"';
			}

			echo '<span class="lexical-lode-phrase" data-post-id="' . esc_attr( $line['post_id'] ) . '"' . $hover_attr . '>' . esc_html( $line['phrase'] ) . '</span>';

			if ( $index < count( $lines ) - 1 ) {
				echo ' ';
			}
		}
		echo '</p>';
	}

	/**
	 * Render as a numbered/bulleted list.
	 */
	private static function render_list( $lines, $attribution ) {
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
	 * Enqueue the frontend script (for hover attribution popovers).
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

	}
}
