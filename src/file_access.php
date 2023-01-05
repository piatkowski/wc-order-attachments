<?php

/**
 * This class allows access to the uploaded files.
 * Files should be available to admin, shop managers and customer.
 */
final class WCOA_File_Access {

	const SLUG = 'order-file';

	static function init() {
		add_filter( 'query_vars', [ __CLASS__, 'add_query_var' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_download' ] );
	}

	static function add_query_var( $query_vars ) {
		$query_vars[] = 'wcoa_order_id';
		$query_vars[] = 'wcoa_protected_download';
		$query_vars[] = 'wcoa_salt';

		return $query_vars;
	}

	static function get_customer_url( $order_id, $file_hash ) {
		return get_site_url() . '/' . self::SLUG . '/' . $order_id . '/' . $file_hash;
	}

	static function get_customer_files( $order_id ) {
		if ( $order_id > 0 && get_current_user_id() > 0 ) {
			try {
				$order = new WC_Order( $order_id );
			} catch ( \Exception $e ) {
				return false;
			}
			if ( current_user_can( 'manage_woocommerce' ) || $order->get_customer_id() === get_current_user_id() ) {
				return $order->get_meta( 'wcoa_files' );
			}
		}
	}

	private static function get_authorized_file_path( $order_id, $file_hash ) {
		if ( $order_id > 0 && get_current_user_id() > 0 ) {
			try {
				$order = new WC_Order( $order_id );
			} catch ( \Exception $e ) {
				return false;
			}
			if ( current_user_can( 'manage_woocommerce' ) || $order->get_customer_id() === get_current_user_id() ) {
				$files = $order->get_meta( 'wcoa_files' );
				if ( ! empty( $files ) && is_array( $files ) && ! empty( $files[ $file_hash ] ) ) {
					return $files[ $file_hash ];
				}
			}
		}

		return false;
	}

	static function handle_download() {

		$salt = get_query_var( 'wcoa_salt' );

		if ( ! empty( $salt ) ) {

			if ( ! is_user_logged_in() ) {
				wp_redirect( wp_login_url() );
				exit;
			}

			if ( $salt !== get_option( 'wcoa_salt' ) ) {
				wp_send_json_error( 'No direct access' );
			}

			$order_id = absint( get_query_var( 'wcoa_order_id' ) );
			if ( $order_id === 0 ) {
				wp_send_json_error( 'Post ID is invalid' );
			}

			$file_hash = get_query_var( 'wcoa_protected_download' );
			if ( empty( $file_hash ) ) {
				wp_send_json_error( 'File hash is invalid' );
			}

			$file_path = self::get_authorized_file_path( $order_id, $file_hash );

			if ( $file_path !== false ) {
				if ( ob_get_level() ) {
					ob_end_clean();
				}
				while ( ob_get_level() ) {
					ob_end_clean();
				}
				header( "Content-Description: File Transfer" );
				header( "Content-Type: application/octet-stream" );
				header( "Content-Disposition: attachment; filename=\"" . basename( $file_path ) . "\"" );
				readfile( $file_path );
			} else {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				get_template_part( 404 );
			}
			exit;
		}
	}

}