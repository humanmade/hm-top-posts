<?php
/**
 * Contains .
 *
 * @copyright 2016 Sigma Software
 * @package   UB
 *
 * @author    Dmitriy Mamlyga(dmitriy.mamlyga@sigma.software).
 */

namespace HMTP;

require_once HMTP_PLUGIN_PATH . '/hmtp.settings-handler.php';


class NetworkSettingsHandler extends SettingsHandler {

	private $network_id = '';

	public function __construct() {
		$this->network_id = get_current_network_id();
	}

	public function get_option( $option_name ) {
		return get_network_option( $this->network_id, $option_name );
	}

	public function update_option( $option_name, $option_value ) {
		update_network_option( $this->network_id, $option_name, $option_value );
	}

	public function delete_option( $option_name ) {
		delete_network_option( $this->network_id, $option_name );
	}
}