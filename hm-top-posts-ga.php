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
			'term_operator' => '||',
			'start_date' => null, // format YYYY-mm-dd
			'end_date' => null, // format YYYY-mm-dd
			'post_type' => array( 'post' ), // only supports post & page.
			'background_only' => true
		);

		// If too many results - can filter results using permalink structure.
		// 'pagePath =~ ^' . str_replace( '/%postname%', '', get_option('permalink_structure') ) . '.*'

		$this->args = wp_parse_args( $this->args, $defaults );
		$this->query_id = 'hmtp_' . hash( 'crc32', serialize( array_merge( $this->args, array( $this->profile_id ) ) ) );

	}

	function get_results( $expires = 86400 ) {

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

		$results_per_loop = $this->args['count'] * 2;

		$this->args['filter'] = apply_filters( 'hmtp_ga_filter', $this->args['filter'], $this->args );

		while ( count( $top_posts ) < $this->args['count'] ) {

 			try {
				$ga->requestReportData( $this->profile_id, $dimensions, $metrics, '-pageviews', $this->args['filter'], $this->args['start_date'], null, $start_index, $results_per_loop );
			} catch( Exception $e ) {
				update_option( 'hmtp_top_posts_error_message', $e->getMessage() );
				return array();
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
					$post_names[$url] = $result->getPageviews();
			}

			$posts = $wpdb->get_results( "SELECT * FROM wp_posts WHERE post_name IN ( '". implode( '\', \'', array_keys( $post_names ) ) . "' ) AND post_type IN ( '" .  implode( '\', \'', $this->args['post_type'] ) . "' )" );

			foreach ( $posts as $post )
				$post_ids[] = (int) $post->ID;

			$opt_outs = $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM wp_postmeta WHERE meta_key = %s AND meta_value = %s', 'hmtp_top_posts_optout', 'on' ) );

			if ( ! is_null( $this->args['taxonomy'] ) && ! empty( $this->args['terms'] ) )
				$posts_in_terms = $wpdb->get_col(
					"SELECT object_id FROM wp_term_taxonomy INNER JOIN wp_term_relationships ON wp_term_taxonomy.term_taxonomy_id=wp_term_relationships.term_taxonomy_id" .
					" WHERE taxonomy IN ( '" . $this->args['taxonomy'] . "' ) AND term_id IN ( " . implode( ', ', $this->args['terms'] ) . " ) AND object_id IN ( " . implode( ', ', $post_ids ) . " )"
				);

			foreach ( $posts as $post ) {

				if ( in_array( $post->ID, $opt_outs ) )
					continue;

				// If taxonomy and terms supplied - check if there is any intersect between those terms and the post terms.
				if ( ! is_null( $this->args['taxonomy'] ) && ! empty( $this->args['terms'] ) && ! in_array( $post->ID, $posts_in_terms ) )
					continue;

				// Build an array of $post_id => $pageviews
				$top_posts[(int) $post->ID] = array(
					'post_id' =>  (int) $post->ID,
					'views'   => $post_names[$post->post_name],
				);

				// Once we have enough posts we can break out of this.
				if ( isset( $top_posts ) && count( $top_posts ) >= $this->args['count'] )
					break;
			}

			$start_index += $results_per_loop;

		}

		uasort( $top_posts, function( $a, $b ) {
			return $a['views'] < $b['views'];
		} );

		return $top_posts;

	}

}