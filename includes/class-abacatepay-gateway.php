<?php
/**
 * AbacatePay Payment Gateway Class
 *
 * @package AbacatePay_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AbacatePay Payment Gateway
 */
class AbacatePay_WC_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'abacatepay';
		$this->icon               = apply_filters( 'woocommerce_abacatepay_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'AbacatePay', 'abacatepay-woocommerce' );
		$this->method_description = __( 'Gateway de pagamento AbacatePay com suporte a PIX e Cartão', 'abacatepay-woocommerce' );
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->api_key_dev        = $this->get_option( 'api_key_dev' );
		$this->api_key_prod       = $this->get_option( 'api_key_prod' );
		$this->webhook_url        = $this->get_option( 'webhook_url' );
		$this->dev_mode           = 'yes' === $this->get_option( 'dev_mode' );
		$this->payment_methods    = $this->get_option( 'payment_methods' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialize form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Ativar/Desativar', 'abacatepay-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar AbacatePay', 'abacatepay-woocommerce' ),
				'default' => 'no',
			),
			'title'              => array(
				'title'       => __( 'Título', 'abacatepay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Título do método de pagamento exibido no checkout', 'abacatepay-woocommerce' ),
				'default'     => __( 'AbacatePay', 'abacatepay-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Descrição', 'abacatepay-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Descrição do método de pagamento exibida no checkout', 'abacatepay-woocommerce' ),
				'default'     => __( 'Pague com PIX ou Cartão através da AbacatePay', 'abacatepay-woocommerce' ),
				'desc_tip'    => true,
			),
			'dev_mode'           => array(
				'title'   => __( 'Modo Desenvolvedor', 'abacatepay-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar Modo Desenvolvedor (Testes)', 'abacatepay-woocommerce' ),
				'default' => 'yes',
			),
			'api_key_dev'        => array(
				'title'       => __( 'Chave de API - Desenvolvimento', 'abacatepay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Sua chave de API da AbacatePay para o ambiente de desenvolvimento', 'abacatepay-woocommerce' ),
				'desc_tip'    => true,
			),
			'api_key_prod'       => array(
				'title'       => __( 'Chave de API - Produção', 'abacatepay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Sua chave de API da AbacatePay para o ambiente de produção', 'abacatepay-woocommerce' ),
				'desc_tip'    => true,
			),
			'webhook_url'        => array(
				'title'       => __( 'URL do Webhook', 'abacatepay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'URL para receber notificações de pagamento da AbacatePay', 'abacatepay-woocommerce' ),
				'default'     => home_url( '/wp-json/abacatepay/v1/webhook' ),
				'desc_tip'    => true,
			),
			'payment_methods'    => array(
				'title'       => __( 'Métodos de Pagamento', 'abacatepay-woocommerce' ),
				'type'        => 'multiselect',
				'description' => __( 'Selecione os métodos de pagamento que deseja aceitar', 'abacatepay-woocommerce' ),
				'options'     => array(
					'PIX'  => __( 'PIX', 'abacatepay-woocommerce' ),
					'CARD' => __( 'Cartão de Crédito', 'abacatepay-woocommerce' ),
				),
				'default'     => array( 'PIX', 'CARD' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order ID
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'failure',
				'messages' => __( 'Erro ao processar o pedido.', 'abacatepay-woocommerce' ),
			);
		}

		try {
			// Get API instance
			$api = new AbacatePay_WC_API( $this->get_current_api_key() );

			// Prepare billing data
			$billing_data = $this->prepare_billing_data( $order );

			// Create billing via API
			$response = $api->create_billing( $billing_data );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			if ( isset( $response['error'] ) && $response['error'] ) {
				throw new Exception( 'Erro ao criar cobrança: ' . wp_json_encode( $response['error'] ) );
			}

			// Store billing ID in order meta
			$billing_id = $response['data']['id'] ?? null;
			if ( ! $billing_id ) {
				throw new Exception( __( 'ID de cobrança não retornado pela API.', 'abacatepay-woocommerce' ) );
			}

			update_post_meta( $order_id, '_abacatepay_billing_id', $billing_id );
			update_post_meta( $order_id, '_abacatepay_billing_url', $response['data']['url'] ?? '' );

			// Add order note
			$order->add_order_note(
				sprintf(
					__( 'Cobrança AbacatePay criada: %s', 'abacatepay-woocommerce' ),
					$billing_id
				)
			);

			// Set order status to pending payment
			$order->set_status( 'pending' );
			$order->save();

			// Redirect to AbacatePay payment page
			return array(
				'result'   => 'success',
				'redirect' => $response['data']['url'] ?? '',
			);
		} catch ( Exception $e ) {
			wc_add_notice(
				sprintf(
					__( 'Erro ao processar pagamento: %s', 'abacatepay-woocommerce' ),
					$e->getMessage()
				),
				'error'
			);

			return array(
				'result'   => 'failure',
				'messages' => $e->getMessage(),
			);
		}
	}

	/**
	 * Prepare billing data for API
	 *
	 * @param WC_Order $order Order object
	 * @return array
	 */
	private function prepare_billing_data( $order ) {
		$products = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$products[] = array(
				'externalId' => (string) $product->get_id(),
				'name'       => $item->get_name(),
				'description' => $product->get_short_description(),
				'quantity'   => (int) $item->get_quantity(),
				'price'      => (int) ( $item->get_total() * 100 ), // Convert to cents
			);
		}

		$billing_data = array(
			'frequency'     => 'ONE_TIME',
			'methods'       => (array) $this->payment_methods,
			'products'      => $products,
			'returnUrl'     => $order->get_checkout_order_received_url(),
			'completionUrl' => $order->get_checkout_order_received_url(),
			'customer'      => array(
				'name'      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'cellphone' => $order->get_billing_phone(),
				'email'     => $order->get_billing_email(),
				'taxId'     => $this->get_customer_tax_id( $order ),
			),
		);

		return $billing_data;
	}

	/**
	 * Get customer tax ID (CPF/CNPJ)
	 *
	 * @param WC_Order $order Order object
	 * @return string
	 */
	private function get_customer_tax_id( $order ) {
		// Try to get from order meta (if stored by another plugin)
		$tax_id = get_post_meta( $order->get_id(), '_billing_cpf', true );
		if ( $tax_id ) {
			return $tax_id;
		}

		// If Dev Mode is active, use a placeholder CPF for testing, otherwise return empty string
		if ( $this->dev_mode ) {
			return '00000000000'; // Placeholder CPF for Dev Mode
		}

		// Return empty string if not found, which may cause API error if taxId is mandatory
		return '';
	}

	/**
	 * Get current API key based on mode
	 *
	 * @return string
	 */
	private function get_current_api_key() {
		return $this->dev_mode ? $this->api_key_dev : $this->api_key_prod;
	}

	/**
	 * Process refund
	 *
	 * @param int    $order_id Order ID
	 * @param float  $amount Amount to refund
	 * @param string $reason Reason for refund
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Pedido inválido.', 'abacatepay-woocommerce' ) );
		}

		$billing_id = get_post_meta( $order_id, '_abacatepay_billing_id', true );

		if ( ! $billing_id ) {
			return new WP_Error( 'no_billing_id', __( 'ID de cobrança não encontrado.', 'abacatepay-woocommerce' ) );
		}

		// TODO: Implement refund logic via AbacatePay API
		// For now, return success to allow manual refunds
		$order->add_order_note(
			sprintf(
				__( 'Reembolso manual de %s solicitado. ID de cobrança: %s', 'abacatepay-woocommerce' ),
				wc_price( $amount ),
				$billing_id
			)
		);

		return true;
	}

	/**
	 * Thank you page
	 *
	 * @param int $order_id Order ID
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$billing_id = get_post_meta( $order_id, '_abacatepay_billing_id', true );
		$billing_url = get_post_meta( $order_id, '_abacatepay_billing_url', true );

		if ( $billing_id ) {
			echo '<p>' . esc_html__( 'Seu pagamento está sendo processado. Você será redirecionado em breve.', 'abacatepay-woocommerce' ) . '</p>';
			if ( $billing_url ) {
				echo '<a href="' . esc_url( $billing_url ) . '" class="button">' . esc_html__( 'Voltar para o Pagamento', 'abacatepay-woocommerce' ) . '</a>';
			}
		}
	}

	/**
	 * Email instructions
	 *
	 * @param WC_Order $order Order object
	 * @param bool     $sent_to_admin Whether email is sent to admin
	 * @param bool     $plain_text Whether email is plain text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || 'abacatepay' !== $order->get_payment_method() ) {
			return;
		}

		$billing_id = get_post_meta( $order->get_id(), '_abacatepay_billing_id', true );

		if ( $billing_id ) {
			if ( $plain_text ) {
				echo esc_html__( 'Seu pagamento está pendente. Clique no link abaixo para completar o pagamento.', 'abacatepay-woocommerce' ) . "\n";
			} else {
				echo '<p>' . esc_html__( 'Seu pagamento está pendente. Clique no link abaixo para completar o pagamento.', 'abacatepay-woocommerce' ) . '</p>';
			}
		}
	}
}
