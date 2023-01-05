<?php

/**
 * This class is used to handle all upload features of the plugin
 */
final class WCOA_Attachment_Uploader {

	/**
	 * Registers WP actions
	 * @return void
	 */
	static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_wcoa_upload', [ __CLASS__, 'upload' ] );
		add_action( 'wp_ajax_wcoa_remove_file', [ __CLASS__, 'remove_file' ] );
		if ( WCOA_Plugin::DEBUG ) {
			add_action( 'wp_ajax_wcoa_clear_metadata', [ __CLASS__, 'clear_metadata' ] );
		}
	}

	/**
	 * Overrides upload path used by `wp_handle_upload` in this class
	 *
	 * @param $param
	 *
	 * @return mixed
	 */
	static function upload_dir( $param ) {

		$upload_path = self::get_upload_path();

		if ( ! file_exists( $upload_path ) ) {
			wp_mkdir_p( $upload_path );
		}

		if ( ! file_exists( $upload_path . '/index.php' ) ) {
			file_put_contents( $upload_path . '/index.php', '' );
		}
		if ( ! file_exists( $upload_path . '/.htaccess' ) ) {
			file_put_contents( $upload_path . '/.htaccess', 'deny from all' );
		}

		$upload_path     = $upload_path . '/' . date( 'Y/m' );
		$param['path']   = $upload_path;
		$param['subdir'] = str_replace( $param['basedir'], '', $param['path'] );

		return $param;
	}

	/**
	 * Gets plugin's upload path. The path is generated on first activation.
	 * @return false|mixed|void
	 */
	private static function get_upload_path() {
		return get_option( 'wcoa_upload_path' );
	}

	/**
	 * Enqueues JS scripts and CSS styles on `shop_order` post type in WP-Admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	static function enqueue_scripts( $hook ) {
		$screen = get_current_screen();
		if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $screen && $screen->post_type === 'shop_order' ) {
			wp_register_script(
				'wcoa-attachments-metabox-js',
				plugins_url( 'assets/js/admin' . ( WCOA_Plugin::DEBUG ? '' : '.min' ) . '.js', WCOA_Plugin::get_path() ),
				[ 'jquery' ],
				WCOA_Plugin::VERSION,
				true
			);
			wp_register_style(
				'wcoa-attachments-metabox-css',
				plugins_url( 'assets/css/styles' . ( WCOA_Plugin::DEBUG ? '' : '.min' ) . '.css', WCOA_Plugin::get_path() ),
				null,
				WCOA_Plugin::VERSION
			);

			wp_enqueue_script( 'wcoa-attachments-metabox-js' );
			wp_enqueue_style( 'wcoa-attachments-metabox-css' );
		}
	}

	/**
	 * Adds uploaded files path to the post meta data. Returns list of all files uploaded to the post
	 *
	 * @param $order_id
	 * @param $file_paths
	 *
	 * @return mixed|void
	 */
	private static function add_order_files( $order_id, $file_paths ) {
		if ( $order_id > 0 ) {
			$meta = get_post_meta( $order_id, 'wcoa_files', true );

			if ( $meta && is_array( $meta ) ) {
				$new_meta = array_merge( $meta, $file_paths );
			} else {
				$new_meta = $file_paths;
			}
			update_post_meta( $order_id, 'wcoa_files', $new_meta );

			return get_post_meta( $order_id, 'wcoa_files', true );
		}
	}

	/**
	 * Debug only ! Clears post meta data
	 * @return void
	 */
	static function clear_metadata() {
		if ( is_admin() && WCOA_Plugin::DEBUG ) {
			$order_id = absint( $_POST['order_id'] );
			if ( $order_id > 0 ) {
				delete_post_meta( $order_id, 'wcoa_files' );
				wp_send_json_success( 'Ok' );
			}
		}
		wp_send_json_error( 'Admin debug only' );
	}

	/**
	 * Removes file from the post meta data and file system
	 *
	 * @return void
	 */
	public static function remove_file() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wc-order-attachments' ) );
		}

		if ( ! wp_verify_nonce( $_POST['_wcoa_nonce'], 'wcoa_upload' ) ) {
			wp_send_json_error( __( 'Bad request (invalid nonce)', 'wc-order-attachments' ) );
		}

		$order_id = 0;
		if ( ! empty( $_POST['order_id'] ) ) {
			$order_id = absint( $_POST['order_id'] );
		}

		if ( empty( $_POST['order_id'] ) || $order_id <= 0 ) {
			wp_send_json_error( __( 'Order_id is required', 'wc-order-attachments' ) );
		}

		if ( empty( $_POST['file_hash'] ) ) {
			wp_send_json_error( __( 'File hash is required', 'wc-order-attachments' ) );
		}

		$file_hash = sanitize_text_field( $_POST['file_hash'] );

		$files = WCOA_File_Access::get_customer_files( $order_id );
		if ( ! empty( $files[ $file_hash ] ) ) {
			@unlink( $files[ $file_hash ] );
			unset( $files[ $file_hash ] );
			update_post_meta( $order_id, 'wcoa_files', $files );
			wp_send_json_success( 'OK' );
		}

		wp_send_json_success( __( 'File not found.', 'wc-order-attachments' ) );
	}

	private static function uploaded_files_metabox( $order_id ) {
		$files  = WCOA_File_Access::get_customer_files( $order_id );
		$output = '';
		foreach ( $files as $file_hash => $secret_path ) {
			$output .= '<li id="wcoa_' . $file_hash . '">';
			$output .= '<a href="' . WCOA_File_Access::get_customer_url( $order_id, $file_hash ) . '" class="wcoa_title">' . basename( $secret_path ) . '</a>';
			$output .= '<a href="#" data-file-hash="' . $file_hash . '" class="wcoa_remove_file">X</a>';
			$output .= '</li>';
		}

		return $output;
	}

	/**
	 * Ajax action handles multiple file upload.
	 * Action is available to users who can manage_woocommerce.
	 * All default wp mime types are allowed.
	 * @return void
	 */
	static function upload() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'wc-order-attachments' ) );
		}

		if ( ! wp_verify_nonce( $_POST['_wcoa_nonce'], 'wcoa_upload' ) ) {
			wp_send_json_error( __( 'Bad request (invalid nonce)', 'wc-order-attachments' ) );
		}

		$order_id = 0;
		if ( ! empty( $_POST['order_id'] ) ) {
			$order_id = absint( $_POST['order_id'] );
		}

		if ( empty( $_POST['order_id'] ) || $order_id <= 0 ) {
			wp_send_json_error( __( 'Order_id is required', 'wc-order-attachments' ) );
		}

		if ( empty( $_FILES ) ) {
			wp_send_json_error( __( 'No files to upload', 'wc-order-attachments' ) );
		}

		add_filter( 'upload_dir', [ __CLASS__, 'upload_dir' ] );
		$file_index     = 0;
		$upload_message = [];
		$uploaded_files = [];
		foreach ( $_FILES as $file ) {
			$uploaded = wp_handle_upload( $file, [ 'action' => 'wcoa_upload' ] );
			if ( ! empty( $uploaded['file'] ) ) {
				$upload_message[ $file_index ]                  = 'OK';
				$uploaded_files[ wp_hash( $uploaded['file'] ) ] = $uploaded['file'];
			} else if ( ! empty( $uploaded['error'] ) ) {
				$upload_message[ $file_index ] = $uploaded['error'];
			}
			$file_index ++;
		}
		remove_filter( 'upload_dir', [ __CLASS__, 'upload_dir' ] );
		self::add_order_files( $order_id, $uploaded_files );
		wp_send_json_success( [
			'file_list' => self::uploaded_files_metabox( $order_id ),
			'uploads'   => $upload_message
		] );
	}
}