<?php 
class Cammino_Mailchimp_EcommerceController extends Mage_Core_Controller_Front_Action {
	
	public function orderAction() {
		$session 	  = Mage::getSingleton('checkout/session');
		$ecommerce    = Mage::getModel('mailchimp/ecommerce');
		$orderId 	  = $this->getRequest()->getParam("id");
		
		if(!$orderId) {
			$orderId = $session->getLastRealOrderId();
		}
		$ecommerce->order($orderId);
	}

	public function cartAction() {
		$ecommerce  = Mage::getModel('mailchimp/ecommerce');
		$quote 	    = Mage::getSingleton('checkout/session')->getQuote();
		$ecommerce->cart($quote);
	}
}