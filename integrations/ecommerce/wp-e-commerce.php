<?php 

class Segment_Commerce_WPSC extends Segment_Commerce {

	public function init() {

		$this->register_hook( 'segment_get_current_page'      , 'viewed_category'  , 1, $this );
		$this->register_hook( 'segment_get_current_page_track', 'viewed_product'   , 1, $this );
		$this->register_hook( 'segment_get_current_page_track', 'completed_order'  , 1, $this );
		$this->register_hook( 'segment_get_current_page_track', 'added_to_cart'    , 2, $this );
		$this->register_hook( 'segment_get_current_page_track', 'removed_from_cart', 2, $this );

		/* HTTP actions */
		add_action( 'wpsc_add_to_cart', array( $this, 'add_to_cart' ), 10, 2 );

		/* When WPeC 3.9 is the min. version for this plugin, we'll use `wpsc_remove_item` */
		add_action( 'wpsc_refresh_item', array( $this, 'remove_from_cart' ), 10 );

	}
	
	public function viewed_category() {

		$args = func_get_args();
		$page = $args[0];

		if ( is_tax( 'wpsc_product_category' ) ) {
				$page = array(
					'page'       => single_term_title( '', false ),
					'properties' => array()
				);
		}

		return $page;
	}

	public function viewed_product() {

		$args  = func_get_args();
		$track = $args[0];

		if ( is_singular( 'wpsc-product' ) ) {
				$track = array(
					'event'      => 'Viewed Product',
					'properties' => array(
						'id'       => get_the_ID(),
						'sku'      => wpsc_product_sku(),
						'name'     => wpsc_the_product_title(),
						'price'    => wpsc_string_to_float( wpsc_the_product_price() ),
						'category' => implode( ', ', wp_list_pluck( wpsc_get_product_terms( get_the_ID(), 'wpsc_product_category' ), 'name' ) ),
					)
				);
		}
		
		return $track;
	}

	public function add_to_cart( $product, $cart_item ) {

		Segment_Cookie::set_cookie( 'added_to_cart', json_encode( 
				array( 
					'ID'       => $product->ID, 
					'quantity' => $cart_item->quantity,
					'name'     => $product->post_title,
					'price'    => $cart_item->unit_price
				)
			) 
		);
	}

	public function added_to_cart() {
		$args = func_get_args();

		$track = $args[0];

		if ( Segment_Cookie::get_cookie( 'added_to_cart' ) ) {

			$product = json_decode( Segment_Cookie::get_cookie( 'added_to_cart' ) );

			$item = array(
				'id'       => $product->ID,
				'sku'      => wpsc_product_sku( $product->ID ),
				'name'     => $product->name,
				'price'    => $product->price,
				'quantity' => $product->quantity,
				'category' => implode( ', ', wp_list_pluck( wpsc_get_product_terms( $product->ID, 'wpsc_product_category' ), 'name' ) ),
			);

			$track = array(
				'event'      => 'Added Product',
				'properties' => $item,
				'http_event' => 'added_to_cart'
			);

		}

		return $track;
	}

	public function remove_from_cart( $cart_item ) {
		if ( 0 == $cart_item->quantity ) {
			Segment_Cookie::set_cookie( 'removed_from_cart', json_encode( 
					array( 
						'ID'       => $cart_item->product_id, 
						'quantity' => 0,
						'name'     => $cart_item->product_title,
						'price'    => $cart_item->unit_price
					)
				) 
			);
		}
		
	}

	public function removed_from_cart() {
		$args = func_get_args();

		$track = $args[0];

		if ( Segment_Cookie::get_cookie( 'removed_from_cart' ) ) {

			$product = json_decode( Segment_Cookie::get_cookie( 'removed_from_cart' ) );

			$item = array(
				'id'       => $product->ID,
				'sku'      => wpsc_product_sku( $product->ID ),
				'name'     => $product->name,
				'price'    => $product->price,
				'quantity' => 0,
				'category' => implode( ', ', wp_list_pluck( wpsc_get_product_terms( $product->ID, 'wpsc_product_category' ), 'name' ) ),
			);

			$track = array(
				'event'      => 'Removed Product',
				'properties' => $item,
				'http_event' => 'removed_from_cart'
			);

		}

		return $track;
	}

	public function completed_order() {
		$args  = func_get_args();
		$track = $args[0];

		if ( did_action( 'wpsc_transaction_results_shutdown' ) && isset( $_GET['sessionid'] ) ) {

			$log = new WPSC_Purchase_Log( $_GET['sessionid'], 'sessionid' );

			/* We like checking is_order_received(), as that's what the manual payment gateway uses. */
			if ( $log->is_transaction_completed() || $log->is_order_received() ) {
				
				$gateway_data = $log->get_gateway_data();
				$items        = $log->get_cart_contents();
				$products     = array();

				foreach ( $items as $item ) {

					$product = array(
						'id'       => $item->prodid,
						'sku'      => wpsc_product_sku( $item->prodid ),
						'name'     => $item->name,
						'price'    => $item->price,
						'quantity' => $item->quantity,
						'category' => implode( ', ', wp_list_pluck( wpsc_get_product_terms( $item->prodid, 'wpsc_product_category' ), 'name' ) ),
					);

					$products[] = $product;

				}

				$track = array(
					'event'      => 'Completed Order',
					'properties' => array(
						'id'       => $log->get( 'id' )        ,
						'total'    => $log->get( 'totalprice' ),
						'revenue'  => $gateway_data['subtotal'],
						'shipping' => $gateway_data['shipping'],
						'tax'      => $gateway_data['tax'],
						'products' => $products
					)
				);

			}
		}

		return $track;
	}

}

function segment_commerce_wpsc() {
	$commerce = new Segment_Commerce_WPSC();

	return $commerce->init();
}

add_action( 'plugins_loaded', 'segment_commerce_wpsc', 100 );