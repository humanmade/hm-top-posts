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


class LocalSettingsHandler extends SettingsHandler {

	public function get_option( $option_name ) {
		return get_option( $option_name );
	}

	public function update_option( $option_name, $option_value ) {
		update_option( $option_name, $option_value );
	}

	public function delete_option( $option_name ) {
		delete_option( $option_name );
	}
}
