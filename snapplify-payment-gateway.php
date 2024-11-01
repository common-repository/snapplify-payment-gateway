<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://snapplify.com
 * @since             1.0.0
 * @package           Snapplify_Payment_Gateway
 *
 * @wordpress-plugin
 * Plugin Name:       Snapplify Payment Gateway
 * Plugin URI:        https://www.snapplify.com/woo-plugin
 * Description:       Accept payments in WooCommerce using Snapplify Pay.
 * Version:           1.0.2
 * Author:            Snapplify
 * Author URI:        https://snapplify.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       snapplify-payment-gateway

 * Snapplify Payment Gateway is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * Snapplify Payment Gateway} is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Snapplify Payment Gateway. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
*/

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function snap_woocommerce_snapplify_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'snap_woocommerce_payment_gateway_check_notice' );
		return;
	}

	define( 'SNAP_DIR', plugin_dir_url( __FILE__ ) );
	define( 'SNAP_VERSION', '1.0.2');

	require_once plugin_basename( 'includes/class-helpers.php' );
	require_once plugin_basename( 'includes/class-wc-gateway-snapplify.php' );
	add_filter( 'woocommerce_payment_gateways', 'snap_register_gateway' );
}
add_action( 'plugins_loaded', 'snap_woocommerce_snapplify_init', 0 );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'snap_woocommerce_snapplify_plugin_links' );

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function snap_register_gateway( $methods ) {
	$methods[] = 'SNAP_WC_Gateway_Snapplify';
	return $methods;
}

function snap_woocommerce_snapplify_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'wc_gateway_snapplify',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'snapplify-pay' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

function snap_woocommerce_payment_gateway_check_notice()
{
	$admin_notice = new WC_Admin_Notices();
	$admin_notice->add_custom_notice( 'snapplify_plugin_error', '<div class="alert alert-danger notice is-dismissible"><p>Sorry, but this plugin requires WooCommerce Payment Gateway in order to work.<br>So please ensure that WooCommerce Payment Gateway is installed and activated.</p></div>' );
	$admin_notice->output_custom_notices();
}
