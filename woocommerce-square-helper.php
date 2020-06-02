<?php
/**
 * Plugin Name: WooCommerce Square Helper
 * Version: 1.0.1
 * Plugin URI: https://github.com/jessepearson/woocommerce-square-helper
 * Description: WooCommerce Square Helper tool for use with debugging.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Requires at least: 4.7.0
 * Tested up to: 5.4.0
 *
 * @package WordPress
 * @author WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Square_Helper' ) ) {
	/**
	 * Main class.
	 *
	 * @package WC_Square_Helper
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WC_Square_Helper {

		/**
		 * Notices.
		 * Shouldn't really need to touch this.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 * @var
		 */
		public $notice;

		/**
		 * Constructor.
		 * Shouldn't really need to touch this.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function __construct() {
			$this->init();
		}

		/**
		 * Initialize.
		 * Shouldn't really need to touch this.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function init() {
			add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );
			add_action( 'init', array( $this, 'catch_requests' ), 20 );
		}

		/**
		 * Adds submenu page to tools.
		 * Shouldn't really need to touch this.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function add_submenu_page() {
			add_submenu_page( 'tools.php', 'Square Helper', 'Square Helper', 'manage_options', 'square-helper', array( $this, 'tool_page' ) );
		}

		/**
		 * Renders the tool page.
		 * 
		 * In order to create another tool on the page, copy and paste the form, then add/modify needed fields.
		 * Once new form is added move to catch_requests() to add your new action. 
		 *
		 * @since 1.0.0
		 * @version 1.0.1
		 */
		public function tool_page() {

			// Start output
			?>
			<div class="wrap">
				<h1>Square Helper</h1>
				<hr />
				<div>

			<?php 

			// Check to see that WooCommerce and Square are both active, and Square is connected
			$woocommerce_inactive = $square_inactive = $square_connected = false;
			if ( ! class_exists( 'WooCommerce' ) ) {
				$this->print_notice( 'WooCommerce is not active.', 'error' );
				$woocommerce_inactive = true;
			}

			if ( ! class_exists( 'WooCommerce_Square_Loader' ) ) {
				$this->print_notice( 'Square is not active.', 'error' );
				$square_inactive = true;
			} else {

				if ( ! wc_square()->get_settings_handler()->is_connected() ) {
					$this->print_notice( 'Square is not connected.', 'error' );
					$square_not_connected = true;
				}
			}

			// Output any notices or errors. 
			if ( ! empty( $this->notice ) ) {
				echo $this->notice;
			}

			// If we have an error, ask to fix it and exit. 
			if ( $woocommerce_inactive || $square_inactive || $square_not_connected ) {	
				?>
					<p>Please correct the errors listed above, and then the tools will be available.</p>
				</div>
			</div>

				<?php
				exit;
			} 

			// Set the form action URL.
			$action_url = add_query_arg( array( 'page' => 'square-helper' ), admin_url( 'tools.php' ) );
			?>

					<h3>Sync Product Inventory</h3>
					<form action="<?php echo $action_url; ?>" method="post" style="margin-bottom:20px;border:1px solid #ccc;padding:5px;">
						<table>
							<tr>
								<td>
									<label>Product or variation ID: <input type="number" name="product_id" min="1" /></label>
									<input type="submit" class="button" value="Sync Inventory" /> <label>Syncs inventory of a specific product or variation with Square.</label>
									<input type="hidden" name="action" value="sync_product" />
									<?php wp_nonce_field( 'sync_product' ); ?>
								</td>
							</tr>
						</table>
					</form>

				</div>
			</div>
			<?php
		}

		/**
		 * Catches form requests.
		 *
		 * Here you will need to add your action to the $actions array. 
		 * Next your action will need to be added to the switch statement to call your processing function.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function catch_requests() {

			// Check to make sure we're on the proper page.
			if ( ! isset( $_GET['page'] ) || 'square-helper' !== $_GET['page'] ) {
				return;
			}

			// If there's no action or nonce, exit quietly. 
			if ( ! isset( $_POST['action'] ) || ! isset( $_POST['_wpnonce'] ) ) {
				return;
			}

			$actions = [
				'sync_product',
			];

			if ( ! in_array( $_POST['action'], $actions ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], $_POST['action'] ) ) {
				wp_die( 'Cheatin&#8217; huh?' );
			}

			switch ( $_POST['action'] ) {
				case 'sync_product':
					$this->sync_single_product();
					break;
			}
		}

		/**
		 * An exmple processing function.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function example_processing_function() {
			$this->log( 'Begin example processing.' );

			try {

				if ( true ) {
					$this->log( 'That worked.' );
					$this->print_notice( 'That worked.' );

				} else  {
					$this->log( 'That failed.' );
					throw new Exception( 'That failed!' );
				}

				$this->print_notice( 'Done processing.' );

			} catch ( Exception $e ) {

				$this->print_notice( $e->getMessage() );
				return;
			}
		}

		/**
		 * Syncs a specific product by ID.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		public function sync_single_product() {
			$this->log( 'Attempting to sync inventory of single product.' );

			try {
				// Make sure we have a valid product
				$product_id     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : '';
				$this->log( 'Product ID submitted: '. $product_id );
				
				$product = wc_get_product( $product_id );

				if ( $product instanceof WC_Product ) {

					$term_product = $product;
					// if this is a variation, check its parent
					if ( $parent_product = wc_get_product( $term_product->get_parent_id() ) ) {
						
						if ( $parent_product instanceof WC_Product ) {
							$term_product = $parent_product;
							$this->log( $product_id .' is a variation of '. $product->get_id() );
						}
					}
				} else  {
					$this->log( $product_id .' is not a valid product.' );
					throw new Exception( 'Product ID: '. $product_id .' does not exist!' );
				}

				// Does it have the proper taxonomy?
				$terms = wp_get_post_terms( $term_product->get_id(), 'wc_square_synced', [ 'fields' => 'names' ] );

				if ( empty( $terms ) || 'yes' !== $terms[0] ) {
					$this->log( $product_id .' is not set to be synced with Square.' );
					throw new Exception( 'Product ID: '. $product_id .' is not set to be synced with Square' );
				}

				// Does it have the _square_item_variation_id?
				$square_item_variation_id = get_post_meta( $product_id, '_square_item_variation_id', true ) ?: null;

				if ( null === $square_item_variation_id ) {
					$this->log( $product_id .' does not have a _square_item_variation_id set.' );
					throw new Exception( 'Product ID: '. $product_id .' does not have a _square_item_variation_id set.' );
				} else {
					$this->log( $product_id .' has _square_item_variation_id:'. $square_item_variation_id );
				}

				// Set the args.
				$args = [
					'location_ids'       => [ wc_square()->get_settings_handler()->get_location_id() ],
					'catalog_object_ids' => [ $square_item_variation_id ],
				];

				// Query the inventory from Square for the product. 
				$this->log( 'Querying batch_retrieve_inventory_counts with args:'."\n". print_r( $args, true ) );
				$response = wc_square()->get_api()->batch_retrieve_inventory_counts( $args );
				$this->log( 'Response received:'."\n". print_r( $response, true ) );

				foreach ( $response->get_counts() as $count ) {

					// Square can return multiple "types" of counts, WooCommerce only distinguishes whether a product is in stock or not
					if ( 'IN_STOCK' === $count->getState() ) {

						$product->set_stock_quantity( $count->getQuantity() );
						$product->save();
						$this->log( $product_id .' inventory count updated to: '. $count->getQuantity() );
						$this->print_notice( $product_id .' inventory count updated to: '. $count->getQuantity() );

					}
				}

				$this->print_notice( 'Done processing.' );

			} catch ( Exception $e ) {

				$this->print_notice( $e->getMessage() );
				return;
			}
		}

		/**
		 * Prints notices.
		 *
		 * Shouldn't really need to touch this.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 * @param string $message
		 * @param string $type
		 */
		public function print_notice( $message = '', $type = 'warning' ) {

			$notice = '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . '</p></div>';

			if ( '' !== $this->notice ) {
				$this->notice = $this->notice ."\n". $notice;
			} else {
				$this->notice = $notice;
			}
		}

		/**
		 * Logs data.
		 *
		 * Shouldn't really need to touch this.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 * @param string $message
		 * @param string $type
		 */
		public function log( $message = '' ) {
			$log     = wc_get_logger();
			$log->debug( $message, [ 'source' => 'square-helper' ] );
		}
	}

	new WC_Square_Helper();
}
