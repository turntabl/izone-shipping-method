<?php
/*
Plugin Name: Izone Shipping method plugin
Description: Izone shipping plugin allows you to get the nearest stores based on your address or location
Version: 1.0.0
Author: Samuel Osei Kwakye
*/

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function izone_shipping_method_init() {
		if ( ! class_exists( 'WC_Izone_Shipping_Method' ) ) {
			class WC_Izone_Shipping_Method extends WC_Shipping_Method {
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					$this->id                 = 'izone_shipping_method_id'; // Id for your shipping method. Should be uunique.
					$this->method_title       = __( 'Izone Shipping Plugin' );  // Title shown in admin
					$this->method_description = __( 'Izone shipping plugin allows you to get the nearest stores based on your address or location' ); // Description shown in admin

					$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
					$this->title              = "Izone"; // This can be added as an setting but for this example its forced.

					$this->init();
				}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package = array() ) {
					$rate = array(
						'id' => $this->id,
						'label' => $this->title
						// 'cost' => '10.00',
						// 'calc_tax' => 'per_item'
					);

					// Register the rate
					$this->add_rate( $rate );
				}

			}
		}
	}	

	// Add custom fields to a specific selected shipping method
	add_action( 'woocommerce_after_shipping_rate', 'extra_custom_fields', 20, 2 );
	function extra_custom_fields( $method, $index ) {
	    if( ! is_checkout()) return; // Only on checkout page

	    $customer_shipping_method = 'izone_shipping_method_id';

	    if( $method->id != $customer_shipping_method ) return; // Only display for "turntabl shipping method"
	   
		$chosen_method_id = WC()->session->chosen_shipping_methods[ $index ];
		$customer_data = WC()->session->get('customer');
		$billing_city = $customer_data['city'];
		// echo '<br>';
		// echo $billing_city;

	    // If the chosen shipping method is 'turntabl_shipping_method' we display
	    if($chosen_method_id == $customer_shipping_method ){
			if(!class_exists("WC_IzoneUtility"))
						require plugin_dir_path(__FILE__) . "/utility.php";
			// echo "Address : ".$billing_city;
			if($billing_city == ""){
				wc_add_notice( ( "Please don't forget to enter town/city." ), "error" );
				return;
			}
			
			$url = "http://api.services.turntabl.io/gis/api/v1/nearest/store/".$billing_city;
			$response = WC_IzoneUtility::get_to_url($url);
			$response_decoded = json_decode($response);
			// var_dump("Response from gis server");
			// var_dump($response_decoded->data);
			// echo "Response from gis server : ".$response_decoded->code;
			$code = $response_decoded->code;
			$msg = $response_decoded->msg;
			$stores = $response_decoded->data;
			if ($code == "00") {

				if(empty($stores)){
					echo '<br>';
					woocommerce_form_field( 'selected_store' , array(
						'type'          => 'select',
						'class'         => array('form-row-wide selected-store'),
						'label'         => 'Select Store:',
						'required'      => true,
						'options'       => array(
							"" => ("No Store Available"),
					)), WC()->checkout->get_value( 'selected_store' ));
					wc_add_notice( ( "Could not find nearest store(s) for ".$billing_city ), "error" );

				}else{

					echo '<br>';
					woocommerce_form_field( 'selected_store' , array(
						'type'          => 'select',
						'class'         => array('form-row-wide selected-store'),
						'label'         => 'Select Store:',
						'required'      => true,
						'options'       => array(
							"" => ("No Store Available"),
					)), WC()->checkout->get_value( 'selected_store' ));

					?>
						<script type="text/javascript">
							jQuery( function($){
								var response = <?php echo $response; ?>
								console.log("Getting stores | ",response);
								if( response.data.length != 0){
									option = "";
									for (var i = 0; i < response.data.length; i++) {
										option+="<option value='" + JSON.stringify(response.data[i]) + "'>" + response.data[i]['label'].toLowerCase() +", "+ response.data[i]['landmark'].toLowerCase() + "</option>";
									}
									jQuery("#selected_store").html("");
									jQuery("#selected_store").html(option);
								}else{
									option = '<option value="">No Store Available</option>';
									jQuery("#selected_store").html("");
									jQuery("#selected_store").html(option);
								}
							});
						</script>
					<?php

				}
			}else{
				echo '<br>';
				woocommerce_form_field( 'selected_store' , array(
					'type'          => 'select',
					'class'         => array('form-row-wide selected-store'),
					'label'         => 'Select Store:',
					'required'      => true,
					'options'       => array(
						"" => ("No Store Available"),
				)), WC()->checkout->get_value( 'selected_store' ));
				wc_add_notice( ( $msg ), "error" );
			}
		}
	}

	//Check custom fields validation
	add_action('woocommerce_checkout_process', 'customer_checkout_process',10);
	function customer_checkout_process($order_id) {
	    if( isset( $_POST['selected_store'] ) && empty( $_POST['selected_store'] ) )
			wc_add_notice( ( "Please don't forget to select the delivery address." ), "error" );

	}

	add_action( 'woocommerce_checkout_order_processed', 'get_order_details',  1, 1  );
	function get_order_details( $order_id ){

		$order = new WC_Order( $order_id );

		$carts = [];
		foreach( $order->get_items() as $item_id => $item ){

			$product = array(
				"product_name" => $item['name'],
				"item_qty" => $item['quantity'],
				"line_subtotal" => $item['line_subtotal'],
				"line_total" => $item['line_total']
			);
			array_push($carts , $product);		

		}

		$shipping_add = [
			"firstname" => $order->billing_first_name,
			"lastname" => $order->billing_last_name,
			"address1" => $order->billing_address_1,
			"address2" => $order->billing_address_2,
			"city" => $order->billing_city,
			"zipcode" => $order->billing_postcode,
			"phone" => $order->billing_phone,
			"state_name" => $order->billing_state,
			"country" => $order->billing_country,
			"selected_store" =>  json_decode($order->selected_store),
			"items" => $carts
		];
		if(!class_exists("WC_IzoneUtility"))
				require plugin_dir_path(__FILE__) . "/utility.php";

				$url = "https://hook.integromat.com/mmwgf5v5bmk3cuk3r9g4wxabcnr14frq";
				$response = WC_IzoneUtility::post_to_url($url, $shipping_add);
				// var_dump("Response from integromat");
				// var_dump($response);

	}

	// Save custom fields to order meta data
	add_action( 'woocommerce_checkout_update_order_meta', 'customer_update_order_meta',1 );
	function customer_update_order_meta( $order_id ) {
	    if( isset( $_POST['selected_store'] ))
	        update_post_meta( $order_id, '_selected_store', sanitize_text_field( $_POST['selected_store'] ) );
	}

	add_action( 'woocommerce_shipping_init', 'izone_shipping_method_init' );

	function izone_shipping_method( $methods ) {
		$methods['izone_shipping_method_id'] = 'WC_Izone_Shipping_Method';
		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'izone_shipping_method' );


}