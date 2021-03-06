<?php
class Picpay_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{
    const ONPAGE_MODE   = 1;
    const IFRAME_MODE   = 2;
    const REDIRECT_MODE = 3;

    const XML_PATH_SYSTEM_CONFIG    = "payment/picpay_standard";
    const SUCCESS_PATH_URL          = "checkout/onepage/success";
    const SUCCESS_IFRAME_PATH_URL   = "picpay/standard/success";

    const PHTML_SUCCESS_PATH_ONPAGE = "picpay/success.qrcode.phtml";
    const PHTML_SUCCESS_PATH_IFRAME = "picpay/success.iframe.phtml";

    const DEFAULT_QRCODE_WIDTH      = 150;
    const DEFAULT_IFRAME_HEIGHT     = 300;

    /**
     * Store
     * @var bool|Mage_Core_Model_Store
     */
    protected $_store = false;

    /**
     * Construtor
     */
    public function __construct()
    {
        if(is_null($this->_store)) {
            $this->_store = Mage::app()->getStore();
        }
    }

    /**
     * Get config from system configs module
     *
     * @param string $path config path
     * @return string value
     */
    public function getStoreConfig($path)
    {
        return Mage::getStoreConfig( self::XML_PATH_SYSTEM_CONFIG . '/' . $path, $this->_store );
    }

    /**
     * Get object store
     */
    public function getStore()
    {
        if($this->_store) {
            return $this->_store;
        }

        $this->_store = Mage::app()->getStore();

        return $this->_store;
    }

    /**
     * Check if picpay payment is enabled
     *
     * @return string
     */
    public function isActive()
    {
        return $this->getStoreConfig("active");
    }

    /**
     * Get mode of checkout
     *
     * @return string
     */
    public function getCheckoutMode()
    {
        return $this->getStoreConfig("mode");
    }

    /**
     * Check if mode is On Page
     *
     * @return string
     */
    public function isOnpageMode()
    {
        return $this->getCheckoutMode() == self::ONPAGE_MODE;
    }

    /**
     * Check if mode is Iframe
     *
     * @return string
     */
    public function isIframeMode()
    {
        return $this->getCheckoutMode() == self::IFRAME_MODE;
    }

    /**
     * Check if mode is Redirect
     *
     * @return string
     */
    public function isRedirectMode()
    {
        return $this->getCheckoutMode() == self::REDIRECT_MODE;
    }

    /**
     * Get Picpay Token for API
     *
     * @return string
     */
    public function getToken()
    {
        return $this->getStoreConfig("token");
    }

    /**
     * Get Seller Token for API
     *
     * @return string
     */
    public function getSellerToken()
    {
        return $this->getStoreConfig("seller_token");
    }

    /**
     * Get qrcode width info
     *
     * @return string
     */
    public function getQrcodeInfoWidth()
    {
        $value = $this->getStoreConfig("qrcode_info_width");
        return $value ? $value : self::DEFAULT_QRCODE_WIDTH;
    }

    /**
     * Get qrcode width info
     *
     * @return string
     */
    public function getQrcodeOnpageWidth()
    {
        $value = $this->getStoreConfig("onpage_width");
        return $value ? $value : self::DEFAULT_QRCODE_WIDTH;
    }

    /**
     * Get iframe style on iframe mode
     */
    public function getIframeStyle()
    {
        $valueW = $this->getStoreConfig("iframe_width");
        $valueH = $this->getStoreConfig("iframe_height");
        $width = $valueW ? $valueW : self::DEFAULT_QRCODE_WIDTH;
        $height = $valueH ? $valueH : self::DEFAULT_IFRAME_HEIGHT;

        $style = "";
        $style .= "margin: 20px auto;";
        $style .= "width: {$width}px;";
        $style .= "height: {$height}px;";
        return $style;
    }

    /**
     * Check if notification enabled
     *
     * @return string
     */
    public function isNotificationEnabled()
    {
        return $this->getStoreConfig("notification");
    }

    /**
     * Get API url to do request to API
     *
     * @param string $method
     * @return string
     */
    public function getApiUrl($method = "")
    {
        return $this->getStoreConfig("api_url") . $method;
    }

    /**
     * Get flat to use or not custom form html
     *
     * @return string
     */
    public function useCustomForm()
    {
        return $this->getStoreConfig("use_custom_form");
    }

    /**
     * Get custom HTML Form
     *
     * @return string
     */
    public function getCustomHtmlForm()
    {
        return $this->getStoreConfig("custom_form_html");
    }

    /**
     * Get message to show on success page
     *
     * @return string
     */
    public function getMessageOnpageSuccess()
    {
        return $this->getStoreConfig("onpage_message");
    }

    /**
     * Get message to show on callback iframe
     *
     * @return string
     */
    public function getMessageIframeCallback()
    {
        return $this->getStoreConfig("iframe_message");
    }

    /**
     * Get fields from a given entity
     *
     * @author Gabriela D'Ávila (http://davila.blog.br)
     * @param $type
     * @return mixed
     */
    public function getFields($type = 'customer_address')
    {
        $entityType = Mage::getModel('eav/config')->getEntityType($type);
        $entityTypeId = $entityType->getEntityTypeId();
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')->setEntityTypeFilter($entityTypeId);
        return $attributes->getData();
    }

    /**
     * Get URL to return to store
     */
    public function getReturnUrl()
    {
        $isSecure = Mage::app()->getStore()->isCurrentlySecure();
        $webUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, array("_secure" => $isSecure));

        if($this->isIframeMode()) {
            return $webUrl . self::SUCCESS_IFRAME_PATH_URL;
        }
        return $webUrl . self::SUCCESS_PATH_URL;
    }

    /**
     * Get URL to return to store
     */
    public function getCallbackUrl()
    {
        $isSecure = Mage::app()->getStore()->isCurrentlySecure();
        return Mage::getUrl('picpay/notification', array("_secure" => $isSecure));
    }

    /**
     * Validate a HTTP Request Authorization
     *
     * @param Zend_Controller_Request_Http $request
     * @throws Zend_Controller_Request_Exception
     * @return bool
     */
    public function validateAuth(Zend_Controller_Request_Http $request)
    {
        // Validate system config values
        if (!$this->getSellerToken()) {
            return false;
        }

        // Validate Authorization string
        if (false == ($token = $request->getHeader('x-seller-token'))) {
            return false;
        }

        return ($token == $this->getSellerToken());
    }

    /**
     * Log function to debug
     *
     * @param mixed
     */
    public function log($data)
    {
        if($this->getStoreConfig("debug")) {
            Mage::log($data, null, "picpay.log");
        }
    }

    /**
     * cURL request to PicPay API
     *
     * @param $url
     * @param $fields
     * @param string $type
     * @param integer $timeout
     * @return array
     */
    public function requestApi($url, $fields, $type = "POST", $timeout = 20)
    {
        $tokenApi = $this->getToken();

        try {
            $curl = curl_init();

            $configs = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $type,
                CURLOPT_POSTFIELDS => Mage::helper('core')->jsonEncode($fields),
                CURLOPT_HTTPHEADER => array(
                    "x-picpay-token: {$tokenApi}",
                    "cache-control: no-cache",
                    "content-type: application/json"
                ),
            );

            $this->log("JSON sent to PicPay API. URL: ".$url);
            $this->log(Mage::helper('core')->jsonEncode($fields));

            curl_setopt_array($curl, $configs);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            $this->log("JSON Response from PicPay API");
            $this->log($response);

            if ($err) {
                return array (
                    'success' => 0,
                    'return' => $err
                );
            } else {
                return array (
                    'success' => 1,
                    'return' => Mage::helper('core')->jsonDecode( trim($response) )
                );
            }
        }
        catch (Exception $e) {
            $this->log("ERROR on requesting API: " . $e->getMessage());
            Mage::logException($e);

            return array (
                'success' => 0,
                'return' => $e->getMessage()
            );
        }
    }

    /**
     * Get buyer object from Order
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getBuyer($order) {

        $buyerFirstname = $order->getCustomerFirstname();
        $buyerLastname = $order->getCustomerLastname();
        $buyerDocument = $this->_getCustomerCpfValue($order);
        $buyerEmail = $order->getCustomerEmail();
        $buyerPhone = $this->_extractPhone($order->getBillingAddress()->getData($this->_getTelephoneAttribute()));

        return array(
            "firstName" => $buyerFirstname,
            "lastName"  => $buyerLastname,
            "document"  => $buyerDocument,
            "email"     => $buyerEmail,
            "phone"     => $buyerPhone
        );
    }

    /**
     * Get telephone attribute code
     *
     * @return string
     */
    protected function _getTelephoneAttribute()
    {
        return $this->getStoreConfig("address_telephone_attribute");
    }

    /**
     * Extracts phone area code and returns phone number
     *
     * @param string $phone
     * @return string
     */
    private function _extractPhone($phone)
    {
        $digits = new Zend_Filter_Digits();
        $phone = $digits->filter($phone);
        //se começar com zero, pula o primeiro digito
        if (substr($phone, 0, 1) == '0') {
            $phone = substr($phone, 1, strlen($phone));
        }
        $originalPhone = $phone;
        $phone = preg_replace('/^(\d{2})(\d{7,9})$/', '$1-$2', $phone);
        if (is_array($phone) && count($phone) == 2) {
            list($area, $number) = explode('-', $phone);
            return implode(" ", array(
                'country' => "+55",
                'area' => (string)substr($originalPhone, 0, 2),
                'number'=> (string)substr($originalPhone, 2, 9),
            ));
        }
        return implode(" ", array(
            'country' => "+55",
            'area' => (string)substr($originalPhone, 0, 2),
            'number'=> (string)substr($originalPhone, 2, 9),
        ));
    }

    /**
     * Returns customer's CPF based on your module configuration
     *
     * @param Mage_Sales_Model_Order $order
     * @return mixed
     */
    private function _getCustomerCpfValue(Mage_Sales_Model_Order $order)
    {
        $customerCpfAttribute = $this->getStoreConfig('customer_cpf_attribute');
        $cpfAttributeCnf = explode('|', $customerCpfAttribute);
        $entity = reset($cpfAttributeCnf);
        $attrName = end($cpfAttributeCnf);
        $cpf = '';
        if ($entity && $attrName) {
            if (!$order->getCustomerIsGuest()) {
                $address = ($entity == 'customer') ? $order->getShippingAddress() : $order->getBillingAddress();
                $cpf = $address->getData($attrName);
                //if fail,try to get cpf from customer entity
                if (!$cpf) {
                    $customer = $order->getCustomer();
                    $cpf = $customer->getData($attrName);
                }
            }
            //for guest orders...
            if (!$cpf && $order->getCustomerIsGuest()) {
                $cpf = $order->getData($entity . '_' . $attrName);
            }
        }
        return $cpf;
    }

    /**
     * Update Order by Status on PicPay api
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $consult
     * @param string $authorizationId
     * @throws Mage_Core_Exception
     */
    public function updateOrder($order, $consult, $authorizationId)
    {
        $status = $consult["return"]["status"];
        switch ($status) {
            case "expired":
            case "refunded":
            case "chargeback":
                $this->_processRefundOrder($order, $authorizationId);
                break;
            case "paid":
            case "completed":
                $this->_processPaidOrder($order, $authorizationId);
            default: //created, analysis - don't change status order
                break;
        }
    }

    /**
     * Process Refund Order by Status on Picpay
     *
     * @param Mage_Sales_Model_Order $order
     * @throws Mage_Core_Exception
     */
    protected function _processRefundOrder($order)
    {
        if($order->canUnhold()) {
            $order->unhold()->save();
        }

        if($order->canCancel()) {
            $order->cancel();
            return false;
        }

        // not can cancel, need do a creditmemo
        $service = Mage::getModel('sales/service_order', $order);

        $invoices = array();
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->canRefund()) {
                $invoices[] = $invoice;
            }
        }

        if(empty($invoices)) {
            $message = $this->__("There isn't invoice to refund on order " . $order->getIncrementId());
            if($this->getStore()->isAdmin()) {
                Mage::getSingleton('adminhtml/session')->addError($message);
                return false;
            }
            else {
                Mage::throwException($message);
            }
        }

        foreach ($invoices as $invoice) {
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice)->register()->save();

            Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder())
                ->addObject($creditmemo->getInvoice())
                ->save();
        }
    }

    /**
     * Process Paid Order by Status on Picpay
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $authorizationId
     * @throws Mage_Core_Exception
     */
    protected function _processPaidOrder($order, $authorizationId)
    {
        if($order->getBaseTotalDue() <= 0) {
            return false;
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation("authorizationId", $authorizationId);
        $payment->save();

        $invoice = Mage::getModel('sales/service_order', $order)
            ->prepareInvoice();

        if (!$invoice->getTotalQty()) {
            $message = $this->__("Cannot create an invoice without products.");
            if($this->getStore()->isAdmin()) {
                Mage::getSingleton('adminhtml/session')->addError($message);
                return false;
            }
            else {
                Mage::throwException($message);
            }
        }

        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->getOrder()->setIsInProcess(true);

        $order->addStatusHistoryComment($this->__("Order invoiced by API notification. Authorization Id: ".$authorizationId), false);

        $invoice->pay();
        $invoice->sendEmail(true);

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($order);

        $transactionSave->save();

        /** @var Mage_Sales_Model_Order_Status $status */
        $status = Mage::getModel('sales/order_status')->loadDefaultByState("processing");
        if($status) {
            $order->setStatus($status->getStatus());
        }

        $order->save();
    }

    public function generateQrCode($dataText, $imageWidth = 200)
    {
        $svgTagId   = 'picpay-qrcode';
        $saveToFile = false;
        return QRcode::svg($dataText, $svgTagId, $saveToFile, QR_ECLEVEL_L, $imageWidth);
    }
}
