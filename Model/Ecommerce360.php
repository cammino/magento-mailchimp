<?php 

require_once(Mage::getBaseDir('lib') . '/MailChimp/MailChimp.php');

class Cammino_Mailchimp_Model_Ecommerce360 extends Mage_Core_Model_Abstract {

	private $_token, $_enabled;

	protected function _construct() {
		$this->_enabled = Mage::getStoreConfig("newsletter/mailchimp/ecommerce360");
		$this->_token   = Mage::getStoreConfig("newsletter/mailchimp/token");
	}

	public function send ($orderId) {
		try {

			if (intval($this->_enabled) == 1) {
				$mailchimp  = new MailChimp($this->_token);
				$request 	= $this->getOrder($orderId);
				$callResult = $mailchimp->call('ecomm/order-add', $request);
			}

		} catch (Exception $e) {}
	}

	private function getOrder($orderId)
	{
        if (empty($orderId)) {
            return;
        }
		
		// MailChimp
		$storeId = Mage::getStoreConfig("newsletter/mailchimp/store_id");
		$storeName = Mage::app()->getStore()->getFrontendName();

		// Order
		$order 	  = Mage::getModel('sales/order')->loadByIncrementId($orderId);
		$products = $this->getProducts($order);
		
		$result = array(
			'order'  => array( 
				'id' => $order->getIncrementId(),
				'email' => $order->getCustomerEmail(),
				'total' => (double)number_format($order->getBaseGrandTotal(), 2, '.', ''),
				'order_date' => $order->getCreatedAt(),
				'shipping' => (double)number_format($order->getBaseShippingAmount(), 2, '.', ''),
				'tax' => (double)number_format($order->getBaseTaxAmount(), 2, '.', ''),
				'store_id' => $storeId ? $storeId : $storeName,
				'store_name' => $storeName,
				'items' => $products
			)
		);

		return $result;
	}

	private function getProducts ($order) {
        
		$items = $order->getAllVisibleItems();
		$lastItem = end($items);
		$categoryLevel = -1;

		foreach ($items as $item) {	
			
			$prod 		 = Mage::getModel('catalog/product')->load($item->getProductId());
			$categoryIds = $prod->getCategoryIds();
			
			foreach($categoryIds as $id) {
				$category = Mage::getModel('catalog/category')->load($id);

				if ((intval($category->getLevel()) > $categoryLevel)) {
					$categoryLevel = intval($category->getLevel());
					$cat = $category;
				}
			}


			$result[] = array(
				'product_id' => (int)$item->getId(), 
				'sku' => $item->getSku(),
				'product_name' => $item->getName(),
				'category_id' => ($cat != null) ? (int)$cat->getId() : 0,
				'category_name' => ($cat != null) ? $cat->getName() : '',
				'qty'  => (double)number_format($item->getQtyOrdered(), 0, '', ''),
				'cost' => (double)number_format($item->getBasePrice(), 2, '.', '')
			);
        }
	  	
	  	return $result;
	}


}