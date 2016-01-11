<?php

namespace HMTP;

abstract class API {

	/**
	 * @var int
	 */
	private $expiry = 86400; // $this->expiry one day.

	/**
	 * @var array
	 */
	private $args = array();

	/**
	 * @var array
	 */
	private $args_defaults = array(
		'count'      => 5,
		'filter'     => null, // gapi filter
		'taxonomy'   => null, // (string) taxonomy to query by.
		'terms'      => array(), // array of terms to query by
		'start_date' => null, // format YYYY-mm-dd. 1 month ago.
		'end_date'   => null, // format YYYY-mm-dd
		'post_type'  => array( 'post' ), // only supports post & page.
		'filters'    => null, // see https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters
	);

	/**
	 * @var \Google_Service_Analytics
	 */
	private $analytics;

	/**
	 * @var
	 */
	private $ga_property_profile_id;

	/**
	 * @param  string                   $ga_property_profile_id
	 * @param \Google_Service_Analytics $analytics
	 */
	function __construct( $ga_property_profile_id, \Google_Service_Analytics $analytics ) {

		$this->args_defaults['start_date'] = date( 'Y-m-d', time() - YEAR_IN_SECONDS / 12 );
		$this->args_defaults['end_date']   = date( 'Y-m-d', time() );

		$this->ga_property_profile_id = $ga_property_profile_id;
		$this->analytics              = $analytics;

	}

	abstract function fetch_results( array $args = array() );

	abstract function get_results( array $args = array() );

	/**
	 * Cached version of url_to_postid, which can be expensive.
	 *
	 * Taken from wpcom_vip_url_to_postid
	 * Examine a url and try to determine the post ID it represents.
	 *
	 * @param string $url Permalink to check.
	 * @return int Post ID, or 0 on failure.
	 */
	private function url_to_postid( $url ) {

		$cache_key = md5( $url );
		$post_id   = wp_cache_get( $cache_key, 'url_to_postid' );

		if ( false === $post_id ) {
			$post_id = url_to_postid( $url ); // returns 0 on failure, so need to catch the false condition
			wp_cache_add( $cache_key, $post_id, 'url_to_postid' );
		}

		return $post_id;

	}


	/**
	 *
	 *
	 * @param $host
	 */
	private function get_blog_id_from_host( $host ) {

	}

}
