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
class Payone_Core_Model_Mapper_ApiRequest_Payment_Capture
    extends Payone_Core_Model_Mapper_ApiRequest_Payment_Abstract
{
    /** @var Mage_Sales_Model_Order_Invoice */
    protected $invoice = null;

    /**
     * @return Payone_Api_Request_Capture
     */
    protected function getRequest()
    {
        return $this->getFactory()->getRequestPaymentCapture();
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Payone_Api_Request_Capture
     */
    public function mapFromPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $this->init($payment);

        $request = $this->getRequest();

        $this->mapDefaultParameters($request);

        $this->mapDefaultCaptureParameters($request);

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
     * @param Payone_Api_Request_Capture $request
     */
    protected function mapDefaultCaptureParameters(Payone_Api_Request_Capture $request)
    {
        $order = $this->getOrder();

        $transaction = $this->getFactory()->getModelTransaction();
        $transaction = $transaction->loadByPayment($order->getPayment());

        $request->setTxid($order->getPayment()->getLastTransId());
        $request->setSequencenumber($transaction->getNextSequenceNumber());
        $request->setCurrency($order->getOrderCurrencyCode());
        $request->setAmount($this->getAmount());
        $request->setRequest(Payone_Api_Enum_RequestType::CAPTURE);
    }

    /**
     * @return Payone_Api_Request_Parameter_Capture_Business
     */
    protected function mapBusinessParameters()
    {
        $business = new Payone_Api_Request_Parameter_Capture_Business();
        $business->setSettleaccount('auto');
        $business->setBookingDate('');
        $business->setDocumentDate('');
        $business->setDueTime('');
        return $business;
    }

    /**
     * @return Payone_Api_Request_Parameter_Invoicing_Transaction
     */
    protected function mapInvoicingParameters()
    {
        $order = $this->getOrder();
        $invoice = $this->getInvoice();

        $invoiceIncrementId = $invoice->getIncrementId();
        if ($invoiceIncrementId === null) {
            $invoiceIncrementId = $this->fetchNewIncrementId($invoice);
        }

        $appendix = $this->getInvoiceAppendix($invoice);

        $invoicing = new Payone_Api_Request_Parameter_Invoicing_Transaction();
        $invoicing->setInvoiceid($invoiceIncrementId);
        $invoicing->setInvoiceappendix($appendix);

        // Regular order items:
        foreach ($invoice->getItemsCollection() as $itemData) {
            /** @var $itemData Mage_Sales_Model_Order_Invoice_Item */
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

        // Shipping / Fees:
        if ($invoice->getShippingInclTax() > 0) {
            $invoicing->addItem($this->mapShippingFeeAsItem());
        }

        return $invoicing;
    }

    /**
     * @return Mage_Sales_Model_Order_Invoice|null
     */
    protected function getInvoice()
    {
        if ($this->invoice === null) {
            // we need to check registry because Magento won't give the invoice instance to PaymentMethodInstance
            $invoice = Mage::registry('current_invoice');
            if (is_null($invoice)) {
                // fallback to lastInvoice when invoice could not be fetched from Registry
                $order = $this->getOrder();
                $invoice = $order->getInvoiceCollection()->getLastItem();
            }
            $this->invoice = $invoice;
        }
        return $this->invoice;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     */
    public function setInvoice(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

}