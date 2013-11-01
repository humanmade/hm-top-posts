<?php

/**
 * Class HMTP_Opt_Out
 */
class HMTP_Opt_Out {

	/**
	 * Instance of this class.
	 *
	 * @var
	 */
	private static $instance;

	/**
	 * Initialization
	 */
	private function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'setup' ) );
		add_action( 'save_post', array( $this, 'meta_box_save' ) );

	}

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @return  A single instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;

	}

	/**
	 * Adds a metabox for the opt out setting
	 */
	public function setup() {

		add_meta_box( 'hmtp_top_posts_optout_meta_box', 'Top Posts Opt Out', array( $this, 'meta_box' ), 'post', 'normal' );

	}

	/**
	 * Display metabox fields
	 *
	 * @param $post
	 * @param $metabox
	 */
	public function meta_box( $post, $metabox ) { ?>

		<label for="hmtp_top_posts_optout">
			<input type="checkbox" id="hmtp_top_posts_optout" name="hmtp_top_posts_optout" <?php checked( get_post_meta( $post->ID, 'hmtp_top_posts_optout', true ), 'on' ); ?>/>
			Opt out of Top Post lists.
		</label>

		<br />

		<span class="description" style="display: inline-block; margin-top: 5px; ">
			By checking this box, this article will not be shown in the Top/Most Read lists.
		</span>

		<?php wp_nonce_field( 'hmtp_top_posts_optout', 'hmtp_top_posts_optout_nonce' ); ?>

	<?php
	}

	/**
	 * Save post data
	 */
	public function meta_box_save() {

		global $post;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( ! isset( $_POST['hmtp_top_posts_optout_nonce'] ) || ! wp_verify_nonce( $_POST['hmtp_top_posts_optout_nonce'], 'hmtp_top_posts_optout' ) )
			return;

		if ( isset( $_POST['hmtp_top_posts_optout'] ) && $_POST['hmtp_top_posts_optout'] == 'on' )
			update_post_meta( $post->ID, 'hmtp_top_posts_optout', 'on' );

		else
			delete_post_meta( $post->ID, 'hmtp_top_posts_optout' );

	}

}
