<?php
/**
 * Удаление плагина — очистка опций и transient-кеша.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_block_boosty_options' );
delete_transient( 'wpbb_dynamic_css' );
