<?php
class Cammino_Clearsale_Model_Source_Environment {
	
	public function toOptionArray() {
		return array(
			array(
				"value" => "production",
				"label" => " Produção"
			),
			array(
				"value" => "homolog",
				"label" => " Homologação"
			)
		);
	}
}