<?php 
class Cammino_Mailchimp_Ecommerce360Controller extends Mage_Core_Controller_Front_Action {
	
	public function sendAction() {
	
		$session 	  = Mage::getSingleton('checkout/session');
		$ecommerce360 = Mage::getModel('mailchimp/ecommerce360');
		$orderId 	  = $this->getRequest()->getParam("id");
		
		if(!$orderId) {
			$orderId = $session->getLastRealOrderId();
		}

		$ecommerce360->send($orderId);
	}
}