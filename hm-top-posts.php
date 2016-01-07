<?php
/*
Plugin Name: HM Top Posts
Description: Top Posts. By Google Analytics.
Version: 0.1
Author: Human Made Limited
Author URI: http://hmn.md
*/

namespace HMTP;

/**
 * The absolute path to the plugin directory
 */
define( 'HMTP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', array( 'HMTP\\Plugin', 'get_instance' ) );

/**
 * Class HMTP_Plugin
 */
class Plugin {

	/**
	 * The class instance.
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * The plugin settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * An instance of the Google API class
	 *
	 * @var \Google_Client
	 */
	private $ga_client;

	/**
	 * An instance of the Google Analytics Service class
	 *
	 * @var \Google_Service_Analytics
	 */
	private $ga_service;

	/**
	 * @var Top_Posts
	 */
	private $top_posts;

	/**
	 * @var Opt_Out
	 */
	private $opt_out;

	/**
	 * @var Admin
	 */
	private $admin;

	/**
	 * Initialization
	 */
	private function __construct() {

		require_once HMTP_PLUGIN_PATH . 'vendor/autoload.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.class.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.admin.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.opt-out.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.widget.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.template-tags.php';

		$this->settings = wp_parse_args(
			get_option( 'hmtp_setting', array() ),
			array(
				'ga_property_id'         => null,
				'ga_property_account_id' => null,
				'ga_property_profile_id' => null,
				'ga_client_id'           => null,
				'ga_client_secret'       => null,
				'ga_redirect_url'        => admin_url( 'options-general.php?page=hmtp_settings_page' ),
				'allow_opt_out'          => false,
			)
		);

		if ( defined( 'HMTP_GA_CLIENT_ID' ) && HMTP_GA_CLIENT_ID ) {
			$this->settings['ga_client_id'] = HMTP_GA_CLIENT_ID;
		}

		if ( defined( 'HMTP_GA_CLIENT_SECRET' ) && HMTP_GA_CLIENT_SECRET ) {
			$this->settings['ga_client_secret'] = HMTP_GA_CLIENT_SECRET;
		}

		if ( defined( 'HMTP_GA_REDIRECT_URL' ) && HMTP_GA_REDIRECT_URL ) {
			$this->settings['ga_redirect_url'] = HMTP_GA_REDIRECT_URL;
		}

		$this->token = get_option( 'hmtp_ga_token' );

		$this->ga_client = new \Google_Client();

		$this->ga_client->setApplicationName( "WP Top Posts by GA" );

		// Visit https://code.google.com/apis/console?api=analytics to generate your
		// client id, client secret, and to register your redirect uri.
		$this->ga_client->setClientId( $this->settings['ga_client_id'] );
		$this->ga_client->setClientSecret( $this->settings['ga_client_secret'] );
		$this->ga_client->setRedirectUri( $this->settings['ga_redirect_url'] );
		$this->ga_client->setScopes( 'https://www.googleapis.com/auth/analytics' );

		if ( $this->token ) {
			$this->ga_client->setAccessToken( $this->token );

			// Refresh token if necessary
			if ( $this->ga_client->isAccessTokenExpired() && $this->ga_client->getRefreshToken() ) {
				$this->ga_client->refreshToken( $this->ga_client->getRefreshToken() );
				update_option( 'hmtp_ga_token', $this->ga_client->getAccessToken() );
			}
		}

		$this->ga_service = new \Google_Service_Analytics( $this->ga_client );

		add_action( 'init', array( $this, 'init' ) );

		$this->admin = new Admin( $this->settings, $this->ga_client, $this->ga_service );

		if ( $this->settings['ga_property_profile_id'] ) {
			$this->top_posts = new Top_Posts( $this->settings['ga_property_profile_id'], $this->ga_service );
		}

		if ( $this->settings['allow_opt_out'] ) {
			$this->opt_out = Opt_Out::get_instance();
		}

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

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Authenticate.
		if ( isset( $_GET['code'] ) ) {

			$this->ga_client->authenticate( sanitize_text_field( $_GET['code'] ) );
			update_option( 'hmtp_ga_token', $this->ga_client->getAccessToken() );

			wp_safe_redirect( admin_url( 'options-general.php?page=hmtp_settings_page' ) );
			exit;
		}

	}

	/**
	 * Fetch the top posts
	 *
	 * @param array $args
	 * @return array|mixed
	 */
	public function get_results( Array $args = array() ) {
		if ( ! is_object( $this->top_posts ) ) {
			return false;
		}
		return $this->top_posts->get_results( $args );
	}

}

/**
 * Get Top Posts.
 *
 * @param  array $args
 * @return array or top posts.
 */
function get_top_posts( Array $args = array() ) {
	return Plugin::get_instance()->get_results( $args );
}