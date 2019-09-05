<?php 

require_once(Mage::getBaseDir('lib') . '/MailChimp/MailChimp.php');
require_once(Mage::getBaseDir('lib') . '/MailChimp/MailChimp3.php');

class Cammino_Mailchimp_Model_Ecommerce extends Mage_Core_Model_Abstract {

	private $_token, $_enabled;

	protected function _construct() {
		$this->_enabled  = Mage::getStoreConfig("newsletter/mailchimp/ecommerce");
		$this->_token    = Mage::getStoreConfig("newsletter/mailchimp/token");
		$this->_store_id = Mage::getStoreConfig("newsletter/mailchimp/store_id");
		$this->_list_id  = Mage::getStoreConfig("newsletter/mailchimp/list_id");
		$this->_mailchimp  = new MailChimp3($this->_token);
		$this->store();
	}

	public function store() {
		// se a loja não estiver vinculada com uma lista, faz o vínculo e muda valor da config do admin
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
		if ($this->_enabled && $this->_store_id) {
			try {
				$this->handleProduct($quote->getAllItems());				
				$addQuote = $this->getQuote($quote);
				$callResultAddQuote = $this->_mailchimp->post('ecommerce/stores/' . $this->_store_id . '/carts', $addQuote);
			} catch (Exception $e) {
				var_dump($e->getMessage()); die;
			}
		}
	}

	public function order($orderId) {
		if ($this->_enabled && $this->_store_id) {
			try {
				// $request3 = $mailchimp->get('ecommerce/stores', []);
				// var_dump($request3);die;
				// $request4 = $mailchimp->get('ecommerce/stores/vital-atman/products', []);
				// var_dump($request4);die;
				// $request4	 = $this->postProducts($orderId);
				// $callResult  = $mailchimp->post('ecommerce/stores/vital-atman/products', $request4);
				// var_dump($callResult);die;
				$order 	  = Mage::getModel('sales/order')->loadByIncrementId($orderId);
				$this->handleProduct($order->getAllVisibleItems());				
				
				$addOrder	 = $this->getOrder($order);
				Mage::log($addOrder, null, 'mailchimp-ecommerce-api.log');
				$callResultAddOrder = $this->_mailchimp->post('ecommerce/stores/' . $this->_store_id . '/orders', $addOrder);
				Mage::log($callResultAddOrder, null, 'mailchimp-ecommerce-api.log');
				
			} catch (Exception $e) {
				var_dump($e->getMessage()); die;
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
		$categoryName = $this->getProductCategories($item);

		if ($categoryName) {
			$data["type"] = $categoryName;
			$data["vendor"] = $data["type"];
		}

		$result = array(
			'id' => $item->getProductId(), 
			'title' => $item->getName(),
			'vendor' => $data["vendor"],
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

	protected function getProductCategories($product)
    {
        $categoryIds = $product->getCategoryIds();
        $categoryNames = array();
		$categoryName = null;

        if (is_array($categoryIds) && count($categoryIds)) {
            $collection = Mage::getModel('catalog/category')->getCollection();
            $collection->addAttributeToSelect(array('name'))
                ->addAttributeToFilter('is_active', array('eq' => '1'))
                ->addAttributeToFilter('entity_id', array('in' => $categoryIds))
                ->addAttributeToSort('level', 'asc');

            foreach ($collection as $category) {
                $categoryNames[] = $category->getName();
			}

            $categoryName = (count($categoryNames)) ? implode(" - ", $categoryNames) : 'None';
		}

        return $categoryName;
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
}