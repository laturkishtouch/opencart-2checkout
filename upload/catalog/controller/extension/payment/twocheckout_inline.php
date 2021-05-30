<?php

/**
 * Class ControllerExtensionPaymentTwoCheckoutInline
 */
class ControllerExtensionPaymentTwoCheckoutInline extends Controller
{

    /**
     * ControllerExtensionPaymentTwoCheckoutInline constructor.
     * load all necessary models and language helper
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/twocheckout_inline');
        $this->load->model('localisation/country');
        $this->load->language('extension/payment/twocheckout_inline');
    }

    /**
     * payment form shown
     * @return mixed
     */
    public function index()
    {

        $data['config'] = $this->model_setting_setting->getSetting('payment_twocheckout_inline');
        $order_id = $this->session->data['order_id'];
        $model = $this->model_extension_payment_twocheckout_inline;
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $config = $this->model_setting_setting->getSetting('payment_twocheckout_inline');
        $seller_id = $this->config->get('payment_twocheckout_inline_account');
        $secret_word = $this->config->get('payment_twocheckout_inline_secret_word');
        $total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

        $billingAddressData = [
            'name'         => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
            'phone'        => $order_info['telephone'],
            'country'      => $order_info['payment_iso_code_2'],
            'state'        => isset($order_info['payment_zone_code']) ? $order_info['payment_zone_code'] : 'XX',
            'email'        => $order_info['email'],
            'address'      => $order_info['payment_address_1'],
            'address2'     => isset($order_info['payment_address_2']) ? $order_info['payment_address_2'] : '',
            'city'         => $order_info['payment_city'],
            'company-name' => $order_info['payment_company'],
            'zip'          => $order_info['payment_postcode'],
        ];

        $shippingAddressData = [
            'ship-name'     => $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'],
            'ship-country'  => $order_info['shipping_iso_code_2'],
            'ship-state'    => isset($order_info['shipping_zone_code']) ? $order_info['shipping_zone_code'] : 'XX',
            'ship-city'     => $order_info['shipping_city'],
            'ship-email'    => $order_info['email'],
            'ship-address'  => $order_info['shipping_address_1'],
            'ship-address2' => (isset($order_info['shipping_address_2']) && !empty($order_info['shipping_address_2'])
            ) ? $order_info['shipping_address_2'] : '',
        ];

        $payload['products'][] = [
            'type'     => 'PRODUCT',
            'name'     => 'Cart_' . $order_id,
            'price'    => $total,
            'tangible' => 0,
            'qty'      => 1,
        ];

        $payload['currency'] = strtoupper($order_info['currency_code']);
        $payload['language'] = strtoupper(substr($this->session->data['language'], 0, 2));
        $payload['return-method'] = [
            'type' => 'redirect',
            'url'  => $this->url->link('extension/payment/twocheckout_inline/callback', '', true)
        ];
        $payload['test'] = $config['payment_twocheckout_inline_test'] === 'yes' ? 1 : 0;
        $payload['order-ext-ref'] = $order_id;
        $payload['customer-ext-ref'] = $order_info['email'];
        $payload['src'] = 'OPENCART_'.str_replace('.','_',VERSION);
        $payload['mode'] = 'DYNAMIC';
        $payload['dynamic'] = '1';
        $payload['country'] = strtoupper($order_info['payment_iso_code_2']);
        $payload['merchant'] = $seller_id;
        $payload['shipping_address'] = ($shippingAddressData);
        $payload['billing_address'] = ($billingAddressData);
        array_merge($payload, $billingAddressData);
        array_merge($payload, $billingAddressData);
        $payload['signature'] = $model->getInlineSignature($seller_id, $secret_word, $payload);

        $data['payload'] = json_encode($payload);
        $data['button_confirm'] = $this->language->get('button_confirm');


        return $this->load->view('extension/payment/twocheckout_inline', $data);
    }

    /**
     * buyer cancel the 3ds order and we redirect him to his Shopping cart
     */
    public function cancel()
    {
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    /**
     * all good
     */
    public function success()
    {
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    /**
     * empty the cart and redirect to success page
     * add a comment with transaction ID from 2Checkout
     */
    public function callback()
    {
        $params = $_GET;

        if (!isset($params['refno']) || empty($params['refno'])) {
            $this->cancel();
        }
        $model = $this->model_extension_payment_twocheckout_inline;
        $tco_order = $model->call('/orders/' . $params['refno'] . '/');

        if ($tco_order && !empty($tco_order['RefNo']) && !empty($tco_order['Status'])) {

            //default status
            $status = 1; //pending
            if (in_array($tco_order['Status'], ['COMPLETE', 'AUTHRECEIVED'])) {
                $status = $this->config->get('payment_twocheckout_inline_order_status_id');
            }

            $this->model_checkout_order->addOrderHistory(
                $this->session->data['order_id'],
                $status,
                '2Checkout transaction ID:<strong style="color: #12578c;"> ' . $params['refno'] . '</strong>'
            );

            $this->success();
        }
        $this->cancel();
    }

    /**
     * updates the order status over 2co IPN into the opencart & add notes
     * on the order with the transaction ID form 2CO and all other status updates
     * @throws \Exception
     */
    public function ipn()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            exit('Not allowed');
        }

        $secret_key = $this->config->get('payment_twocheckout_inline_secret_key');
        $params = $this->request->post;
        $order = $this->model_checkout_order->getOrder($params['REFNOEXT']);
//        ignore all other payment methods
        if ($order && $order['payment_code'] === 'twocheckout_inline') {

            $model = $this->model_extension_payment_twocheckout_inline;

            if (!isset($params['REFNOEXT']) && (!isset($params['REFNO']) && empty($params['REFNO']))) {
                throw new Exception(sprintf('Cannot identify order: "%s".', $params['REFNOEXT']));
            }
            if (!$model->isIpnResponseValid($params, $secret_key)) {
                throw new Exception(sprintf('MD5 hash mismatch for 2Checkout IPN with date: "%s".', $params['IPN_DATE']));
            }

            $model->processOrderStatus($params);

            echo $model->calculateIpnResponse($params, $secret_key);
        }
        exit();
    }

}
