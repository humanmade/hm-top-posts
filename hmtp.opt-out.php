<?php

add_action( 'add_meta_boxes', 'hmtp_top_posts_optout_setup' );
add_action( 'save_post', 'hmtp_top_posts_optout_meta_box_save' );

function hmtp_top_posts_optout_setup() {

	if ( get_option( 'hmtp_top_posts_allow_opt_out' ) )
    	add_meta_box(  'hmtp_top_posts_optout_meta_box', 'Top Posts Opt Out', 'hmtp_top_posts_optout_meta_box', 'post', 'normal' );

}

function hmtp_top_posts_optout_meta_box( $post, $metabox ) {  ?>

	<label for="hmtp_top_posts_optout">
		<input type="checkbox" id="hmtp_top_posts_optout" name="hmtp_top_posts_optout" <?php checked( get_post_meta( $post->ID, 'hmtp_top_posts_optout', true  ), 'on' ); ?>/>
		Opt out of Top Post lists.
	</label>

	<br />

	<span class="description" style="display: inline-block; margin-top: 5px; ">
		By checking this box, this article will not be shown in the Top/Most Read lists.
	</span>

	<?php echo wp_nonce_field( 'hmtp_top_posts_optout', 'hmtp_top_posts_optout_nonce' ); ?>

	<?php
}

function hmtp_top_posts_optout_meta_box_save() {

	global $post;

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return;

	if ( ! isset( $_POST['hmtp_top_posts_optout_nonce'] ) || ! wp_verify_nonce( $_POST['hmtp_top_posts_optout_nonce'], 'hmtp_top_posts_optout'  ) )
		return;

	if ( isset( $_POST['hmtp_top_posts_optout'] ) && $_POST['hmtp_top_posts_optout'] == 'on' )
		update_post_meta( $post->ID, 'hmtp_top_posts_optout', 'on' );

	else
		delete_post_meta( $post->ID, 'hmtp_top_posts_optout' );

}


