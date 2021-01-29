<?php

class ModelExtensionPaymentTwoCheckoutApi extends Model
{
    // API url & version
    const API_URL = 'https://api.2checkout.com/rest/';
    const API_VERSION = '6.0';
    // 2CO status
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

    // fraud status
    const FRAUD_STATUS_APPROVED = 'APPROVED';
    const FRAUD_STATUS_DENIED = 'DENIED';
    // opencart status
    const OPENCART_CHARGEBACK = 13;
    const OPENCART_DENIED = 8;
    const OPENCART_COMPLETE = 5;
    const OPENCART_PROCESSING = 2;

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getHeaders()
    {
        $sellerId = $this->config->get('payment_twocheckout_api_account');
        $secretKey = $this->config->get('payment_twocheckout_api_secret_key');
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
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function call($params)
    {

        try {
            $url = self::API_URL . self::API_VERSION . '/orders/';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
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
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/twocheckout_api');
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_twocheckout_api_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
        if ($this->config->get('payment_twocheckout_api_total') > 0 && $this->config->get('payment_twocheckout_api_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('payment_twocheckout_api_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code'       => 'twocheckout_api',
                'title'      => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_twocheckout_api_sort_order')
            ];
        }

        return $method_data;
    }


    /**
     * @param array  $post_data
     * @param string $country_iso
     *
     * @return array
     */
    public function getBillingDetails(array $post_data, string $country_iso)
    {
        $address = [
            'Address1'    => $post_data['payment_address_1'],
            'City'        => $post_data['payment_city'],
            'State'       => $post_data['payment_zone_code'] ?? 'XX',
            'CountryCode' => $country_iso,
            'Email'       => $post_data['email'],
            'FirstName'   => $post_data['payment_firstname'],
            'LastName'    => $post_data['payment_lastname'],
            'Phone'       => $post_data['telephone'],
            'Zip'         => $post_data['payment_postcode'],
            'Company'     => $post_data['payment_company']
        ];

        if ($post_data['payment_address_2']) {
            $address['Address2'] = $post_data['payment_address_2'];
        }

        return $address;
    }

    /**
     * @param string $name
     * @param float  $total
     * @return mixed
     */
    public function getItem(string $name, float $total)
    {
        $items[] = [
            'Code'             => null,
            'Quantity'         => 1,
            'Name'             => $name,
            'Description'      => 'N/A',
            'RecurringOptions' => null,
            'IsDynamic'        => true,
            'Tangible'         => false,
            'PurchaseType'     => 'PRODUCT',
            'Price'            => [
                'Amount' => number_format($total, 2, '.', ''),
                'Type'   => 'CUSTOM'
            ]
        ];

        return $items;
    }

    /**
     * @param string $type
     * @param string $token
     * @param string $currency
     * @return array
     */
    public function getPaymentDetails(string $type, string $token, string $currency)
    {

        return [
            'Type'          => $type,
            'Currency'      => $currency,
            'CustomerIP'    => $this->getCustomerIp(),
            'PaymentMethod' => [
                'EesToken'           => $token,
                'Vendor3DSReturnURL' => $this->url->link('extension/payment/twocheckout_api/success', '', true),
                'Vendor3DSCancelURL' => $this->url->link('extension/payment/twocheckout_api/cancel', '', true)
            ],
        ];

    }

    /**
     * @param mixed $has3ds
     *
     * @return string|null
     */
    public function hasAuthorize3DS($has3ds)
    {

        return (isset($has3ds) && isset($has3ds['Href']) && !empty($has3ds['Href'])) ?
            $has3ds['Href'] . '?avng8apitoken=' . $has3ds['Params']['avng8apitoken'] :
            null;
    }

    /**
     * get customer ip or returns a default ip
     * @return mixed|string
     */
    public function getCustomerIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $ip;
        }

        return '1.0.0.1';
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
        $this->load->language('extension/payment/twocheckout_api');
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
        // we need to mock up a message with some params in order to add this note
        if (!empty(trim($params['CHARGEBACK_RESOLUTION']) && trim($params['CHARGEBACK_RESOLUTION']) !== 'NONE') &&
            !empty(trim($params['CHARGEBACK_REASON_CODE']))) {

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

            $why = isset($reasons[trim($params['CHARGEBACK_REASON_CODE'])]) ?
                $reasons[trim($params['CHARGEBACK_REASON_CODE'])] :
                $reasons['UNKNOWN'];
            $message = '2Checkout chargeback status is now ' . $params['CHARGEBACK_RESOLUTION'] . '. Reason: ' . $why . '!';
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
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "twocheckout_api` 
        WHERE `order_id` = '" . (int)$params['REFNOEXT'] . "' LIMIT 1");
        if ($query->num_rows) {
            return $this->db->query("
                UPDATE `" . DB_PREFIX . "twocheckout_api` SET 
                `transaction_id` = '" . trim($this->db->escape($params['REFNO'])) . "',
                `amount` = '" . trim($this->db->escape($params['IPN_TOTALGENERAL'])) . "',
                `currency` = '" . trim(strtoupper($this->db->escape($params['CURRENCY']))) . "',
                `tco_order_status` = '" . trim(strtoupper($this->db->escape($params['ORDERSTATUS']))) . "'
                WHERE `order_id` = '" . (int)$params['REFNOEXT'] . "'
                ");
        }

        return $this->db->query("
                INSERT INTO `" . DB_PREFIX . "twocheckout_api` SET 
                `order_id` = '" . (int)$params['REFNOEXT'] . "', 
                `transaction_id` = '" . trim($this->db->escape($params['REFNO'])) . "',
                `amount` = '" . trim($this->db->escape($params['IPN_TOTALGENERAL'])) . "',
                `currency` = '" . trim(strtoupper($this->db->escape($params['CURRENCY']))) . "',
                `tco_order_status` = '" . trim(strtoupper($this->db->escape($params['ORDERSTATUS']))) . "'
                ");

    }
}
