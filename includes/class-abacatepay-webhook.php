<?php
/**
 * AbacatePay Webhook Handler Class
 *
 * @package AbacatePay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AbacatePay Webhook Handler
 */
class AbacatePay_WC_Webhook {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
	}

	/**
	 * Register webhook REST API route
	 */
	public function register_webhook_route() {
		register_rest_route(
			'abacatepay/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhook signature will be validated in callback
			)
		);
	}

	/**
	 * Handle webhook
	 *
	 * @param WP_REST_Request $request REST request
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		try {
			// Get webhook payload
			$payload = $request->get_json_params();

			if ( ! $payload ) {
				return new WP_REST_Response(
					array( 'error' => 'No payload provided' ),
					400
				);
			}

			// Get gateway settings
			$gateway = new AbacatePay_WC_Gateway();
			$api_key = $gateway->dev_mode ? $gateway->api_key_dev : $gateway->api_key_prod;

			// Validate webhook signature
			if ( ! $this->validate_webhook_signature( $request, $api_key ) ) {
				return new WP_REST_Response(
					array( 'error' => 'Invalid signature' ),
					401
				);
			}

			// Log webhook
			$this->log_webhook( $payload );

			// Process webhook based on event type
			$event = $payload['event'] ?? null;

			switch ( $event ) {
				case 'billing.paid':
					$this->handle_billing_paid( $payload );
					break;
				case 'pix.paid':
					$this->handle_pix_paid( $payload );
					break;
				case 'pix.expired':
					$this->handle_pix_expired( $payload );
					break;
				case 'withdraw.paid':
					$this->handle_withdraw_paid( $payload );
					break;
				default:
					$this->log_webhook( array( 'message' => 'Unknown event: ' . $event ) );
			}

			return new WP_REST_Response( array( 'success' => true ), 200 );
		} catch ( Exception $e ) {
			$this->log_webhook( array( 'error' => $e->getMessage() ) );
			return new WP_REST_Response(
				array( 'error' => $e->getMessage() ),
				500
			);
		}
	}

	/**
	 * Validate webhook signature
	 *
	 * @param WP_REST_Request $request REST request
	 * @param string          $api_key API key
	 * @return bool
	 */
	private function validate_webhook_signature( $request, $api_key ) {
		// Get signature from header
		$signature = $request->get_header( 'x-abacatepay-signature' );

		// Fallback for Nginx/Proxy configurations that strip custom headers
		if ( ! $signature ) {
			$auth_header = $request->get_header( 'authorization' );
			if ( $auth_header && strpos( $auth_header, 'Bearer ' ) === 0 ) {
				// AbacatePay might send the signature as a Bearer token
				$signature = substr( $auth_header, 7 );
			}
		}

		if ( ! $signature ) {
			return false;
		}

		// Get raw body
		$body = $request->get_body();

		// Calculate expected signature using HMAC-SHA256
		$expected_signature = hash_hmac( 'sha256', $body, $api_key );

		// Compare signatures
		return hash_equals( $signature, $expected_signature );
	}

	/**
	 * Handle billing.paid event
	 *
	 * @param array $payload Webhook payload
	 */
	private function handle_billing_paid( $payload ) {
		$billing_id = $payload['data']['id'] ?? null;

		if ( ! $billing_id ) {
			return;
		}

		// Find order by billing ID
		$order_id = $this->find_order_by_billing_id( $billing_id );

		if ( ! $order_id ) {
			$this->log_webhook( array( 'message' => 'Order not found for billing ID: ' . $billing_id ) );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Update order status to processing
		$order->set_status( 'processing' );
		$order->add_order_note(
			sprintf(
				__( 'Pagamento recebido via AbacatePay. ID da cobrança: %s', 'abacatepay-woocommerce' ),
				$billing_id
			)
		);
		$order->save();

		// Reduce stock
		wc_reduce_stock_levels( $order_id );
	}

	/**
	 * Handle pix.paid event
	 *
	 * @param array $payload Webhook payload
	 */
	private function handle_pix_paid( $payload ) {
		$pix_id = $payload['data']['id'] ?? null;

		if ( ! $pix_id ) {
			return;
		}

		// Find order by PIX ID
		$order_id = $this->find_order_by_pix_id( $pix_id );

		if ( ! $order_id ) {
			$this->log_webhook( array( 'message' => 'Order not found for PIX ID: ' . $pix_id ) );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Update order status to processing
		$order->set_status( 'processing' );
		$order->add_order_note(
			sprintf(
				__( 'Pagamento PIX recebido. ID: %s', 'abacatepay-woocommerce' ),
				$pix_id
			)
		);
		$order->save();

		// Reduce stock
		wc_reduce_stock_levels( $order_id );
	}

	/**
	 * Handle pix.expired event
	 *
	 * @param array $payload Webhook payload
	 */
	private function handle_pix_expired( $payload ) {
		$pix_id = $payload['data']['id'] ?? null;

		if ( ! $pix_id ) {
			return;
		}

		// Find order by PIX ID
		$order_id = $this->find_order_by_pix_id( $pix_id );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Update order status to cancelled
		$order->set_status( 'cancelled' );
		$order->add_order_note(
			sprintf(
				__( 'Pagamento PIX expirado. ID: %s', 'abacatepay-woocommerce' ),
				$pix_id
			)
		);
		$order->save();
	}

	/**
	 * Handle withdraw.paid event
	 *
	 * @param array $payload Webhook payload
	 */
	private function handle_withdraw_paid( $payload ) {
		$withdraw_id = $payload['data']['id'] ?? null;

		if ( ! $withdraw_id ) {
			return;
		}

		// Log withdrawal confirmation
		$this->log_webhook(
			array(
				'event'         => 'withdraw.paid',
				'withdraw_id'   => $withdraw_id,
				'amount'        => $payload['data']['amount'] ?? 0,
				'status'        => $payload['data']['status'] ?? 'unknown',
			)
		);
	}

	/**
	 * Find order by billing ID
	 *
	 * @param string $billing_id Billing ID
	 * @return int|null
	 */
	private function find_order_by_billing_id( $billing_id ) {
		$args = array(
			'post_type'  => 'shop_order',
			'meta_key'   => '_abacatepay_billing_id',
			'meta_value' => $billing_id,
			'numberposts' => 1,
		);

		$orders = get_posts( $args );

		return ! empty( $orders ) ? $orders[0]->ID : null;
	}

	/**
	 * Find order by PIX ID
	 *
	 * @param string $pix_id PIX ID
	 * @return int|null
	 */
	private function find_order_by_pix_id( $pix_id ) {
		$args = array(
			'post_type'  => 'shop_order',
			'meta_key'   => '_abacatepay_pix_id',
			'meta_value' => $pix_id,
			'numberposts' => 1,
		);

		$orders = get_posts( $args );

		return ! empty( $orders ) ? $orders[0]->ID : null;
	}

	/**
	 * Log webhook
	 *
	 * @param array $data Data to log
	 */
	private function log_webhook( $data ) {
		if ( ! defined( 'WC_LOG_DIR' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->info(
			wp_json_encode( $data ),
			array( 'source' => 'abacatepay-webhook' )
		);
	}
}

// Initialize webhook handler
new AbacatePay_WC_Webhook();
