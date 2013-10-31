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
			'hmtp_settings_auth',
			'Auth Settings',
			array( $this, 'hmtp_settings_field_auth_display' ),
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

	}

	public function settings_page() {
		
		// hm( $this->settings );
		
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

	public function hmtp_settings_section_display() {
	}

	public function hmtp_settings_field_property_display() {

		if ( ! $this->settings['ga_client_id'] || ! $this->settings['ga_client_secret'] || ! $this->settings['ga_api_key'] || ! $this->settings['ga_redirect_url'] ) {

			echo '<p>Please provide your Google API access details</p>';
			return;

		}

		if ( ! $this->ga_client->getAccessToken() ) {
		
			printf( 
				'<p><a class="button" href="%s">Authenticate with Google</a></p>', 
				esc_url( $this->ga_client->createAuthUrl() )
			);

			return;
		
		}

		$props = $this->ga_service->management_webproperties->listManagementWebproperties("~all");
		
		$deauth_url = wp_nonce_url( add_query_arg( array() ), 'hmtp_deauth', 'hmtp_deauth' );

		?>

		<input type="hidden" name="hmtp_setting[ga_property_id]" value="<?php echo $this->settings['ga_property_id']; ?>" />
		<input type="hidden" name="hmtp_setting[ga_access_token]" value="<?php echo esc_attr( json_encode( $this->settings['ga_access_token'] ) ); ?>" />
		<input type="hidden" name="hmtp_setting[ga_property_profile_id]" value="<?php echo $this->settings['ga_property_profile_id']; ?>" />
							
		<select name="hmtp_setting[ga_property_account_id]">
				
			<option value="0">Select a property</option>

			<?php 

			foreach ( $props->items as $property ) {
				printf( 
					'<option value="%s" %s>%s</option>', 
					$property->accountId,
					selected( $property->accountId, $this->settings['ga_property_account_id'], false ),
					$property->name
				);
			}

			?>

		</select>

		<a class="button" href="<?php echo esc_url( $deauth_url ); ?>">Deauthorize</a>
	
		<?php

	}

	public function hmtp_settings_field_auth_display() {

		?>

		<style>
			.hmtp_auth_fields label { display: inline-block; width: 100px; margin-right: 10px; }
		</style>

		<div class="hmtp_auth_fields">
			<p><label>Client ID</label>     <input type="text" id="hmtp_setting-ga_client_id"     name="hmtp_setting[ga_client_id]"     value="<?php echo esc_attr( $this->settings['ga_client_id'] ); ?>"/></p>
			<p><label>Client Secret</label> <input type="text" id="hmtp_setting-ga_client_secret" name="hmtp_setting[ga_client_secret]" value="<?php echo esc_attr( $this->settings['ga_client_secret'] ); ?>"/></p>
			<p><label>API Key</label>       <input type="text" id="hmtp_setting-ga_api_key"       name="hmtp_setting[ga_api_key]"       value="<?php echo esc_attr( $this->settings['ga_api_key'] ); ?>"/></p>
			<p><label>Redirect URL</label>  <input type="text" id="hmtp_setting-ga_redirect_url"  name="hmtp_setting[ga_redirect_url]"  value="<?php echo esc_attr( $this->settings['ga_redirect_url'] ); ?>"/></p>
			<p>Visit <a href="https://code.google.com/apis/console?api=analytics">https://code.google.com/apis/console?api=analytics</a> to generate your client id, client secret, and to register your redirect uri.</p>
		</div>
		<?php

	}

	/**
	 * Process input.
	 * 
	 * @param  on submit, we should 
	 * @return [type]        [description]
	 */
	public function hmtp_settings_sanitize( $input ) {
		
		if ( isset( $input['ga_property_account_id'] ) ) {
			
			try {			
				
				$properties = $this->ga_service->management_webproperties->listManagementWebproperties( $input['ga_property_account_id'] );
				
				if ( count( $properties->getItems() ) < 1 )
					throw new Exception( 'Property not found' );

				$input['ga_property_id'] = $properties->getItems()[0]->getId();
				
				// Not so sure about this...
				$input['ga_access_token'] = json_decode( htmlspecialchars_decode( $input['ga_access_token'] ) );
      		
      			$profiles = $this->ga_service->management_profiles->listManagementProfiles( $input['ga_property_account_id'], $input['ga_property_id'] );

      			if ( count( $profiles->getItems() ) < 0 )
      				throw new Exception('Property not found' );
				
				$items = (array) $profiles->getItems();
				$input['ga_property_profile_id'] = reset( $items )->getId();
			
			} catch( Exception $e ) {
				
				return false;
			}

		}

    	return $input;

	}

}