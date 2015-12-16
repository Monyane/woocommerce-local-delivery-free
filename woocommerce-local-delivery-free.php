<?php
/**
 * Plugin Name: WooCommerce Local Delivery Free
 * Plugin URI: https://github.com/wpnit/woocommerce-local-delivery-free
 * Description: WooCommerce plugin joining the delivery methods Local Delivery and Free Shipping
 * Author: WPNit
 * Author URI: http://wpnit.com
 * Version: 1.0
 * Text Domain: woocommerce-local-delivery-free
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function local_delivery_free_method_init() {
        if ( ! class_exists( 'WC_Shipping_Local_Delivery_Free' ) ) {


/**
 * Local Delivery Shipping Method With Free Option.
 *
 * A simple shipping method allowing local delivery as a shipping method with a free shipping option.
 *
 * @class               WC_Shipping_Local_Delivery_Free
 * @version             1.0.0
 * @package             WooCommerce/Classes/Shipping
 * @author              WPNit
 */
class WC_Shipping_Local_Delivery_Free extends WC_Shipping_Local_Pickup {

	/** @var float Min amount to be valid */
	public $min_amount;

	/** @var string Requires option */
	public $requires;
	
    /**
    * Constructor.
    */
    public function __construct() {
        $this->id                 = 'local_delivery_free';
        $this->method_title       = __( 'Local Delivery', 'woocommerce' ) . ' ' . __( 'Free', 'woocommerce' );
        $this->method_description = __( 'Local delivery is a simple shipping method for delivering orders locally.', 'woocommerce' );
        $this->init();
    }

    /**
    * init function.
    */
    public function init() {
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->type         = $this->get_option( 'type' );
        $this->fee          = $this->get_option( 'fee' );
        $this->type         = $this->get_option( 'type' );
        $this->codes        = $this->get_option( 'codes' );
        $this->availability = $this->get_option( 'availability' );
        $this->countries    = $this->get_option( 'countries' );

		$this->requires		= $this->get_option( 'requires' );
		$this->min_amount 	= $this->get_option( 'min_amount', 0 );
        
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * calculate_shipping function.
     *
     * @param array $package (default: array())
     */
    public function calculate_shipping( $package = array() ) {
        $shipping_total = 0;
        $is_free        = false;

        // Check if shipping is free
        if ( in_array( $this->requires, array( 'min_amount', 'coupon', 'either', 'both' ) ) ) {
            $is_free = $this->is_free( $package );
        }
    
        if ( !$is_free ) {
            switch ( $this->type ) {
                case 'fixed' :
                    $shipping_total = $this->fee;
                    break;
                case 'percent' :
                    $shipping_total = $package['contents_cost'] * ( $this->fee / 100 );
                    break;
                case 'product' :
                    foreach ( $package['contents'] as $item_id => $values ) {
                        if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
                            $shipping_total += $this->fee * $values['quantity'];
                        }
                    }
                    break;
            }
        }

        $rate = array(
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $shipping_total
        );

        $this->add_rate( $rate );
    }

	/**
	 * is_free function.
	 * @param array $package
	 * @return bool
	 */
	public function is_free( $package ) {
		// Enabled logic
		$is_available       = false;
		$has_coupon         = false;
		$has_met_min_amount = false;

		if ( in_array( $this->requires, array( 'coupon', 'either', 'both' ) ) ) {
			if ( $coupons = WC()->cart->get_coupons() ) {
				foreach ( $coupons as $code => $coupon ) {
					if ( $coupon->is_valid() && $coupon->enable_free_shipping() ) {
						$has_coupon = true;
					}
				}
			}
		}

		if ( in_array( $this->requires, array( 'min_amount', 'either', 'both' ) ) && isset( WC()->cart->cart_contents_total ) ) {
			if ( WC()->cart->prices_include_tax ) {
				$total = WC()->cart->cart_contents_total + array_sum( WC()->cart->taxes );
			} else {
				$total = WC()->cart->cart_contents_total;
			}
			if ( $total >= $this->min_amount ) {
				$has_met_min_amount = true;
			}
		}

		switch ( $this->requires ) {
			case 'min_amount' :
				if ( $has_met_min_amount ) {
					$is_available = true;
				}
			    break;
			case 'coupon' :
				if ( $has_coupon ) {
					$is_available = true;
				}
			    break;
			case 'both' :
				if ( $has_met_min_amount && $has_coupon ) {
					$is_available = true;
				}
			    break;
			case 'either' :
				if ( $has_met_min_amount || $has_coupon ) {
					$is_available = true;
				}
			    break;
			default :
				$is_available = false;
			    break;
		}

		//return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
		return $is_available;
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable local delivery', 'woocommerce' ),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'Local Delivery', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'type' => array(
				'title'       => __( 'Fee Type', 'woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'How to calculate delivery charges', 'woocommerce' ),
				'default'     => 'fixed',
				'options'     => array(
				    'fixed'       => __( 'Fixed amount', 'woocommerce' ),
					'percent'     => __( 'Percentage of cart total', 'woocommerce' ),
					'product'     => __( 'Fixed amount per product', 'woocommerce' ),
				),
				'desc_tip'    => true,
			),
			'fee' => array(
				'title'       => __( 'Delivery Fee', 'woocommerce' ),
				'type'        => 'price',
				'description' => __( 'What fee do you want to charge for local delivery, disregarded if you choose free. Leave blank to disable.', 'woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => wc_format_localized_price( 0 )
			),
			'codes' => array(
                'title'       => __( 'Allowed Zip/Post Codes', 'woocommerce' ),			    
				'type'        => 'text',
				'desc_tip'    => __( 'What zip/post codes are available for local delivery?', 'woocommerce' ),
				'default'     => '',
				'description' => __( 'Separate codes with a comma. Accepts wildcards, e.g. <code>P*</code> will match a postcode of PE30. Also accepts a pattern, e.g. <code>NG1___</code> would match NG1 1AA but not NG10 1AA', 'woocommerce' ),
				'placeholder' => 'e.g. 12345, 56789'
			),
			'availability' => array(
				'title'       => __( 'Method availability', 'woocommerce' ),
				'type'        => 'select',
				'default'     => 'all',
				'class'       => 'availability wc-enhanced-select',
				'options'     => array(
					'all'         => __( 'All allowed countries', 'woocommerce' ),
					'specific'    => __( 'Specific Countries', 'woocommerce' )
				)
			),
			'countries' => array(
				'title'       => __( 'Specific Countries', 'woocommerce' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'css'         => 'width: 450px;',
				'default'     => '',
				'options'     => WC()->countries->get_shipping_countries(),
				'custom_attributes' => array(
					'data-placeholder' => __( 'Select some countries', 'woocommerce' )
				)
			),
			'requires' => array(
				'title' 		=> __( 'Free Shipping Requires...', 'woocommerce' ),
				'type' 			=> 'select',
				'class'         => 'wc-enhanced-select',
				'default' 		=> '',
				'options'		=> array(
					'' 				=> __( 'N/A', 'woocommerce' ),
					'coupon'		=> __( 'A valid free shipping coupon', 'woocommerce' ),
					'min_amount' 	=> __( 'A minimum order amount (defined below)', 'woocommerce' ),
					'either' 		=> __( 'A minimum order amount OR a coupon', 'woocommerce' ),
					'both' 			=> __( 'A minimum order amount AND a coupon', 'woocommerce' ),
				)
			),
			'min_amount' => array(
				'title' 		=> __( 'Minimum Order Amount', 'woocommerce' ),
				'type' 			=> 'price',
				'placeholder'	=> wc_format_localized_price( 0 ),
				'description' 	=> __( 'Users will need to spend this amount to get free shipping (if enabled above).', 'woocommerce' ),
				'default' 		=> '0',
				'desc_tip'		=> true
			)
		);
	}

}


    }

    function add_local_delivery_free_method( $methods ) {
        $methods[] = 'WC_Shipping_Local_Delivery_Free';
        return $methods;
    }

    add_filter( 'woocommerce_shipping_methods', 'add_local_delivery_free_method' );
  }

  add_action( 'woocommerce_shipping_init', 'local_delivery_free_method_init' );

}
