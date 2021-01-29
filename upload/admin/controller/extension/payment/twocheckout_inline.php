<?php

class ControllerExtensionPaymentTwoCheckoutInline extends Controller
{
    private $error = [];
    /**
     * name of the payment method`
     */
    const NAME = 'payment_twocheckout_inline';

    /**
     * settings page
     */
    public function index()
    {
        $this->load->language('extension/payment/twocheckout_inline');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        $post_data = $this->request->post;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_twocheckout_inline', $post_data);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_account'] = isset($this->error['account']) ? $this->error['account'] : '';
        $data['error_secret_key'] = isset($this->error['secret_key']) ? $this->error['secret_key'] : '';
        $data['error_secret_word'] = isset($this->error['secret_word']) ? $this->error['secret_word'] : '';

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/twocheckout_inline', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['action'] = $this->url->link('extension/payment/twocheckout_inline', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data[self::NAME . '_ipn'] = HTTPS_CATALOG . 'index.php?route=extension/payment/twocheckout_inline/ipn';
        $data[self::NAME . '_account'] = isset($post_data[self::NAME . '_account']) ?
            $post_data[self::NAME . '_account'] : $this->config->get(self::NAME . '_account');

        $data[self::NAME . '_secret_key'] = isset($post_data[self::NAME . '_secret_key']) ?
            $post_data[self::NAME . '_secret_key'] : $this->config->get(self::NAME . '_secret_key');

        $data[self::NAME . '_secret_word'] = isset($post_data[self::NAME . '_secret_word']) ?
            $post_data[self::NAME . '_secret_word'] : $this->config->get(self::NAME . '_secret_word');

        $data[self::NAME . '_display'] = isset($post_data[self::NAME . '_display']) ?
            $post_data[self::NAME . '_display'] : $this->config->get(self::NAME . '_display');

        $data[self::NAME . '_test'] = isset($post_data[self::NAME . '_test']) ?
            $post_data[self::NAME . '_test'] : $this->config->get(self::NAME . '_test');

        $data[self::NAME . '_total'] = isset($post_data[self::NAME . '_total']) ?
            $post_data[self::NAME . '_total'] : $this->config->get(self::NAME . '_total');

        $data[self::NAME . '_order_status_id'] = isset($post_data[self::NAME . '_order_status_id']) ?
            $post_data[self::NAME . '_order_status_id'] : $this->config->get(self::NAME . '_order_status_id');

        $data[self::NAME . '_geo_zone_id'] = isset($post_data[self::NAME . '_geo_zone_id']) ?
            $post_data[self::NAME . '_geo_zone_id'] : $this->config->get(self::NAME . '_geo_zone_id');

        $data[self::NAME . '_status'] = isset($post_data[self::NAME . '_status']) ?
            $post_data[self::NAME . '_status'] : $this->config->get(self::NAME . '_status');

        $data[self::NAME . '_sort_order'] = isset($post_data[self::NAME . '_sort_order']) ?
            $post_data[self::NAME . '_sort_order'] : $this->config->get(self::NAME . '_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/twocheckout_inline', $data));
    }

    /**
     * validates the request
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/twocheckout_inline')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->request->post[self::NAME . '_account']) {
            $this->error['account'] = $this->language->get('error_account');
        }
        if (!$this->request->post[self::NAME . '_secret_key']) {
            $this->error['secret_key'] = $this->language->get('error_secret_key');
        }

        if (!$this->request->post[self::NAME . '_secret_word']) {
            $this->error['secret_word'] = $this->language->get('error_secret_word');
        }

        return !$this->error;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function order()
    {
        $this->load->language('extension/payment/twocheckout_inline');
        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($this->request->get['order_id']);
        if ($this->config->get('payment_twocheckout_inline_status') && $order['payment_code'] === 'twocheckout_inline') {
            $this->load->model('extension/payment/twocheckout_inline');

            $data['text_refund'] = $this->language->get('text_refund');
            $data['text_total_amount_refund'] = $this->language->get('text_total_amount_refund');
            $data['text_refund_final'] = $this->language->get('text_refund_final');
            $data['order'] = $order;
            $data['order_total'] = $this->currency->format($order['total'], $order['currency_code'], '', false);
            $data['transaction'] = $this->model_extension_payment_twocheckout_inline->getTransactionByOrderId($order['order_id']);
            $data['user_token'] = $this->session->data['user_token'];
            $data['action'] = $this->url->link('extension/payment/twocheckout_inline/refund', 'user_token=' . $this->session->data['user_token'], true);

            return $this->load->view('extension/payment/twocheckout_inline_order', $data);
        }
    }

    /**
     * refunds an order
     */
    public function refund()
    {
        //default response
        $response = ['success' => false, 'message' => 'Missing data'];

        if (isset($this->request->post['order_id']) && !empty($this->request->post['order_id'])) {
            $this->load->model('extension/payment/twocheckout_inline');
            $response = $this->model_extension_payment_twocheckout_inline->refund(
                $this->request->post['order_id'],
                $this->request->post['comment']
            );
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }

    /**
     * install the module
     */
    function install()
    {
        $this->load->model('extension/payment/twocheckout_inline');
        $this->model_extension_payment_twocheckout_inline->install();

    }

    /**
     * uninstall the module
     */
    public function uninstall()
    {
        $this->load->model('extension/payment/twocheckout_inline');
        $this->model_extension_payment_twocheckout_inline->uninstall();
    }
}
