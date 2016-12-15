<?php

namespace HMTP;

/**
 * Class HMTP_Admin
 */
class Admin {

	/**
	 * An instance of this class
	 *
	 * @var object
	 */
	protected static $_instance = null;

	/**
	 * @var \Google_Client|null
	 */
	private $ga_client = null;

	/**
	 * @var \Google_Service_Analytics|null
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
	 * @param array                     $settings
	 * @param \Google_Client            $ga_client
	 * @param \Google_Service_Analytics $ga_analytics
	 */
	public function __construct( $settings = array(), \Google_Client $ga_client, \Google_Service_Analytics $ga_analytics ) {

		$this->settings   = $settings;
		$this->ga_client  = $ga_client;
		$this->ga_service = $ga_analytics;

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'add_options_pages' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_options_pages' ) );
		add_action( 'network_admin_edit_forms_processing', array( $this, 'forms_processing' ) );
	}

	function add_options_pages() {
		if ( ! hmtp_is_network_activated() ) {
			add_options_page( 'HM Top Posts', 'Top Posts', 'manage_options', 'hmtp_settings_page', array( $this, 'settings_page' ) );
		}
	}

	function add_network_options_pages() {
		add_submenu_page(
			'settings.php',
			__( 'HM Top Posts', 'ub' ),
			'Net Top Posts',
			'manage_network',
			'hmtp_settings_page',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Should not be able to clone singletons
	 */
	final private function __clone() { }

	/**
	 * Initiate authorization
	 */
	function init() {

		// Deauthenticate.
		if ( isset( $_GET['hmtp_deauth'] ) && wp_verify_nonce( $_GET['hmtp_deauth'], 'hmtp_deauth' ) ) {

			delete_option( 'hmtp_setting' );
			delete_option( 'hmtp_ga_token' );

			if ( hmtp_is_network_activated() ) {
				wp_safe_redirect( network_admin_url( 'settings.php?page=hmtp_settings_page' ) );
			} else {
				wp_safe_redirect( admin_url( 'options-general.php?page=hmtp_settings_page' ) );
			}
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
			array( $this, 'settings_sanitize' )
		);

		add_settings_section(
			'settings_section',
			'Top Posts Settings',
			array( $this, 'settings_section_display' ),
			'hmtp_settings_page'
		);

		if ( ! $this->ga_client->getAccessToken() ) {
			if ( ! ( defined( 'HMTP_GA_CLIENT_ID' ) && HMTP_GA_CLIENT_ID ) ) {
				add_settings_field(
					'settings_client_id',
					'Client ID',
					array( $this, 'settings_field_client_id_display' ),
					'hmtp_settings_page',
					'settings_section'
				);
			}

			if ( ! ( defined( 'HMTP_GA_CLIENT_SECRET' ) && HMTP_GA_CLIENT_SECRET ) ) {
				add_settings_field(
					'settings_client_secret',
					'Client Secret',
					array( $this, 'settings_field_client_secret_display' ),
					'hmtp_settings_page',
					'settings_section'
				);
			}

			if ( ! ( defined( 'HMTP_GA_REDIRECT_URL' ) && HMTP_GA_REDIRECT_URL ) ) {
				add_settings_field(
					'settings_redirect_url',
					'Redirect URL',
					array( $this, 'settings_field_redirect_display' ),
					'hmtp_settings_page',
					'settings_section'
				);
			}
		}

		add_settings_field(
			'settings_property',
			'Select Web Property',
			array( $this, 'settings_field_property_display' ),
			'hmtp_settings_page',
			'settings_section'
		);

		add_settings_field(
			'settings_allow_opt_out',
			'Allow Opt-Out',
			array( $this, 'settings_field_opt_out_display' ),
			'hmtp_settings_page',
			'settings_section'
		);

	}

	/**
	 * Output the plugin settings page
	 */
	public function settings_page() {
		if ( hmtp_is_network_activated() ) {
			$action = 'edit.php?action=forms_processing';
		} else {
			$action = 'options.php';
		}
		?>
		<div class="wrap">
			<h1>HM Top Posts</h1>
			<form action="<?php echo esc_url( $action ); ?>" method="POST">
				<?php
				settings_fields( 'hmtp_settings' );
				do_settings_sections( 'hmtp_settings_page' );
				submit_button();
				?>
			</form>
			<?php if ( $this->ga_client->getAccessToken() ) : ?>
				<form action="<?php echo esc_url( $action ); ?>" method="POST">
					<input type="hidden" name="reset_token" value=" "/>

					<?php
					settings_fields( 'hmtp_settings' );
					echo '<p><input type="submit" name="submit" id="submit" class="button button-secondary" value="Reset settings" /></p>';
					?>
				</form>

				<?php $results = test_request( array( 'post_type' => 'post' ) ); ?>

				<h2><?php esc_html_e( 'Plugin status', 'hmtp' ); ?></h2>
				<?php if ( $results['success'] ) : ?>
					<?php printf( '<p>%s</p>', esc_html( $results['status_message'] ) ); ?>
				<?php else : ?>
					<p>An error ocured, please check an error message below</p>
					<?php printf( '<p>%s</p>', esc_html( $results['status_message'] ) ); ?>
				<?php endif; ?>

			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Output the settings section description
	 */
	public function settings_section_display() {

		if ( ! $this->ga_client->getAccessToken() ) {

		?>
		<p>
			Visit
			<a target="_blank" href="https://code.google.com/apis/console?api=analytics">https://code.google.com/apis/console?api=analytics</a> to generate your client id and client secret.
		</p>
		<ol>
			<li>Click "New credentials"</li>
			<li>Choose "OAuth client ID"</li>
			<li>Select "Web application" and enter a name</li>
			<li>Copy the redirect URI value from the field below into the redirect URI field on the new credential form</li>
			<li>Fill in the generated client ID and secret below</li>
		</ol>
		<?php

		}

	}

	/**
	 * Display the client ID field
	 */
	public function settings_field_client_id_display() { ?>
		<input class="widefat" type="text" name="hmtp_setting[ga_client_id]" value="<?php echo esc_attr( $this->settings['ga_client_id'] ); ?>" />
		<?php
	}

	/**
	 * Display the client secret field
	 */
	public function settings_field_client_secret_display() { ?>
		<input class="widefat" type="password" name="hmtp_setting[ga_client_secret]" value="<?php echo esc_attr( $this->settings['ga_client_secret'] ); ?>" />
		<?php
	}

	/**
	 * Display the redirect URL field
	 */
	public function settings_field_redirect_display() { ?>
		<input class="widefat" type="text" readonly="readonly" name="hmtp_setting[ga_redirect_url]" value="<?php echo esc_attr( $this->settings['ga_redirect_url'] ); ?>" />
		<?php
	}

	/**
	 * Display the property dropdown field
	 */
	public function settings_field_property_display() {

		// Do not show the authenticate button or inputs if api details have not been added.
		if ( ! $this->settings['ga_client_id'] || ! $this->settings['ga_client_secret'] || ! $this->settings['ga_redirect_url'] ) {
			?>
			<p>Please provide a client ID and secret before continuing.</p>
			<?php
			return;
		}

		// Show authenticate button only.
		if ( ! $this->ga_client->getAccessToken() || $this->ga_client->isAccessTokenExpired() ) :

			$this->ga_client->setApprovalPrompt( 'force' );
			$this->ga_client->setAccessType( 'offline' );

			printf(
				'<p><a class="button" href="%s">Authenticate with Google</a></p>',
				esc_url( $this->ga_client->createAuthUrl() )
			);

		// If authenticated & api details are provided, show the property select field.
		else :
			try {
				$props = $this->ga_service->management_webproperties->listManagementWebproperties( "~all" );
			} catch ( \Exception $e ) {
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return array();
			}

			$deauth_url = wp_nonce_url( add_query_arg( array() ), 'hmtp_deauth', 'hmtp_deauth' );

			?>

			<input type="hidden" name="hmtp_setting[ga_client_id]" value="<?php echo esc_attr( $this->settings['ga_client_id'] ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_client_secret]" value="<?php echo esc_attr( $this->settings['ga_client_secret'] ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_redirect_url]" value="<?php echo esc_attr( $this->settings['ga_redirect_url'] ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_property_id]" value="<?php echo esc_attr( $this->settings['ga_property_id'] ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_access_token]" value="<?php echo esc_attr( json_encode( $this->settings['ga_access_token'] ) ); ?>" />
			<input type="hidden" name="hmtp_setting[ga_property_profile_id]" value="<?php echo esc_attr( $this->settings['ga_property_profile_id'] ); ?>" />

			<select name="hmtp_setting[ga_property_profile_id]">

				<option value="0">Select a property</option>

				<?php

				foreach ( $props->items as $property ) {

					$profiles = $this->ga_service->management_profiles->listManagementProfiles( $property->accountId, $property->getId() );

					printf( '<optgroup label="%s">', $property->name );

					foreach ( $profiles->getItems() as $profile ) {

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

	/**
	 * Display the post optout checkbox field
	 */
	public function settings_field_opt_out_display() { ?>
		<label>
			<input type="checkbox" name="hmtp_setting[allow_opt_out]" <?php checked( true, $this->settings['allow_opt_out'] ); ?>/>
			Allow excluding individual posts from Top Posts results.
		</label>
		<?php
	}

	/**
	 * Filter the user input
	 *
	 * @param $input
	 * @return bool
	 */
	public function settings_sanitize( $input ) {
		$input['allow_opt_out'] = isset( $input['allow_opt_out'] ) ? (boolean) $input['allow_opt_out'] : false;

		// Reset token if client ID / secret change
		if ( $input['ga_client_id'] !== $this->settings['ga_client_id'] ) {
			get_settings_handler()->delete_option( 'hmtp_ga_token' );
		}
		if ( $input['ga_client_secret'] !== $this->settings['ga_client_secret'] ) {
			get_settings_handler()->delete_option( 'hmtp_ga_token' );
		}

		// Reset token if client ID / secret change
		if ( isset( $_POST['reset_token'] ) ) {
			get_settings_handler()->delete_option( 'hmtp_ga_token' );
			exit;
		}

		return $input;
	}

	public function forms_processing() {
		// Reset token if client ID / secret change
		if ( isset( $_POST['reset_token'] ) ) {
			get_settings_handler()->delete_option( 'hmtp_ga_token' );
			wp_safe_redirect( network_admin_url( 'settings.php?page=hmtp_settings_page' ) );
			exit;
		}

		$options = get_settings_handler()->get_option( 'hmtp_setting' );

		$to_save = array();
		foreach ( $_POST['hmtp_setting'] as $key => $value ) {
			if ( 'ga_access_token' === $key ) { continue; }
			$to_save[ $key ] = $value ? $value : ''; // save raw code - doesn't require sanitization.
		}
		if ( ! array_key_exists( 'allow_opt_out', $to_save ) ) {
			$to_save['allow_opt_out'] = false;
		}
		$options = wp_parse_args( $to_save, $options );
		get_settings_handler()->update_option( 'hmtp_setting', $options );
		wp_safe_redirect( network_admin_url( 'settings.php?page=hmtp_settings_page' ) );

		exit;

	}
}
