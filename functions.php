<?php

// Add a custom select fields for packing option fee
add_action( 'woocommerce_before_order_notes', 'checkout_shipping_form_packing_addition', 20 );
function checkout_shipping_form_packing_addition( ) {
	$domain = 'woocommerce';

	echo '<tr class="packing-select"><th>' . __('Packing options', $domain) . '</th><td>';

	$chosen   = WC()->session->get('chosen_packing');

	// Add a custom checkbox field
	woocommerce_form_field( 'chosen_packing', array(
		'type'      => 'select',
		'class'     => array( 'form-row-wide packing' ),
		'options'   => array(
			''    => __("Choose a packing option ...", $domain),
			'free' => sprintf( __("Free Box (%s)", $domain), strip_tags( wc_price(0.00) ) ),
			'box' => sprintf( __("In a gift box (%s)", $domain), strip_tags( wc_price(10.00) ) ),
		),
		'required'  => true,
	), $chosen );

	echo '</td></tr>';
}

// jQuery - Ajax script
add_action( 'wp_footer', 'checkout_shipping_packing_script' );
function checkout_shipping_packing_script() {
	// Only checkout page
	if ( is_checkout() && ! is_wc_endpoint_url() ) :

		WC()->session->__unset('chosen_packing');
		?>
        <script type="text/javascript">
            jQuery( function($){
                $('form.checkout').on('change', 'select#chosen_packing', function(){
                    var p = $(this).val();
                    console.log(p);
                    $.ajax({
                        type: 'POST',
                        url: wc_checkout_params.ajax_url,
                        data: {
                            'action': 'woo_get_ajax_data',
                            'packing': p,
                        },
                        success: function (result) {
                            $('body').trigger('update_checkout');
                            console.log('response: '+result); // just for testing | TO BE REMOVED
                        },
                        error: function(error){
                            console.log(error); // just for testing | TO BE REMOVED
                        }
                    });
                });
            });
        </script>
	<?php
	endif;
}

// Php Ajax (Receiving request and saving to WC session)
add_action( 'wp_ajax_woo_get_ajax_data', 'woo_get_ajax_data' );
add_action( 'wp_ajax_nopriv_woo_get_ajax_data', 'woo_get_ajax_data' );
function woo_get_ajax_data() {
	if ( isset($_POST['packing']) ){
		$packing = sanitize_key( $_POST['packing'] );
		WC()->session->set('chosen_packing', $packing );
		echo json_encode( $packing );
	}
	die(); // Alway at the end (to avoid server error 500)
}

// Add a custom dynamic packaging fee
add_action( 'woocommerce_cart_calculate_fees', 'add_packaging_fee', 20, 1 );
function add_packaging_fee( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) )
		return;

	$domain      = "woocommerce";
	$packing_fee = WC()->session->get( 'chosen_packing' ); // Dynamic packing fee

	if ( $packing_fee === 'free' ) {
		$label = __("Bag packing fee", $domain);
		$cost  = 0.00;
	} elseif ( $packing_fee === 'box' ) {
		$label = __("Gift box packing fee", $domain);
		$cost  = 10.00;
	}

	if ( isset($cost) )
		$cart->add_fee( $label, $cost );
}

// Field validation, as this packing field is required
add_action('woocommerce_checkout_process', 'packing_field_checkout_process');
function packing_field_checkout_process() {
	// Check if set, if its not set add an error.
	if ( isset($_POST['chosen_packing']) && empty($_POST['chosen_packing']) )
		wc_add_notice( __( "Please choose a packing option...", "woocommerce" ), 'error' );
}

// Save the custom checkout field in the order meta, when checkbox has been checked
add_action( 'woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta', 10, 1 );
function custom_checkout_field_update_order_meta( $order_id ) {

	if ( ! empty( $_POST['packing'] ) ){
		update_post_meta( $order_id, 'packing', $_POST['packing'] );
	}
}

// Display the custom field result on the order edit page (backend) when checkbox has been checked
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_on_order_edit_pages', 10, 1 );
function display_custom_field_on_order_edit_pages( $order ){
	$packing = get_post_meta( $order->get_id(), 'packing', true );
    echo '<p><strong style="color: #f44336">packing:  </strong> <span > ' . $packing .' </span></p>';
}
