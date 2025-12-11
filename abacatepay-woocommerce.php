<?php
/**
 * Plugin Name: AbacatePay for WooCommerce
 * Plugin URI: https://www.abacatepay.com
 * Description: Gateway de pagamento AbacatePay para WooCommerce com suporte a PIX e Cartão
 * Version: 1.0.0
 * Author: Luan Pena
 * Author URI: https://instagram.com/luanpena
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: abacatepay-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @package AbacatePay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Define plugin constants
 */
define( 'ABACATEPAY_WC_VERSION', '1.0.0' );
define( 'ABACATEPAY_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABACATEPAY_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABACATEPAY_WC_PLUGIN_FILE', __FILE__ );

/**
 * Check if WooCommerce is active
 */
function abacatepay_wc_is_woocommerce_active() {
	return in_array(
		'woocommerce/woocommerce.php',
		apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
		true
	) || ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins' ) ) );
}

/**
 * Check if WooCommerce is installed and activated
 */
if ( ! abacatepay_wc_is_woocommerce_active() ) {
	add_action( 'admin_notices', 'abacatepay_wc_woocommerce_missing_notice' );
	return;
}

/**
 * Display notice if WooCommerce is not active
 */
function abacatepay_wc_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'AbacatePay for WooCommerce requer que o WooCommerce esteja instalado e ativado.', 'abacatepay-woocommerce' );
	echo '</p></div>';
}

/**
 * Load plugin text domain
 */
function abacatepay_wc_load_textdomain() {
	load_plugin_textdomain(
		'abacatepay-woocommerce',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'abacatepay_wc_load_textdomain' );

/**
 * Load plugin classes
 */
function abacatepay_wc_load_classes() {
	require_once ABACATEPAY_WC_PLUGIN_DIR . 'includes/class-abacatepay-gateway.php';
	require_once ABACATEPAY_WC_PLUGIN_DIR . 'includes/class-abacatepay-webhook.php';
	require_once ABACATEPAY_WC_PLUGIN_DIR . 'includes/class-abacatepay-api.php';
}
add_action( 'plugins_loaded', 'abacatepay_wc_load_classes', 11 );

/**
 * Register payment gateway
 */
function abacatepay_wc_register_gateway( $gateways ) {
	$gateways[] = 'AbacatePay_WC_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'abacatepay_wc_register_gateway' );

/**
 * Set the icon for the payment gateway
 */
function abacatepay_wc_set_icon( $icon ) {
	// Usando o link SVG fornecido pelo usuário
	return 'https://www.abacatepay.com/logo.svg';
}
add_filter( 'woocommerce_abacatepay_icon', 'abacatepay_wc_set_icon' );

/**
 * Activation hook
 */
function abacatepay_wc_activate() {
	// Create webhook endpoint
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'abacatepay_wc_activate' );

/**
 * Deactivation hook
 */
function abacatepay_wc_deactivate() {
	// Clean up on deactivation
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'abacatepay_wc_deactivate' );

/**
 * Enqueue admin scripts and styles
 */
function abacatepay_wc_enqueue_admin_assets( $hook ) {
	if ( 'woocommerce_page_wc-settings' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'abacatepay-wc-admin',
		ABACATEPAY_WC_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		ABACATEPAY_WC_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'abacatepay_wc_enqueue_admin_assets' );

/**
 * Enqueue frontend scripts and styles
 */
function abacatepay_wc_enqueue_frontend_assets() {
	if ( ! is_checkout() ) {
		return;
	}

	wp_enqueue_script(
		'abacatepay-wc-checkout',
		ABACATEPAY_WC_PLUGIN_URL . 'assets/js/checkout.js',
		array( 'jquery' ),
		ABACATEPAY_WC_VERSION,
		true
	);

	wp_localize_script(
		'abacatepay-wc-checkout',
		'abacatepayWC',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'abacatepay_wc_enqueue_frontend_assets' );
