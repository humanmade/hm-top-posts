<?php

namespace HMTP;

add_action( 'widgets_init', function () { register_widget( 'HMTP\\Widget' ); } );

class Widget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'HMTP_Widget', // Base ID
			'Top Post Widget', // Name
			array( 'description' => 'Display top posts using Google Analytics data.' ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {

		// Demo.
		$results = hmtp_get_top_posts( $instance['args'] );

		if ( ! $results ) {
			return;
		}

		echo $args['before_widget'];

		if ( $instance['title'] ) {
			echo $args['before_title'];
			echo esc_html( $instance['title'] );
			echo $args['after_title'];
		}

		ob_start();

		?>

		<ol class="hmtp-widget">
			<?php foreach ( $results as $post ) : ?>
				<li><?php printf(
						'<a href="%s">%s</a>',
						esc_url( get_permalink( $post['post_id'] ) ),
						esc_html( get_the_title( $post['post_id'] ) )
					); ?></li>
			<?php endforeach; ?>
		</ol>

		<?php

		echo apply_filters( 'hmtp-widget-output', ob_get_clean(), $results );

		echo $args['after_widget'];

	}


	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		$title = isset( $instance['title'] ) ? $instance['title'] : 'Most Popular';

		$args = wp_parse_args(
			(array) $instance['args'],
			array(
				'count'     => 5,
				'post_type' => array( 'post' ),
				'taxonomy'  => null,
				'terms'     => array(),
			)
		);

		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title</label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'args' ); ?>-count">Count</label>
			<input class="widefat" type="number" id="<?php echo $this->get_field_id( 'args' ); ?>-count" name="<?php echo $this->get_field_name( 'args' ); ?>[count]" value="<?php echo intval( $args['count'] ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'args' ); ?>-post-type">Post Type</label>
			<select class="widefat" multiple id="<?php echo $this->get_field_id( 'args' ); ?>-post-type" name="<?php echo $this->get_field_name( 'args' ); ?>[post_type][]">
				<?php foreach ( get_post_types( array( 'public' => true, 'publicly_queryable' => true ) ) as $post_type ) : ?>
					<option <?php selected( true, in_array( $post_type, (array) $args['post_type'] ) ); ?>><?php echo esc_html( $post_type ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'args' ); ?>-taxonomy">Taxonomy</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'args' ); ?>-taxonomy" name="<?php echo $this->get_field_name( 'args' ); ?>[taxonomy]">
				<option>None</option>
				<?php foreach ( get_taxonomies( '', 'names' ) as $tax ) : ?>
					<option <?php selected( $args['taxonomy'], $tax ); ?>><?php echo esc_html( $tax ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Tags</label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'args' ); ?>-terms" name="<?php echo $this->get_field_name( 'args' ); ?>[terms]" value="<?php echo esc_attr( implode( ', ', $args['terms'] ) ); ?>" />
			<small>Comma separated list of terms</small>
		</p>

		<?php

	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		$instance['args'] = array();

		$instance['args']['count'] = intval( $new_instance['args']['count'] );

		if ( ! empty( $new_instance['args']['post_type'] ) ) {
			$instance['args']['post_type'] = $new_instance['args']['post_type'];
		}

		if ( ! empty( $new_instance['args']['taxonomy'] ) && taxonomy_exists( $new_instance['args']['taxonomy'] ) ) {
			$instance['args']['taxonomy'] = $new_instance['args']['taxonomy'];
		}

		if ( ! empty( $new_instance['args']['terms'] ) ) {
			$instance['args']['terms'] = array_map( 'trim', explode( ',', $new_instance['args']['terms'] ) );
		}

		return $instance;

	}

}