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
class Cammino_Clearsale_Block_Adminhtml_Sales_Order_View_Tab_Info extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Info {
	public function  __construct() {
		parent::__construct();
	}

	public function getPaymentHtml()
	{
		$order = $this->getOrder();

		$clearsale = Mage::getModel('cammino_clearsale/gateway');	
			$url = $clearsale->getScoreUrl($order);
	
			$exibepagto = parent::getPaymentHtml();
			$montaclearsale .= "<div style=\"margin-left:-8px;\"><iframe id=\"clearsale\" style=\"width:277px;height:96px;border:none;\" src=\"\"></iframe></div>";
			
			$botaoantifraude = '<button id="clear_sale_button" title="Verificar Score ClearSale" type="button" class="scalable" onclick="document.getElementById(\'clearsale\').src=\'' . $url . '\';document.getElementById(\'clear_sale_button\').style.display=\'none\'" style=""><span><span><span>ANÁLISE ANTI-FRAUDE CLEARSALE</span></span></span></button>';
					
	
			return $exibepagto.$montaclearsale.$botaoantifraude;
	}
}
