<?php
/**
 * Get Top Articles by Google Analytics.
 *
 * Get the results of the query stored for quiery_id. If it doesn't exist, 
 * a new one is generated based on $args.
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

	/**
	 * @var string
	 */
	private $prefix = 'hmtp';
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
		'count' => 5,
		'filter' => null, // gapi filter
		'taxonomy' => null, // (string) taxonomy to query by.
		'terms' => array(), // array of terms to query by
		'start_date' => null, // format YYYY-mm-dd. 1 month ago.
		'end_date' => null, // format YYYY-mm-dd
		'post_type' => array( 'post' ), // only supports post & page.
	);

	/**
	 * @var Google_AnalyticsService
	 */
	private $analytics;

 	/**
	 *  @varPlugin Settings
	 */
	private $settings;


	/**
	 * @var
	 */
	private $ga_property_profile_id;

	function __construct( $ga_property_profile_id, Google_AnalyticsService $analytics ) {

		$this->args_defaults['start_date'] = date( 'Y-m-d', time() - 2628000 );
		$this->args_defaults['end_date']   = date( 'Y-m-d', time() );

		$this->settings = $settings;
		$this->analytics = $analytics;

		// If too many results - can filter results using permalink structure.
		// 'pagePath =~ ^' . str_replace( '/%postname%', '', get_option('permalink_structure') ) . '.*'

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
		if ( ! empty( $args['terms'] ) )
			foreach ( $args['terms'] as &$term ) {
				if ( ! is_numeric( $term ) )
					$term = get_term_by( 'name', $term, $args['taxonomy'] )->term_id;
			}

		$this->query_id = 'hmtp_' . hash( 'md5', $this->settings['ga_property_profile_id'] . json_encode( $args ) );
		
		// If TLC Transients exists, use that.
		if ( class_exists( 'TLC_Transient' ) ) {
			$results = tlc_transient( $this->query_id )->expires_in( $this->expiry )->background_only()->updates_with( array( $this, 'fetch_results' ), array( $args ) )->get();

			return $results;

		// Fall back to boring old normal transients.
		} else {

			if ( $results = get_transient( $this->query_id ) )
				return $results;

			$results = $this->fetch_results( $args );

			set_transient( $this->query_id, $results, $this->expiry );

			return $results;
		
		}

	}

	function fetch_results( $args ) {
		
		$dimensions = array( 'pagePath' );
		$metrics = array( 'pageviews' );
		$max_results = 1000;

		// Build up a list of top posts.
		// Keeps going looping through - $max_results results at a time -
		// until there are either enough posts or no more results from GA.
		$top_posts = array();
		$start_index = 1;

		while ( count( $top_posts ) < $args['count'] ) {

			try {
				
				$results = $this->analytics->data_ga->get(
					'ga:' . $this->settings['ga_property_profile_id'],
					$args['start_date'],
					$args['end_date'],
					'ga:pageviews',
			        array(
			            'dimensions' => 'ga:pagePath',
			            'sort' => '-ga:pageviews',
			            'max-results' => $max_results,
			            'start-index' => $start_index
			        )
			    );

			} catch( Exception $e ) {
				
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return;
			
			}

			if ( count( $results->getRows() ) < 1 )
				break;

			$posts = $this->get_posts_from_results( $results->getRows(), $args );

			foreach ( $posts as $post  ) {

				// Check the post type is one of the ones we want.
				if ( ! in_array( get_post_type( $post['post_id'] ), $args['post_type'] ) )
					continue;

				// Check this post is not excluded from results.
				if ( get_post_meta( $post['post_id'], 'hmtp_top_posts_optout', true  ) )
					continue;

				// If taxonomy and terms supplied - check if theyre is any intersect between those terms and the post terms.
				if ( ! empty( $args['taxonomy'] ) && ! empty( $args['terms'] ) ) {
					$object_terms = wp_get_object_terms( $post['post_id'], $args['taxonomy'], array( 'fields' => 'ids') );
					if ( ! count( array_intersect( $object_terms, $args['terms'] ) ) )
					continue;
				}

				// Build an array of $post_id => $pageviews
				$top_posts[$post['post_id']] = array(
					'post_id' => $post['post_id'],
					'views'   => $result[1],
				);

				// Once we have enough posts we can break out of this.
				if ( isset( $top_posts ) && count( $top_posts ) >= $args['count'] )
					break;

			}

			$start_index += $max_results;

		}

		return $top_posts;

	}

	/**
	 *  Proccess the analytics query results and return post id and pageview count.
	 *
	 *	This is split into 
	 * 
	 * @param  [type] $results [description]
	 * @return [type]          [description]
	 */
	function get_posts_from_results( $results, $args ) {
		
		$posts = array();

		if ( $this->settings['no_url_to_postid'] ) {

			$post_names = array();

			foreach ( $results as &$result  ) {
				
				$url = apply_filters( 'hmtp_result_url', (string) $result );
				$url = esc_sql( sanitize_text_field( end( explode( '/', untrailingslashit( reset( explode( '?',  $url ) ) ) ) ) ) );

				if ( $url )
					$result['post-name'] = ( empty( $post_names[$url] ) ) ? $result->getPageviews() : $post_names[$url] + $result->getPageviews();
			
			}

			$post_names = array_map( function( $result ) { return $result['post-name'] }, $results );

			global $wpdb;

			// @todo prepare.
			$posts = $wpdb->get_results( "SELECT ID, post_name FROM wp_posts WHERE post_name IN ( '". implode( '\', \'', array_keys( $post_names ) ) . "' ) ");

		} else {

			foreach ( $results as $result  ) {
					
				// Get the post id from the url
				// Does not work for custom post types.
				$post_id = url_to_postid( str_replace( 'index.htm', '', apply_filters( 'hmtp_result_url', (string) $result[0] ) ) );

				if ( $post_id )
					$posts[$post_id] = array( 'post_id' => $post_id, 'views' => $result[1] );

			}

		}

		return $posts;

	}

}