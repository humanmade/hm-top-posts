<?php

namespace HMTP;

/**
 * Get Top Articles by Google Analytics.
 *
 * Get the results of the query stored for query_id. If it doesn't exist, a new one is generated based on $args.
 * Returns an array of  $post_id => $page_views.
 *
 * USAGE
 *
 * Instantiate class - passing args.
 * Call get_top_posts
 * Done!
 *
 * @param (array) $args
 */
class Top_Posts {

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

	/**
	 * Get the results
	 *
	 * @param array $args
	 * @return array|mixed
	 */
	function get_results( Array $args = array() ) {

		$args = wp_parse_args( $args, $this->args_defaults );

		// Convert term names to IDs.
		if ( ! empty( $args['terms'] ) ) {
			foreach ( $args['terms'] as &$term ) {
				if ( ! is_numeric( $term ) ) {
					$term = get_term_by( 'name', $term, $args['taxonomy'] )->term_id;
				}
			}
		}

		$query_id = 'hmtp_' . hash( 'md5', $this->ga_property_profile_id . json_encode( $args ) );

		// If TLC Transients exists, use that.
		if ( class_exists( '\\TLC_Transient' ) ) {

			return tlc_transient( $query_id )
				->expires_in( $this->expiry )
				->background_only()
				->updates_with( 'HMTP\\fetch_results', array( $args ) )
				->get();

		} else {

			if ( $results = get_transient( $query_id ) ) {
				return $results;
			}

			$results = $this->fetch_results( $args );

			set_transient( $query_id, $results, $this->expiry );

			return $results;

		}

	}

	/**
	 * Fetch data from Google API
	 *
	 * @param array $args
	 * @return array
	 */
	public function fetch_results( $args ) {

		$dimensions  = array( 'pagePath' );
		$metrics     = array( 'pageviews' );
		$max_results = 1000;

		// Build up a list of top posts.
		// Keeps going looping through - $max_results results at a time - until there are either enough posts or no more results from GA.
		$top_posts   = array();
		$start_index = 1;

		$opt_params = array(
			'dimensions'  => 'ga:pagePath',
			'sort'        => '-ga:pageviews',
			'max-results' => $max_results,
			'start-index' => $start_index,
		);

		if ( ! empty( $this->args['filters'] ) ) {
			$opt_params['filters'] = $this->args['filters'];
		}

		while ( count( $top_posts ) < $args['count'] ) {

			try {
				$results = $this->analytics->data_ga->get(
					'ga:' . $this->ga_property_profile_id,
					$args['start_date'],
					$args['end_date'],
					'ga:pageviews',
					$opt_params
				);
			} catch ( \Exception $e ) {
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return array();
			}

			if ( count( $results->getRows() ) < 1 ) {
				break;
			}

			foreach ( $results->getRows() as $result ) {

				$url = str_replace( 'index.htm', '', apply_filters( 'hmtp_result_url', (string) $result[0] ) );

				// URls are relative so let's turn them into absolute URLS.
				$url = WP_SITEURL . $url;

				// Get the post id from the url
				// Does not work for custom post types.
				$post_id = $this->url_to_postid( $url );

				// Handle having a page as the home page when queriying for pages.
				if ( '/' === $url && 'page' === get_option( 'show_on_front' ) ) {
					$post_id = get_option( 'page_on_front' );
				}

				// If post for given URL can't be found - skip.
				// This will be the case for most archives.
				if ( ! $post_id ) {
					continue;
				}

				// GA will return all URL results - try and filter by post type.
				// Note that it would be far more efficient to filter GA results by using the permalink for the post type.
				// You can pass this as an arg.
				if ( ! in_array( get_post_type( $post_id ), (array) $args['post_type'] ) && 'any' !== $args['post_type'] ) {
					continue;
				}

				// Skip manually hidden.
				if ( get_post_meta( $post_id, 'hmtp_top_posts_optout', true ) ) {
					continue;
				}

				// If results should be restricted by taxonomy/terms supplied
				if (
					! is_null( $args['taxonomy'] ) &&
					! empty( $args['terms'] ) &&
					0 == count( array_intersect( wp_get_object_terms( $post_id, $args['taxonomy'], array( 'fields' => 'ids' ) ), $args['terms'] ) )
				) {
					continue;
				}

				if ( ! empty( $args['filter_callback'] ) ) {
					if ( ! call_user_func( $args['filter_callback'], $post_id, $result ) ) {
						continue;
					}
				}

				// Build an array of $post_id => $pageviews
				$top_posts[ $post_id ] = array(
					'post_id' => $post_id,
					'views'   => $result[1],
				);

				// break when we have enough posts.
				if ( isset( $top_posts ) && count( $top_posts ) >= $args['count'] ) {
					break;
				}

			}

			$opt_params['start-index'] += $max_results;

		}

		return $top_posts;

	}

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

}
