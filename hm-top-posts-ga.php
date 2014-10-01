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
				if ( ! is_numeric( $term ) )
					$term = get_term_by( 'name', $term, $this->args['taxonomy'] )->term_id;

		$defaults = array(
			'count' => 5,
			'filter' => null, // gapi filter
			'taxonomy' => null, // (string) taxonomy to query by.
			'terms' => array(), // array of terms to query by
			'term_operator' => 'IN', //IN, NOT IN, AND
			'start_date' => null, // format YYYY-mm-dd
			'end_date' => null, // format YYYY-mm-dd
			'post_type' => array( 'post' ), // only supports post & page.
			'background_only' => false
		);

		// If too many results - can filter results using permalink structure.
		// 'pagePath =~ ^' . str_replace( '/%postname%', '', get_option('permalink_structure') ) . '.*'

		$this->args = wp_parse_args( $this->args, $defaults );
		$this->query_id = 'hmtp_' . hash( 'crc32', serialize( array_merge( $this->args, array( $this->profile_id ) ) ) );

	}

	function get_results( $expires = 86400 ) {

		$this->args['background_only'] = false;

		if ( class_exists( 'TLC_Transient' ) ) {

			if ( $this->args['background_only'] )
				$results = tlc_transient( $this->query_id )->expires_in( $expires )->background_only()->updates_with( array( $this, 'fetch_results' ) )->get();

			else
				$results = tlc_transient( $this->query_id )->expires_in( $expires )->updates_with( array( $this, 'fetch_results' ) )->get();

			return $results;

		} else {

			if ( $results = get_transient( $this->query_id ) )
				return $results;

			$results = $this->fetch_results();

			set_transient( $this->query_id, $results, $expires );

			return $results;
		
		}

	}

	function fetch_results() {

		global $wpdb;
		/*@var wpdb $wpdb */

		$opt_outs = $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM wp_postmeta WHERE meta_key = %s AND meta_value = %s', 'hmtp_top_posts_optout', 'on' ) );

		// Build up a list of top posts.
		$top_posts = array();

		$args = array(
			'post_type'			=> $this->args['post_type'],
			'meta_key'		 	=> 'hmtp_view_count',
			'orderby'		 	=> 'meta_value',
			'posts_per_page'	=> $this->args['count'],
			'post__not_in'		=> $opt_outs,
		);

		if ( $this->args['taxonomy'] && $this->args['terms'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' 	=> $this->args['taxonomy'],
					'field' 	=> 'id',
					'terms' 	=> $this->args['terms'],
					'operator'	=> $this->args['term_operator']
				)
			);
		} elseif ( $this->args['taxonomy'] ) {

			$args['taxonomy'] = $this->args['taxonomy'];
		}

		$query = new WP_Query( $args );

		foreach( $query->get_posts() as $post ) {

			// Build an array of $post_id => $pageviews
			$top_posts[(int) $post->ID] = array(
				'post_id' =>  (int) $post->ID,
				'views'   => (int) get_post_meta( $post->ID, 'hmtp_view_count', true ),
			);
		}

		return $top_posts;
	}

	function update_post_view_counts( $hard_reset = false ) {

		global $wpdb;
		/* @var wpdb $wpdb */

		// If not - lets not bother going any further with this shall we.
		if ( empty( $this->username ) || empty( $this->password ) )
			return;

		try {
			$ga = new gapi( $this->username, $this->password );
		} catch( Exception $e ) {
			trigger_error( $e->getMessage(), E_USER_WARNING );
			update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
			return;
		}

		$dimensions = array( 'pagePath' );
		$metrics = array( 'pageviews' );

		// Update post meta with post view counts, hard reset will reset the post view counts.
		// Keeps going looping through - 30 results at a time - until there are no more results from GA.
		$top_posts = array();
		$start_index = 1;
		$results_per_loop = 1000;

		//reset view counts if hard reset
		if ( $hard_reset )
			$wpdb->delete( 'wp_postmeta', array( 'meta_key' => 'hmtp_view_count' ) );

		$start_date = ( $hard_reset ) ? date( 'Y-m-d', strtotime( '-5 years' ) ) : date( 'Y-m-d', strtotime( '-24 hours', time() ) );
		$end_date = date( 'Y-m-d', time() );

		while ( 1 ) {

			try {
				$ga->requestReportData( $this->profile_id, $dimensions, $metrics, '-pageviews', null, $start_date, $end_date, $start_index, $results_per_loop );
			} catch( Exception $e ) {
				trigger_error( $e->getMessage(), E_USER_WARNING );
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return;
			}

			$gaResults = $ga->getResults();

			if ( empty( $gaResults ) )
				break;

			$post_names =  array();

			//get the post names from the urls being hit
			foreach ( $gaResults as $result  ) {
				$url = apply_filters( 'hmtp_result_url', (string) $result );
				$url = esc_sql( sanitize_text_field( end( explode( '/', untrailingslashit( reset( explode( '?',  $url ) ) ) ) ) ) );

				if ( $url )
					$post_names[$url] = ( empty( $post_names[$url] ) ) ? $result->getPageviews() : $post_names[$url] + $result->getPageviews();
			}

			$posts = $wpdb->get_results( "SELECT * FROM wp_posts WHERE post_name IN ( '". implode( '\', \'', array_keys( $post_names ) ) . "' ) ");

			foreach ( $posts as $post ) {

				if ( empty( $post_names[$post->post_name] ) )
					continue;

				//update post meta with view count, add leading zeroes because meta_value is a string formatted field, orderby will not work correctly
				$cur = (int) get_post_meta( $post->ID, 'hmtp_view_count', true );

				$new_str = (string) ( $cur + $post_names[$post->post_name] );

				$new_leading_zeros = '';

				for ( $i = strlen( $new_str );  $i < 8; $i++ )
				 $new_leading_zeros .= '0';

				$new_leading_zeros .= $new_str;

				update_post_meta( $post->ID, 'hmtp_view_count', $new_leading_zeros );
			}

			$start_index += $results_per_loop;
		}

	}

}