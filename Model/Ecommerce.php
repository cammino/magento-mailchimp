<?php 

require_once(Mage::getBaseDir('lib') . '/MailChimp/MailChimp3.php');

class Cammino_Mailchimp_Model_Ecommerce extends Mage_Core_Model_Abstract {

	private $_token, $_enabled;

	protected function _construct() {
		$this->_enabled  = Mage::getStoreConfig("newsletter/mailchimp/ecommerce");
		$this->_token    = Mage::getStoreConfig("newsletter/mailchimp/token");
		$this->_store_id = Mage::getStoreConfig("newsletter/mailchimp/store_id");
		$this->_list_id  = Mage::getStoreConfig("newsletter/mailchimp/list_id");
		$this->_debug    = Mage::getStoreConfig("newsletter/mailchimp/debug");
		$this->_mailchimp  = new MailChimp3($this->_token);
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
	        if ($productsVerification) {
				$addProduct = $this->postProducts($item);
				$callResult = $this->_mailchimp->post('ecommerce/stores/' . $this->_store_id . '/products', $addProduct);
			}
		}
	}

	private function getQuote($quote) {
		$customer = $this->getCustomer($quote, null);
		$products = $this->getProducts($quote->getAllItems());
		;
		$result = array(
			'id' => $quote->getId(),
			'customer' => $customer,
			'currency_code' => 'BRL',
			'order_total' => (double)number_format($quote->getGrandTotal(), 2, '.', ''),
			'lines' => $products
		);

		if (Mage::getSingleton('core/session')->getCampaignCode())
			$result['campaign_id']= Mage::getSingleton('core/session')->getCampaignCode();
		return $result;
	}

	private function verifyProduct($productId) {
		if (Mage::getStoreConfig("newsletter/mailchimp/verification_skip")) {
			return true;
		}
		$returnGetProduct = $this->_mailchimp->get('ecommerce/stores/' . $this->_store_id . '/products/' . $productId, []);
		return ($returnGetProduct['status'] == 404);		
	}

	private function getOrder($order)
	{
		$customer = $this->getCustomer(null, $order);
		$products = $this->getProducts($order->getAllVisibleItems());
		
		$result = array(
			'id' => $order->getIncrementId(),
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
			'id' => $item->getProductId(), 
			'title' => $item->getName(),
			'variants' => array(
				array(
					'id' => $item->getProductId(), 
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
				'id' => $item->getProductId(), 
				'product_id' => $item->getProductId(), 
				'product_variant_id' => $item->getProductId(), 
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