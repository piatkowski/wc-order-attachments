<?php
defined( 'ABSPATH' ) || die( 'No direct access' );

global $post;
$files = WCOA_File_Access::get_customer_files( $post->ID );
?>
<ul id="wcoa_uploaded_files" class="wcoa_the_list">
	<?php
	foreach ( $files as $file_hash => $secret_path ) {
		echo '<li id="wcoa_' . $file_hash . '">';
		echo '<a href="' . WCOA_File_Access::get_customer_url( $post->ID, $file_hash ) . '" class="wcoa_title">' . basename( $secret_path ) . '</a>';
		echo '<a href="#" data-file-hash="' . $file_hash . '" class="wcoa_remove_file">X</a>';
		echo '</li>';
	}
	?>
</ul>
<h4 class="wcoa_hide_on_empty"><?php _e( 'Upload Queue', 'wc-order-attachments' ); ?></h4>
<?php wp_nonce_field( 'wcoa_upload', '_wcoa_nonce' ); ?>
<input type="file" id="wcoa_files" maxlength="10"
       accept="<?php esc_html_e( join( ',', get_allowed_mime_types() ) ); ?>" multiple>
<ul id="wcoa_upload_list" class="wcoa_the_list"></ul>
<button class="button" type="button" id="wcoa_add_file"><?php _e( '+ Add File(s)', 'wc-order-attachments' ); ?></button>
<button class="button button-primary wcoa_hide_on_empty" type="button"
        id="wcoa_do_upload"><?php _e( 'Upload files', 'wc-order-attachments' ); ?></button>
<?php
if ( WCOA_Plugin::DEBUG ) {
	echo '<button type="button" id="wcoa_clear_metadata">Clear Meta</button>';
}
?>
<div id="wcoa_progress">
    <div class="bar">0%</div>
</div>
<div id="wcoa_message"></div>
<div class="wcoa_hide_on_empty">
    <p class="small"><?php _e( 'The files will be immediately available to the buyer in the order details. If you want the files to be available in "Completed Order" email, please add the files before you mark the order as Complete', 'wc-order-attachments' ); ?></p>
</div>