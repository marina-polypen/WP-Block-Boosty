<?php
/**
 * Plugin Name: WP Block Boosty
 * Description: Enhances Gutenberg blocks with advanced styling: lists, tables, quotes, images, galleries, headings, and more.
 * Version: 1.6.0
 * Author: MarinaP
 * Requires PHP: 8.0
 * Text Domain: wp-block-boosty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPBB_VERSION', '1.6.0' );
define( 'WPBB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBB_OPTION_KEY', 'wp_block_boosty_options' );

require_once WPBB_PLUGIN_DIR . 'includes/class-settings.php';
require_once WPBB_PLUGIN_DIR . 'includes/class-admin.php';
require_once WPBB_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Main plugin class — singleton.
 */
final class WP_Block_Boosty {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		WPBB_Settings::instance();
		WPBB_Admin::instance();
		WPBB_Frontend::instance();
	}

	/**
	 * Загрузка перевода.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-block-boosty', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}

WP_Block_Boosty::instance();
