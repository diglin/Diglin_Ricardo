<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2015 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Diglin_Ricento_Model_Dispatcher_Order
 */
class Diglin_Ricento_Model_Dispatcher_Order extends Diglin_Ricento_Model_Dispatcher_Abstract
{
    /**
     * @var int
     */
    protected $_logType = Diglin_Ricento_Model_Products_Listing_Log::LOG_TYPE_ORDER;

    /**
     * @var string
     */
    protected $_jobType = Diglin_Ricento_Model_Sync_Job::TYPE_ORDER;

    /**
     * @return $this|mixed
     */
    public function proceed()
    {
        $customerTransactions = $timestampTransactions = array();
        $mergeOrder = Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_MERGE_ORDER);

        $delay = (($mergeOrder) ? 30 : 0) * 60;

        /**
         * Get transaction older than 30 or 0 minutes and when no order was created
         * Those will be merged in one order if the customer is the same
         */
        $transactionCollection = Mage::getResourceModel('diglin_ricento/sales_transaction_collection');
        $transactionCollection
            ->getSelect()
            ->where('order_id IS NULL');

        if (!$transactionCollection->getSize()) {
            return $this;
        }

        $inc = 0;
        foreach ($transactionCollection->getItems() as $transactionItem) {
            if ($mergeOrder) {
                $timestampTransactions[$transactionItem->getCustomerId()][] = strtotime($transactionItem->getSoldAt());
                $customerTransactions[$transactionItem->getCustomerId()][] = $transactionItem;
                array_multisort($timestampTransactions[$transactionItem->getCustomerId()], SORT_ASC, SORT_NUMERIC);
            } else {
                $customerTransactions[++$inc] = $transactionItem;
            }
        }

        if ($mergeOrder && count($customerTransactions)) {
            foreach ($timestampTransactions as $customerId => $timestampTransaction) {
                $qtyTransactions = count($timestampTransaction);
                // do not create order for transactions which may need to be merged
                if ($timestampTransaction[$qtyTransactions - 1] + $delay > time()) {
                    unset($customerTransactions[$customerId]);
                }
            }
        }

        /**
         * Create new order for each customer
         */
        if (count($customerTransactions) > 0) {
            foreach ($customerTransactions as $transactions) {
                if (!is_array($transactions)) {
                    $transactions = array($transactions);
                }
                $this->createNewOrder($transactions);
            }
        }

        unset($transactionCollection);
        unset($customerTransactions);

        return $this;
    }

    /**
     * @return $this
     */
    protected function _proceed()
    {
        return $this;
    }

    /**
     * If a customer ordered several articles of the same seller in a short period of time
     * the order will merge all articles.
     *
     * @param array $transactions
     */
    public function createNewOrder($transactions)
    {
        $store = $quote = null;
        $dispatchedTransactions = $articleIds = array();
        $shippingTransactionMethod = $shippingMethodFee = $highestShippingFee = 0;
        $shippingText = $shippingDescription = '';

        try {
            /* @var $transaction Diglin_Ricento_Model_Sales_Transaction */
            foreach ($transactions as $transaction) {

                $store = $this->getStoreFromWebsite($transaction->getWebsiteId());

                Mage::app()->getStore($store->getId())->setCurrentCurrency($this->_getCurrency());
                Mage::app()->getLocale()->emulate($store->getId());

                if (is_null($quote)) {
                    $quote = $this->createQuote($transaction, $store);
                }

                if (!$this->addProductToQuote($transaction, $store, $quote)) {
                    continue;
                }

                // @todo provide correct language
                $shippingText = $transaction->getShippingText();
                $shippingDescription = $transaction->getShippingDescription();

                $dispatchedTransactions[$transaction->getBidId()] = $transaction->getId();
                $articleIds[] = $transaction->getRicardoArticleId();
            }

            if ($quote && $store) {

                // @see Diglin_Ricento_Model_Sales_Method_Shipping::collectRates
                $this->_getHelper()->getRicardoShippingRegistry()
                    ->setRicardoShippingDescription($shippingText . "\n" . $shippingDescription)
                    ->setRicardoShippingMethod($shippingTransactionMethod);

                $this->prepareQuote($quote, $dispatchedTransactions, $transaction->getPaymentMethods(), $articleIds);

                if ($quote->getId()) {
                    $orderCreateModel = $this->getOrderCreateModel();
                    $orderCreateModel
                        ->setQuote($quote)
                        ->setStore($store)
                        ->initRuleData()
                        ->setSendConfirmation(Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_ORDER_CREATION_EMAIL, $store->getId()));

                    $order = $orderCreateModel->createOrder();
                    $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Diglin_Ricento_Helper_Data::ORDER_STATUS_PENDING, $this->_getHelper()->__('Payment is pending'), false);
                    $order->save();

                    // @fixme getIsTransactionCompleted or getIsTransactionCancelled has no value from ricardo.ch side at the moment, wait API update
//                    $rawData = $this->_getHelper()->extractData(Mage::helper('core')->jsonDecode($transaction->getRawData()));
//                    if ($rawData->getTransaction()->getIsTransactionCompleted()) {
//                        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $this->_getHelper()->__('Payment has been completed on ricardo.ch side'), false);
//                    }

//                    if ($rawData->getTransaction()->getIsTransactionCancelled()) {
//                        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, Diglin_Ricento_Helper_Data::ORDER_STATUS_CANCEL, $this->_getHelper()->__('Order canceled on ricardo.ch side'), false);
//                    }

                    /**
                     * Save the new order id to the ricardo transaction
                     */
                    if ($order->getId()) {
                        foreach ($transactions as $transaction) {
                            $transaction
                                ->setOrderId($order->getId())
                                ->save();
                        }
                    }
                }
            }

            Mage::app()->getLocale()->revert();

        } catch (Exception $e) {

            if (!isset($transaction) || !($transaction instanceof Diglin_Ricento_Model_Sales_Transaction)) {
                $transaction = new Varien_Object();
            }

            $message = 'Error with ricardo Transaction ID: ' . $transaction->getBidId() . ' - Product ID:' . $transaction->getProductId() . "\n" . $e->__toString();

            // We store and send the exception but don't block the rest of the process
            Mage::log($message, Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            if ($this->_getHelper()->canSendEmailNotification()) {
                Mage::helper('diglin_ricento/tools')->sendAdminNotification($message);
            }

            // Deactivate the last quote if a problem occur to prevent cart display in frontend to the customer
            if ($quote && $quote->getId()) {
                $quote->setIsActive(false)
                    ->setReservedOrderId(null)
                    ->save();
            }

            Mage::app()->getLocale()->revert();
        }

        // force to cleanup model, session and rule_data for the next orders to generate otherwise conflicts will occur
        Mage::unregister('_singleton/diglin_ricento/sales_order_create');
        Mage::unregister('rule_data');
        Mage::unregister('ricardo_shipping');
    }

    /**
     * Init Quote and define customer and his address
     *
     * @param Diglin_Ricento_Model_Sales_Transaction $transaction
     * @param Mage_Core_Model_Store $store
     * @return Mage_Sales_Model_Quote
     */
    public function createQuote(Diglin_Ricento_Model_Sales_Transaction $transaction, Mage_Core_Model_Store $store)
    {
        $customer = Mage::getModel('customer/customer')->load($transaction->getCustomerId());
        $address = Mage::getModel('customer/address')->load($transaction->getAddressId());
        $address->setCustomer($customer);

        $quoteAddress = Mage::getModel('sales/quote_address');
        $quoteAddress->importCustomerAddress($address);
        $quoteAddress->setLimitCarrier(Diglin_Ricento_Model_Sales_Method_Shipping::SHIPPING_CODE);

        $quote = Mage::getModel('sales/quote');
        $quote
            ->setIsSuperMode(true) // quote without restriction
            ->setStoreId($store->getId())
            ->assignCustomerWithAddressChange($customer, $quoteAddress, $quoteAddress);

        $quote->getBillingAddress()->setPaymentMethod(Diglin_Ricento_Model_Sales_Method_Payment::PAYMENT_CODE);
        $quote->save();

        return $quote;
    }

    /**
     * Add product and its information to the quote
     *
     * @param Diglin_Ricento_Model_Sales_Transaction $transaction
     * @param Mage_Core_Model_Store $store
     * @param Mage_Sales_Model_Quote $quote
     * @throws Mage_Core_Exception
     * @return bool
     */
    public function addProductToQuote(Diglin_Ricento_Model_Sales_Transaction $transaction, Mage_Core_Model_Store $store, Mage_Sales_Model_Quote $quote)
    {
        $infoBuyRequest = new Varien_Object();
        $infoBuyRequest
            ->setQty($transaction->getQty())
            ->setIsRicardo(true)
            ->setRicardoTransactionId($transaction->getId())
            ->setShippingCumulativeFee($transaction->getShippingCumulativeFee())
            ->setShippingFee($this->getShippingFee($transaction, $store, $quote));

        $product = Mage::getModel('catalog/product')
            ->setStoreId($store->getId())
            ->load($transaction->getProductId())
            ->setSkipCheckRequiredOption(true);

        if (!$product->getId()) {
            return false;
        }

        $quoteItem = $quote->addProduct($product, $infoBuyRequest);

        // Error with a product which is missing or have required options
        if (is_string($quoteItem)) {
            Mage::throwException($quoteItem);
        }

        $totalBidPrice = Mage::helper('diglin_ricento/price')
            ->getPriceWithOrWithoutTax($product, $transaction->getTotalBidPrice(), $store, $quote);

        $quoteItem
            // Set unit custom price
            ->setCustomPrice($totalBidPrice )
            ->setOriginalCustomPrice($totalBidPrice);

        return true;
    }

    /**
     * @param Diglin_Ricento_Model_Sales_Transaction $transaction
     * @param Mage_Core_Model_Store $store
     * @return float|int|null
     */
    public function getShippingFee(Diglin_Ricento_Model_Sales_Transaction $transaction, Mage_Core_Model_Store $store, Mage_Sales_Model_Quote $quote)
    {
        $currency = $this->_getCurrency();
        $shippingFee = $transaction->getShippingFee();
        $helperPrice = Mage::helper('diglin_ricento/price');

        // To trick Magento tax calculation while creating the order, add tax to Shipping price if shop is outside Switzerland
        $pseudoProduct = new Varien_Object();
        $pseudoProduct->setTaxClassId(Mage::helper('tax')->getShippingTaxClass($store));
        $shippingFee = $helperPrice->getPriceWithOrWithoutTax($pseudoProduct, $shippingFee, $store, $quote);

        $baseCurrencyCode = Mage::app()->getStore($store)->getBaseCurrencyCode();

        if ($baseCurrencyCode != $currency->getCode()) {
            $shippingFee = $helperPrice->convert($shippingFee, $currency->getCode(), $baseCurrencyCode);
        }

        return $shippingFee;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param array $dispatchedTransactions
     * @param $paymentMethods
     * @return Mage_Sales_Model_Quote
     */
    public function prepareQuote(Mage_Sales_Model_Quote $quote, array $dispatchedTransactions, $paymentMethods, $articleIds)
    {
        $shippingTransactionMethod = $this->_getHelper()
            ->getRicardoShippingRegistry()
            ->getRicardoShippingMethod();

        /**
         * Define payment method and related information
         */
        $quote
            ->setIsRicardo(1)
            ->setForcedCurrency($this->_getCurrency());

        // Must be before getPayment()->ImportData() cause of calls of collectTotals() method
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress
            ->setCollectShippingRates(true)
            ->setShippingMethod(Diglin_Ricento_Model_Sales_Method_Shipping::SHIPPING_CODE . '_' . $shippingTransactionMethod)
            ->collectShippingRates();

        $quote->getPayment()->importData(
            array(
                'method' => Diglin_Ricento_Model_Sales_Method_Payment::PAYMENT_CODE,
                'additional_data' => Mage::helper('core')->jsonEncode(array(
                        'is_ricardo' => true,
                        'ricardo_payment_methods' => $paymentMethods,
                        'ricardo_transaction_ids' => implode(',', $dispatchedTransactions),
                        'ricardo_bid_ids' => implode(',', array_keys($dispatchedTransactions)),
                        'ricardo_article_ids' => implode(',', $articleIds)
                    )
                )
            )
        );

        $quote
            ->addData(
                array(
                    'customer_note_notify' => false,
                    'customer_note' => $this->_getHelper()->__('Order automatically generated by the ricardo.ch Extension.')
                )
            );

        $quote->save();

        return $quote;
    }

    /**
     * Retrieve order create model
     *
     * @return Diglin_Ricento_Model_Sales_Order_Create
     */
    public function getOrderCreateModel()
    {
        return Mage::getSingleton('diglin_ricento/sales_order_create');
    }

    /**
     * @param int $websiteId
     * @return Mage_Core_Model_Store
     */
    public function getStoreFromWebsite($websiteId)
    {
        return Mage::app()->getWebsite($websiteId)->getDefaultStore();
    }
}
