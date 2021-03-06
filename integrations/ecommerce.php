<?php 

abstract class Segment_Commerce {

	protected $registered_events = array();

	public function __construct() {

		$this->registered_events = array(
			'viewed_category',
			'viewed_product',
			'added_to_cart',
			'removed_from_cart',
			'completed_order',
		);

		return $this;
	}

	/**
	 * Registers hooks for the Segment Commerce system.
	 * 
	 * Usable by plugins to register methods or functions to hook into different eCommerce events.
	 * Someday, late static binding will be available to all WordPress users, which will make this a bit less hacky.
	 * 
	 * @since  0.6
	 * @access public
	 * 
	 * @param  string $hook  The WordPress action ( e.g. do_action( '' ) )
	 * @param  string $event The name of the function or method that handles the tracking output.
	 * @param  object $class The class, if any, that contains the $event method.
	 * 
	 * @return mixed  $registered False if no event was registered, string if function was registered, array if method.
	 */
	public function register_hook( $hook, $event, $args = 1, $class = '' ) {
		
		$registered_events = $this->get_registered_hooks();

		if ( ! empty( $class ) && is_callable( array( $class, $event ) ) ) {

			$this->registered_events[ $hook ] = array( $class, $event );
			
			$registered = add_filter( $hook, array( $class, $event ), 10, $args );

		} else if ( is_callable( $event ) ) {

			$registered = $this->registered_events[ $hook ] = $event;
			$registered = add_filter( $hook, $event, 10, $args );

		} else {

			$registered = false;

		}

		return $registered;
	}

	public function get_registered_hooks() {
		return apply_filters( 'segment_commerce_events', array_filter( $this->registered_events ), $this );
	}

	public static function bootstrap() {
		
		if ( class_exists( 'WP_eCommerce' ) ) {
			include_once SEG_FILE_PATH . '/integrations/ecommerce/wp-e-commerce.php';
		} else if ( class_exists( 'WooCommerce' ) ) {
			include_once SEG_FILE_PATH . '/integrations/ecommerce/woocommerce.php';
		}

	}

	abstract function viewed_category();
	abstract function viewed_product();
	abstract function added_to_cart();
	abstract function removed_from_cart();
	abstract function completed_order();

}

Segment_Commerce::bootstrap();