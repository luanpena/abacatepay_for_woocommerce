<?php
/**
 * AbacatePay API Class
 *
 * @package AbacatePay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AbacatePay API
 */
class AbacatePay_WC_API {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base_url = 'https://api.abacatepay.com/v1';

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor
	 *
	 * @param string $api_key API key
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Create billing
	 *
	 * @param array $data Billing data
	 * @return array|WP_Error
	 */
	public function create_billing( $data ) {
		return $this->request( 'POST', '/billing/create', $data );
	}

	/**
	 * Get billing
	 *
	 * @param string $billing_id Billing ID
	 * @return array|WP_Error
	 */
	public function get_billing( $billing_id ) {
		return $this->request( 'GET', '/billing/get?id=' . urlencode( $billing_id ) );
	}

	/**
	 * List billings
	 *
	 * @return array|WP_Error
	 */
	public function list_billings() {
		return $this->request( 'GET', '/billing/list' );
	}

	/**
	 * Create customer
	 *
	 * @param array $data Customer data
	 * @return array|WP_Error
	 */
	public function create_customer( $data ) {
		return $this->request( 'POST', '/customer/create', $data );
	}

	/**
	 * Get customer
	 *
	 * @param string $customer_id Customer ID
	 * @return array|WP_Error
	 */
	public function get_customer( $customer_id ) {
		return $this->request( 'GET', '/customer/list' );
	}

	/**
	 * Create PIX QR Code
	 *
	 * @param array $data PIX data
	 * @return array|WP_Error
	 */
	public function create_pix_qrcode( $data ) {
		return $this->request( 'POST', '/pixQrCode/create', $data );
	}

	/**
	 * Check PIX QR Code status
	 *
	 * @param string $qrcode_id QR Code ID
	 * @return array|WP_Error
	 */
	public function check_pix_qrcode( $qrcode_id ) {
		return $this->request( 'GET', '/pixQrCode/check?id=' . urlencode( $qrcode_id ) );
	}

	/**
	 * Simulate PIX payment (Dev Mode only)
	 *
	 * @param string $qrcode_id QR Code ID
	 * @param array  $data Additional data
	 * @return array|WP_Error
	 */
	public function simulate_pix_payment( $qrcode_id, $data = array() ) {
		return $this->request( 'POST', '/pixQrCode/simulate-payment?id=' . urlencode( $qrcode_id ), $data );
	}

	/**
	 * Create coupon
	 *
	 * @param array $data Coupon data
	 * @return array|WP_Error
	 */
	public function create_coupon( $data ) {
		return $this->request( 'POST', '/coupon/create', array( 'data' => $data ) );
	}

	/**
	 * List coupons
	 *
	 * @return array|WP_Error
	 */
	public function list_coupons() {
		return $this->request( 'GET', '/coupon/list' );
	}

	/**
	 * Create withdrawal
	 *
	 * @param array $data Withdrawal data
	 * @return array|WP_Error
	 */
	public function create_withdrawal( $data ) {
		return $this->request( 'POST', '/withdraw/create', $data );
	}

	/**
	 * Get withdrawal
	 *
	 * @param string $withdrawal_id Withdrawal ID
	 * @return array|WP_Error
	 */
	public function get_withdrawal( $withdrawal_id ) {
		return $this->request( 'GET', '/withdraw/get?id=' . urlencode( $withdrawal_id ) );
	}

	/**
	 * List withdrawals
	 *
	 * @return array|WP_Error
	 */
	public function list_withdrawals() {
		return $this->request( 'GET', '/withdraw/list' );
	}

	/**
	 * Get store details
	 *
	 * @return array|WP_Error
	 */
	public function get_store() {
		return $this->request( 'GET', '/store/get' );
	}

	/**
	 * Make API request
	 *
	 * @param string $method HTTP method
	 * @param string $endpoint API endpoint
	 * @param array  $data Request data
	 * @return array|WP_Error
	 */
	private function request( $method, $endpoint, $data = array() ) {
		$url = $this->api_base_url . $endpoint;
		if ( ! $this->api_key ) {
			return new WP_Error(
				'missing_api_key',
				__( 'Chave de API da AbacatePay não configurada.', 'abacatepay-woocommerce' )
			);
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout' => 30,
		);

		if ( 'POST' === $method && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		// Log the raw response for debugging
		$this->log_api_response( $url, $method, $code, $body );

		$result = json_decode( $body, true );

		// Check for API errors first, even if JSON is invalid
		if ( $code >= 400 ) {
			$error_message = $result['error']['message'] ?? ( $body ?: __( 'Erro desconhecido', 'abacatepay-woocommerce' ) );
			return new WP_Error(
				'api_error',
				sprintf(
					__( 'Erro da API AbacatePay: %s (Código: %d)', 'abacatepay-woocommerce' ),
					$error_message,
					$code
				)
			);
		}

		if ( null === $result ) {
			return new WP_Error(
				'invalid_json',
				sprintf(
					__( 'Resposta inválida da API (Código: %d)', 'abacatepay-woocommerce' ),
					$code
				)
			);
		}

		return $result;
	}

	/**
	 * Log API response
	 *
	 * @param string $url Request URL
	 * @param string $method Request method
	 * @param int    $code Response code
	 * @param string $body Response body
	 */
	private function log_api_response( $url, $method, $code, $body ) {
		if ( ! defined( 'WC_LOG_DIR' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->info(
			sprintf(
				'API Request: %s %s | Response Code: %d | Body: %s',
				$method,
				$url,
				$code,
				$body
			),
			array( 'source' => 'abacatepay-api' )
		);
	}
}
