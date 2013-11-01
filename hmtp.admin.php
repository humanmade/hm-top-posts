<?php

/**
 * Class HMTP_Admin
 */
class HMTP_Admin {

	/**
	 * An instance of this class
	 *
	 * @var object
	 */
	protected static $_instance = null;

	/**
	 * @var Google_Client|null
	 */
	private $ga_client = null;

	/**
	 * @var Google_AnalyticsService|null
	 */
	private $ga_service = null;

	/**
	 * @var null
	 */
	private $ga_property_account_id = null;

	/**
	 * @var null
	 */
	private $ga_property_id = null;

	/**
	 * Initialization
	 *
	 * @param                         $settings
	 * @param Google_Client           $ga_client
	 * @param Google_AnalyticsService $ga_analytics
	 */
	public function __construct( $settings = array(), Google_Client $ga_client, Google_AnalyticsService $ga_analytics ) {

		$this->settings   = $settings;
		$this->ga_client  = $ga_client;
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
	final private function __clone() {}

	/**
	 * Initiate authorization
	 */
	function init() {

		// Deauthenticate.
		if ( isset( $_GET['hmtp_deauth'] ) && wp_verify_nonce( $_GET['hmtp_deauth'], 'hmtp_deauth' ) ) {

			delete_option( 'hmtp_setting' );
			delete_option( 'hmtp_ga_token' );

			wp_safe_redirect(
				add_query_arg(
					array( 'page' => 'hmtp_settings_page' ),
					get_admin_url() . 'options-general.php'
				)
			);

			exit;
		}

	}

	/**
	 * Build out the plugin settings
	 */
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

	/**
	 * Output the plugin settings page
	 */
	public function settings_page() { ?>

		<form action="options.php" method="POST">

			<?php

			settings_fields( 'hmtp_settings' );
			do_settings_sections( 'hmtp_settings_page' );
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

	/**
	 * Output the settings section description
	 */
	public function hmtp_settings_section_display() { ?>
		<p>Visit
			<a href="https://code.google.com/apis/console?api=analytics">https://code.google.com/apis/console?api=analytics</a> to generate your client id, client secret, and to register your redirect uri.
		</p>
	<?php
	}

	/**
	 * Display the client ID field
	 */
	public function hmtp_settings_field_client_id_display() { ?>
		<input type="text" name="hmtp_setting[ga_client_id]" value="<?php echo esc_attr( $this->settings['ga_client_id'] ); ?>" />
	<?php
	}

	/**
	 * Display the client secret field
	 */
	public function hmtp_settings_field_client_secret_display() { ?>
		<input type="text" name="hmtp_setting[ga_client_secret]" value="<?php echo esc_attr( $this->settings['ga_client_secret'] ); ?>" />
	<?php
	}

	/**
	 * Display the client API key field
	 */
	public function hmtp_settings_field_api_key_display() { ?>
		<input type="text" name="hmtp_setting[ga_api_key]" value="<?php echo esc_attr( $this->settings['ga_api_key'] ); ?>" />
	<?php
	}

	/**
	 * Display the redirect URL field
	 */
	public function hmtp_settings_field_redirect_display() { ?>
		<input type="text" name="hmtp_setting[ga_redirect_url]" value="<?php echo esc_attr( $this->settings['ga_redirect_url'] ); ?>" />
	<?php
	}

	/**
	 * Display the property dropdown field
	 */
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

			$props = $this->ga_service->management_webproperties->listManagementWebproperties( "~all" );

			$deauth_url = wp_nonce_url( add_query_arg( array() ), 'hmtp_deauth', 'hmtp_deauth' );

			?>

			<input type="hidden" name="hmtp_setting[ga_property_id]" value="<?php echo esc_attr( $this->settings['ga_property_id'] ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_access_token]" value="<?php echo esc_attr( json_encode( $this->settings['ga_access_token'] ) ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_property_profile_id]" value="<?php echo esc_attr( $this->settings['ga_property_profile_id'] ); ?>" />

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

		<?php

		endif;

	}

	/**
	 * Display the post optout checkbox field
	 */
	public function hmtp_settings_field_opt_out_display() { ?>
		<label><input type="checkbox" name="hmtp_setting[allow_opt_out]"  <?php checked( true, $this->settings['allow_opt_out'] ); ?>/> Allow excluding individual posts from Top Posts results.</label>
	<?php
	}

	/**
	 * Filter the user input
	 *
	 * @param $input
	 * @return bool
	 */
	public function hmtp_settings_sanitize( $input ) {

		$input['allow_opt_out'] = isset( $input['allow_opt_out'] );

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
					throw new Exception( 'Property not found' );

				$items                           = (array) $profiles->getItems();
				$input['ga_property_profile_id'] = reset( $items )->getId();

			} catch ( Exception $e ) {

				return false;
			}

		}

		return $input;

	}

}
