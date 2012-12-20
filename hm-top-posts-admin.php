<?php

/**
 * Register the Google analytics settings
 *
 * @access public
 * @return null
 */
function hmtp_top_posts_setting_admin_init() {
	register_setting( 'hmtp_top_posts_setting', 'hmtp_top_posts_setting_profile_id' );
	register_setting( 'hmtp_top_posts_setting', 'hmtp_top_posts_setting_username' );
	register_setting( 'hmtp_top_posts_setting', 'hmtp_top_posts_setting_password', 'hmtp_top_posts_setting_option_sanitize' );
	register_setting( 'hmtp_top_posts_setting', 'hmtp_top_posts_setting_auth_delete', 'hmtp_top_posts_setting_option_auth_delete' );
	register_setting( 'hmtp_top_posts_setting', 'hmtp_top_posts_allow_opt_out' );
}
add_action( 'admin_init', 'hmtp_top_posts_setting_admin_init' );

/**
 * Add the auto links page to the settings menu
 *
 * @access public
 * @return null
 */
function hmtp_top_posts_setting_admin_menu() {
	add_options_page( 'HM Top Posts', 'Top Posts', 'manage_options', 'hmtp_top_posts_setting', 'hmtp_top_posts_setting_admin_page' );
}
add_action( 'admin_menu', 'hmtp_top_posts_setting_admin_menu' );

/**
 * Output the auto links admin page
 *
 * @access public
 * @return null
 */
function hmtp_top_posts_setting_admin_page() { ?>

	<style>
		#hmtp_top_posts_setting_existing th { text-align: left; vertical-align: top; width: 150px;  }
		#hmtp_top_posts_setting_existing th,
		#hmtp_top_posts_setting_existing td { padding-bottom: 10px; }

	</style>

	<div class="wrap">

		<h2>Top Posts Settings</h2>

		<p>Top posts uses google analytics data. Enter your details below.</p>
		<p>Your password is not saved, only used to generate an auth token.</p>

		<form action="options.php" method="post">

			<table id="hmtp_top_posts_setting_existing">

			    <tr>
				    <th>Profile Id</th>
			    	<td><input type="text" name="hmtp_top_posts_setting_profile_id" value="<?php echo get_option( 'hmtp_top_posts_setting_profile_id' ); ?>" class="regular-text"/></td>
			    </tr>

			    <tr>
				    <th>Username</th>
			    	<td><input type="text" name="hmtp_top_posts_setting_username" value="<?php echo get_option( 'hmtp_top_posts_setting_username' ); ?>" class="regular-text"/></td>
			    </tr>

			    <tr>
				    <th>Password</th>
			    	<td><input type="password" name="hmtp_top_posts_setting_password" value="<?php echo get_option( 'hmtp_top_posts_setting_password' ); ?>"  class="regular-text"/></td>
			    </tr>

				<?php if ( $auth_code = get_option('hmtp_top_posts_setting_auth_code') ) : ?>

				    <tr>
					    <th>Auth Code</th>
				    	<td>
				    		<input readonly="readonly" class="regular-text" value="<?php echo $auth_code; ?>" /><br/>
				    		<label><input type="checkbox" name="hmtp_top_posts_setting_auth_delete" /> Check box to delete auth code.</label>
				    	</td>
				    </tr>

				<?php endif; ?>


				    <tr>
					    <th>Enable Post opt-out.</th>
				    	<td>
				    		<label><input type="checkbox" name="hmtp_top_posts_allow_opt_out" <?php checked( 'on', get_option( 'hmtp_top_posts_allow_opt_out' ) ); ?> /> Allow opting out on a per post basis.</label>
				    	</td>
				    </tr>


		</table>

		<?php settings_fields( 'hmtp_top_posts_setting' ); ?>

		<p class="submit"><input type="submit" name="hmtp_top_posts_setting_submit" id="submit" class="button-primary" value="Save Changes"></p>

		</form>

	</div>


	<?php

	$top_posts = new HMTP_Top_Posts();
	$results = $top_posts->get_results();

	if ( $results ) : ?>

		<h4>Top Posts</h4>
		<ul>
			<?php foreach ( $results as $post ) : ?>
				<li><?php printf( '%s (%d)', get_the_title( $post['post_id'] ), $post['views'] ); ?></li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

<?php }

/**
 * hmtp_top_posts_setting_option_sanitize function.
 *
 * sanitizes the related_articles options, converting to the format term => link
 * called from the register_settings function.
 *
 */
function hmtp_top_posts_setting_option_sanitize( $password ) {

	$username = get_option( 'hmtp_top_posts_setting_username' );
	$ga_profile_id = get_option( 'hmtp_top_posts_setting_profile_id' );

	if ( empty( $password ) || ! $username )
		return null;

	$ga = new gapi( $username, $password );

	try {
		$auth_token = $ga->getAuthToken();
		delete_option( 'hmtp_top_posts_error_message' );
	} catch( Exception $e ){
		update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
		return;
	}

	if ( ! empty( $auth_token ) )
		update_option( 'hmtp_top_posts_setting_auth_code', $auth_token );

	return $password;

}

function hmtp_top_posts_setting_option_auth_delete( $auth_delete ){

	if ( isset( $auth_delete ) && $auth_delete == 'on' ) {
		delete_option( 'hmtp_top_posts_setting_auth_code' );
		delete_option( 'hmtp_top_posts_error_message' );
	}

	return false;

}