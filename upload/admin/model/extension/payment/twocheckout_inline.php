<?php

class ModelExtensionPaymentTwocheckoutInline extends Model
{
    // API url & version
    const API_URL = 'https://api.2checkout.com/rest/';
    const API_VERSION = '6.0';
    const OPENCART_REFUND = 11;

    /**
     * install the module & creates the table
     */
    public function install()
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "twocheckout_inline` (
            `id` INT(11) NOT NULL AUTO_INCREMENT ,
            `order_id` INT(11) NOT NULL ,
            `amount` DECIMAL(10,2) NOT NULL ,
            `currency` VARCHAR(3) NULL ,
            `transaction_id` VARCHAR(30) NOT NULL ,
            `tco_order_status` VARCHAR(50) NOT NULL ,
            `comment` TEXT NULL ,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
            `updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
            PRIMARY KEY (`id`)
            ) ENGINE = InnoDB;");
    }

    /**
     * uninstall and drop the table
     */
    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "twocheckout_inline`;");
    }

    /**
     * @param int $order_id
     * @return null
     */
    public function getTransactionByOrderId( $order_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "twocheckout_inline` WHERE `order_id` = '" .
                                  intval($order_id) . "' LIMIT 1");

        return ($query->num_rows) ? $query->row : null;
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
    private function call($endpoint, $params = [], $method = 'GET')
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
     * @param        $order_id
     * @param string $comment
     * @return array
     * @throws \Exception
     */
    public function refund($order_id, $comment = '')
    {
        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($order_id);
        $total = $this->currency->format($order['total'], $order['currency_code'], '', false);
        $transaction = $this->getTransactionByOrderId($order_id);
        $tco_order = $this->call('/orders/' . $transaction['transaction_id'] . '/');
        if (!$order || !$tco_order || !$transaction) {
            return [
                'success' => false,
                'message' => 'Order/transaction not found!'
            ];
        }

        if ($total != $tco_order['GrossPrice']) {
            return [
                'success'    => false,
                'message'    => 'Only full refund is supported!',
                'total'      => $total,
                'GrossPrice' => $tco_order['GrossPrice']
            ];
        }
        if ($order['order_status_id'] == self::OPENCART_REFUND) {
            return [
                'success' => false,
                'message' => 'Order already refunded!'
            ];
        }
        $params = [
            "amount"  => $tco_order['GrossPrice'],
            "comment" => $comment,
            "reason"  => 'Other'
        ];
        $response = $this->call('/orders/' . $transaction['transaction_id'] . '/refund/', $params, 'POST');
        if (isset($response['error_code']) && !empty($response['error_code'])) {
            return [
                'success' => false,
                'message' => 'Refund failed. Please login to your 2Checkout admin to issue the partial refund manually.'
            ];
        }

        $this->updateRefundTransaction($transaction['order_id'], $comment);

        return [
            'success' => true,
            'message' => 'Success, refund done!'
        ];
    }

    /**
     * @param $order_id
     * @param $comment
     */
    private function updateRefundTransaction($order_id, $comment)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "twocheckout_inline` SET 
                `tco_order_status` = 'REFUND',
                `comment` = '" . trim($this->db->escape($comment)) . "'
                WHERE `order_id` = '" . $order_id . "'");

        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
                `order_status_id` =  '" . self::OPENCART_REFUND . "'
                WHERE `order_id` = '" . $order_id . "'");

        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET 
                `order_id` =  '" . $order_id . "',
                `order_status_id` =  '" . self::OPENCART_REFUND . "',
                `date_added` =  '" . date('Y-m-d H:i:s', time()) . "',
                `comment` =  '" . trim($this->db->escape($comment)) . "'");
    }

}
