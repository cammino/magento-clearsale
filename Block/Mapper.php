<?php 
class Cammino_Clearsale_Block_Mapper extends Mage_Core_Block_Template {

    protected function _toHtml() {

        $sessionId = Mage::getSingleton("core/session")->getEncryptedSessionId();
        $clearSaleSessionId = Mage::getSingleton('core/session')->getClearSaleSessionId();
        $path   = Mage::app()->getRequest()->getOriginalPathInfo();
        $script = "";


        if (($clearSaleSessionId == null) || !isset($clearSaleSessionId) || (strval($clearSaleSessionId) == "")) {
            Mage::getSingleton('core/session')->setClearSaleSessionId($sessionId);
            $clearSaleSessionId = $sessionId;
        }

        if (strpos($path, 'checkout') !== false) {
            $fingeprint = Mage::getStoreConfig("payment_services/clearsale_standard/app_fingeprint");
            $script =   "<script>
                        (function (a, b, c, d, e, f, g) {
                        a['CsdpObject'] = e; a[e] = a[e] || function () {
                        (a[e].q = a[e].q || []).push(arguments)
                        }, a[e].l = 1 * new Date(); f = b.createElement(c),
                        g = b.getElementsByTagName(c)[0]; f.async = 1; f.src = d; g.parentNode.insertBefore(f, g)
                        })(window, document, 'script', '//device.clearsale.com.br/p/fp.js', 'csdp');
                        csdp('app', '". $fingeprint ."');
                        csdp('sessionid', '".$clearSaleSessionId."');
                        </script>";
        }

        return $script;
    }

}