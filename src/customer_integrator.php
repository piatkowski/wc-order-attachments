<?php

final class WCOA_Customer_Integrator {

	static function init() {
		add_action( 'woocommerce_view_order', [
			__CLASS__,
			'woocommerce_order_details_table'
		], 10 ); //alternative: woocommerce_after_order_details
		add_action( 'woocommerce_email_order_details', [
			__CLASS__,
			'woocommerce_email_order_details'
		], 10, 4 );
	}

	static function woocommerce_email_order_details( $order, $sent_to_admin, $plain_text, $email ) {
		@self::woocommerce_order_details_table( $order->get_id() );
	}

	static function woocommerce_order_details_table( $order_id ) {
		if ( $order_id > 0 ) {
			$files = WCOA_File_Access::get_customer_files( $order_id );
			if ( $files ) {
				echo '<h2>' . __( 'Download Files', 'wc-order-attachments' ) . '</h2>';
				echo '<ul class="wcoa_customer_download_list">';
				foreach ( $files as $file_hash => $secret_path ) {
					?>
                    <li>
                        <a href="<?php echo esc_url( WCOA_File_Access::get_customer_url( $order_id, $file_hash ) ); ?>">
							<?php echo esc_html( basename( $secret_path ) ); ?>
                        </a>
                    </li>
					<?php
				}
				echo '</ul>';
			}
		}
	}

}