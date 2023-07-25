<?php 

require_once(Mage::getBaseDir('lib') . '/MailChimp/MailChimp3.php');

class Cammino_Mailchimp_Model_Ecommerce extends Mage_Core_Model_Abstract {

	private $_token, $_enabled;

	protected function _construct() {
		$this->_enabled    = Mage::getStoreConfig("newsletter/mailchimp/ecommerce");
		$this->_token      = Mage::getStoreConfig("newsletter/mailchimp/token");
		$this->_store_id   = Mage::getStoreConfig("newsletter/mailchimp/store_id");
		$this->_list_id    = Mage::getStoreConfig("newsletter/mailchimp/list_id");
		$this->_debug      = Mage::getStoreConfig("newsletter/mailchimp/debug");
		$this->_mailchimp  = new MailChimp3($this->_token);
		$this->_initial_id = (int)Mage::getStoreConfig("newsletter/mailchimp/initial_id");
		$this->store();
	}

	public function store() {
		if (!Mage::getStoreConfig("newsletter/mailchimp/store_linked_to_list")) {
			$params = array(
				  "id" => $this->_store_id,
			      "list_id" => $this->_list_id,
			      "name" => Mage::app()->getStore()->getName(),
			      "currency_code" => "BRL"
			);
			$request = $this->_mailchimp->post('ecommerce/stores', $params);
			
			Mage::getModel('core/config')->saveConfig("newsletter/mailchimp/store_linked_to_list", 1);
		}
	}
	public function cart($quote) {
		$this->log('New cart sent.');

		if ($this->_enabled && $this->_store_id) {
			try {
				$this->handleProduct($quote->getAllItems());				
				$addQuote = $this->getQuote($quote);
				$callResultAddQuote = $this->_mailchimp->post('ecommerce/stores/' . $this->_store_id . '/carts', $addQuote);
				$this->log($callResultAddQuote);

				if(!Mage::getSingleton('customer/session')->isLoggedIn()) {
					$customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
					$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
					if ($subscriber->getId()) {
						$mailchimp  = new MailChimp3(Mage::getStoreConfig("newsletter/mailchimp/token"));
						$list = Mage::getStoreConfig("newsletter/mailchimp/list_id");				
						$nameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/name_merge_var");
						$lastNameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/last_name_merge_var");
						$genderNameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/gender_merge_var");
						$groupNameMergeVar = Mage::getStoreConfig("newsletter/mailchimp/group_name_merge_var");
						$birthdayMergeVar = Mage::getStoreConfig("newsletter/mailchimp/birthday_merge_var");
						$mergeVars = array();
						if (!empty($nameMergeVar)) {
							$mergeVars[$nameMergeVar]     = $customer->getFirstname();
						}
						if (!empty($lastNameMergeVar)) {
							$mergeVars[$lastNameMergeVar] = $customer->getLastname();
						}
						if (!empty($genderNameMergeVar)) {
							$gender = $customer->getResource()->getAttribute('gender')->getSource()->getOptionText($customer->getData('gender'));
							if($gender == "masculino" || $gender == "male" || $gender == "homem") {
								$gender = "M";
							} elseif($gender == "feminino" || $gender == "female" || $gender == "mulher") {
								$gender = "F";
							}
							$mergeVars[$genderNameMergeVar] = $gender;
						}
						if (!empty($groupNameMergeVar)) {					
							$group = $customer->getGroupId();
							$mergeVars[$groupNameMergeVar] = Mage::getModel('customer/group')->load($group)->getCustomerGroupCode();;
						}
						if (!empty($birthdayMergeVar)) {
							$mergeVars[$birthdayMergeVar] = $customer->getDob();
						}
						$params = array(
							'email_address' => $customer->getEmail(),
							'status_if_new' => 'subscribed',
							'email_type' => 'html',
							'status' => 'subscribed',
							'merge_fields' => $mergeVars
						);
						Mage::log('PARAMS: ' . json_encode($params, true), null, 'mailchimp_subscriber3.log');
						$callResult = $mailchimp->put('/lists' . '/' . $list . '/members' . '/' . md5($customer->getEmail()), $params);
						Mage::log('RESULT: ' . json_encode($callResult, true), null, 'mailchimp_subscriber3.log');
					}
				}

				if ($callResultAddQuote['title'] = 'Cart Already Exists') {
					$updateQuote = array(
						'checkout_url' => Mage::getUrl('checkout/cart'),
						'lines' => $addQuote['lines']
					);
					$callResultUpdateQuote = $this->_mailchimp->patch('ecommerce/stores/' . $this->_store_id . '/carts/'. $addQuote['id'], $updateQuote);
					$this->log($callResultUpdateQuote);				
				}

			} catch (Exception $e) {
				Mage::log($e->getMessage(), null, 'mailchimp.log');
			}
		}
	}

	public function order($orderId) {

		$this->log('New order sent.');

		if ($this->_enabled && $this->_store_id) {
			try {
				$order 	  = Mage::getModel('sales/order')->loadByIncrementId($orderId);
				$this->handleProduct($order->getAllVisibleItems());				
				
				$addOrder	 = $this->getOrder($order);
				$callResultAddOrder = $this->_mailchimp->post('ecommerce/stores/' . $this->_store_id . '/orders', $addOrder);

				$this->log($callResultAddOrder);
				
			} catch (Exception $e) {
				Mage::log($e->getMessage(), null, 'mailchimp.log');
			}
		}
	}

	private function handleProduct($items) {
		foreach($items as $item) {
	        $productsVerification = $this->verifyProduct($item->getProductId());
	        if (!$productsVerification) {
				$addProduct = $this->postProducts($item);
				$callResult = $this->_mailchimp->post('ecommerce/stores/' . $this->_store_id . '/products', $addProduct);
			}
		}
	}

	private function getQuote($quote) {
		$customer = $this->getCustomer($quote, null);
		$products = $this->getProducts($quote->getAllItems());
		$result = array(
			'id' => $quote->getId(),
			'customer' => $customer,
			'currency_code' => 'BRL',
			'order_total' => (double)number_format($quote->getGrandTotal(), 2, '.', ''),
			'checkout_url' => Mage::getUrl('checkout/cart'),
			'lines' => $products
		);

		if (Mage::getSingleton('core/session')->getCampaignCode())
			$result['campaign_id']= Mage::getSingleton('core/session')->getCampaignCode();
		return $result;
	}

	private function verifyProduct($productId) {
		$returnGetProduct = $this->_mailchimp->get('ecommerce/stores/' . $this->_store_id . '/products/' . (string)($productId  + $this->_initial_id), []);
		return(!empty($returnGetProduct['id']));
	}

	private function getOrder($order)
	{
		$customer = $this->getCustomer(null, $order);
		$products = $this->getProducts($order->getAllVisibleItems());
		
		$result = array(
			'id' => (string)$order->getIncrementId(),
			'store_id' => $this->_store_id,
			'customer' => $customer,
			'email' => $order->getCustomerEmail(),
			'currency_code' => 'BRL',
			'order_total' => (double)number_format($order->getBaseGrandTotal(), 2, '.', ''),
			'shipping_total' => (double)number_format($order->getBaseShippingAmount(), 2, '.', ''),
			'processed_at_foreign' => $order->getCreatedAt(),
			'lines' => $products
		);
		if (Mage::getSingleton('core/session')->getCampaignCode())
			$result['campaign_id']= Mage::getSingleton('core/session')->getCampaignCode();
		return $result;
	}

	private function postProducts($item) {		
		$result = array(
			'id' => (string)($item->getProductId() + $this->_initial_id),
			'title' => $item->getName(),
			'variants' => array(
				array(
					'id' => (string)($item->getProductId() + $this->_initial_id),
					'title' => $item->getName(),
					'price' => (double)number_format($item->getBasePrice(), 2, '.', ''),
					'sku'   => $item->getSku()
				)
			), 
		);
	  	return $result;
	}

	private function getProducts($items) {
        foreach ($items as $item) {	
        	$result[] = array(
				'id' => (string)($item->getProductId() + $this->_initial_id),
				'product_id' => (string)($item->getProductId() + $this->_initial_id),
				'product_variant_id' => (string)($item->getProductId() + $this->_initial_id), 
				'quantity'  => $item->getQtyOrdered() ? (double)number_format($item->getQtyOrdered(), 0, '', ''): (double)number_format($item->getQty(), 0, '', ''),
				'price' => (double)number_format($item->getBasePrice(), 2, '.', '')
			);
        }
	  	
	  	return $result;
	}

	private function getCustomer($quote, $order = null) {
		if ($order) {
			$billingAddress = $order->getBillingAddress();
			$customer = array(
				"id" => $order->getCustomerEmail(),
				"opt_in_status" => true,
				"email_address" => $order->getCustomerEmail(), 
				"first_name" => $billingAddress->getFirstname(),
				"last_name" => $billingAddress->getLastname(),
			);
		}
		else if ($quote) {
			$customerObj = Mage::getSingleton('customer/session')->getCustomer();
			$customer = array(
				"id" => $customerObj->getEmail(),
				"opt_in_status" => true,
				"email_address" => $customerObj->getEmail(), 
				"first_name" => $customerObj->getFirstname(),
				"last_name" => $customerObj->getLastname(),
			);
		}

		return $customer;
	}

	private function log($content) {
		if ($this->_debug) {
			Mage::log($content, null, 'mailchimp.log');	
		}
	}
}