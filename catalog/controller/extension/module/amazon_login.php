<?php

class ControllerExtensionModuleAmazonLogin extends Controller {
	public function index() {
		$this->load->model('extension/payment/amazon_login_pay');

		if ($this->config->get('payment_amazon_login_pay_status') && $this->config->get('module_amazon_login_status') && !$this->customer->isLogged() && !empty($this->request->server['HTTPS'])) {
			// capital L in Amazon cookie name is required, do not alter for coding standards
			if (isset($this->request->cookie['amazon_Login_state_cache'])) {
				setcookie('amazon_Login_state_cache', '', time() - 4815162342);
			}

			$amazon_payment_js = $this->model_extension_payment_amazon_login_pay->getWidgetJs();
			
			$this->document->addScript($amazon_payment_js);

			$data['payment_amazon_login_pay_client_id'] = $this->config->get('payment_amazon_login_pay_client_id');

			$data['return_url'] = $this->url->link('extension/module/amazon_login/login', 'language=' . $this->config->get('config_language'));
			
			if ($this->config->get('payment_amazon_login_pay_test') == 'sandbox') {
				$data['payment_amazon_login_pay_test'] = true;
			}

			if ($this->config->get('module_amazon_login_button_type')) {
				$data['module_amazon_login_button_type'] = $this->config->get('module_amazon_login_button_type');
			} else {
				$data['module_amazon_login_button_type'] = 'lwa';
			}

			if ($this->config->get('module_amazon_login_button_colour')) {
				$data['module_amazon_login_button_colour'] = $this->config->get('module_amazon_login_button_colour');
			} else {
				$data['module_amazon_login_button_colour'] = 'gold';
			}

			if ($this->config->get('module_amazon_login_button_size')) {
				$data['module_amazon_login_button_size'] = $this->config->get('module_amazon_login_button_size');
			} else {
				$data['module_amazon_login_button_size'] = 'medium';
			}

			if ($this->config->get('payment_amazon_login_pay_language')) {
				$data['payment_amazon_login_pay_language'] = $this->config->get('payment_amazon_login_pay_language');
			} else {
				$data['payment_amazon_login_pay_language'] = 'en-US';
			}

			return $this->load->view('extension/module/amazon_login', $data);
		}
	}

	public function login() {
		$this->load->model('extension/payment/amazon_login_pay');
		$this->load->model('account/customer');
		$this->load->model('account/customer_group');
		$this->load->language('extension/payment/amazon_login_pay');

		unset($this->session->data['lpa']);
		unset($this->session->data['access_token']);

		if (isset($this->request->get['access_token'])) {
			$this->session->data['access_token'] = $this->request->get['access_token'];

			$user = $this->model_extension_payment_amazon_login_pay->getUserInfo($this->request->get['access_token']);
		} else {
			$user = array();
		}

		if ((array)$user) {
			if (isset($user->error)) {
				$this->model_extension_payment_amazon_login_pay->logger($user->error . ': ' . $user->error_description);

				$this->session->data['lpa']['error'] = $this->language->get('error_login');

				$this->response->redirect($this->url->link('extension/payment/amazon_login_pay/loginFailure'));
			}

			$customer_info = $this->model_account_customer->getCustomerByEmail($user->email);
			$this->model_extension_payment_amazon_login_pay->logger($user);

			if ($customer_info) {
				if ($this->validate($user->email)) {
					unset($this->session->data['guest']);

					$this->load->model('account/address');

					if ($this->config->get('config_tax_customer') == 'payment') {
						$payment_address = $this->model_account_address->getAddress($this->customer->getAddressId());

						if ($payment_address){
							$this->session->data['payment_address'] = $payment_address;
						}
					}

					if ($this->config->get('config_tax_customer') == 'shipping') {
						$shipping_address = $this->model_account_address->getAddress($this->customer->getAddressId());

						if ($shipping_address){
							$this->session->data['shipping_address'] = $shipping_address;
						}
					}

					$this->model_extension_payment_amazon_login_pay->logger('Customer logged in - ID: ' . $customer_info['customer_id'] . ', Email: ' . $customer_info['email']);
				} else {
					$this->model_extension_payment_amazon_login_pay->logger('Could not login to - ID: ' . $customer_info['customer_id'] . ', Email: ' . $customer_info['email']);

					$this->session->data['lpa']['error'] = $this->language->get('error_login');

					$this->response->redirect($this->url->link('extension/payment/amazon_login_pay/loginFailure'));
				}

				$this->response->redirect($this->url->link('account/account'));
			} else {
				$country_id = 0;
				$zone_id = 0;

				$full_name = explode(' ', $user->name);
				$last_name = array_pop($full_name);
				$first_name = implode(' ', $full_name);

				$data = array(
					'customer_group_id' => (int)$this->config->get('config_customer_group_id'),
					'firstname' => $first_name,
					'lastname' => $last_name,
					'email' => $user->email,
					'telephone' => '',
					'password' => uniqid(rand(), true),
					'company' => '',
					'address_1' => '',
					'address_2' => '',
					'city' => '',
					'postcode' => '',
					'country_id' => (int)$country_id,
					'zone_id' => (int)$zone_id,
				);

				$customer_id = $this->model_extension_payment_amazon_login_pay->addCustomer($data);

				$this->model_extension_payment_amazon_login_pay->logger('Customer ID created: ' . $customer_id);

				if ($this->validate($user->email)) {
					unset($this->session->data['guest']);

					$this->load->model('account/address');

					if ($this->config->get('config_tax_customer') == 'payment') {
						$payment_address = $this->model_account_address->getAddress($this->customer->getAddressId());
						if($payment_address){
							$this->session->data['payment_address'] = $payment_address;
						}
					}

					if ($this->config->get('config_tax_customer') == 'shipping') {
						$shipping_address = $this->model_account_address->getAddress($this->customer->getAddressId());
						if($shipping_address){
							$this->session->data['shipping_address'] = $shipping_address;
						}
					}

					$this->model_extension_payment_amazon_login_pay->logger('Customer logged in - ID: ' . $customer_id . ', Email: ' . $user->email);

					$this->response->redirect($this->url->link('account/account'));
				} else {
					$this->model_extension_payment_amazon_login_pay->logger('Could not login to - ID: ' . $customer_id . ', Email: ' . $user->email);

					$this->session->data['lpa']['error'] = $this->language->get('error_login');
					$this->response->redirect($this->url->link('extension/payment/amazon_login_pay/loginFailure'));
				}
			}
		} else {
			$this->session->data['lpa']['error'] = $this->language->get('error_login');
			$this->response->redirect($this->url->link('extension/payment/amazon_login_pay/loginFailure'));
		}
	}

	public function logout() {
		unset($this->session->data['lpa']);
		unset($this->session->data['access_token']);

		// capital L in Amazon cookie name is required, do not alter for coding standards
		if (isset($this->request->cookie['amazon_Login_state_cache'])) {
			setcookie('amazon_Login_state_cache', '', time() - 4815162342);
		}
	}

	protected function validate($email) {
		if (!$this->customer->login($email, '', true)) {
			$this->error['warning'] = $this->language->get('error_login');
		}

		$customer_info = $this->model_account_customer->getCustomerByEmail($email);

		if ($customer_info && !$customer_info['status']) {
			$this->error['warning'] = $this->language->get('error_approved');
		}

		return !$this->error;
	}

}
