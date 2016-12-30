<?php
/**
 * Non namespaced template tags
 */

/**
 * Fetch and array of the top posts according to GA
 *
 * @param array $args
 * @return array|mixed
 */
function hmtp_get_top_posts( array $args = array() ) {
	return HMTP\get_top_posts( $args );
}
