<?php
/*
Copyright 2014 Cammino Comunicação Online Ltda ME

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
class Cammino_Clearsale_Model_Gateway {

	public function exportOrder($order){

        try {
			$items = $order->getAllItems();
			$payment = $order->getPayment();
			$customerModel = Mage::getModel('customer/customer');
			$customer = $customerModel->load($order->getCustomerId());
			$billingAddress = $order->getBillingAddress();
			$shippingAddress = $order->getShippingAddress();

			$billingName = $billingAddress->getFirstname() . " " . $billingAddress->getMiddlename() . " " . $billingAddress->getLastname();
			$billingName = trim(str_replace("  ", " ", $billingName));
			$billingCountry = Mage::getModel('directory/country')->loadByCode($billingAddress->getCountry());
			$billingPhone = preg_replace('/[^0-9]/', '', $billingAddress->getTelephone());

			$shippingName = $shippingAddress->getFirstname() . " " . $shippingAddress->getMiddlename() . " " . $shippingAddress->getLastname();
			$shippingName = trim(str_replace("  ", " ", $shippingName));
			$shippingCountry = Mage::getModel('directory/country')->loadByCode($shippingAddress->getCountry());
			$shippingPhone = preg_replace('/[^0-9]/', '', $shippingAddress->getTelephone());

			$paymentType = 0;
			$creditcardBrand = 0;

			$creditcardMethods = explode(",", Mage::getStoreConfig("payment_services/clearsale/credicardmethod"));
			$boletoMethods = explode(",", Mage::getStoreConfig("payment_services/clearsale/boletomethod"));

			if (in_array($payment->getMethodInstance()->getCode(), $creditcardMethods)) {
				$paymentType = 1;
				$creditcardBrand = 4;
				$paymentData = $payment->getData("additional_data");

				if (strripos($paymentData, "diners") !== false)
					$creditcardBrand = 1;

				if (strripos($paymentData, "mastercard") !== false)
					$creditcardBrand = 2;

				if (strripos($paymentData, "visa") !== false)
					$creditcardBrand = 3;

				if ((strripos($paymentData, "amex") !== false) || (strripos($paymentData, "american express") !== false))
					$creditcardBrand = 5;

				if (strripos($paymentData, "hipercard") !== false)
					$creditcardBrand = 6;

				if (strripos($paymentData, "aura") !== false)
					$creditcardBrand = 7;

				if (strripos($paymentData, "carrefour") !== false)
					$creditcardBrand = 8;
			}

			if (in_array($payment->getMethodInstance()->getCode(), $boletoMethods)) {
				$paymentType = 2;
			}

			$data = array(

				"CodigoIntegracao" => Mage::getStoreConfig("payment_services/clearsale/key"),

				"PedidoID" => $order->getRealOrderId(),
				"Data" => date('d-m-Y H:i:s', strtotime($order->getCreatedAt())),
				"IP" => $order->getRemoteIp(),

				"Total" => number_format(floatval($order->getGrandTotal()), 2, ".", ""),

				"TipoPagamento" => $paymentType,
				"TipoCartao" => $creditcardBrand,
			//	"Cartao_Bin" => "",
			//	"Cartao_Fim" => "",
			//	"Cartao_Numero_Mascarado" => "",

				"Cobranca_Nome" => $billingName,
			//	"Cobranca_Nascimento" => "",
				"Cobranca_Email" => $customer->getEmail(),
				"Cobranca_Documento" => preg_replace('/[^0-9]/', '', $customer->getTaxvat()),
				"Cobranca_Logradouro" => $billingAddress->getStreet(1),
				"Cobranca_Logradouro_Numero" => $billingAddress->getStreet(2),
				"Cobranca_Logradouro_Complemento" => $billingAddress->getStreet(4),
				"Cobranca_Bairro" => $billingAddress->getStreet(3),
				"Cobranca_Cidade" => $billingAddress->getCity(),
				"Cobranca_Estado" => $billingAddress->getRegionCode(),
				"Cobranca_CEP" => preg_replace('/[^0-9]/', '', $billingAddress->getPostcode()),
				"Cobranca_Pais" => $billingCountry->getName(),
				"Cobranca_DDD_Telefone_1" => substr($billingPhone, 0, 2),
				"Cobranca_Telefone_1" => substr($billingPhone, 2, 9),
			//	"Cobranca_DDD_Celular" => "",
			//	"Cobranca_Celular" => "",

				"Entrega_Nome" => $shippingName,
			//	"Entrega_Nascimento" => "",
			//	"Entrega_Email" => "",
			//	"Entrega_Documento" => "",
				"Entrega_Logradouro" => $shippingAddress->getStreet(1),
				"Entrega_Logradouro_Numero" => $shippingAddress->getStreet(2),
				"Entrega_Logradouro_Complemento" => $shippingAddress->getStreet(4),
				"Entrega_Bairro" => $shippingAddress->getStreet(3),
				"Entrega_Cidade" => $shippingAddress->getCity(),
				"Entrega_Estado" => $shippingAddress->getRegionCode(),
				"Entrega_CEP" => preg_replace('/[^0-9]/', '', $shippingAddress->getPostcode()),
				"Entrega_Pais" => $shippingCountry->getName(),
				"Entrega_DDD_Telefone_1" => substr($shippingPhone, 0, 2),
				"Entrega_Telefone_1" => substr($shippingPhone, 2, 9) //,
			//	"Entrega_DDD_Celular" => "",
			//	"Entrega_Celular" => "",

			);

			$itemIndex = 1;

			foreach ($items as $item) {
				$data["Item_ID_$itemIndex"] = $item->getSku();
				$data["Item_Nome_$itemIndex"] = $item->getName();
				$data["Item_Qtd_$itemIndex"] = intval($item->getQtyOrdered());
				$data["Item_Valor_$itemIndex"] = number_format(floatval($item->getPrice()), 2, ".", "");
				// $data["Item_Categoria_$itemIndex"] = "";
				$itemIndex++;
			}

			$returnString = $this->postData($data);

        } catch (Exception $e) {
            Mage::logException($e);
        }
	}

	public function getScoreUrl($order)
	{
		$payment = $order->getPayment();
		$addata = unserialize($payment->getData("additional_data"));

		if ($addata["clearsale"] != "exported") {
			$this->exportOrder($order);
			$addata["clearsale"] = "exported";
			$payment->setAdditionalData(serialize($addata))->save();
		}

		$url = $this->getBaseUrl() . "?codigoIntegracao=". Mage::getStoreConfig("payment_services/clearsale/key") ."&PedidoID=" . $order->getRealOrderId();

		return $url;
	}

	public function getBaseUrl() {
		if (Mage::getStoreConfig("payment_services/clearsale/environment") == 'homolog'){
			$url = 'http://homolog.clearsale.com.br/start/Entrada/EnviarPedido.aspx';
		} else {
    		$url = 'http://www.clearsale.com.br/start/Entrada/EnviarPedido.aspx';
		}

    	return $url;
	}

	public function postData($data)
	{

		$url = $this->getBaseUrl();
    	$data = $this->serializeData($data);
	    $ch = curl_init();
	    
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS,  $data);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_FAILONERROR, true);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
	    
	    $returnString = curl_exec($ch);
	    curl_close($ch);

	    return $returnString;
	}

	public function serializeData($data)
	{
		$str = "";

		foreach($data as $key => $value) {
			$str .= $key . "=" . $value . "&";
		}

		return $str;
	}

}
?>