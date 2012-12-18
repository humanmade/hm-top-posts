<?php
/**
 * Get Top Articles by Google Analytics.
 *
 * Get the results of the query stored for quiery_id. If it doesn't exist, a new one is generated based on $args.
 * Retruns an array of  $post_id => $page_views.
 *
 * USAGE
 *
 * Instantiate class - passing args.
 * Call get_top_posts
 * Done!
 *
 * @param (array) $args
 */
class HMTP_Top_Posts {

	var $prefix = 'hmtp';

	function __construct( $args = array() ) {

		$this->profile_id = get_option('hmtp_top_posts_setting_profile_id');
		$this->username = get_option('hmtp_top_posts_setting_username');
		$this->password = get_option('hmtp_top_posts_setting_password');

		$this->args = $args;
		$this->parse_args();

	}

	function parse_args() {

		// Convert term names to IDs.
		if ( ! empty( $this->args['terms'] ) )
			foreach( $this->args['terms'] as &$term )
				if ( ! is_int( $term ) )
					$term = get_term_by( 'name', $term, $this->args['taxonomy'] )->term_id;

		$defaults = array(
			'count' => 5,
			'filter' => 'pagePath =~ ^' . str_replace( '/%postname%', '', get_option('permalink_structure') ) . '.*',
			'taxonomy' => null,
			'terms' => array(),
			'start_date' => null, // 1 year ago
			'post_type' => array( 'post' )
		);

		$this->args = wp_parse_args( $this->args, $defaults );
		$this->query_id = 'hmtp_' . hash( 'crc32', serialize( array_merge( $this->args, array( $this->profile_id ) ) ) );

	}

	function get_results() {

		if ( $results = get_transient( $this->query_id ) )
			return $results;

		$results = $this->fetch_results();

		set_transient( $this->query_id, $results, 86400 );

		return $results;

	}

	function fetch_results() {

		// If not - lets not bother going any further with this shall we.
		if ( empty( $this->username ) || empty( $this->password ) )
			return array();

		try {
			$ga = new gapi( $this->username, $this->password );
		} catch( Exception $e ) {
			update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
			return;
		}

		$dimensions = array( 'pagePath' );
		$metrics = array( 'pageviews' );

		// Build up a list of top posts.
		// Keeps going looping through - 30 results at a time - until there are either enough posts or no more results from GA.
		$top_posts = array();
		$start_index = 1;
		while ( count( $top_posts ) < $this->args['count'] ) {

			try {
				$ga->requestReportData( $this->profile_id, $dimensions, $metrics, '-pageviews', $this->args['filter'], $this->args['start_date'], null, $start_index, 30 );
			} catch( Exception $e ) {
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return;
			}

			$gaResults = $ga->getResults();

			if ( empty( $gaResults ) )
				break;

			foreach ( $ga->getResults() as $result  ) {

				// Get the post id from the url
				// Does not work for custom post types.
				$post_id = url_to_postid( str_replace( 'index.htm', '', (string) apply_filters( 'hmtp_result_url', $result ) ) );

				// Does this top url even relate to a post at all?
				// If your permalink structure clashes with page/category/tag structure it just might.
				if ( ! $post_id )
					continue;

				// This can get confusing if we don't pass explicit post types. Who knows what GA will come up with.
				if ( ! in_array( get_post_type( $post_id ), $this->args['post_type'] ) )
					continue;

				if ( get_post_meta( $post_id, 'hmtp_top_posts_optout', true  ) === 'on' )
					continue;

				// If taxonomy and terms supplied - check if theyre is any intersect between those terms and the post terms.
				if ( ! is_null( $this->args['taxonomy'] ) && ! empty( $this->args['terms'] ) && 0 == count( array_intersect( wp_get_object_terms( $post_id, $this->args['taxonomy'], array( 'fields' => 'ids') ), $this->args['terms'] ) ) )
					continue;

				// Build an array of $post_id => $pageviews
				$top_posts[ $post_id ] = $result->getPageviews();

				// Once we have enough posts we can break out of this.
				if ( isset( $top_posts ) && count( $top_posts ) >= $this->args['count'] )
					break;

			}

			$start_index += 30;

		}

		return $top_posts;

	}

}