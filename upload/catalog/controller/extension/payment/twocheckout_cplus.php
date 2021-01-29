<?php

class ControllerExtensionPaymentTwoCheckoutCplus extends Controller {

	/**
	 * ControllerExtensionPaymentTwoCheckoutCplus constructor.
	 *
	 * @param $registry
	 */
	public function __construct( $registry ) {
		parent::__construct( $registry );
		$this->load->language( 'extension/payment/twocheckout_cplus' );
		$this->load->model( 'extension/payment/twocheckout_cplus' );
		$this->load->model( 'checkout/order' );
	}

	/**
	 * @return mixed
	 */
	public function index() {
		$data['button_confirm'] = $this->language->get( 'button_confirm' );
		$data['action']         = $this->url->link( 'extension/payment/twocheckout_cplus/checkout' );
		if ( ! empty( $this->session->data['order_id'] ) )
		{
			$data['order_id'] = $this->session->data['order_id'];
		}
		return $this->load->view( 'extension/payment/twocheckout_cplus', $data );
	}

	/**
	 * @throws Exception
	 */
	public function checkout() {
		$post_data = $this->request->post;
		if ( ! empty( $post_data['order_id'] ) )
		{
			$order_info      = $this->model_checkout_order->getOrder( $post_data['order_id'] );
			$test = $this->config->get( 'payment_twocheckout_cplus_test' );
			$seller_id = $this->config->get( 'payment_twocheckout_cplus_account' );
			$price = $this->currency->format( $order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false );
			$language = strtolower( substr( $this->session->data['language'], 0, 2 ) );

			$buy_link_params = [];
			$billing_details = $this->model_extension_payment_twocheckout_cplus->getBillingDetails($order_info);
			$shipping_details = $this->model_extension_payment_twocheckout_cplus->getShippingDetails($order_info,$this->cart->hasShipping());
			$other_details = $this->model_extension_payment_twocheckout_cplus->getOtherDetails($order_info, $test, $seller_id, $price, $language);

			$buy_link_params = array_merge($buy_link_params, $billing_details);
			$buy_link_params = array_merge($buy_link_params, $shipping_details);
			$buy_link_params = array_merge($buy_link_params, $other_details);

			$buy_link_params['signature'] = $this->model_extension_payment_twocheckout_cplus->getSignature(
				$this->config->get( 'payment_twocheckout_cplus_account' ),
				html_entity_decode( $this->config->get( 'payment_twocheckout_cplus_secret_word' ) ),
				$buy_link_params );

			$tcoQueryStrings = http_build_query( $buy_link_params );
			$this->response->redirect( 'https://secure.2checkout.com/checkout/buy/?' . $tcoQueryStrings );
		}
	}

	/**
	 *
	 */
	public function success() {
		$params = $_GET;
		if ( ! isset( $_GET['order-ext-ref'] ) || empty( $_GET['order-ext-ref'] ) )
		{
			$this->response->redirect( $this->url->link( 'checkout/success', '', true ) );
		}

		if ( ! isset( $_GET['refno'] ) || empty( $_GET['refno'] ) )
		{
			$this->response->redirect( $this->url->link( 'checkout/success', '', true ) );
		}

		$order_info = $this->model_checkout_order->getOrder( $params['order-ext-ref'] );
		if ( ! isset( $order_info ) || empty( $order_info ) )
		{
			$this->response->redirect( $this->url->link( 'checkout/success', '', true ) );
		}

		$seller_id = $this->config->get( 'payment_twocheckout_cplus_account' );
		$secret_key = $this->config->get('payment_twocheckout_cplus_secret_key' );

		$api_response = $this->model_extension_payment_twocheckout_cplus->call( 'orders/' . $params['refno'] . '/', [], 'GET', $seller_id, $secret_key);
		if(!empty($api_response['Status']) && isset($api_response['Status'])){
			if ( in_array( $api_response['Status'], [ 'AUTHRECEIVED', 'COMPLETE' ] ) )
			{
				$order_status_id = $this->config->get( 'payment_twocheckout_cplus_processing_status_id' );
				$this->model_checkout_order->addOrderHistory( $params['order-ext-ref'], $order_status_id );
			}
		}

		$this->response->redirect( $this->url->link( 'checkout/success', '', true ) );
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function ipn() {
		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' )
		{
			return false;
		}
		if ( ! isset( $_REQUEST['REFNOEXT'] ) )
		{
			return false;
		}

		$post_data = $this->request->post;
		unset( $post_data['route'] );
		$order_info = $this->model_checkout_order->getOrder( $post_data['REFNOEXT'] );

		if ( ! isset( $order_info ) || empty( $order_info ) )
		{
			return false;
		}

		if ( ! $this->model_extension_payment_twocheckout_cplus->indexAction( $post_data ) )
		{
			return false;
		}

		return true;
	}
}

