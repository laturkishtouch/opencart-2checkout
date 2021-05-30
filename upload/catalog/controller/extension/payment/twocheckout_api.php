<?php

/**
 * Class ControllerExtensionPaymentTwoCheckoutApi
 */
class ControllerExtensionPaymentTwoCheckoutApi extends Controller
{

    /**
     * ControllerExtensionPaymentTwoCheckoutApi constructor.
     * load all necessary models and language helper
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/twocheckout_api');
        $this->load->model('localisation/country');
        $this->load->language('extension/payment/twocheckout_api');
    }

    /**
     * payment form shown
     * @return mixed
     */
    public function index()
    {
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['config'] = $this->model_setting_setting->getSetting('payment_twocheckout_api');
        $custom_style = str_replace('&quot;', '"', $data['config']['payment_twocheckout_api_custom_style']);
        $custom_style = preg_replace('/\v(?:[\v\h]+)/', '', $custom_style);
        $custom_style = preg_replace("/\s+/", "", $custom_style);

        $data['config']['payment_twocheckout_api_custom_style'] = $custom_style;
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('extension/payment/twocheckout_api/purchase', '', true);
        $data['customer'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];

        return $this->load->view('extension/payment/twocheckout_api', $data);
    }

    /**
     * creates the order
     */
    public function purchase()
    {

        $order_id = $this->session->data['order_id'];
        $model = $this->model_extension_payment_twocheckout_api;
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $config = $this->model_setting_setting->getSetting('payment_twocheckout_api');
        $currency = strtolower($order_info['currency_code']);
        $total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $type = $config['payment_twocheckout_api_test'] === 'yes' ? 'TEST' : 'EES_TOKEN_PAYMENT';
        $country = $this->model_localisation_country->getCountry($order_info['payment_country_id']);
        $country_iso = strtolower($country['iso_code_2']);
        $token = $this->request->post['ess_token'];

        $order_params = [
            'Currency'          => $currency,
            'Language'          => strtolower(substr($this->session->data['language'], 0, 2)),
            'Country'           => $country_iso,
            'CustomerIP'        => $model->getCustomerIp(),
            'Source'            => 'OPENCART_'.str_replace('.','_',VERSION),
            'ExternalReference' => $order_id,
            'Items'             => $model->getItem($order_info['store_name'], $total),
            'BillingDetails'    => $model->getBillingDetails($order_info, $country_iso),
            'PaymentDetails'    => $model->getPaymentDetails($type, $token, $currency)
        ];

        try {
            $api_response = $model->call($order_params);
            if (!$api_response || isset($api_response['error_code']) && !empty($api_response['error_code'])) { // we dont get any response from 2co or internal account related error
                $error_message = $this->language->get('generic_error');
                if ($api_response && isset($api_response['message']) && !empty($api_response['message'])) {
                    $error_message = $api_response['message'];
                }
                $json_response = ['success' => false, 'messages' => $error_message, 'redirect' => null];
            } else {
                if ($api_response['Errors']) { // errors that must be shown to the client
                    $error_message = '';
                    foreach ($api_response['Errors'] as $key => $value) {
                        $error_message .= $value . PHP_EOL;
                    }
                    $json_response = ['success' => false, 'messages' => $error_message, 'redirect' => null];
                } else {
                    $has3ds = null;
                    if (isset($api_response['PaymentDetails']['PaymentMethod']['Authorize3DS'])) {
                        $has3ds = $model->hasAuthorize3DS($api_response['PaymentDetails']['PaymentMethod']['Authorize3DS']);
                    }
                    if ($has3ds) {
                        $redirect_url = $has3ds;
                        $json_response = [
                            'success'  => true,
                            'messages' => '3dSecure Redirect',
                            'redirect' => $redirect_url
                        ];
                    } else {
                        $this->model_checkout_order->addOrderHistory(
                            $order_id,
                            $this->config->get('payment_twocheckout_api_order_status_id'),
                            '2Checkout transaction ID:<strong style="color: #12578c;"> ' . $api_response["RefNo"] . '</strong>'
                        );

                        $json_response = [
                            'success'  => true,
                            'messages' => 'Order payment success',
                            'redirect' => $this->url->link('checkout/success', '', true)
                        ];
                    }
                }
            }

        } catch (Exception $e) {

            $json_response = [
                'success'  => false,
                'messages' => $e->getMessage(),
                'redirect' => null
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json_response));
    }

    /**
     * buyer cancel the 3ds order and we redirect him to his Shopping cart
     */
    public function cancel()
    {
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    /**
     * get here after a success 3ds payment
     * empty the cart and redirect to success page
     * add a comment with transaction ID from 2Checkout
     */
    public function success()
    {
        if (!isset($_GET['REFNO']) || empty($_GET['REFNO'])) {
            $this->cancel();
        }
        $params = $_GET;
        $this->model_checkout_order->addOrderHistory(
            $this->session->data['order_id'],
            $this->config->get('payment_twocheckout_api_order_status_id'),
            '2Checkout transaction ID:<strong style="color: #12578c;"> ' . $params['REFNO'] . '</strong>'
        );

        $this->response->redirect($this->url->link('checkout/success', '', true));
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
        $secret_key = $this->config->get('payment_twocheckout_api_secret_key');
        $params = $this->request->post;
        $order = $this->model_checkout_order->getOrder($params['REFNOEXT']);

//        ignore all other payment methods
        if ($order && $order['payment_code'] === 'twocheckout_api') {

            $model = $this->model_extension_payment_twocheckout_api;
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
