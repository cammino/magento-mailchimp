<?php 
class Cammino_Mailchimp_Model_Observer_Ecommerce extends Varien_Object
{
	public function sendMcOrder(Varien_Event_Observer $observer)
	{
		$block = Mage::app()->getFrontController()->getAction()->getLayout()->createBlock("mailchimp/ecommerce");

		$blockContent = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('content');

		if ($blockContent) {
			$blockContent->append($block);
		}
	}

	public function sendMcCart(Varien_Event_Observer $observer)
	{
		$block = Mage::app()->getFrontController()->getAction()->getLayout()->createBlock("mailchimp/cart");

		$blockContent = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('content');
	
		if ($blockContent) {
			$blockContent->append($block);
		}
		$block->toHtml();
	}

	
}