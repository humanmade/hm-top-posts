<?php
/*
Plugin Name: HM Top Posts
Description: Top Posts. By Google Analytics.
Version: 0.1
Author: Human Made Limited
Author URI: http://hmn.md
*/

define( 'HMTP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once HMTP_PLUGIN_PATH . 'google-api-php-client/src/Google_Client.php';
require_once HMTP_PLUGIN_PATH . 'google-api-php-client/src/contrib/Google_AnalyticsService.php';

require_once HMTP_PLUGIN_PATH . 'hmtp.class.php';
require_once HMTP_PLUGIN_PATH . 'hmtp.admin.php';
require_once HMTP_PLUGIN_PATH . 'hmtp.opt-out.php';
require_once HMTP_PLUGIN_PATH . 'hmtp.widget.php';

HMTP_Plugin::get_instance();

class HMTP_Plugin {

	private static $instance = null;
	private $settings = null;
	private $ga_client;
	private $ga_service;
	private $top_posts;
	private $opt_out;

	private $admin;

	private function __construct() {

		$this->settings = wp_parse_args( 
			get_option( 'hmtp_setting', array() ), 
			array(
				'ga_property_id'         => null,
				'ga_property_account_id' => null,
				'ga_property_profile_id' => null,
				'ga_client_id'           => null,
				'ga_client_secret'       => null,
				'ga_api_key'             => null,
				'ga_redirect_url'        => null,
				'allow_opt_out'          => false,
			) 
		);

		$this->token = get_option( 'hmtp_ga_token' );
		
		$this->ga_client = new Google_Client();

		$this->ga_client->setApplicationName("WP Top Posts by GA");

		// Visit https://code.google.com/apis/console?api=analytics to generate your
		// client id, client secret, and to register your redirect uri.
		$this->ga_client->setClientId( $this->settings['ga_client_id'] );
		$this->ga_client->setClientSecret( $this->settings['ga_client_secret'] );
		$this->ga_client->setDeveloperKey( $this->settings['ga_api_key'] );
		$this->ga_client->setRedirectUri( $this->settings['ga_redirect_url']  );
		$this->ga_client->setUseObjects( true );
			
		if ( $this->token )
			$this->ga_client->setAccessToken( $this->token );

		$this->ga_service = new Google_AnalyticsService( $this->ga_client );

		add_action( 'init', array( $this, 'init' ) );

		$this->admin     = new HMTP_Admin( $this->settings, $this->ga_client, $this->ga_service );
		
		if ( $this->settings['ga_property_profile_id'] )
			$this->top_posts = new HMTP_Top_Posts( $this->settings['ga_property_profile_id'], $this->ga_service );

		if ( $this->settings['allow_opt_out'] )
			$this->opt_out = HMTP_Opt_Out::get_instance();
	
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
	 * Handle the authentication & redirect to the admin.
	 * 
	 * @return null.
	 * @todo nonce
	 */
	public function init() {
		
		if ( ! current_user_can( 'manage_options' ) )
			return;

		// Authenticate.
		if ( isset( $_GET['code'] ) ) {
			
			$this->ga_client->authenticate();
			update_option( 'hmtp_ga_token', $this->ga_client->getAccessToken() );
			
			wp_safe_redirect( add_query_arg( 
				array( 'page' => 'hmtp_settings_page' ), 
				get_admin_url() . 'options-general.php'
			) );

			exit;
		}

	}

	public function get_results( Array $args = array() ) {

		if ( ! $this->top_posts )
			return array();

		return $this->top_posts->get_results( $args );

	}

}

/**
 * Get Top Posts.
 * 
 * @param  array $args
 * @return array or top posts.
 */
function hmtp_get_top_posts( Array $args = array() ) {

	return HMTP_Plugin::get_instance()->get_results( $args );
	
}