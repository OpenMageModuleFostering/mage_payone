<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License (GPL 3)
 * that is bundled with this package in the file LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Payone_Core to newer
 * versions in the future. If you wish to customize Payone_Core for your
 * needs please refer to http://www.payone.de for more information.
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Mapper
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Mapper
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */
abstract class Payone_Core_Model_Mapper_ApiRequest_Payment_Authorize_Abstract
    extends Payone_Core_Model_Mapper_ApiRequest_Payment_Abstract
{
    /**
     * @return Payone_Api_Request_Authorization_Abstract
     */
    abstract protected function getRequest();

    /**
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Payone_Api_Request_Preauthorization|Payone_Api_Request_Authorization
     */
    public function mapFromPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $this->init($payment);

        $configPayment = $this->getConfigPayment();

        $request = $this->getRequest();

        $this->beforeMapFromPayment($request);

        // Add Default Api Parameters
        $this->mapDefaultParameters($request);

        // Add Default Authorize Parameters
        $this->mapDefaultAuthorizeParameters($request);

        // PersonalData
        $personalData = $this->mapPersonalParameters();
        $request->setPersonalData($personalData);

        // ShippingData, only for non-virtual orders.
        if ($payment->getOrder()->getIsNotVirtual()) {
            $deliveryData = $this->mapDeliveryParameters();
            $request->setDeliveryData($deliveryData);
        }
        // Only add Invoiceing Parameters if enabled
        if ($configPayment->isInvoiceTransmitEnabled()) {
            $invoicing = $this->mapInvoicingParameters();
            $request->setInvoicing($invoicing);
        }

        $payment = $this->mapPaymentParameters();

        // Not every Paymentmethod has an extra Parameter Set
        if ($payment !== null) {
            $request->setPayment($payment);
        }

        $this->afterMapFromPayment($request);

        return $request;
    }

    /**
     * @param Payone_Api_Request_Authorization_Abstract $request
     */
    public function beforeMapFromPayment(Payone_Api_Request_Authorization_Abstract $request)
    {

    }

    /**
     * @param Payone_Api_Request_Authorization_Abstract $request
     */
    public function afterMapFromPayment(Payone_Api_Request_Authorization_Abstract $request)
    {

    }

    /**
     * @param Payone_Api_Request_Authorization_Abstract $request
     */
    protected function mapDefaultAuthorizeParameters(Payone_Api_Request_Authorization_Abstract $request)
    {
        $order = $this->getOrder();
        $paymentMethod = $this->getPaymentMethod();

        $request->setRequest($this->configPayment->getRequestType());
        $request->setAid($this->configPayment->getAid());
        $request->setClearingtype($this->mapClearingType($paymentMethod));
        $request->setCurrency($order->getOrderCurrencyCode());
        $request->setReference($order->getIncrementId());
        $request->setParam(''); // @comment currently empty

        $narrativeText = '';
        /** load correct narrative text from config */
        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Creditcard) {
            $narrativeText = $this->getNarrativeText('creditcard');
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_DebitPayment) {
            $narrativeText = $this->getNarrativeText('debit_payment');
        }
        $request->setNarrativeText($narrativeText);

        $request->setAmount($order->getGrandTotal());
    }


    /**
     * @return Payone_Api_Request_Parameter_Authorization_PersonalData
     */
    protected function mapPersonalParameters()
    {
        $helper = $this->helper();
        $order = $this->getOrder();
        $billingAddress = $order->getBillingAddress();
        $billingCountry = $billingAddress->getCountry();
        $customer = $order->getCustomer();

        $personalData = new Payone_Api_Request_Parameter_Authorization_PersonalData();
        $personalData->setCustomerid($customer->getIncrementId());
        $personalData->setTitle($billingAddress->getPrefix());
        $personalData->setFirstname($billingAddress->getFirstname());
        $personalData->setLastname($billingAddress->getLastname());
        $personalData->setCompany($billingAddress->getCompany());

        $street = $helper->normalizeStreet($billingAddress->getStreet());
        $personalData->setStreet($street);
        $personalData->setAddressaddition('');
        $personalData->setZip($billingAddress->getPostcode());
        $personalData->setCity($billingAddress->getCity());
        $personalData->setCountry($billingCountry);
        $personalData->setEmail($billingAddress->getEmail());
        $personalData->setTelephonenumber($billingAddress->getTelephone());

        $birthday = $this->formatBirthday($order->getCustomerDob());
        $personalData->setBirthday($birthday);

        $language = $helper->getDefaultLanguage();
        $personalData->setLanguage($language);
        $personalData->setVatid($order->getCustomerTaxvat());
        $personalData->setIp($order->getRemoteIp());

        // US and CA always need state and shipping_state paramters
        if ($billingCountry == 'US' or $billingCountry == 'CA') {
            $personalData->setState($billingAddress->getRegionCode());
        }

        return $personalData;
    }

    /**
     * @return Payone_Api_Request_Parameter_Authorization_DeliveryData
     */
    protected function mapDeliveryParameters()
    {
        $helper = $this->helper();
        $shippingAddress = $this->getOrder()->getShippingAddress();

        $deliveryData = new Payone_Api_Request_Parameter_Authorization_DeliveryData();

        $shippingCountry = $shippingAddress->getCountry();

        $deliveryData->setShippingFirstname($shippingAddress->getFirstname());
        $deliveryData->setShippingLastname($shippingAddress->getLastname());
        $deliveryData->setShippingCompany($shippingAddress->getCompany());
        $street = $helper->normalizeStreet($shippingAddress->getStreet());
        $deliveryData->setShippingStreet($street);
        $deliveryData->setShippingZip($shippingAddress->getPostcode());
        $deliveryData->setShippingCity($shippingAddress->getCity());
        $deliveryData->setShippingCountry($shippingCountry);

        // US and CA always need shipping_state paramters
        if ($shippingCountry == 'US' or $shippingCountry == 'CA') {
            $deliveryData->setShippingState($shippingAddress->getRegionCode());
        }

        return $deliveryData;
    }

    /**
     * @return Payone_Api_Request_Parameter_Invoicing_Transaction
     */
    protected function mapInvoicingParameters()
    {
        $order = $this->getOrder();

        $invoiceAppendix = $this->getInvoiceAppendix();

        $invoicing = new Payone_Api_Request_Parameter_Invoicing_Transaction();
        $invoicing->setInvoiceappendix($invoiceAppendix);

        // Order items:
        foreach ($order->getItemsCollection() as $key => $itemData) {
            /** @var $itemData Mage_Sales_Model_Order_Item */
            $params['id'] = $itemData->getSku();
            $params['pr'] = $itemData->getPriceInclTax();
            $params['no'] = $itemData->getQtyToInvoice();
            $params['de'] = $itemData->getName();
            $params['va'] = number_format($itemData->getTaxPercent(), 0, '.', '');

            $item = new Payone_Api_Request_Parameter_Invoicing_Item();
            $item->init($params);
            $invoicing->addItem($item);
        }

        // Shipping / Fees:
        if ($order->getShippingInclTax() > 0) {
            $invoicing->addItem($this->mapShippingFeeAsItem());
        }

        return $invoicing;
    }

    /**
     * @return Payone_Api_Request_Parameter_Authorization_3dsecure
     */
    protected function map3dSecureParameters()
    {
        $secure3d = new Payone_Api_Request_Parameter_Authorization_3dsecure();
        // @comment 3D Secure is currently not available in Magento
        return $secure3d;
    }

    /**
     * @return Payone_Api_Request_Parameter_Authorization_PaymentMethod_Abstract
     */
    protected function mapPaymentParameters()
    {
        $payment = null;
        $paymentMethod = $this->getPaymentMethod();
        $info = $paymentMethod->getInfoInstance();
        $isRedirect = false;

        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_CashOnDelivery) {
            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_CashOnDelivery();
            $payment->setShippingprovider(Payone_Api_Enum_Shippingprovider::DHL);
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Creditcard) {
            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_CreditCard();

            // check if it is an adminorder and set ecommercemode to moto
            if ($this->getIsAdmin()) {
                $payment->setEcommercemode('moto');
            }
            $payment->setPseudocardpan($info->getPayonePseudocardpan());
            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_OnlineBankTransfer) {
            $country = $this->getOrder()->getBillingAddress()->getCountry();

            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_OnlineBankTransfer();
            $payment->setBankcountry($country);
            $payment->setBankaccount($info->getPayoneAccountNumber());
            $payment->setBankcode($info->getPayoneBankCode());
            $payment->setBankgrouptype($info->getPayoneBankGroup());
            $payment->setOnlinebanktransfertype($info->getPayoneOnlinebanktransferType());

            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Wallet) {
            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_Wallet();
            // @comment currently hardcoded because there is no other Type
            $payment->setWallettype(Payone_Api_Enum_WalletType::PAYPAL_EXPRESS);

            $isRedirect = true;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_DebitPayment) {
            $country = $this->getOrder()->getBillingAddress()->getCountry();

            $payment = new Payone_Api_Request_Parameter_Authorization_PaymentMethod_DebitPayment();
            $payment->setBankcountry($country);
            $payment->setBankaccount($info->getPayoneAccountNumber());
            $payment->setBankaccountholder($info->getPayoneAccountOwner());
            $payment->setBankcode($info->getPayoneBankCode());
        }

        if ($isRedirect === true) {
            $successurl = $this->helperUrl()->getSuccessUrl();
            $errorurl = $this->helperUrl()->getErrorUrl();
            $backurl = $this->helperUrl()->getBackUrl();

            $payment->setSuccessurl($successurl);
            $payment->setErrorurl($errorurl);
            $payment->setBackurl($backurl);
        }

        return $payment;
    }

    /**
     * @param Payone_Core_Model_Payment_Method_Abstract $paymentMethod
     * @return string
     */
    protected function mapClearingType(Payone_Core_Model_Payment_Method_Abstract $paymentMethod)
    {
        $clearingType = '';

        if ($paymentMethod instanceof Payone_Core_Model_Payment_Method_CashOnDelivery) {
            $clearingType = Payone_Enum_ClearingType::CASHONDELIVERY;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Creditcard) {
            $clearingType = Payone_Enum_ClearingType::CREDITCARD;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_OnlineBankTransfer) {
            $clearingType = Payone_Enum_ClearingType::ONLINEBANKTRANSFER;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Wallet) {
            $clearingType = Payone_Enum_ClearingType::WALLET;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_Invoice) {
            $clearingType = Payone_Enum_ClearingType::INVOICE;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_AdvancePayment) {
            $clearingType = Payone_Enum_ClearingType::ADVANCEPAYMENT;
        }
        elseif ($paymentMethod instanceof Payone_Core_Model_Payment_Method_DebitPayment) {
            $clearingType = Payone_Enum_ClearingType::DEBITPAYMENT;
        }

        return $clearingType;
    }

    /**
     * @param $date
     * @return string
     */
    public function formatBirthday($date)
    {
        if (strlen($date) > 0) {
            $date = substr($date, 0, 4) . substr($date, 5, 2) . substr($date, 8, 2);
        }
        return $date;
    }

    /**
     * Returns the narrative text and substitutes the placeholder if neccessary
     * @param $type
     * @return string
     */
    protected function getNarrativeText($type)
    {
        $storeId = $this->getPaymentMethod()->getStore();
        $general = $this->helperConfig()->getConfigGeneral($storeId);
        $parameterNarrativeText = $general->getParameterNarrativeText();

        $narrativeText = '';
        if ($type === 'creditcard') {
            $narrativeText = $parameterNarrativeText->getCreditcard();
        }
        elseif ($type === 'debit_payment') {
            $narrativeText = $parameterNarrativeText->getDebitPayment();
        }

        $substitutionArray = array(
            '{{order_increment_id}}' => $this->getOrder()->getIncrementId()
        );

        $narrativeText = str_replace(array_keys($substitutionArray), array_values($substitutionArray), $narrativeText);

        return $narrativeText;
    }
}