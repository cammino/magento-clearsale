<?php
class Cammino_Clearsale_Adminhtml_ClearsaleController extends Mage_Adminhtml_Controller_Action {

	protected function getOrder() {
		$orderId = $this->getRequest()->getParam("id");
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
		return $order;		
	}

	public function approveAction() {
		$order = $this->getOrder();
		$clearsale = Mage::getModel('cammino_clearsale/standard');
		$clearsale->approveOrder($order);
		$this->_getSession()->addSuccess($this->__('Pedido enviado para ClearSale como APROVADO.'));
		$this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
	}

	public function disapproveAction() {
		$order = $this->getOrder();
		$clearsale = Mage::getModel('cammino_clearsale/standard');
		$clearsale->disapproveOrder($order);
		$this->_getSession()->addSuccess($this->__('Pedido enviado para ClearSale como REPROVADO.'));
		$this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
	}

	public function reanalyzeAction() {
		$order = $this->getOrder();
		$clearsale = Mage::getModel('cammino_clearsale/standard');
		$clearsale->reanalyzeOrder($order);
		$this->_getSession()->addSuccess($this->__('Pedido enviado para reanálise na ClearSale.'));
		$this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
	}

}