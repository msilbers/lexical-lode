<?php
/**
 * Plugin settings page for Lexical Lode.
 */
class Lexical_Lode_Settings {

	const FORMATS = array(
		'sonnet'     => 'Sonnet',
		'free_verse' => 'Free Verse',
		'couplets'   => 'Couplets',
		'prose'      => 'Prose Paragraph',
		'list'       => 'List / Aphorisms',
	);

	public static function register() {
		register_setting( 'lexical_lode_settings', 'lexical_lode_enabled_formats', array(
			'type'              => 'array',
			'default'           => array_keys( self::FORMATS ),
			'sanitize_callback' => array( __CLASS__, 'sanitize_formats' ),
		) );

		register_setting( 'lexical_lode_settings', 'lexical_lode_excluded_categories', array(
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => array( __CLASS__, 'sanitize_ids' ),
		) );

		register_setting( 'lexical_lode_settings', 'lexical_lode_excluded_tags', array(
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => array( __CLASS__, 'sanitize_ids' ),
		) );


		add_settings_section(
			'lexical_lode_formats_section',
			__( 'Enabled Formats', 'lexical-lode' ),
			function () {
				echo '<p>' . esc_html__( 'Choose which formats appear in the block format picker.', 'lexical-lode' ) . '</p>';
			},
			'lexical-lode'
		);

		add_settings_field(
			'lexical_lode_formats',
			__( 'Formats', 'lexical-lode' ),
			array( __CLASS__, 'render_formats_field' ),
			'lexical-lode',
			'lexical_lode_formats_section'
		);

		add_settings_section(
			'lexical_lode_exclusions_section',
			__( 'Post Exclusions', 'lexical-lode' ),
			function () {
				echo '<p>' . esc_html__( 'Exclude posts by category or tag from being used as source material.', 'lexical-lode' ) . '</p>';
			},
			'lexical-lode'
		);

		add_settings_field(
			'lexical_lode_excluded_categories',
			__( 'Excluded Categories', 'lexical-lode' ),
			array( __CLASS__, 'render_categories_field' ),
			'lexical-lode',
			'lexical_lode_exclusions_section'
		);

		add_settings_field(
			'lexical_lode_excluded_tags',
			__( 'Excluded Tags', 'lexical-lode' ),
			array( __CLASS__, 'render_tags_field' ),
			'lexical-lode',
			'lexical_lode_exclusions_section'
		);

	}

	public static function render_formats_field() {
		$enabled = get_option( 'lexical_lode_enabled_formats', array_keys( self::FORMATS ) );
		if ( ! is_array( $enabled ) ) {
			$enabled = array_keys( self::FORMATS );
		}
		foreach ( self::FORMATS as $key => $label ) {
			printf(
				'<label><input type="checkbox" name="lexical_lode_enabled_formats[]" value="%s" %s> %s</label><br>',
				esc_attr( $key ),
				checked( in_array( $key, $enabled, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	public static function render_categories_field() {
		$excluded = get_option( 'lexical_lode_excluded_categories', array() );
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}
		$categories = get_categories( array( 'hide_empty' => false ) );
		foreach ( $categories as $cat ) {
			printf(
				'<label><input type="checkbox" name="lexical_lode_excluded_categories[]" value="%d" %s> %s</label><br>',
				$cat->term_id,
				checked( in_array( $cat->term_id, $excluded, true ), true, false ),
				esc_html( $cat->name )
			);
		}
		if ( empty( $categories ) ) {
			echo '<p>' . esc_html__( 'No categories found.', 'lexical-lode' ) . '</p>';
		}
	}

	public static function render_tags_field() {
		$excluded = get_option( 'lexical_lode_excluded_tags', array() );
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}
		$tags = get_tags( array( 'hide_empty' => false ) );
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				printf(
					'<label><input type="checkbox" name="lexical_lode_excluded_tags[]" value="%d" %s> %s</label><br>',
					$tag->term_id,
					checked( in_array( $tag->term_id, $excluded, true ), true, false ),
					esc_html( $tag->name )
				);
			}
		} else {
			echo '<p>' . esc_html__( 'No tags found.', 'lexical-lode' ) . '</p>';
		}
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'lexical_lode_settings' );
				do_settings_sections( 'lexical-lode' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function sanitize_formats( $input ) {
		if ( ! is_array( $input ) ) {
			return array_keys( self::FORMATS );
		}
		return array_intersect( $input, array_keys( self::FORMATS ) );
	}

	public static function sanitize_ids( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $input ) ) );
	}

	/**
	 * Get enabled formats for use in the block editor.
	 */
	public static function get_enabled_formats() {
		$enabled = get_option( 'lexical_lode_enabled_formats', array_keys( self::FORMATS ) );
		if ( ! is_array( $enabled ) ) {
			$enabled = array_keys( self::FORMATS );
		}
		$formats = array();
		foreach ( $enabled as $key ) {
			if ( isset( self::FORMATS[ $key ] ) ) {
				$formats[] = array(
					'value' => $key,
					'label' => self::FORMATS[ $key ],
				);
			}
		}
		return $formats;
	}
}
