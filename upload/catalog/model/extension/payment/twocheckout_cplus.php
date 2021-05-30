<?php

class ModelExtensionPaymentTwoCheckoutCplus extends Model
{

	/**
	 * Ipn Constants
	 *
	 * Not all are used, however they should be left here
	 * for future reference
	 */

	const API_URL = 'https://api.2checkout.com/rest/';
	const API_VERSION = '6.0';

	const ORDER_CREATED = 'ORDER_CREATED';
	const FRAUD_STATUS_CHANGED = 'FRAUD_STATUS_CHANGED';
	const INVOICE_STATUS_CHANGED = 'INVOICE_STATUS_CHANGED';
	const REFUND_ISSUED = 'REFUND_ISSUED';
	//Order Status Values:
	const ORDER_STATUS_PENDING = 'PENDING';
	const ORDER_STATUS_PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
	const ORDER_STATUS_SUSPECT = 'SUSPECT';
	const ORDER_STATUS_AUTHRECEIVED = 'AUTHRECEIVED';
	const ORDER_STATUS_INVALID = 'INVALID';
	const ORDER_STATUS_COMPLETE = 'COMPLETE';
	const ORDER_STATUS_REFUND = 'REFUND';
	const ORDER_STATUS_REVERSED = 'REVERSED';
	const ORDER_STATUS_PURCHASE_PENDING = 'PURCHASE_PENDING';
	const ORDER_STATUS_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
	const ORDER_STATUS_CANCELED = 'CANCELED';
	const ORDER_STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
	const FRAUD_STATUS_APPROVED = 'APPROVED';
	const FRAUD_STATUS_DENIED = 'DENIED';
	const FRAUD_STATUS_REVIEW = 'UNDER REVIEW';
	const FRAUD_STATUS_PENDING = 'PENDING';

	/**
	 * @param $address
	 * @param $total
	 *
	 * @return array
	 */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/twocheckout_cplus');
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_twocheckout_cplus_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
        if ($this->config->get('payment_twocheckout_cplus_total') > 0 && $this->config->get('payment_twocheckout_cplus_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('payment_twocheckout_cplus_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code'       => 'twocheckout_cplus',
                'title'      => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_twocheckout_cplus_sort_order')
            ];
        }

        return $method_data;
    }

	/**
	 *
	 * @return mixed
	 * @throws Exception
	 */
	private function getHeaders($sellerId, $secretKey)
	{

		if (!$sellerId || !$secretKey) {
			throw new Exception('Merchandiser needs a valid 2Checkout SellerId and SecretKey to authenticate!');
		}
		$gmtDate = gmdate('Y-m-d H:i:s');
		$string = strlen($sellerId) . $sellerId . strlen($gmtDate) . $gmtDate;
		$hash = hash_hmac('md5', $string, $secretKey);

		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Accept: application/json';
		$headers[] = 'X-Avangate-Authentication: code="' . $sellerId . '" date="' . $gmtDate . '" hash="' . $hash . '"';

		return $headers;
	}

	/**
	 * @param string $endpoint
	 * @param array  $params
	 * @param string $method
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function call( $endpoint, array $params, $method = 'POST', $sellerId, $secretKey) {
		// if endpoint does not starts or end with a '/' we add it, as the API needs it
		if ( $endpoint[0] !== '/' ) {
			$endpoint = '/' . $endpoint;
		}
		if ( $endpoint[ - 1 ] !== '/' ) {
			$endpoint = $endpoint . '/';
		}

		try {
			$url = self::API_URL . self::API_VERSION . $endpoint;
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->getHeaders($sellerId, $secretKey) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			if ( $method === 'POST' ) {
				curl_setopt( $ch, CURLOPT_POST, true );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params, JSON_UNESCAPED_UNICODE ) );
			}
		
            if ( $this->config->get( 'payment_twocheckout_cplus_test' ) ) {
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false ); //by default value is 2
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); //by default value is 1
            }
			$response = curl_exec( $ch );

			if ( $response === false ) {
				exit( curl_error( $ch ) );
			}
			curl_close( $ch );

			return json_decode( $response, true );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}

	/**
	 * @param $merchant_id
	 * @param $buy_link_secret_word
	 * @param $payload
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function getSignature( $merchant_id, $buy_link_secret_word, $payload ) {
		$jwtToken = $this->generateJWTToken(
			$merchant_id,
			time(),
			time() + 3600,
			$buy_link_secret_word
		);

		$curl = curl_init();

		curl_setopt_array( $curl, [
			CURLOPT_URL            => "https://secure.2checkout.com/checkout/api/encrypt/generate/signature",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( $payload ),
			CURLOPT_HTTPHEADER     => [
				'content-type: application/json',
				'cache-control: no-cache',
				'merchant-token: ' . $jwtToken,

			],
		] );

        if ( $this->config->get( 'payment_twocheckout_cplus_test' ) ) {
            curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, false ); //by default value is 2
            curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false ); //by default value is 1
        }
		$response = curl_exec( $curl );
		$err      = curl_error( $curl );
		curl_close( $curl );

		if ( $err )
		{
			throw new Exception( sprintf( 'Unable to get proper response from signature generation API. In file %s at line %s', __FILE__, __LINE__ ) );
		}

		$response = json_decode( $response, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $response['signature'] ) )
		{
			throw new Exception( sprintf( 'Unable to get proper response from signature generation API. Signature not set. In file %s at line %s', __FILE__, __LINE__ ) );
		}

		return $response['signature'];

	}

	/**
	 * @param $sub
	 * @param $iat
	 * @param $exp
	 * @param $buy_link_secret_word
	 *
	 * @return string
	 */
	private function generateJWTToken( $sub, $iat, $exp, $buy_link_secret_word ) {
		$header    = $this->encode( json_encode( [ 'alg' => 'HS512', 'typ' => 'JWT' ] ) );
		$payload   = $this->encode( json_encode( [ 'sub' => $sub, 'iat' => $iat, 'exp' => $exp ] ) );
		$signature = $this->encode(
			hash_hmac( 'sha512', "$header.$payload", $buy_link_secret_word, true )
		);

		return implode( '.', [
			$header,
			$payload,
			$signature
		] );
	}

	/**
	 * @param $data
	 *
	 * @return string|string[]
	 */
	private function encode( $data ) {
		return str_replace( '=', '', strtr( base64_encode( $data ), '+/', '-_' ) );
	}

	/**
	 * @param $array
	 *
	 * @return string
	 */
	private function arrayExpand($array)
	{
		$retval = '';
		foreach ($array as $key => $value) {
			$size = strlen(stripslashes($value));
			$retval .= $size . stripslashes($value);
		}
		return $retval;
	}


	/**
	 * @param $key
	 * @param $data
	 *
	 * @return string
	 */
	private function hmac($key, $data)
	{
		$b = 64; // byte length for md5
		if (strlen($key) > $b) {
			$key = pack("H*", md5($key));
		}

		$key = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad;
		$k_opad = $key ^ $opad;

		return md5($k_opad . pack("H*", md5($k_ipad . $data)));
	}

	/**
	 * @param $params
	 *
	 * @throws Exception
	 */
	public function indexAction($params) {
		if ( ! isset( $params['REFNOEXT'] ) && ( ! isset( $params['REFNO'] ) && empty( $params['REFNO'] ) ) )
		{
			throw new Exception( sprintf( 'Cannot identify order: "%s".',
				$params['REFNOEXT'] ) );
		}

		$order = $this->model_checkout_order->getOrder($params['REFNOEXT']);
//        ignore all other payment methods
		if ($order && $order['payment_code'] === 'twocheckout_cplus')
		{


			$secret_key = $this->config->get( 'payment_twocheckout_cplus_secret_key' );
			if ( ! $this->isIpnResponseValid( $params, $secret_key ) )
			{
				throw new Exception( sprintf( 'MD5 hash mismatch for 2Checkout IPN with date: "%s".',
					$params['IPN_DATE'] ) );
			}

			// do not wrap this in a try catch
			// it's intentionally left out so that the exceptions will bubble up
			// and kill the script if one should arise
			$this->_processFraud( $params );

			if ( $this->_isNotFraud( $params ) )
			{
				$this->_processOrderStatus( $params );
			}

			echo $this->_calculateIpnResponse(
				$params,
				html_entity_decode( $this->config->get( 'payment_twocheckout_cplus_secret_key' ) )
			);
		}
		die;
	}

	/**
	 * @param $params
	 *
	 * @return bool
	 */
	private function _isNotFraud( $params ) {
		return ( isset( $params['FRAUD_STATUS'] ) && trim( $params['FRAUD_STATUS'] ) === self::FRAUD_STATUS_APPROVED );
	}

	/**
	 * @param $params
	 *
	 * @return bool
	 */
	private function isIpnResponseValid($params, $secret_key) {
		$result       = '';
		$receivedHash = $params['HASH'];
		foreach ( $params as $key => $val )
		{

			if ( $key != "HASH" )
			{
				if ( is_array( $val ) )
				{
					$result .= $this->arrayExpand( $val );
				}
				else
				{
					$size   = strlen( stripslashes( $val ) );
					$result .= $size . stripslashes( $val );
				}
			}
		}
		if ( isset( $params['REFNO'] ) && ! empty( $params['REFNO'] ) )
		{
			$calcHash = $this->hmac($secret_key, $result );
			if ( $receivedHash === $calcHash )
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $params
	 * @param $secret_key
	 *
	 * @return string
	 */
	private function _calculateIpnResponse($params, $secret_key) {
		$resultResponse    = '';
		$ipnParamsResponse = [];
		// we're assuming that these always exist, if they don't then the problem is on avangate side
		$ipnParamsResponse['IPN_PID'][0]   = $params['IPN_PID'][0];
		$ipnParamsResponse['IPN_PNAME'][0] = $params['IPN_PNAME'][0];
		$ipnParamsResponse['IPN_DATE']     = $params['IPN_DATE'];
		$ipnParamsResponse['DATE']         = date( 'YmdHis' );

		foreach ( $ipnParamsResponse as $key => $val )
		{
			$resultResponse .= $this->arrayExpand( (array) $val );
		}

		return sprintf(
			'<EPAYMENT>%s|%s</EPAYMENT>',
			$ipnParamsResponse['DATE'],
			$this->hmac($secret_key, $resultResponse )
		);
	}

	/**
	 * @param $params
	 *
	 * @throws Exception
	 */
	private function _processOrderStatus( $params ) {
		$orderStatus = $params['ORDERSTATUS'];
		$text = $this->language->get('updated_order_status');
		$this->addUpdateTransaction($params); // for further refunds

		if ( ! empty( $orderStatus ) )
		{
			switch ( trim( $orderStatus ) )
			{
				case self::ORDER_STATUS_PENDING:
				case self::ORDER_STATUS_PURCHASE_PENDING:
				case self::ORDER_STATUS_AUTHRECEIVED:
				case self::ORDER_STATUS_PAYMENT_RECEIVED:
				case self::ORDER_STATUS_PENDING_APPROVAL:
				case self::ORDER_STATUS_PAYMENT_AUTHORIZED:
					$this->log->write('Order status changed to processing');
					$order_status_id = $this->config->get('payment_twocheckout_cplus_processing_status_id');
					$this->model_checkout_order->addOrderHistory($params['REFNOEXT'],  $order_status_id, $text . 'processing');
					break;

				case self::ORDER_STATUS_COMPLETE:
					$this->log->write('Order status changed to complete');
					$order_status_id = $this->config->get('payment_twocheckout_cplus_order_status_id');
					if (!$this->isChargeBack($params)) {
						$this->model_checkout_order->addOrderHistory($params['REFNOEXT'],  $order_status_id, $text . 'complete');
					}
					break;

				case self::ORDER_STATUS_INVALID:
					$this->log->write('Order status changed to cancelled');
					$order_status_id = $this->config->get('payment_twocheckout_cplus_canceled_status_id');
					$this->model_checkout_order->addOrderHistory($params['REFNOEXT'],  $order_status_id, $text. ' cancelled');
					break;

				default:
					throw new Exception( 'Cannot handle Ipn message type for message' );
			}
		}
	}

	/**
	 * Update status & place a note on the Order
	 * @param $params
	 * @return bool
	 */
	private function isChargeBack($params)
	{
        $chargeBackResolution = isset($params['CHARGEBACK_RESOLUTION']) ? trim($params['CHARGEBACK_RESOLUTION']) : '';
        $chargeBackReasonCode = isset($params['CHARGEBACK_REASON_CODE']) ? trim($params['CHARGEBACK_REASON_CODE']) : '';

        // we need to mock up a message with some params in order to add this note
        if (!empty($chargeBackResolution) && $chargeBackResolution !== 'NONE' && !empty($chargeBackReasonCode)) {

            $this->load->model('checkout/order');
            // list of chargeback reasons on 2CO platform
            $reasons = [
                'UNKNOWN'                  => 'Unknown', //default
                'MERCHANDISE_NOT_RECEIVED' => 'Order not fulfilled/not delivered',
                'DUPLICATE_TRANSACTION'    => 'Duplicate order',
                'FRAUD / NOT_RECOGNIZED'   => 'Fraud/Order not recognized',
                'FRAUD'                    => 'Fraud',
                'CREDIT_NOT_PROCESSED'     => 'Agreed refund not processed',
                'NOT_RECOGNIZED'           => 'New/renewal order not recognized',
                'AUTHORIZATION_PROBLEM'    => 'Authorization problem',
                'INFO_REQUEST'             => 'Information request',
                'CANCELED_RECURRING'       => 'Recurring payment was canceled',
                'NOT_AS_DESCRIBED'         => 'Product(s) not as described/not functional'
            ];

            $why = isset($reasons[$chargeBackReasonCode]) ?
                $reasons[$chargeBackReasonCode] :
                $reasons['UNKNOWN'];
            $message = '2Checkout chargeback status is now ' . $chargeBackResolution . '. Reason: ' . $why . '!';

			$this->log->write('Order status changed to chargeback');
			$order_status_id = $this->config->get('payment_twocheckout_cplus_chargeback_status_id');
			$this->model_checkout_order->addOrderHistory($params['REFNOEXT'],  $order_status_id, $message);

			return true;
		}

		return false;
	}



	/**
	 * @param $params
	 */
	private function _processFraud( $params ) {
		$text = $this->language->get('updated_order_status');
		if ( isset( $params['FRAUD_STATUS'] ) )
		{
			if( trim( $params['FRAUD_STATUS'] )  == self::FRAUD_STATUS_DENIED){
				$this->log->write('Order status changed to failed');
				$order_status_id = $this->config->get('payment_twocheckout_cplus_failed_status_id');
				$this->model_checkout_order->addOrderHistory($params['REFNOEXT'],  $order_status_id, $text. ' failed/denied');
			}
		}
	}

	/**
	 * @param $order_info
	 *
	 * @return array
	 */
	public function getBillingDetails($order_info){
		$buy_link_params = array();
		$buy_link_params['name']         = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
		$buy_link_params['phone']        = $order_info['telephone'];
		$buy_link_params['country']      = $order_info['payment_iso_code_2'];
		$buy_link_params['state']        = $order_info['payment_zone'];
		$buy_link_params['email']        = $order_info['email'];
		$buy_link_params['address']      = $order_info['payment_address_1'];
		$buy_link_params['city']         = $order_info['payment_city'];
		$buy_link_params['company-name'] = $order_info['payment_company'];
		return $buy_link_params;
	}

	/**
	 * @param $order_info
	 * @param $cart
	 *
	 * @return array
	 */
	public function getShippingDetails($order_info, $cart){
		$buy_link_params = array();

		if ( $cart )
		{
			$buy_link_params['ship-name']     = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
			$buy_link_params['ship-country']  = $order_info['shipping_iso_code_2'];
			$buy_link_params['ship-state']    = $order_info['shipping_zone'];
			$buy_link_params['ship-city']     = $order_info['shipping_city'];
			$buy_link_params['ship-email']    = $order_info['email'];
			$buy_link_params['ship-address']  = $order_info['shipping_address_1'];
			$buy_link_params['ship-address2'] = $order_info['shipping_address_2'];
			$buy_link_params['zip']           = $order_info['shipping_postcode'];
		}
		else
		{
			$buy_link_params['ship-name']     = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
			$buy_link_params['ship-country']  = $order_info['payment_iso_code_2'];
			$buy_link_params['ship-state']    = $order_info['payment_zone'];
			$buy_link_params['ship-city']     = $order_info['payment_city'];
			$buy_link_params['ship-email']    = $order_info['email'];
			$buy_link_params['ship-address']  = $order_info['payment_address_1'];
			$buy_link_params['ship-address2'] = $order_info['payment_address_2'];
			$buy_link_params['zip']           = $order_info['payment_postcode'];
		}
		return $buy_link_params;
	}

	/**
	 * @param $order_info
	 * @param $test
	 * @param $seller_id
	 * @param $price
	 * @param $language
	 *
	 * @return array
	 */
	public function getOtherDetails($order_info, $test, $seller_id, $price, $language){
		$buy_link_params = array();

		$buy_link_params['prod']     = 'Cart_' . $order_info['order_id'];
		$buy_link_params['price']    = $price;
		$buy_link_params['qty']      = 1;
		$buy_link_params['type']     = 'PRODUCT';
		$buy_link_params['tangible'] = 0;
		$buy_link_params['src']      = 'OPENCART_'.str_replace('.','_',VERSION);
		$buy_link_params['return-type']      = 'redirect';
		$buy_link_params['return-url']       = $this->url->link( 'extension/payment/twocheckout_cplus/success' );
		$buy_link_params['expiration']       = time() + ( 3600 * 5 );
		$buy_link_params['order-ext-ref']    = $order_info['order_id'];
		$buy_link_params['item-ext-ref']     = date( 'YmdHis' );
		$buy_link_params['customer-ext-ref'] = $order_info['email'];
		$buy_link_params['currency']         = strtolower( $order_info['currency_code'] );
		$buy_link_params['language']         = $language;
		$buy_link_params['test']             = ( $test == 1 ) ? '1' : '0';
		$buy_link_params['merchant']         = $seller_id;
		$buy_link_params['dynamic']          = 1;

		return $buy_link_params;
	}

	/**
	 * once the payment is made we save/update the transaction for future refunds
	 * @param $params
	 * @return mixed
	 */
	public function addUpdateTransaction($params)
	{
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "twocheckout_cplus` 
        WHERE `order_id` = '" . (int)$params['REFNOEXT'] . "' LIMIT 1");
		if ($query->num_rows) {
			return $this->db->query("
                UPDATE `" . DB_PREFIX . "twocheckout_cplus` SET 
                `transaction_id` = '" . trim($this->db->escape($params['REFNO'])) . "',
                `amount` = '" . trim($this->db->escape($params['IPN_TOTALGENERAL'])) . "',
                `currency` = '" . trim(strtoupper($this->db->escape($params['CURRENCY']))) . "',
                `tco_order_status` = '" . trim(strtoupper($this->db->escape($params['ORDERSTATUS']))) . "'
                WHERE `order_id` = '" . (int)$params['REFNOEXT'] . "'
                ");
		}

		return $this->db->query("
                INSERT INTO `" . DB_PREFIX . "twocheckout_cplus` SET 
                `order_id` = '" . (int)$params['REFNOEXT'] . "', 
                `transaction_id` = '" . trim($this->db->escape($params['REFNO'])) . "',
                `amount` = '" . trim($this->db->escape($params['IPN_TOTALGENERAL'])) . "',
                `currency` = '" . trim(strtoupper($this->db->escape($params['CURRENCY']))) . "',
                `tco_order_status` = '" . trim(strtoupper($this->db->escape($params['ORDERSTATUS']))) . "'
                ");

	}
}


