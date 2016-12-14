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


abstract class SettingsHandler {

	abstract function get_option( $option_name );

	abstract function update_option( $option_name, $option_value );

	abstract function delete_option( $option_name );
}
