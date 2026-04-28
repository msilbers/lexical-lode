<?php
/**
 * Extracts usable phrases from WordPress post content.
 */
class Lexical_Lode_Phrase_Extractor {

	const MIN_WORDS = 5;
	const MAX_WORDS = 9;

	/**
	 * Extract phrases from a single post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of phrase strings.
	 */
	public static function extract_from_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}

		$content = self::strip_to_plaintext( $post->post_content );
		return self::split_into_phrases( $content );
	}

	/**
	 * Strip post content down to plaintext while preserving punctuation.
	 *
	 * @param string $content Raw post content.
	 * @return string Cleaned plaintext.
	 */
	public static function strip_to_plaintext( $content ) {
		// Remove block comments (<!-- wp:... --> and <!-- /wp:... -->).
		$content = preg_replace( '/<!--\s*\/?wp:.*?-->/s', ' ', $content );

		// Process shortcodes — strip them entirely.
		$content = strip_shortcodes( $content );

		// Remove HTML tags but preserve the text inside them.
		$content = wp_strip_all_tags( $content );

		// Decode HTML entities so &amp; becomes & etc.
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

		// Normalize whitespace (collapse multiple spaces/newlines into single space).
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Split plaintext into phrases on periods, commas, semicolons, and em dashes.
	 * Keep only fragments that are 5-9 words.
	 *
	 * @param string $text Plaintext content.
	 * @return array Array of phrase strings.
	 */
	public static function split_into_phrases( $text ) {
		// Split on period, comma, semicolon, em dash (—), and en dash (–).
		$fragments = preg_split( '/[.,;]|—|–/u', $text );

		$phrases = array();
		foreach ( $fragments as $fragment ) {
			$fragment = trim( $fragment );
			if ( empty( $fragment ) ) {
				continue;
			}

			// Use Unicode-aware word counting: match runs of letters in any script.
			// This is consistent across server locales and handles non-ASCII text correctly.
			$word_count = preg_match_all( '/\pL+/u', $fragment );
			if ( $word_count >= self::MIN_WORDS && $word_count <= self::MAX_WORDS ) {
				$phrases[] = $fragment;
			}
		}

		return $phrases;
	}

	/**
	 * Get a random phrase from a specific post.
	 *
	 * @param int  $post_id          Post ID.
	 * @param bool $check_exclusions Whether to validate against excluded categories/tags.
	 * @return string|null A phrase, or null if none available or post is excluded.
	 */
	public static function get_random_phrase( $post_id, $check_exclusions = false ) {
		if ( $check_exclusions && self::is_post_excluded( $post_id ) ) {
			return null;
		}

		$phrases = self::extract_from_post( $post_id );
		if ( empty( $phrases ) ) {
			return null;
		}
		return $phrases[ array_rand( $phrases ) ];
	}

	/**
	 * Check if a post belongs to an excluded category or tag.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if the post should be excluded.
	 */
	public static function is_post_excluded( $post_id ) {
		$excluded_cats = get_option( 'lexical_lode_excluded_categories', array() );
		if ( is_array( $excluded_cats ) && ! empty( $excluded_cats ) ) {
			$post_cats = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
			if ( array_intersect( $post_cats, $excluded_cats ) ) {
				return true;
			}
		}

		$excluded_tags = get_option( 'lexical_lode_excluded_tags', array() );
		if ( is_array( $excluded_tags ) && ! empty( $excluded_tags ) ) {
			$post_tags = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
			if ( array_intersect( $post_tags, $excluded_tags ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get posts that have usable phrases, respecting exclusions.
	 *
	 * @param int    $count            Number of posts to retrieve.
	 * @param array  $exclude_post_ids Post IDs to exclude (already used).
	 * @param string $order            'random', 'newest', or 'oldest'.
	 * @return array Array of post IDs that have at least one usable phrase.
	 */
	public static function get_source_posts( $count, $exclude_post_ids = array(), $order = 'random' ) {
		$excluded_cats = get_option( 'lexical_lode_excluded_categories', array() );
		$excluded_tags = get_option( 'lexical_lode_excluded_tags', array() );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count * 3, // Fetch extra in case some have no usable phrases.
			'post__not_in'   => $exclude_post_ids,
			'fields'         => 'ids',
		);

		if ( 'newest' === $order ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		} elseif ( 'oldest' === $order ) {
			$args['orderby'] = 'date';
			$args['order']   = 'ASC';
		} else {
			$args['orderby'] = 'rand';
		}

		if ( is_array( $excluded_cats ) && ! empty( $excluded_cats ) ) {
			$args['category__not_in'] = $excluded_cats;
		}

		if ( is_array( $excluded_tags ) && ! empty( $excluded_tags ) ) {
			$args['tag__not_in'] = $excluded_tags;
		}

		$post_ids = get_posts( $args );

		// Filter to only posts that actually produce usable phrases.
		$valid_posts = array();
		foreach ( $post_ids as $post_id ) {
			$phrases = self::extract_from_post( $post_id );
			if ( ! empty( $phrases ) ) {
				$valid_posts[] = $post_id;
			}
			if ( count( $valid_posts ) >= $count ) {
				break;
			}
		}

		return $valid_posts;
	}

	/**
	 * Generate a full set of lines for a block.
	 *
	 * @param int    $line_count       Number of lines to generate.
	 * @param array  $exclude_post_ids Post IDs to exclude.
	 * @param string $order            'random', 'newest', or 'oldest'.
	 * @return array Array of [ 'post_id' => int, 'phrase' => string ] items.
	 */
	public static function generate_lines( $line_count, $exclude_post_ids = array(), $order = 'random' ) {
		$post_ids = self::get_source_posts( $line_count, $exclude_post_ids, $order );
		$lines    = array();

		foreach ( $post_ids as $post_id ) {
			$phrase = self::get_random_phrase( $post_id );
			if ( null !== $phrase ) {
				$lines[] = array(
					'post_id' => $post_id,
					'phrase'  => $phrase,
				);
			}
		}

		return $lines;
	}
}
