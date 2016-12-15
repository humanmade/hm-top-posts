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
	public $top_posts;

	/**
	 * @var Top_Blogs
	 */
	public $top_blogs;

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
		require_once HMTP_PLUGIN_PATH . 'hmtp.top-posts.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.top-blogs.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.admin.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.opt-out.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.widget.php';
		require_once HMTP_PLUGIN_PATH . 'hmtp.template-tags.php';

		$options = get_settings_handler()->get_option( 'hmtp_setting' );
		$this->token = get_settings_handler()->get_option( 'hmtp_ga_token' );
		if ( hmtp_is_network_activated() ) {
			$ga_redirect_url = network_admin_url( 'settings.php?page=hmtp_settings_page' );
		} else {
			$ga_redirect_url = admin_url( 'options-general.php?page=hmtp_settings_page' );
		}

		$this->settings = wp_parse_args(
			$options,
			array(
				'ga_property_id'         => null,
				'ga_property_account_id' => null,
				'ga_property_profile_id' => null,
				'ga_client_id'           => null,
				'ga_client_secret'       => null,
				'ga_redirect_url'        => $ga_redirect_url,
				'allow_opt_out'          => false,
				'allow_opt_out_blogs'    => false,
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

		$this->ga_client = new \Google_Client();

		$this->ga_client->setApplicationName( 'WP Top Posts by GA' );

		// Visit https://code.google.com/apis/console?api=analytics to generate your
		// client id, client secret, and to register your redirect uri.
		$this->ga_client->setClientId( $this->settings['ga_client_id'] );
		$this->ga_client->setClientSecret( $this->settings['ga_client_secret'] );
		$this->ga_client->setRedirectUri( $this->settings['ga_redirect_url'] );
		$this->ga_client->setScopes( 'https://www.googleapis.com/auth/analytics' );

		if ( $this->token ) {
			try {
				$this->ga_client->setAccessToken( $this->token );
				// Refresh token if necessary
				if ( $this->ga_client->isAccessTokenExpired() && $this->ga_client->getRefreshToken() ) {
					$this->ga_client->refreshToken( $this->ga_client->getRefreshToken() );
					get_settings_handler()->update_option( 'hmtp_ga_token', $this->ga_client->getAccessToken() );
				}
			} catch ( \Exception $e ) {
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return array();
			}
		}

		$this->ga_service = new \Google_Service_Analytics( $this->ga_client );

		add_action( 'init', array( $this, 'init' ) );

		$this->admin = new Admin( $this->settings, $this->ga_client, $this->ga_service );

		if ( $this->settings['ga_property_profile_id'] ) {
			$this->top_posts = new Top_Posts( $this->settings['ga_property_profile_id'], $this->ga_service );
			if ( is_multisite() ) {
				$this->top_blogs = new Top_Blogs( $this->settings['ga_property_profile_id'], $this->ga_service );
			}
		}

		if ( $this->settings['allow_opt_out'] || $this->settings['allow_opt_out_blogs'] ) {
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
		if ( isset( $_GET['page'] ) && 'hmtp_settings_page' === $_GET['page'] && isset( $_GET['code'] ) ) {

			$this->ga_client->authenticate( sanitize_text_field( $_GET['code'] ) );
			get_settings_handler()->update_option( 'hmtp_ga_token', $this->ga_client->getAccessToken() );
			if ( hmtp_is_network_activated() ) {
				wp_safe_redirect( network_admin_url( 'settings.php?page=hmtp_settings_page' ) );
			} else {
				wp_safe_redirect( admin_url( 'options-general.php?page=hmtp_settings_page' ) );
			}
			exit;
		}
	}

	/**
	 * Fetch the top posts
	 *
	 * @param array $args
	 * @return array|mixed
	 */
	public function get_results( array $args = array() ) {
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
function get_top_posts( array $args = array() ) {
	return Plugin::get_instance()->get_results( $args );
}

/**
 * Used for async fetching with TLC transients
 *
 * @param array $args
 * @return array|mixed
 */
function top_posts_fetch_results( array $args = array() ) {
	if ( ! Plugin::get_instance()->top_posts ) {
		return array();
	}
	return Plugin::get_instance()->top_posts->fetch_results( $args );
}

/**
 * Get highest traffic sites on a multisite network.
 *
 * @param array $args
 * @return array
 */
function top_blogs_fetch_results( array $args = array() ) {
	if ( ! Plugin::get_instance()->top_blogs ) {
		return array();
	}
	return Plugin::get_instance()->top_blogs->fetch_results( $args );
}

/**
 * Gets test data.
 *
 * @param array $args
 * @return array
 */
function test_request( array $args = array() ) {
	if ( ! Plugin::get_instance()->top_posts ) {
		return array();
	}
	return Plugin::get_instance()->top_posts->test_request( $args );

}

/**
 * Detect network activated.
 *
 * @return bool
 */
function hmtp_is_network_activated() {
	// Makes sure the plugin is defined before trying to use it
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
	}
	return is_plugin_active_for_network( 'hm-top-posts/hm-top-posts.php' );
}

function get_settings_handler() {

	static $hmtp_settings_handler = null;

	if ( ! isset( $hmtp_settings_handler ) ) {
		require_once HMTP_PLUGIN_PATH . '/hmtp.settings-handler.php';
		require_once HMTP_PLUGIN_PATH . '/hmtp.local-settings-handler.php';
		require_once HMTP_PLUGIN_PATH . '/hmtp.network-settings-handler.php';
		if ( hmtp_is_network_activated() ) {
			$hmtp_settings_handler = new NetworkSettingsHandler();
		} else {
			$hmtp_settings_handler = new LocalSettingsHandler();
		}
	}

	return $hmtp_settings_handler;
}
