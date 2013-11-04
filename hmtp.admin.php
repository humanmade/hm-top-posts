<?php

class HMTP_Admin {

	/**
	 * An instance of this class
	 *
	 * @var object
	 */
	protected static $_instance = null;
	
	private $ga_client = null;
	private $ga_service = null;
	private $ga_property_account_id = null;
	private $ga_property_id = null;

	public function __construct( $settings, Google_Client $ga_client, Google_AnalyticsService $ga_analytics ) {
		
		$this->settings = $settings;
		$this->ga_client = $ga_client;
		$this->ga_service = $ga_analytics;

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'admin_menu', function () {
			add_options_page( 'HM Top Posts', 'Top Posts', 'manage_options', 'hmtp_settings_page', array( $this, 'settings_page' ) );
		} );

	}

	/**
	 * Should not be able to clone singletons
	 */
	final private function __clone(){}

	function init() {

		// Deauthenticate.
		if ( isset( $_GET['hmtp_deauth'] ) && wp_verify_nonce( $_GET['hmtp_deauth'], 'hmtp_deauth' ) ) {
			
			delete_option( 'hmtp_setting' );
			delete_option( 'hmtp_ga_token' );
			
			wp_safe_redirect( add_query_arg( 
				array( 'page' => 'hmtp_settings_page' ), 
				get_admin_url() . 'options-general.php'
			) );

			exit;
		}

	}

	function admin_init() {

		register_setting( 
			'hmtp_settings', 
			'hmtp_setting', 
			array( $this, 'hmtp_settings_sanitize' )
		);

		add_settings_section(
			'hmtp_settings_section',
			'Top Posts Settings',
			array( $this, 'hmtp_settings_section_display' ),
			'hmtp_settings_page'
		);

		add_settings_field(
			'hmtp_settings_client_id',
			'Client ID',
			array( $this, 'hmtp_settings_field_client_id_display' ),
			'hmtp_settings_page',
			'hmtp_settings_section'
		);

		add_settings_field(
			'hmtp_settings_client_secret',
			'Client Secret',
			array( $this, 'hmtp_settings_field_client_secret_display' ),
			'hmtp_settings_page',
			'hmtp_settings_section'
		);

		add_settings_field(
			'hmtp_settings_api_key',
			'API Key',
			array( $this, 'hmtp_settings_field_api_key_display' ),
			'hmtp_settings_page',
			'hmtp_settings_section'
		);

		add_settings_field(
			'hmtp_settings_redirect_url',
			'Redirect URL',
			array( $this, 'hmtp_settings_field_redirect_display' ),
			'hmtp_settings_page',
			'hmtp_settings_section'
		);

		add_settings_field(
			'hmtp_settings_property',
			'Select Web Property',
			array( $this, 'hmtp_settings_field_property_display' ),
			'hmtp_settings_page',
			'hmtp_settings_section'
		);

		add_settings_field(
			'hmtp_settings_allow_opt_out',
			'Allow Opt-Out',
			array( $this, 'hmtp_settings_field_opt_out_display' ),
			'hmtp_settings_page',
			'hmtp_settings_section'
		);

	}

	public function settings_page() {

		?>

        <form action="options.php" method="POST">
			
			<?php

			settings_fields('hmtp_settings');	
			do_settings_sections('hmtp_settings_page');
			submit_button();

			?>

		</form>

		<?php
		
		// Demo.
		$results = hmtp_get_top_posts( array() );
		
		?>
		
		<h4>Top Posts</h4>
		
		<?php if ( $results ) : ?>
			<ul>
				<?php foreach ( $results as $post ) : ?>
					<li><?php printf( '%s (%d)', get_the_title( $post['post_id'] ), $post['views'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p>No posts found</p>
		<?php endif;

	}

	public function hmtp_settings_section_display() { ?>
		<p>Visit <a href="https://code.google.com/apis/console?api=analytics">https://code.google.com/apis/console?api=analytics</a> to generate your client id, client secret, and to register your redirect uri.</p>
	<?php }

	public function hmtp_settings_field_client_id_display() { ?>
		<input type="text" name="hmtp_setting[ga_client_id]"     value="<?php echo esc_attr( $this->settings['ga_client_id'] ); ?>"/>
	<?php }
	
	public function hmtp_settings_field_client_secret_display() { ?>
		<input type="text" name="hmtp_setting[ga_client_secret]" value="<?php echo esc_attr( $this->settings['ga_client_secret'] ); ?>"/>
	<?php }
	
	public function hmtp_settings_field_api_key_display() { ?>
		<input type="text" name="hmtp_setting[ga_api_key]"       value="<?php echo esc_attr( $this->settings['ga_api_key'] ); ?>"/>
	<?php }
	
	public function hmtp_settings_field_redirect_display() { ?>
		<input type="text" name="hmtp_setting[ga_redirect_url]"  value="<?php echo esc_attr( $this->settings['ga_redirect_url'] ); ?>"/>
	<?php }

	public function hmtp_settings_field_property_display() {

		// Do not show the authenticate button or inputs if api details have not been added.
		if ( ! $this->settings['ga_client_id'] || ! $this->settings['ga_client_secret'] || ! $this->settings['ga_api_key'] || ! $this->settings['ga_redirect_url'] ) {
			return;
		}

		// Show authenticate button only. 
		if ( ! $this->ga_client->getAccessToken() ) :
		
			printf( 
				'<p><a class="button" href="%s">Authenticate with Google</a></p>', 
				esc_url( $this->ga_client->createAuthUrl() )
			);

		// If authenticated & api details are provided, show the property select field.
		else :

			$props = $this->ga_service->management_webproperties->listManagementWebproperties("~all");
			
			$deauth_url = wp_nonce_url( add_query_arg( array() ), 'hmtp_deauth', 'hmtp_deauth' );

			?>

			<input type="hidden" name="hmtp_setting[ga_property_id]" value="<?php echo $this->settings['ga_property_id']; ?>" />
			<input type="hidden" name="hmtp_setting[ga_access_token]" value="<?php echo esc_attr( json_encode( $this->settings['ga_access_token'] ) ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_property_profile_id]" value="<?php echo $this->settings['ga_property_profile_id']; ?>" />
			
			<select name="hmtp_setting[ga_property_profile_id]">
					
				<option value="0">Select a property</option>

				<?php 

				foreach ( $props->items as $property ) {

	      			$profiles = $this->ga_service->management_profiles->listManagementProfiles( $property->accountId, $property->getId() );

					printf( '<optgroup label="%s">', $property->name );

	      			foreach ( $profiles->getItems() as $profile ) {
						hm_log( $profile );
						printf( 
							'<option value="%s" %s>%s</option>', 
							$profile->getId(),
							selected( $profile->getId(), $this->settings['ga_property_profile_id'], false ),
							$profile->name
						);

					}
					
					echo '</optgroup>';

				}

				?>

			</select>
			
		<?php

		endif;

	}

	public function hmtp_settings_field_opt_out_display() { ?>
		<label><input type="checkbox" name="hmtp_setting[allow_opt_out]"  <?php checked( true, $this->settings['allow_opt_out'] ); ?>/> Allow excluding individual posts from Top Posts results.</label>
	<?php }

	/**
	 * Process input.
	 * 
	 * @param  on submit, we should 
	 * @return [type]        [description]
	 */
	public function hmtp_settings_sanitize( $input ) {
		
		$input['allow_opt_out'] = isset( $input['allow_opt_out'] );
		
    	return $input;

	}

}