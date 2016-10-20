<?php
/**
 * Class created to verify orders and generate invoice.
 **/
class Cammino_Clearsale_Model_Observer_CronInvoice extends Varien_Object
{
    public function createInvoice()
    {
        $this->verifyClearSaleStatus();
    }


    /**
     * Method returns config of automatic invoice is active or not.
     *
     * @return 1 | 0 - 1 = active, 0 = inactive
     **/
    private function getEnableAutomaticInvoice()
    {
        /**
         * Get the config data
         */
        $enabled = Mage::getStoreConfig("payment_services/clearsale_standard/activeautomaticinvoice");
        
        return $enabled;
    }


    /**
     * Method returns payments methods released of automatic invoice
     *
     * @return false | Array
     **/
    private function getAutomaticInvoiceMethods()
    {
        /**
         * Get the config data
         */
        $methods = Mage::getStoreConfig("payment_services/clearsale_standard/automaticinvoicemethods");
        
        if (!$methods)
            return false;
        
        $methods = explode("," , $methods);

        return $methods;
    }


    protected function getOrders()
    {

        $methodsQuery = '';

        /**
         * Get the resource model
         */
        $resource = Mage::getSingleton('core/resource');

        /**
         * Retrieve the read connection
         */
        $readConnection = $resource->getConnection('core_read');

        $methods = $this->getAutomaticInvoiceMethods();

        if (is_array($methods)) {
            $methodsQuery = 'AND ( ';

            for ($i=0; $i < count($methods); $i++) { 

                if ($i > 0)
                    $methodsQuery .= "OR ";

                $methodsQuery .= "`sales_flat_order_payment`.`method` = '{$methods[$i]}'";
            }

            $methodsQuery .= ')';
        }


        $query = "SELECT `sales_flat_order`.`entity_id`,  `sales_flat_order_payment`.`method`, `sales_flat_order_payment`.`additional_data` FROM `sales_flat_order`
                  LEFT JOIN `sales_flat_order_payment` ON `sales_flat_order`.`entity_id` = `sales_flat_order_payment`.`parent_id`
                  LEFT JOIN `sales_flat_invoice` ON `sales_flat_invoice`.`order_id` = `sales_flat_order`.`entity_id`
                  WHERE `sales_flat_order`.`status` = 'pending_payment' {$methodsQuery} AND `sales_flat_order`.`created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND `sales_flat_invoice`.`entity_id` IS NULL";
        
        /**
         * Execute the query and store the results in $results
         */
        return $readConnection->fetchAll($query);
    }

    /**
     * Verify status of clearsale and create a new invoice.
     * @return void
     **/

    private function verifyClearSaleStatus()
    {
        $orders = $this->getOrders();

        if (!count($orders))
            return false;

        $clearSaleModel = Mage::getModel('cammino_clearsale/standard');
        
        foreach ($orders as $order) {
            $mageOrder   = Mage::getModel('sales/order')->load($order['entity_id']);
            $magePayment = $mageOrder->getPayment();

            if ($order['additional_data']) {
                $addData = unserialize($order['additional_data']);
                
                if ( isset($addData["clearsale"]) ) {
                   if (($addData["clearsale"] != "exported") &&
                        ($addData["clearsale"] != "approved") &&
                        ($addData["clearsale"] != "disapproved")) {

                        $xml = $clearSaleModel->sendOrder($mageOrder);
                        $addData["clearsale"] = "exported";
                        $magePayment->setAdditionalData(serialize($addData))->save();
                    } 
                }
            }

            $statusXml = $clearSaleModel->getOrderStatusXml($mageOrder);
            $statusXml = $clearSaleModel->clearXmlResponse($statusXml);
            $statusXmlObj = simplexml_load_string($statusXml);

            $status = strval($statusXmlObj->Orders->Order->Status);

            switch ($status) {
                case 'APA':
                case 'APM':
                    $this->generateInvoice($mageOrder);
                    break;
            }
        }
    }

    /**
     * Create a new invoice to a order.
     * @param $order - Order data
     * @return false | void - if error returned false.
     **/
    private function generateInvoice($order)
    {
        try {
            
            if(!$order->canInvoice()) {
                return false;
            }

            
            /**
             * config invoice
             **/
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            
            $invoice->register();
            $invoice->setEmailSent(true);
            $invoice->getOrder()->setCustomerNoteNotify(1);
            $invoice->getOrder()->setIsInProcess(true);
            
            $order->addStatusHistoryComment('Fatura automática pela ClearSale.', false);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
            
            try {
                // send email to customer.
                $invoice->sendEmail(1, '');    
            } catch (Exception $e) {}
            
            
            /**
             * save order with invoice.
             **/
            $order->save();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);      
           
        } 
       catch (Exception $e) {
            // $order->addStatusHistoryComment('Cron Invoicer1: Exception occurred during automaticallyInvoiceShipCompleteOrder action. Exception message: '.$e->getMessage(), false);
            return false;
        }
    }

}