<?php

class ModelExtensionPaymentTwoCheckoutInline extends Model
{
    const SIGNATURE_URL = "https://secure.2checkout.com/checkout/api/encrypt/generate/signature";
    // API url & version
    const API_URL = 'https://api.2checkout.com/rest/';
    const API_VERSION = '6.0';

    //Order Status Values:
    const ORDER_STATUS_PENDING = 'PENDING';
    const ORDER_STATUS_PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
    const ORDER_STATUS_AUTHRECEIVED = 'AUTHRECEIVED';
    const ORDER_STATUS_INVALID = 'INVALID';
    const ORDER_STATUS_COMPLETE = 'COMPLETE';
    const ORDER_STATUS_REFUND = 'REFUND';
    const ORDER_STATUS_PURCHASE_PENDING = 'PURCHASE_PENDING';
    const ORDER_STATUS_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
    const ORDER_STATUS_CANCELED = 'CANCELED';
    const ORDER_STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    // fraud status
    const FRAUD_STATUS_APPROVED = 'APPROVED';
    const FRAUD_STATUS_DENIED = 'DENIED';
    // opencart status
    const OPENCART_CHARGEBACK = 13;
    const OPENCART_DENIED = 8;
    const OPENCART_COMPLETE = 5;
    const OPENCART_PROCESSING = 2;

    /**
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/twocheckout_inline');
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_twocheckout_inline_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
        if ($this->config->get('payment_twocheckout_inline_total') > 0 && $this->config->get('payment_twocheckout_inline_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('payment_twocheckout_inline_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code'       => 'twocheckout_inline',
                'title'      => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_twocheckout_inline_sort_order')
            ];
        }

        return $method_data;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getHeaders()
    {
        $sellerId = $this->config->get('payment_twocheckout_inline_account');
        $secretKey = $this->config->get('payment_twocheckout_inline_secret_key');
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
     * @param $endpoint
     * @param $params
     * @param $method
     * @return mixed
     * @throws \Exception
     */
    public function call($endpoint, $params = [], $method = 'GET')
    {

        try {
            $url = self::API_URL . self::API_VERSION . $endpoint;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
            }
            $response = curl_exec($ch);

            if ($response === false) {
                exit(curl_error($ch));
            }
            curl_close($ch);

            return json_decode($response, true);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * @param $params
     * @param $secretKey
     * @return bool
     */
    public function isIpnResponseValid($params, $secretKey)
    {
        $result = '';
        $receivedHash = $params['HASH'];
        foreach ($params as $key => $val) {
            if ($key != "HASH") {
                if (is_array($val)) {
                    $result .= $this->arrayExpand($val);
                } else {
                    $size = strlen(stripslashes($val));
                    $result .= $size . stripslashes($val);
                }
            }
        }

        if (isset($params['REFNO']) && !empty($params['REFNO'])) {
            $calcHash = $this->hmac($secretKey, $result);
            if ($receivedHash === $calcHash) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $ipnParams
     * @param $secret_key
     * @return string
     */
    public function calculateIpnResponse($ipnParams, $secret_key)
    {
        $resultResponse = '';
        $ipnParamsResponse = [];
        // we're assuming that these always exist, if they don't then the problem is on 2CO side
        $ipnParamsResponse['IPN_PID'][0] = $ipnParams['IPN_PID'][0];
        $ipnParamsResponse['IPN_PNAME'][0] = $ipnParams['IPN_PNAME'][0];
        $ipnParamsResponse['IPN_DATE'] = $ipnParams['IPN_DATE'];
        $ipnParamsResponse['DATE'] = date('YmdHis');

        foreach ($ipnParamsResponse as $key => $val) {
            $resultResponse .= $this->arrayExpand((array)$val);
        }

        return sprintf(
            '<EPAYMENT>%s|%s</EPAYMENT>',
            $ipnParamsResponse['DATE'],
            $this->hmac($secret_key, $resultResponse)
        );
    }

    /**
     * @param $array
     *
     * @return string
     */
    private function arrayExpand($array)
    {
        $result = '';
        foreach ($array as $key => $value) {
            $size = strlen(stripslashes($value));
            $result .= $size . stripslashes($value);
        }

        return $result;
    }

    /**
     * @param $key
     * @param $data
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
     * @throws \Exception
     */
    public function processOrderStatus($params)
    {
        $this->load->language('extension/payment/twocheckout_inline');
        $this->load->model('localisation/order_status');
        $this->load->model('checkout/order');

        $text = $this->language->get('updated_order_status');
        $order_status = ($params['FRAUD_STATUS'] && $params['FRAUD_STATUS'] === self::FRAUD_STATUS_DENIED) ?
            self::FRAUD_STATUS_DENIED :
            $params['ORDERSTATUS'];
        $this->addUpdateTransaction($params); // for further refunds

        switch (trim($order_status)) {
            //fraud status
            case self::FRAUD_STATUS_DENIED:
                $status = $this->model_localisation_order_status->getOrderStatus(self::OPENCART_DENIED);
                $this->model_checkout_order->addOrderHistory($params['REFNOEXT'], self::OPENCART_DENIED, $text . $status['name']);
                break;
            case self::FRAUD_STATUS_APPROVED:
                // order status
            case self::ORDER_STATUS_PENDING:
            case self::ORDER_STATUS_PURCHASE_PENDING:
            case self::ORDER_STATUS_AUTHRECEIVED:
            case self::ORDER_STATUS_PAYMENT_RECEIVED:
            case self::ORDER_STATUS_PENDING_APPROVAL:
            case self::ORDER_STATUS_PAYMENT_AUTHORIZED:
                $status = $this->model_localisation_order_status->getOrderStatus(self::OPENCART_PROCESSING);
                $this->model_checkout_order->addOrderHistory($params['REFNOEXT'], self::OPENCART_PROCESSING, $text . $status['name']);
                break;
            case self::ORDER_STATUS_COMPLETE:
                $status = $this->model_localisation_order_status->getOrderStatus(self::OPENCART_COMPLETE);
                if (!$this->isChargeBack($params)) {
                    $this->model_checkout_order->addOrderHistory($params['REFNOEXT'], self::OPENCART_COMPLETE, $text . $status['name']);
                }
                break;

            default:
                throw new Exception('Cannot handle Ipn message type for message');
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
            $this->model_checkout_order->addOrderHistory($params['REFNOEXT'], self::OPENCART_CHARGEBACK, $message);

            return true;
        }

        return false;
    }

    /**
     * once the payment is made we save/update the transaction for future refunds
     * @param $params
     * @return mixed
     */
    public function addUpdateTransaction($params)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "twocheckout_inline` 
        WHERE `order_id` = '" . (int)$params['REFNOEXT'] . "' LIMIT 1");
        if ($query->num_rows) {
            return $this->db->query("
                UPDATE `" . DB_PREFIX . "twocheckout_inline` SET 
                `transaction_id` = '" . trim($this->db->escape($params['REFNO'])) . "',
                `amount` = '" . trim($this->db->escape($params['IPN_TOTALGENERAL'])) . "',
                `currency` = '" . trim(strtoupper($this->db->escape($params['CURRENCY']))) . "',
                `tco_order_status` = '" . trim(strtoupper($this->db->escape($params['ORDERSTATUS']))) . "'
                WHERE `order_id` = '" . (int)$params['REFNOEXT'] . "'
                ");
        }

        return $this->db->query("
                INSERT INTO `" . DB_PREFIX . "twocheckout_inline` SET 
                `order_id` = '" . (int)$params['REFNOEXT'] . "', 
                `transaction_id` = '" . trim($this->db->escape($params['REFNO'])) . "',
                `amount` = '" . trim($this->db->escape($params['IPN_TOTALGENERAL'])) . "',
                `currency` = '" . trim(strtoupper($this->db->escape($params['CURRENCY']))) . "',
                `tco_order_status` = '" . trim(strtoupper($this->db->escape($params['ORDERSTATUS']))) . "'
                ");

    }

    /**
     * @param $sellerId
     * @param $secretWord
     * @param $payload
     * @return mixed
     * @throws \Exception
     */
    public function getInlineSignature($sellerId, $secretWord, $payload)
    {
        $jwtToken = $this->generateJWT($sellerId, $secretWord);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => self::SIGNATURE_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'cache-control: no-cache',
                'merchant-token: ' . $jwtToken,
            ]
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new Exception('Error when trying to place order');
        }

        $response = json_decode($response, true);

        if (JSON_ERROR_NONE !== json_last_error() || !isset($response['signature'])) {
            throw new Exception('Unable to get proper response from signature generation API');
        }

        return $response['signature'];
    }

    /**
     * @param $sellerId
     * @param $secretWord
     * @return string
     */
    private function generateJWT($sellerId, $secretWord)
    {
        $secretWord = html_entity_decode($secretWord);
        $header = $this->encode(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
        $payload = $this->encode(json_encode(['sub' => $sellerId, 'iat' => time(), 'exp' => time() + 3600]));
        $signature = $this->encode(hash_hmac('sha512', "$header.$payload", $secretWord, true));

        return implode('.', [$header, $payload, $signature]);
    }

    /**
     * @param $data
     *
     * @return string|string[]
     */
    private function encode($data)
    {
        return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
    }

}
