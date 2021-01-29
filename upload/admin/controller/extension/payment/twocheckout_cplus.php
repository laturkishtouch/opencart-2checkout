<?php

class ControllerExtensionPaymentTwoCheckoutCplus extends Controller
{
	private $error = [];

	/**
	 *
	 */
	public function index()
	{
		$this->load->language('extension/payment/twocheckout_cplus');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_twocheckout_cplus', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['account'])) {
			$data['error_account'] = $this->error['account'];
		} else {
			$data['error_account'] = '';
		}

		if (isset($this->error['secret_key'])) {
			$data['error_secret_key'] = $this->error['secret_key'];
		} else {
			$data['error_secret_key'] = '';
		}

		if (isset($this->error['secret_word'])) {
			$data['error_secret_word'] = $this->error['secret_word'];
		} else {
			$data['error_secret_word'] = '';
		}

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
			'href' => $this->url->link('extension/payment/twocheckout_cplus', 'user_token=' . $this->session->data['user_token'], true)
		];


		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['action'] = $this->url->link('extension/payment/twocheckout_cplus', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		$data['payment_twocheckout_cplus_ipn'] = HTTPS_CATALOG . 'index.php?route=extension/payment/twocheckout_cplus/ipn';

		if (isset($this->request->post['payment_twocheckout_cplus_account']) && !empty($this->request->post['payment_twocheckout_cplus_account'])) {
			$data['payment_twocheckout_cplus_account'] = $this->request->post['payment_twocheckout_cplus_account'];
		} else {
			$data['payment_twocheckout_cplus_account'] = $this->config->get('payment_twocheckout_cplus_account');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_secret_word']) && !empty($this->request->post['payment_twocheckout_cplus_secret_word'])) {
			$data['payment_twocheckout_cplus_secret_word'] = $this->request->post['payment_twocheckout_cplus_secret_word'];
		} else {
			$data['payment_twocheckout_cplus_secret_word'] = $this->config->get('payment_twocheckout_cplus_secret_word');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_secret_key']) && !empty($this->request->post['payment_twocheckout_cplus_secret_key'])) {
			$data['payment_twocheckout_cplus_secret_key'] = $this->request->post['payment_twocheckout_cplus_secret_key'];
		} else {
			$data['payment_twocheckout_cplus_secret_key'] = $this->config->get('payment_twocheckout_cplus_secret_key');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_display']) && !empty($this->request->post['payment_twocheckout_cplus_display'])) {
			$data['payment_twocheckout_cplus_display'] = $this->request->post['payment_twocheckout_cplus_display'];
		} else {
			$data['payment_twocheckout_cplus_display'] = $this->config->get('payment_twocheckout_cplus_display');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_test']) && !empty($this->request->post['payment_twocheckout_cplus_test'])) {
			$data['payment_twocheckout_cplus_test'] = $this->request->post['payment_twocheckout_cplus_test'];
		} else {
			$data['payment_twocheckout_cplus_test'] = $this->config->get('payment_twocheckout_cplus_test');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_total']) && !empty($this->request->post['payment_twocheckout_cplus_total'])) {
			$data['payment_twocheckout_cplus_total'] = $this->request->post['payment_twocheckout_cplus_total'];
		} else {
			$data['payment_twocheckout_cplus_total'] = $this->config->get('payment_twocheckout_cplus_total');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_order_status_id']) && !empty($this->request->post['payment_twocheckout_cplus_order_status_id'])) {
			$data['payment_twocheckout_cplus_order_status_id'] = $this->request->post['payment_twocheckout_cplus_order_status_id'];
		} else {
			$data['payment_twocheckout_cplus_order_status_id'] = $this->config->get('payment_twocheckout_cplus_order_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_pending_status_id'])&& !empty($this->request->post['payment_twocheckout_cplus_pending_status_id'])) {
			$data['payment_twocheckout_cplus_pending_status_id'] = $this->request->post['payment_twocheckout_cplus_pending_status_id'];
		} else {
			$data['payment_twocheckout_cplus_pending_status_id'] = $this->config->get('payment_twocheckout_cplus_pending_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_processing_status_id'])&& !empty($this->request->post['payment_twocheckout_cplus_processing_status_id'])) {
			$data['payment_twocheckout_cplus_processing_status_id'] = $this->request->post['payment_twocheckout_cplus_processing_status_id'];
		} else {
			$data['payment_twocheckout_cplus_processing_status_id'] = $this->config->get('payment_twocheckout_cplus_processing_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_canceled_status_id'])&& !empty($this->request->post['payment_twocheckout_cplus_canceled_status_id'])) {
			$data['payment_twocheckout_cplus_canceled_status_id'] = $this->request->post['payment_twocheckout_cplus_canceled_status_id'];
		} else {
			$data['payment_twocheckout_cplus_canceled_status_id'] = $this->config->get('payment_twocheckout_cplus_canceled_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_failed_status_id'])&& !empty($this->request->post['payment_twocheckout_cplus_failed_status_id'])) {
			$data['payment_twocheckout_cplus_failed_status_id'] = $this->request->post['payment_twocheckout_cplus_failed_status_id'];
		} else {
			$data['payment_twocheckout_cplus_failed_status_id'] = $this->config->get('payment_twocheckout_cplus_failed_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_chargeback_status_id'])&& !empty($this->request->post['payment_twocheckout_cplus_chargeback_status_id'])) {
			$data['payment_twocheckout_cplus_chargeback_status_id'] = $this->request->post['payment_twocheckout_cplus_chargeback_status_id'];
		} else {
			$data['payment_twocheckout_cplus_chargeback_status_id'] = $this->config->get('payment_twocheckout_cplus_chargeback_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_refunded_status_id'])&& !empty($this->request->post['payment_twocheckout_cplus_refunded_status_id'])) {
			$data['payment_twocheckout_cplus_refunded_status_id'] = $this->request->post['payment_twocheckout_cplus_refunded_status_id'];
		} else {
			$data['payment_twocheckout_cplus_refunded_status_id'] = $this->config->get('payment_twocheckout_cplus_refunded_status_id');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_geo_zone_id']) && !empty($this->request->post['payment_twocheckout_cplus_geo_zone_id'])) {
			$data['payment_twocheckout_cplus_geo_zone_id'] = $this->request->post['payment_twocheckout_cplus_geo_zone_id'];
		} else {
			$data['payment_twocheckout_cplus_geo_zone_id'] = $this->config->get('payment_twocheckout_cplus_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_twocheckout_cplus_status']) && !empty($this->request->post['payment_twocheckout_cplus_status'])) {
			$data['payment_twocheckout_cplus_status'] = $this->request->post['payment_twocheckout_cplus_status'];
		} else {
			$data['payment_twocheckout_cplus_status'] = $this->config->get('payment_twocheckout_cplus_status');
		}

		if (isset($this->request->post['payment_twocheckout_cplus_sort_order']) && !empty($this->request->post['payment_twocheckout_cplus_sort_order'])) {
			$data['payment_twocheckout_cplus_sort_order'] = $this->request->post['payment_twocheckout_cplus_sort_order'];
		} else {
			$data['payment_twocheckout_cplus_sort_order'] = $this->config->get('payment_twocheckout_cplus_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/twocheckout_cplus', $data));
	}

	/**
	 * @return bool
	 */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/twocheckout_cplus')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if (!$this->request->post['payment_twocheckout_cplus_account']) {
			$this->error['account'] = $this->language->get('error_account');
		}

		if (!$this->request->post['payment_twocheckout_cplus_secret_key']) {
			$this->error['secret_key'] = $this->language->get('error_secret_key');
		}

		if (!$this->request->post['payment_twocheckout_cplus_secret_word']) {
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
		$this->load->language('extension/payment/twocheckout_cplus');
		$this->load->model('sale/order');
		$order = $this->model_sale_order->getOrder($this->request->get['order_id']);
		if ($this->config->get('payment_twocheckout_cplus_status') && $order['payment_code'] === 'twocheckout_cplus') {
			$this->load->model('extension/payment/twocheckout_cplus');

			$data['text_refund'] = $this->language->get('text_refund');
			$data['text_total_amount_refund'] = $this->language->get('text_total_amount_refund');
			$data['text_refund_final'] = $this->language->get('text_refund_final');
			$data['order'] = $order;
			$data['order_total'] = $this->currency->format($order['total'], $order['currency_code'],'', false);
			$data['transaction'] = $this->model_extension_payment_twocheckout_cplus->getTransactionByOrderId($order['order_id']);
			$data['user_token'] = $this->session->data['user_token'];
			$data['action'] = $this->url->link('extension/payment/twocheckout_cplus/refund', 'user_token=' . $this->session->data['user_token'], true);

			return $this->load->view('extension/payment/twocheckout_cplus_order', $data);
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
			$this->load->model('extension/payment/twocheckout_cplus');
			$response = $this->model_extension_payment_twocheckout_cplus->refund(
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
		$this->load->model('extension/payment/twocheckout_cplus');
		$this->model_extension_payment_twocheckout_cplus->install();

	}

	/**
	 * uninstall the module
	 */
	public function uninstall()
	{
		$this->load->model('extension/payment/twocheckout_cplus');
		$this->model_extension_payment_twocheckout_cplus->uninstall();
	}
}

?>
