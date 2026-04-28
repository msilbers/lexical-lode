<?php
/**
 * Plugin Name: Lexical Lode
 * Description: Mine your site's blog posts for found poetry.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Author: Zeppo
 * License: GPL-2.0-or-later
 * Text Domain: lexical-lode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEXICAL_LODE_VERSION', '1.0.0' );
define( 'LEXICAL_LODE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEXICAL_LODE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LEXICAL_LODE_PLUGIN_DIR . 'includes/class-phrase-extractor.php';
require_once LEXICAL_LODE_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once LEXICAL_LODE_PLUGIN_DIR . 'includes/class-settings.php';
require_once LEXICAL_LODE_PLUGIN_DIR . 'includes/class-block.php';

add_action( 'init', 'lexical_lode_init' );
add_action( 'rest_api_init', 'lexical_lode_register_rest_routes' );
add_action( 'admin_menu', 'lexical_lode_admin_menu' );
add_action( 'admin_init', 'lexical_lode_register_settings' );

function lexical_lode_init() {
	load_plugin_textdomain( 'lexical-lode' );
	Lexical_Lode_Block::register();
}

function lexical_lode_register_rest_routes() {
	$api = new Lexical_Lode_REST_API();
	$api->register_routes();
}

function lexical_lode_admin_menu() {
	add_options_page(
		__( 'Lexical Lode', 'lexical-lode' ),
		__( 'Lexical Lode', 'lexical-lode' ),
		'manage_options',
		'lexical-lode',
		'lexical_lode_settings_page'
	);
}

function lexical_lode_settings_page() {
	Lexical_Lode_Settings::render_page();
}

function lexical_lode_register_settings() {
	Lexical_Lode_Settings::register();
}
