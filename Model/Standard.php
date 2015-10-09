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

		// if ($addata["clearsale"] != "exported") {
		// 	$this->sendOrder($order);
		// 	$addata["clearsale"] = "exported";
		// 	$payment->setAdditionalData(serialize($addata))->save();
		// }

		$xml = '<?xml version="1.0" encoding="utf-8"?><string xmlns="http://www.clearsale.com.br/integration">&lt;?xml version="1.0" encoding="utf-16"?&gt;&lt;ClearSale&gt;&lt;Orders&gt;&lt;Order&gt;&lt;ID&gt;100002620&lt;/ID&gt;&lt;Status&gt;AMA&lt;/Status&gt;&lt;Score&gt;44.8700&lt;/Score&gt;&lt;/Order&gt;&lt;/Orders&gt;&lt;/ClearSale&gt;</string>';

		//$xml = $this->getOrderStatusXml($order);
		$xmlo = simplexml_load_string($xml);
		var_dump($xmlo->string);

		// $xml .= $this->getAnalystCommentsXml($order);

		return $xml;
	}

	public function getOrderXml($order) {

		$customerModel = Mage::getModel('customer/customer');
		$customer = $customerModel->load($order->getCustomerId());

		$xml  = $this->getHeaderXml();
		$xml .= "<ID>". $order->getRealOrderId() ."</ID>";
		$xml .= "<FingerPrint>";
		$xml .= "	<SessionID>05f57866e6f3977d5a6f9c3ae3954051</SessionID>"; // TODO
		$xml .= "</FingerPrint>";
		$xml .= "<Date>". date('Y-m-d\TH:i:s', strtotime($order->getCreatedAt())) ."</Date>";
		$xml .= "<Email>". $customer->getEmail() ."</Email>";
		$xml .= "<B2B_B2C>B2C</B2B_B2C>"; // TODO
		$xml .= "<ShippingPrice>". number_format(floatval($order->getShippingAmount()), 4, ".", "") ."</ShippingPrice>";
		$xml .= "<TotalItems>". number_format(floatval($order->getSubtotal()), 4, ".", "") ."</TotalItems>";
		$xml .= "<TotalOrder>". number_format(floatval($order->getGrandTotal()), 4, ".", "") ."</TotalOrder>";
		$xml .= "<QtyInstallments>1</QtyInstallments>"; // TODO
		// $xml .= "<DeliveryTimeCD></DeliveryTimeCD>";
		$xml .= "<QtyItems>". intval($order->getTotalQtyOrdered()) ."</QtyItems>";
		// $xml .= "<QtyPaymentTypes></QtyPaymentTypes>";
		$xml .= "<IP>". $order->getRemoteIp() ."</IP>";
		// $xml .= "<Gift></Gift>";
		// $xml .= "<GiftMessage></GiftMessage>";
		// $xml .= "<Obs></Obs>";
		// $xml .= "<Status></Status>";
		// $xml .= "<Reanalise></Reanalise>";
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
		$xml .= $this->getFooterXml();
		return $xml;
	}

	public function getBillingXml($order, $customer) {

		$billingAddress = $order->getBillingAddress();
		$billingName = $billingAddress->getFirstname() . " " . $billingAddress->getMiddlename() . " " . $billingAddress->getLastname();
		$billingName = trim(str_replace("  ", " ", $billingName));
		$billingPhone = preg_replace('/[^0-9]/', '', $billingAddress->getTelephone());

		$xml  = "<BillingData>";
		$xml .= "	<ID>". $order->getCustomerId() ."</ID>";
		$xml .= "	<Type>1</Type>"; // TODO
		$xml .= "	<LegalDocument1>". preg_replace('/[^0-9]/', '', $customer->getTaxvat()) ."</LegalDocument1>";
		// $xml .= "	<LegalDocument2></LegalDocument2>";
		$xml .= "	<Name>". $billingName ."</Name>";
		// $xml .= "	<BirthDate></BirthDate>";
		$xml .= "	<Email>". $customer->getEmail() ."</Email>";
		// $xml .= "	<Gender></Gender>";
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

		$xml  = "<Payments>";
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
			$url = 'http://homologacao.clearsale.com.br/integracaov2/Service.asmx';
		} else {
    		$url = 'https://integracao.clearsale.com.br/service.asmx';
		}

    	return $url;
	}

	public function sendOrder($order)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "/SendOrders";
		$xml = $this->getOrderXml($order);
		$data = "entityCode=" . urlencode($entityCode) . "&xml=" . urlencode($xml);
		return html_entity_decode($this->postData($url, $data));
	}

	public function getOrderStatusXml($order)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "/GetOrderStatus";
		$data = "entityCode=" . urlencode($entityCode) . "&orderID=" . urlencode($order->getRealOrderId());
		return html_entity_decode($this->postData($url, $data));
	}

	public function getAnalystCommentsXml($order)
	{
		$entityCode = Mage::getStoreConfig("payment_services/clearsale_standard/entity_code");
		$url = $this->getBaseUrl() . "/GetAnalystComments";
		$data = "entityCode=" . urlencode($entityCode) . "&orderID=" . urlencode($order->getRealOrderId()) . "&getAll=True";
		return html_entity_decode($this->postData($url, $data));
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

	    return $returnString;
	}

}
?>