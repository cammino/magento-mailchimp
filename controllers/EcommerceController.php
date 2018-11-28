<?php 
class Cammino_Mailchimp_EcommerceController extends Mage_Core_Controller_Front_Action {
	
	public function orderAction() {
		Mage::log("passou pelo Controller -------- Função order", null, 'mailchimp-ecommerce-api.log');
		
		$session 	  = Mage::getSingleton('checkout/session');
		$ecommerce    = Mage::getModel('mailchimp/ecommerce');
		$orderId 	  = $this->getRequest()->getParam("id");
		
		if(!$orderId) {
			$orderId = $session->getLastRealOrderId();
		}
		Mage::log("id: " . $orderId, null, 'mailchimp-ecommerce-api.log');
		$ecommerce->order($orderId);
	}

	public function cartAction() {
		$ecommerce  = Mage::getModel('mailchimp/ecommerce');
		$quote 	    = Mage::getSingleton('checkout/session')->getQuote();
		$ecommerce->cart($quote);
	}
}