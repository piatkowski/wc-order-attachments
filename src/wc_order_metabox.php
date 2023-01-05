<?php

/**
 * This class is used to add custom metabox on `shop_order` post type (WooCommerce Order)
 */
final class WCOA_WC_Order_Metabox {

	/**
	 * Metabox identifier
	 */
	const SLUG = 'wcoa_attachments_metabox';

	/**
	 * Register actions
	 * @return void
	 */
	static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
	}

	/**
	 * Callback for `add_meta_boxes` hook
	 *
	 * @param $post_type
	 *
	 * @return void
	 */
	static function add_meta_box( $post_type ) {
		if ( $post_type === 'shop_order' ) {
			add_meta_box(
				self::SLUG,
				__( 'Order Attachments', 'wc-order-attachments' ),
				[ __CLASS__, 'render_attachments_metabox' ],
				'shop_order',
				'side'
			);
		}
	}

	/**
	 * Prints metabox content
	 * @return void
	 */
	static function render_attachments_metabox() {
		include __DIR__ . '/../views/attachments_metabox.php';
	}
}