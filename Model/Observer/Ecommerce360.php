<?php 
class Cammino_Mailchimp_Model_Observer_Ecommerce360 extends Varien_Object
{
	public function addMcOrder(Varien_Event_Observer $observer)
	{
		
		$block = Mage::app()->getFrontController()->getAction()->getLayout()->createBlock("mailchimp/ecommerce360");

		$blockContent = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('content');

		if ($blockContent) {
			$blockContent->append($block);
		}
	}
}