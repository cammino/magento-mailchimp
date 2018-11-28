<?php 
class Cammino_Mailchimp_Model_Observer_Ecommerce extends Varien_Object
{
	public function addMcOrder(Varien_Event_Observer $observer)
	{
		Mage::log("passou pelo Observer -------- Função MCOrder", null, 'mailchimp-ecommerce-api.log');
		$block = Mage::app()->getFrontController()->getAction()->getLayout()->createBlock("mailchimp/ecommerce");

		$blockContent = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('content');

		if ($blockContent) {
			$blockContent->append($block);
		}
	}

	public function addMcCart(Varien_Event_Observer $observer)
	{
		Mage::log("passou pelo Observer -------- Função MCCart", null, 'mailchimp-ecommerce-api.log');
		$block = Mage::app()->getFrontController()->getAction()->getLayout()->createBlock("mailchimp/cart");

		$blockContent = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('content');
		
		if ($blockContent) {
			$blockContent->append($block);
		}
	}
}