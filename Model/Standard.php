<?php
/*
Copyright 2015 Cammino Comunicação Online Ltda ME

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.

You may obtain a copy of the License at
http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/
class Cammino_Clearsale_Model_Standard {

	public function getScoreTable($order)
	{
		$payment = $order->getPayment();
		$addata = unserialize($payment->getData("additional_data"));

		if (($addata["clearsale"] != "exported") &&
			($addata["clearsale"] != "approved") &&
			($addata["clearsale"] != "disapproved")) {

			$xml = $this->sendOrder($order);
			$addata["clearsale"] = "exported";
			$payment->setAdditionalData(serialize($addata))->save();
		}

		return $this->formatScoreTable($order);
	}

	public function formatScoreTable($order) {

		$statusXml = $this->getOrderStatusXml($order);
		$statusXml = $this->clearXmlResponse($statusXml);
		$statusXmlObj = simplexml_load_string($statusXml);

		$commentsXml = $this->getAnalystCommentsXml($order);
		$commentsXml = $this->clearXmlResponse($commentsXml);
		$commentsXmlObj = simplexml_load_string($commentsXml);

		$status = strval($statusXmlObj->Orders->Order->Status);

		$approveUrl = Mage::helper("adminhtml")->getUrl("/clearsale/approve", array('id' => $order->getIncrementId()));
		$disapproveUrl = Mage::helper("adminhtml")->getUrl("/clearsale/disapprove", array('id' => $order->getIncrementId()));
		$reanalyzeUrl = Mage::helper("adminhtml")->getUrl("/clearsale/reanalyze", array('id' => $order->getIncrementId()));

		$statusColor = "black";

		if (in_array($status, array("APA","APM"))) {
			$statusColor = "green";
		} else if (in_array($status, array("AMA","NVO"))) {
			$statusColor = "black";
		} else {
			$statusColor = "red";
		}

		$html = 	"<table cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%; border: 1px solid #d6d6d6; margin: 20px 0;\">
						<tr>
							<td colspan=\"2\" style=\"background:#6f8992; color:#fff; font-weight: bold; padding: 2px 10px;\">ClearSale</td>
						</tr>
						<tr>
							<td style=\"padding: 10px 10px 2px 10px; width:15%;\">Status:</td>
							<td style=\"padding: 10px 10px 2px 10px; font-weight: bold;  width:85%; color: ".$statusColor.";\">". $this->getLiteralStatus($status) ." (". $status .")</td>
						</tr>
						<tr>
							<td style=\"padding: 2px 10px 10px 10px;\">Score:</td>
							<td style=\"padding: 2px 10px 10px 10px; font-weight: bold;\">". $statusXmlObj->Orders->Order->Score ."</td>
						</tr>
						<tr>
							<td colspan=\"2\" style=\"padding: 2px 10px 10px 10px; border-bottom: 1px solid #d6d6d6;\">";

		if (in_array($status, array("AMA","NVO"))) {
			$html .= "			<button type=\"button\" class=\"scalable save\" onclick=\"deleteConfirm('Deseja APROVAR este pedido na ClearSale?', '".$approveUrl."');\"><span><span><span>Aprovar</span></span></span></button>
								<button type=\"button\" class=\"scalable delete\" onclick=\"deleteConfirm('Deseja REPROVAR este pedido na ClearSale?', '".$disapproveUrl."');\"><span><span><span>Reprovar</span></span></span></button>";
		}

		$html .=	"			<button type=\"button\" class=\"scalable go\" onclick=\"deleteConfirm('Deseja enviar este pedido para REANÁLISE na ClearSale?', '".$reanalyzeUrl."');\"><span><span><span>Reanalisar</span></span></span></button>
							</td>
						</tr>";

		$commentsXmlObj = simplexml_load_string($commentsXml);

		if (strval($commentsXmlObj->AnalystComments) != "") {
			foreach ($commentsXmlObj->AnalystComments as $comment) {
				$html .= 	"<tr>
								<td colspan=\"2\" style=\"padding: 10px 10px 10px 10px; border-bottom: 1px solid #d6d6d6;\"><strong>[". $comment->CreateDate . " @ ". $comment->UserName ."]</strong> " . $comment->Comments ."</td>
							</tr>";
			}
		}

		$html .= "</table>";

		return $html;
	}

	public function getLiteralStatus($value) {
		$literals = array(
			"APA" => "Aprovação Automática",
			"APM" => "Aprovação Manual",
			"RPM" => "Reprovado Sem Suspeita",
			"AMA" => "Análise manual",
			"ERR" => "Erro",
			"NVO" => "Novo",
			"SUS" => "Suspensão Manual",
			"CAN" => "Cancelado pelo Cliente",
			"FRD" => "Fraude Confirmada",
			"RPA" => "Reprovação Automática",
			"RPP" => "Reprovação Por Política"
		);
		return $literals[$value];
	}

	public function clearXmlResponse($xml) {
		$xml = str_replace("<?xml version=\"1.0\" encoding=\"utf-8\"?>", "", $xml);
		$xml = str_replace("<string xmlns=\"http://www.clearsale.com.br/integration\">", "", $xml);
		$xml = str_replace("</string>", "", $xml);
		$xml = str_replace("<?xml version=\"1.0\" encoding=\"utf-16\"?>", "", $xml);
		return $xml;
	}

	public function getOrderXml($order, $args=array()) {

		$customerModel = Mage::getModel('customer/customer');
		$customer = $customerModel->load($order->getCustomerId());

		$xml  = $this->getHeaderXml();
		$xml .= "<ID>". $order->getRealOrderId() ."</ID>";
		$xml .= "<Date>". date('Y-m-d\TH:i:s', strtotime($order->getCreatedAt())) ."</Date>";
		$xml .= "<Email>". $customer->getEmail() ."</Email>";
		$xml .= "<B2B_B2C>B2C</B2B_B2C>"; // TODO
		$xml .= "<ShippingPrice>". number_format(floatval($order->getShippingAmount()), 4, ".", "") ."</ShippingPrice>";
		$xml .= "<TotalItems>". number_format(floatval($order->getSubtotal()), 4, ".", "") ."</TotalItems>";
		$xml .= "<TotalOrder>". number_format(floatval($order->getGrandTotal()), 4, ".", "") ."</TotalOrder>";
		$xml .= "<QtyInstallments>1</QtyInstallments>"; // TODO
		$xml .= "<DeliveryTimeCD>". $order->getShippingDescription() ."</DeliveryTimeCD>";
		$xml .= "<QtyItems>". intval($order->getTotalQtyOrdered()) ."</QtyItems>";
		// $xml .= "<QtyPaymentTypes></QtyPaymentTypes>";
		$xml .= "<IP>". $order->getRemoteIp() ."</IP>";
		// $xml .= "<Gift></Gift>";
		// $xml .= "<GiftMessage></GiftMessage>";
		// $xml .= "<Obs></Obs>";
		// $xml .= "<Status></Status>";
		$xml .= "<Reanalise>". (isset($args["Reanalise"]) ? $args["Reanalise"] : "0") ."</Reanalise>";
		$xml .= "<Origin>Magento</Origin>";
		// $xml .= "<ReservationDate></ReservationDate>";
		// $xml .= "<Country></Country>";
		// $xml .= "<Nationality></Nationality>";
		// $xml .= "<Product></Product>";
		// $xml .= "<ListTypeID></ListTypeID>";
		// $xml .= "<ListID></ListID>";
		$xml .= $this->getBillingXml($order, $customer);
		$xml .= $this->getShippingXml($order, $customer);
		$xml .= $this->getPaymentXml($order);
		$xml .= $this->getItemsXml($order);
		$xml .= $this->getFooterXml();

		return $xml;
	}

	public function getBillingXml($order, $customer) {

		$billingAddress = $order->getBillingAddress();
		$billingName = $billingAddress->getFirstname() . " " . $billingAddress->getMiddlename() . " " . $billingAddress->getLastname();
		$billingName = trim(str_replace("  ", " ", $billingName));
		$billingPhone = preg_replace('/[^0-9]/', '', $billingAddress->getTelephone());
		$customerGender = $this->getCustomerGender($customer);

		$xml  = "<BillingData>";
		$xml .= "	<ID>". $order->getCustomerId() ."</ID>";
		$xml .= "	<Type>1</Type>"; // TODO
		$xml .= "	<LegalDocument1>". preg_replace('/[^0-9]/', '', $customer->getTaxvat()) ."</LegalDocument1>";
		// $xml .= "	<LegalDocument2></LegalDocument2>";
		$xml .= "	<Name>". $billingName ."</Name>";

		if ($customer->getDob() != null) {
			$xml .= "	<BirthDate>". date('Y-m-d\TH:i:s', strtotime($customer->getDob())) ."</BirthDate>";
		}

		$xml .= "	<Email>". $customer->getEmail() ."</Email>";

		if ($customerGender != "") {
			$xml .= "	<Gender>". $customerGender ."</Gender>";
		}

		$xml .= "	<Address>";
		$xml .= "		<Street>". $billingAddress->getStreet(1) ."</Street>";
		$xml .= "		<Number>". $billingAddress->getStreet(2) ."</Number>";
		$xml .= "		<Comp>". $billingAddress->getStreet(4) ."</Comp>";
		$xml .= "		<County>". $billingAddress->getStreet(3) ."</County>";
		$xml .= "		<City>". $billingAddress->getCity() ."</City>";
		$xml .= "		<State>". $billingAddress->getRegionCode() ."</State>";
		$xml .= "		<ZipCode>". preg_replace('/[^0-9]/', '', $billingAddress->getPostcode()) ."</ZipCode>";
		// $xml .= "		<Reference></Reference>";
		// $xml .= "		<County></County>";
		$xml .= "	</Address>";
		$xml .= "	<Phones>";
		$xml .= "		<Phone>";
		$xml .= "			<Type>0</Type>";
		// $xml .= "			<DDI></DDI>";
		$xml .= "			<DDD>". substr($billingPhone, 0, 2) ."</DDD>";
		$xml .= "			<Number>". substr($billingPhone, 2, 9) ."</Number>";
		// $xml .= "			<Extension></Extension>";
		$xml .= "		</Phone>";
		$xml .= "	</Phones>";
		$xml .= "</BillingData>";
		return $xml;
	}

	public function getCustomerGender($customer) {
		$customerGender = Mage::getResourceSingleton('customer/customer')->getAttribute('gender')->getSource()->getOptionText($customer->getGender());
		if (($customerGender == "Masculino") || ($customerGender == "Male")) {
			return "M";
		} else if(($customerGender == "Feminino") || ($customerGender == "Female")) {
			return "F";
		} else {
			return "";
		}
		return "";
	}

	public function getShippingXml($order, $customer) {

		$shippingAddress = $order->getShippingAddress();
		$shippingName = $shippingAddress->getFirstname() . " " . $shippingAddress->getMiddlename() . " " . $shippingAddress->getLastname();
		$shippingName = trim(str_replace("  ", " ", $shippingName));
		$shippingPhone = preg_replace('/[^0-9]/', '', $shippingAddress->getTelephone());

		$xml  = "<ShippingData>";
		$xml .= "	<ID>". $order->getCustomerId() ."</ID>";
		$xml .= "	<Type>1</Type>"; // TODO
		$xml .= "	<LegalDocument1>". preg_replace('/[^0-9]/', '', $customer->getTaxvat()) ."</LegalDocument1>";
		// $xml .= "	<LegalDocument2></LegalDocument2>";
		$xml .= "	<Name>". $shippingName ."</Name>";
		// $xml .= "	<BirthDate></BirthDate>";
		// $xml .= "	<Email></Email>";
		// $xml .= "	<Gender></Gender>";
		$xml .= "	<Address>";
		$xml .= "		<Street>". $shippingAddress->getStreet(1) ."</Street>";
		$xml .= "		<Number>". $shippingAddress->getStreet(2) ."</Number>";
		$xml .= "		<Comp>". $shippingAddress->getStreet(4) ."</Comp>";
		$xml .= "		<County>". $shippingAddress->getStreet(3) ."</County>";
		$xml .= "		<City>". $shippingAddress->getCity() ."</City>";
		$xml .= "		<State>". $shippingAddress->getRegionCode() ."</State>";
		$xml .= "		<ZipCode>".preg_replace('/[^0-9]/', '', $shippingAddress->getPostcode()) ."</ZipCode>";
		// $xml .= "		<Reference></Reference>";
		// $xml .= "		<Country></Country>";
		$xml .= "	</Address>";
		$xml .= "	<Phones>";
		$xml .= "		<Phone>";
		$xml .= "			<Type>0</Type>";
		// $xml .= "			<DDI></DDI>";
		$xml .= "			<DDD>". substr($shippingPhone, 0, 2) ."</DDD>";
		$xml .= "			<Number>". substr($shippingPhone, 2, 9) ."</Number>";
		// $xml .= "			<Extension></Extension>";
		$xml .= "		</Phone>";
		$xml .= "	</Phones>";
		$xml .= "</ShippingData>";
		return $xml;
	}

	public function getPaymentXml($order) {

		$payment = $order->getPayment();
		$paymentTypeId = $this->getPaymentTypeId($payment);
		$addata = unserialize($payment->getData("additional_data"));

		$xml .= "<FingerPrint>";
		$xml .= "	<SessionID>". $addata["clearsale_sessionid"] ."</SessionID>";
		// $xml .= "     <SessionID>". hash(rand(0, getrandmax())) ."</SessionID>";
		$xml .= "</FingerPrint>";
		$xml .= "<Payments>";
		$xml .= "	<Payment>";
		// $xml .= "		<Sequential></Sequential>";
		$xml .= "		<Date>". date('Y-m-d\TH:i:s', strtotime($order->getCreatedAt())) ."</Date>";
		$xml .= "		<Amount>". number_format(floatval($order->getGrandTotal()), 4, ".", "") ."</Amount>";
		$xml .= "		<PaymentTypeID>". $paymentTypeId ."</PaymentTypeID>";
		// $xml .= "		<QtyInstallments></QtyInstallments>";
		// $xml .= "		<Interest></Interest>";
		// $xml .= "		<InterestValue></InterestValue>";
		// $xml .= "		<CardNumber></CardNumber>";
		// $xml .= "		<CardBin></CardBin>";
		// $xml .= "		<CardEndNumber></CardEndNumber>";
		if ($paymentTypeId == 1) {
			$xml .= "		<CardType>". $this->getPaymentCardType($payment) ."</CardType>";
		}
		// $xml .= "		<CardExpirationDate></CardExpirationDate>";
		// $xml .= "		<Name></Name>";
		// $xml .= "		<LegalDocument></LegalDocument>";
		// $xml .= "		<Address>";
		// $xml .= "			<Street></Street>";
		// $xml .= "			<Number></Number>";
		// $xml .= "			<Comp></Comp>";
		// $xml .= "			<County></County>";
		// $xml .= "			<City></City>";
		// $xml .= "			<State></State>";
		// $xml .= "			<Country></Country>";
		// $xml .= "			<ZipCode></ZipCode>";
		// $xml .= "		</Address>";
		// $xml .= "		<Nsu></Nsu>";
		$xml .= "		<Currency>986</Currency>";
		$xml .= "	</Payment>";
		$xml .= "</Payments>";
		return $xml;
	}

	public function getPaymentTypeId($payment) {
		$creditcardMethods = explode(",", Mage::getStoreConfig("payment_services/clearsale_standard/credicardmethod"));
		$boletoMethods = explode(",", Mage::getStoreConfig("payment_services/clearsale_standard/boletomethod"));

		if (in_array($payment->getMethodInstance()->getCode(), $creditcardMethods)) {
			return 1;
		} else if (in_array($payment->getMethodInstance()->getCode(), $boletoMethods)) {
			return 2;
		} else {
			return 14;
		}
	}

	public function isIgnoredMethod($payment){
		$ignoreMethods = explode(",", Mage::getStoreConfig("payment_services/clearsale_standard/ignoremethods"));
		if(in_array($payment,$ignoreMethods))
			return true;
		else
			return false;
	}

	public function getPaymentCardType($payment) {
		$paymentCardType = 4;
		$paymentData = $payment->getData("additional_data");

		if (strripos(strtolower($paymentData), "diners") !== false) {
			$paymentCardType = 1;
		} else if (strripos(strtolower($paymentData), "mastercard") !== false) {
			$paymentCardType = 2;
		} else if (strripos(strtolower($paymentData), "visa") !== false) {
			$paymentCardType = 3;
		} else if ((strripos(strtolower($paymentData), "amex") !== false) || (strripos(strtolower($paymentData), "american express") !== false)) {
			$paymentCardType = 5;
		} else if (strripos(strtolower($paymentData), "hipercard") !== false) {
			$paymentCardType = 6;
		} else if (strripos(strtolower($paymentData), "aura") !== false) {
			$paymentCardType = 7;
		}

		return $paymentCardType;
	}

	public function getItemsXml($order) {

		$items = $order->getAllItems();

		$xml .= "<Items>";

		foreach ($items as $item) {
			$xml .= "	<Item>";
			$xml .= "		<ID>". $item->getSku() ."</ID>";
			$xml .= "		<Name>". $item->getName() ."</Name>";
			$xml .= "		<ItemValue>". number_format(floatval($item->getPrice()), 2, ".", "") ."</ItemValue>";
			$xml .= "		<Qty>". intval($item->getQtyOrdered()) ."</Qty>";
			// $xml .= "		<Gift></Gift>";
			// $xml .= "		<CategoryID></CategoryID>";
			// $xml .= "		<CategoryName></CategoryName>";
			$xml .= "	</Item>";
		}

		$xml .= "</Items>";
		return $xml;
	}

	public function getHeaderXml() {
		return "<ClearSale><Orders><Order>";
	}

	public function getFooterXml() {
		return "</Order></Orders></ClearSale>";
	}

	public function getBaseUrl() {
		if (Mage::getStoreConfig("payment_services/clearsale_standard/environment") == 'homolog'){
			$url = 'http://homologacao.clearsale.com.br/integracaov2/';
		} else {
    		$url = 'https://integracao.clearsale.com.br/';
		}

    	return $url;
	}

	public function sendOrder($order, $args=array())
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "Service.asmx/SendOrders";
		$xml = $this->getOrderXml($order, $args);
		$data = "entityCode=" . urlencode($entityCode) . "&xml=" . urlencode($xml);

		Mage::log($xml, null, 'clearsale.log');

		return html_entity_decode($this->postData($url, $data));
	}

	public function getOrderStatusXml($order)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "Service.asmx/GetOrderStatus";
		$data = "entityCode=" . urlencode($entityCode) . "&orderID=" . urlencode($order->getRealOrderId());
		return html_entity_decode($this->postData($url, $data));
	}

	public function getAnalystCommentsXml($order)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "Service.asmx/GetAnalystComments";
		$data = "entityCode=" . urlencode($entityCode) . "&orderID=" . urlencode($order->getRealOrderId()) . "&getAll=True";
		return html_entity_decode($this->postData($url, $data));
	}

	public function setOrderAsReturned($order)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "Service.asmx/SetOrderAsReturned";
		$data = "entityCode=" . urlencode($entityCode) . "&orderID=" . urlencode($order->getRealOrderId());
		return html_entity_decode($this->postData($url, $data));
	}

	public function updateOrderStatus($order, $status)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "ExtendedService.asmx/SetOrderAsReturned";
		$data = "entityCode=" . urlencode($entityCode) . "&orderId=" . urlencode($order->getRealOrderId()) . "&newStatusId=" . $status . "&obs=";
		return html_entity_decode($this->postData($url, $data));
	}

	public function approveOrder($order)
	{
		$xml = $this->updateOrderStatus($order, "26");
		$this->updateFlag($order, "approved");
		return $xml;
	}

	public function disapproveOrder($order)
	{
		$xml = $this->updateOrderStatus($order, "27");
		$this->updateFlag($order, "disapproved");
		return $xml;
	}

	public function reanalyzeOrder($order)
	{
		$xml = $this->sendOrder($order, array("Reanalise" => 1));
		$this->updateFlag($order, "exported");
	}

	public function updateFlag($order, $value)
	{
		$payment = $order->getPayment();
		$addata = unserialize($payment->getData("additional_data"));
		$addata["clearsale"] = $value;
		$payment->setAdditionalData(serialize($addata))->save();
	}

	public function setSessionId($order, $sessionId)
	{
		$payment = $order->getPayment();
		$addata = unserialize($payment->getData("additional_data"));
		$addata["clearsale_sessionid"] = $sessionId;
		$payment->setAdditionalData(serialize($addata))->save();
	}

	public function postData($url, $data)
	{
	    $ch = curl_init();

	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_FAILONERROR, true);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

	    $returnString = curl_exec($ch);
	    curl_close($ch);

	    Mage::log($returnString, null, 'clearsale.log');

	    return $returnString;
	}

}
?>
