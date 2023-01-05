<?php

/*
Plugin Name: Order Attachments for WooCommerce
Description: Plugin allows to add attachments to orders in WooCommerce. Files are protected and available for download by the buyer.
Version: 1.0.1
Author: Krzysztof Piątkowski
License: GPLv2
*/

defined( 'ABSPATH' ) || die( 'No direct access.' );

include __DIR__ . '/loader.php';

final class WCOA_Plugin {
	const VERSION = '1.0.1';
	const DEBUG = false;

	/**
	 * Initializes plugin
	 * @return void
	 */
	static function run() {
		register_activation_hook( __FILE__, [ __CLASS__, 'activate' ] );
		register_uninstall_hook( __FILE__, [ __CLASS__, 'deactivate' ] );
		register_deactivation_hook( __FILE__, [ __CLASS__, 'deactivate' ] );
		WCOA_WC_Order_Metabox::init();
		WCOA_Attachment_Uploader::init();
		WCOA_File_Access::init();
		WCOA_Customer_Integrator::init();
	}

	/**
	 * Plugin activation hook
	 * Create random secret folder under wp-content/uploads. Adds .htaccess and index file.
	 * @return void
	 */
	static function activate() {
		$upload_path = get_option( 'wcoa_upload_path' );
		if ( $upload_path === false ) {
			$upload_path = WP_CONTENT_DIR . '/uploads/wcoa_' . uniqid();
			if ( wp_mkdir_p( $upload_path ) ) {
				add_option( 'wcoa_upload_path', $upload_path );
			} else {
				echo __( 'Upload directory cannot be created', 'wc-order-attachments' );
			}
		}
		if ( $upload_path ) {
			if ( ! file_exists( $upload_path . '/index.php' ) ) {
				file_put_contents( $upload_path . '/index.php', '' );
			}
			if ( ! file_exists( $upload_path . '/.htaccess' ) ) {
				file_put_contents( $upload_path . '/.htaccess', 'deny from all' );
			}
		}

		$salt = uniqid() . uniqid();
		update_option( 'wcoa_salt', $salt );
		add_rewrite_rule( WCOA_File_Access::SLUG . '/([0-9]+)/([a-z0-9]{32})[/]?$', 'index.php?wcoa_order_id=$matches[1]&wcoa_protected_download=$matches[2]&wcoa_salt=' . $salt, 'top' );
		flush_rewrite_rules();
	}

	/**
	 * Flushes rewrite rules on plugin's deactivation or uninstallation
	 * @return void
	 */
	static function deactivate() {
		flush_rewrite_rules();
		delete_option('wcoa_salt');
	}

	/**
	 * Get plugin's path
	 *
	 * @param $dir plugin's directory path if 'true'
	 *
	 * @return string
	 */
	static function get_path( $dir = false ) {
		return $dir ? __DIR__ : __FILE__;
	}
}

WCOA_Plugin::run();