<?php 
class Cammino_Clearsale_Model_Observer extends Varien_Object
{
	public function setOrderAsReturned(Varien_Event_Observer $observer)
	{
		$invoice = $observer->getInvoice();
		$order = $invoice->getOrder();

		if (intval(Mage::getStoreConfig("payment_services/clearsale_standard/active")) == 1) {
			$clearsale = Mage::getModel('cammino_clearsale/standard');	
			$xml = $clearsale->setOrderAsReturned($order);
		}
	}

	public function setSessionId(Varien_Event_Observer $observer)
	{
		try {
			$event = $observer->getEvent();
			$order = $event->getOrder();
			$sessionId = Mage::getSingleton("core/session")->getEncryptedSessionId();
			$clearsale = Mage::getModel('cammino_clearsale/standard');
			$clearsale->setSessionId($order, $sessionId);
		} catch(Exception $ex) {
		}
	}
}