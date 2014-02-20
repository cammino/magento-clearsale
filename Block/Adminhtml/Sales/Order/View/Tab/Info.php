<?php
class Cammino_Clearsale_Block_Adminhtml_Sales_Order_View_Tab_Info extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Info {
	public function  __construct() {
		parent::__construct();
	}

	public function getPaymentHtml()
	{
		$order = $this->getOrder();

		$clearsale = Mage::getModel('cammino_clearsale/gateway');	
		$url = $clearsale->getScoreUrl($order);

		$html = $this->getChildHtml('order_payment');
		$html .= "<div style=\"margin-left:-8px;\"><iframe style=\"width:277px;height:96px;border:none;\" src=\"". $url ."\"></iframe></div>";

		return $html;
	}
}