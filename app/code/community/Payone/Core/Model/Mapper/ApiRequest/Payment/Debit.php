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
class Payone_Core_Model_Mapper_ApiRequest_Payment_Debit
    extends Payone_Core_Model_Mapper_ApiRequest_Payment_Abstract
{
    /** @var Mage_Sales_Model_Order_Creditmemo */
    protected $creditmemo = null;

    /**
     * @return Payone_Api_Request_Debit
     */
    protected function getRequest()
    {
        return $this->getFactory()->getRequestPaymentDebit();
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Payone_Api_Request_Debit
     */
    public function mapFromPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $this->init($payment);

        $request = $this->getRequest();

        $this->mapDefaultParameters($request);

        $this->mapDefaultDebitParameters($request);

        $business = $this->mapBusinessParameters();
        $request->setBusiness($business);

        /** Set Invoiceing-Parameter only if enabled in Config */
        if ($this->getConfigPayment()->isInvoiceTransmitEnabled()) {
            $invoicing = $this->mapInvoicingParameters();
            $request->setInvoicing($invoicing);
        }

        return $request;
    }

    /**
     * @param Payone_Api_Request_Debit $request
     */
    protected function mapDefaultDebitParameters(Payone_Api_Request_Debit $request)
    {
        $order = $this->getOrder();

        $transaction = $this->getFactory()->getModelTransaction();
        $transaction = $transaction->loadByPayment($order->getPayment());

        $request->setTxid($order->getPayment()->getLastTransId());
        $request->setSequencenumber($transaction->getNextSequenceNumber());
        $request->setCurrency($order->getOrderCurrencyCode());
        $request->setAmount($this->getAmount() * -1);
        $request->setRequest(Payone_Api_Enum_RequestType::DEBIT);
        $request->setUseCustomerdata('yes');
    }

    /**
     * @return Payone_Api_Request_Parameter_Debit_Business
     */
    protected function mapBusinessParameters()
    {
        $business = new Payone_Api_Request_Parameter_Debit_Business();
        $business->setSettleaccount('auto');
        $business->setTransactiontype('');
        $business->setBookingDate('');
        $business->setDocumentDate('');

        return $business;
    }

    /**
     * @return Payone_Api_Request_Parameter_Invoicing_Transaction
     */
    protected function mapInvoicingParameters()
    {
        $order = $this->getOrder();
        $creditmemo = $this->getCreditmemo();

        $creditmemoIncrementId = $creditmemo->getIncrementId();
        if ($creditmemoIncrementId === null) {
            $creditmemoIncrementId = $this->fetchNewIncrementId($creditmemo);
        }

        $appendix = $this->getInvoiceAppendixRefund($creditmemo);

        $invoicing = new Payone_Api_Request_Parameter_Invoicing_Transaction();
        $invoicing->setInvoiceid($creditmemoIncrementId);
        $invoicing->setInvoiceappendix($appendix);

        // Regular order items:
        foreach ($creditmemo->getItemsCollection() as $itemData) {
            /** @var $itemData Mage_Sales_Model_Order_Creditmemo_Item */
            $params['id'] = $itemData->getSku();
            $params['de'] = $itemData->getName();
            $params['no'] = number_format($itemData->getQty(), 0, '.', '');
            $params['pr'] = $itemData->getPriceInclTax();

            // We have to load the tax percentage from the order item
            /** @var $orderItem Mage_Sales_Model_Order_Item */
            $orderItem = $order->getItemById($itemData->getOrderItemId());

            $params['va'] = number_format($orderItem->getTaxPercent(), 0, '.', '');

            $item = new Payone_Api_Request_Parameter_Invoicing_Item();
            $item->init($params);
            $invoicing->addItem($item);
        }

        // Refund shipping
        if ($creditmemo->getShippingInclTax() > 0) {
            $invoicing->addItem($this->mapRefundShippingAsItemByCreditmemo($creditmemo));
        }

        // Adjustment Refund (positive adjustment)
        if($creditmemo->getAdjustmentPositive() > 0) {
            $invoicing->addItem($this->mapAdjustmentPositiveAsItemByCreditmemo($creditmemo));
        }

        // Adjustment Fee (negative adjustment)
        if($creditmemo->getAdjustmentNegative() > 0) {
            $invoicing->addItem($this->mapAdjustmentNegativeAsItemByCreditmemo($creditmemo));
        }

        return $invoicing;
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
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    protected function getCreditmemo()
    {
        if ($this->creditmemo === null) {
            // we need to check registry because Magento won't give the creditmemo instance to PaymentMethodInstance
            $creditmemo = Mage::registry('current_creditmemo');
            if (is_null($creditmemo)) {
                // fallback to lastInvoice when invoice could not be fetched from Registry
                $order = $this->getOrder();
                $creditmemo = $order->getCreditmemosCollection()->getLastItem();
            }
            $this->creditmemo = $creditmemo;
        }
        return $this->creditmemo;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     */
    public function setCreditmemo(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $this->creditmemo = $creditmemo;
    }

}