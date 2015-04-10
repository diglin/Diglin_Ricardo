<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2014 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use \Diglin\Ricardo\Managers\SellerAccount\Parameter\SoldArticlesParameter;

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
     * Create Order Jobs for all products listing with the listed status
     *
     * @return $this
     */
    public function proceed()
    {
        $plResource = Mage::getResourceModel('diglin_ricento/products_listing');
        $readConnection = $plResource->getReadConnection();
        $select = $readConnection
                    ->select()
                    ->from($plResource->getTable('diglin_ricento/products_listing'), 'entity_id');

        $listingIds = $readConnection->fetchCol($select);

        foreach ($listingIds as $listingId) {
            $select = $readConnection
                ->select()
                ->from(array('pli' => $plResource->getTable('diglin_ricento/products_listing_item')), 'item_id')
                ->where('type <> ?', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                ->where('products_listing_id = :id AND status = :status AND is_planned = 0');

            $binds = array('id' => $listingId, 'status' => Diglin_Ricento_Helper_Data::STATUS_LISTED);
            $countListedItems = count($readConnection->fetchAll($select, $binds));

            if ($countListedItems == 0) {
                continue;
            }

            /**
             * Check that there is not already running job instead of creating a new one
             */
            Mage::getResourceModel('diglin_ricento/sync_job')->cleanupPendingJob($this->_jobType, $listingId);

            // pending progress doesn't make sense here as we cleanup before but keep it to be sure everything ok
            $job = Mage::getModel('diglin_ricento/sync_job');
            $job->loadByTypeListingIdProgress($this->_jobType, $listingId, array(
                Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING,
                Diglin_Ricento_Model_Sync_Job::PROGRESS_CHUNK_RUNNING
            ));

            if ($job->getId()) {
                continue;
            }

            $job
                ->setJobType($this->_jobType)
                ->setProgress(Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING)
                ->setJobMessage(array($job->getJobMessage(true)))
                ->save();

            $jobListing = Mage::getModel('diglin_ricento/sync_job_listing');
            $jobListing
                ->setProductsListingId($listingId)
                ->setTotalCount($countListedItems)
                ->setTotalProceed(0)
                ->setJobId($job->getId())
                ->save();
        }

        unset($listingIds);
        unset($readConnection);
        unset($job);
        unset($jobListing);

        return parent::proceed();
    }

    /**
     * @return $this|mixed
     */
    protected function _proceed()
    {
        $article = null;
        $soldArticles = array();

        $itemCollection = $this->_getItemCollection(array(Diglin_Ricento_Helper_Data::STATUS_LISTED), $this->_currentJobListing->getLastItemId());
        $itemCollection->addFieldToFilter('is_planned', 0);

        $ricardoArticleIds = $itemCollection->getColumnValues('ricardo_article_id');
        $lastItem = $itemCollection->getLastItem();

        try {
            $soldArticles = $this->getSoldArticles($ricardoArticleIds);
        } catch (Exception $e) {
            $this->_handleException($e, Mage::getSingleton('diglin_ricento/api_services_selleraccount'));
            $e = null;
            // keep going - no break
        }

        /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
        foreach ($soldArticles as $soldArticle) {
            /**
             * Save item information and eventual error messages
             */
            if (isset($soldArticle['item_message'])) {
                $this->_getListingLog()->saveLog(array(
                    'job_id' => $this->_currentJob->getId(),
                    'product_title' => $soldArticle['product_title'],
                    'product_id' => $soldArticle['product_id'],
                    'products_listing_id' => $this->_productsListingId,
                    'message' => (is_array($soldArticle['item_message'])) ? $this->_jsonEncode($soldArticle['item_message']) : $soldArticle['item_message'],
                    'log_status' => $soldArticle['item_status'],
                    'log_type' => $this->_logType,
                    'created_at' => Mage::getSingleton('core/date')->gmtDate()
                ));
            }
        }

        /**
         * Save the current information of the process to allow live display via ajax call
         */
        $this->_totalProceed += count($ricardoArticleIds);
        $this->_currentJobListing->saveCurrentJob(array(
            'total_proceed' => $this->_totalProceed,
            'last_item_id' => $lastItem->getId()
        ));

        unset($itemCollection);

        return $this;
    }

    /**
     * @param array $articleIds
     * @param Diglin_Ricento_Model_Products_Listing_Item $productItem
     * @return bool
     * @throws Exception
     */
    public function getSoldArticles($articleIds = array(), Diglin_Ricento_Model_Products_Listing_Item $productItem = null)
    {
        $soldArticlesParameter = new SoldArticlesParameter();
        $delay = (3 * 24 * 60 * 60); // 3 days
        $soldArticlesReturn = array();

        $transactionCollection = Mage::getResourceModel('diglin_ricento/sales_transaction_collection');
        $transactionCollection
            ->addFieldToFilter('order_id', new Zend_Db_Expr('NULL'))
            ->getSelect()
            ->where('UNIX_TIMESTAMP(created_at) + (?) < UNIX_TIMESTAMP(now())', $delay);

        /**
         * Set minimum end date to filter e.g. last day. Do not use a higher value as the minimum sales duration is 1 day,
         * we prevent to have conflict with several sold articles having similar internal reference
         */
        $soldArticlesParameter
            ->setPageSize($this->_limit) // if not defined, default is 10
            ->setArticleIdsFilter($articleIds)
            ->setExcludedTransactionIdsFilter($transactionCollection->getColumnValues('transaction_id'))
            ->setMinimumEndDate($this->_getHelper()->getJsonDate(time() - $delay));

        $sellerAccountService = Mage::getSingleton('diglin_ricento/api_services_selleraccount')->setCanUseCache(false);
        $sellerAccountService->setCurrentWebsite($this->_getListing()->getWebsiteId());

        $soldArticlesResult = $sellerAccountService->getSoldArticles($soldArticlesParameter);
        $soldArticles = array_reverse($soldArticlesResult['SoldArticles']);

        foreach ($soldArticles as $soldArticle) {

            $rawData = $soldArticle;
            $soldArticle = $this->_getHelper()->extractData($soldArticle);
            $transaction = $soldArticle->getTransaction();

            if ($transaction && count($transaction) > 0) {

                /**
                 * 1. Check that the transaction doesn't already exists
                 */
                if (Mage::getResourceModel('diglin_ricento/sales_transaction_collection')
                    ->addFieldToFilter('bid_id', $transaction->getBidId())->getSize()
                ) {
                    continue;
                }

                /**
                 * 2. Check if the products listing item exists and is listed
                 */
                $references = $soldArticle->getArticleInternalReferences();
                if (!isset($references[0]['InternalReferenceValue'])) {
                    continue;
                }

                $extractedInternReference = $this->_getHelper()->extractInternalReference($references[0]['InternalReferenceValue']);
                if (!($extractedInternReference instanceof Varien_Object)) {
                    continue;
                }

                if (is_null($productItem) || $productItem->getId() != $extractedInternReference->getItemId()) {
                    $productItem = Mage::getModel('diglin_ricento/products_listing_item')->load($extractedInternReference->getItemId());
                    $productItem->setLoadFallbackOptions(true);
                }

                if (!$productItem->getId() || $productItem->getStatus() != Diglin_Ricento_Helper_Data::STATUS_LISTED) {
                    continue;
                }

                /**
                 * 3. Create customer if not exist and set his default billing address
                 */
                $customer = $this->_getCustomer($transaction->getBuyer(), $this->_getListing()->getWebsiteId());

                if ($customer) {
                    $address = $this->_getBillingAddress($customer, $transaction);
                } else {
                    Mage::log($transaction->getBuyer(), Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
                    throw new Exception($this->_getHelper()->__('Customer creation failed! ricardo.ch transaction cannot be added.'));
                }

                /**
                 * 4. Insert transaction into DB for future use
                 */
                $salesTransaction = $this->_saveTransaction($transaction, $customer, $address, $soldArticle, $productItem, $rawData);

                /**
                 * 5. Decrease the quantity at products listing item level and stop it if needed
                 */
                $productItem
                    ->setQtyInventory($productItem->getQtyInventory() - $salesTransaction->getQty())
                    ->setStatus(Diglin_Ricento_Helper_Data::STATUS_SOLD)
                    ->save();

                if (!isset($soldArticlesReturn[$productItem->getId()])) {
                    $soldArticlesReturn[$productItem->getId()] = array(
                        'item_id' => $productItem->getId(),
                        'product_title' => $productItem->getProductTitle(),
                        'product_id' => $productItem->getProductId(),
                        'item_message' => array('success' => $this->_getHelper()->__('The product has been sold')),
                        'item_status' => Diglin_Ricento_Model_Products_Listing_Log::STATUS_SUCCESS
                    );
                }
            }
        }

        unset($salesTransaction);
        unset($soldArticlesParameter);
        unset($sellerAccountService);
        unset($soldArticles);
        unset($productItem);
        unset($customer);

        return $soldArticlesReturn;
    }

    /**
     * Find or create customer if needed based on ricardo data
     *
     * @param Varien_Object $buyer
     * @param int $websiteId
     * @return bool|Mage_Customer_Model_Customer
     */
    protected function _getCustomer(Varien_Object $buyer, $websiteId = Mage_Core_Model_App::ADMIN_STORE_ID)
    {
        if (!$buyer->getBuyerId()) {
            return false;
        }

        $storeId = $this->_getStoreId($websiteId);

        /* @var $customer Mage_Customer_Model_Customer */
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($websiteId)
            ->loadByEmail($buyer->getEmail());

        if (!$customer->getId()) {
            $customer
                ->setFirstname($buyer->getFirstName())
                ->setLastname($buyer->getLastName())
                ->setEmail($buyer->getEmail())
                ->setPassword($customer->generatePassword())
                ->setStoreId($storeId)
                ->setWebsiteId($websiteId)
                ->setConfirmation(null);
        }

        if (!$customer->getRicardoId()) {
            $customer
                ->setRicardoId($buyer->getBuyerId())
                ->setRicardoUsername($buyer->getNickName());
        }

        $customer->save();

        Mage::app()->getLocale()->emulate($storeId);

        if ($customer->isObjectNew() && Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_ACCOUNT_CREATION_EMAIL, $storeId)) {
            if ($customer->isConfirmationRequired()) {
                $typeEmail = 'confirmation';
            } else {
                $typeEmail = 'registered';
            }
            $customer->sendNewAccountEmail($typeEmail, '', $storeId);
        }

        Mage::app()->getLocale()->revert();

        return $customer;
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     * @param $transaction
     * @return Mage_Customer_Model_Address
     * @throws Exception
     */
    protected function _getBillingAddress(Mage_Customer_Model_Customer $customer, $transaction)
    {
        $buyerAddress = $transaction->getBuyer()->getAddresses();

        $address = $customer->getDefaultBillingAddress();

        $street = $buyerAddress->getAddress1() . ' ' . $buyerAddress->getStreetNumber()
            . (($buyerAddress->getAddress2()) ? "\n" . $buyerAddress->getAddress2() : '')
            . (($buyerAddress->getPostalBox()) ? "\n" . $buyerAddress->getPostalBox() : '');

        $postCode = $buyerAddress->getZipCode();
        $city = $buyerAddress->getCity();

        if (!$address || ($address->getCity() != $city && $address->getPostcode() != $postCode && $address->getStreet() != $street)) {

            /**
             * Ricardo API doesn't provide the region and Magento 1.6 doesn't allow to make region optional
             * We use the first region found for the current country but it's far to be good
             * @todo add a "other" region into each country having required region
             */
            $countryId = $this->_getCountryId($buyerAddress->getCountry());
            $regionId = null;
            if (Mage::helper('directory')->isRegionRequired($countryId)) {
                $regionId = Mage::getModel('directory/region')->getCollection()
                    ->addFieldToFilter('country_id', $countryId)
                    ->getFirstItem()
                    ->getId();
            }

            $phone = ($transaction->getBuyer()->getPhone()) ? $transaction->getBuyer()->getPhone() : $transaction->getBuyer()->getMobile();

            $address = Mage::getModel('customer/address');
            $address
                ->setCustomerId($customer->getId())
                ->setCompany($transaction->getBuyer()->getCompanyName())
                ->setLastname($customer->getLastname())
                ->setFirstname($customer->getFirstname())
                ->setStreet($street)
                ->setPostcode($postCode)
                ->setCity($city)
                ->setRegionId($regionId)
                ->setCountryId($countryId)
                ->setTelephone($phone)
                ->setIsDefaultBilling(true)
                ->setIsDefaultShipping(true)
                ->setSaveInAddressBook(1)
                ->save();

            $customer->addAddress($address);
        }

        return $address;
    }

    /**
     * @param $transaction
     * @param Mage_Customer_Model_Customer $customer
     * @param $address
     * @param $soldArticle
     * @param Diglin_Ricento_Model_Products_Listing_Item $productItem
     * @param $rawData
     * @return Diglin_Ricento_Model_Sales_Transaction
     * @throws Exception
     */
    protected function _saveTransaction($transaction, Mage_Customer_Model_Customer $customer, $address, $soldArticle, Diglin_Ricento_Model_Products_Listing_Item $productItem, $rawData)
    {
        $lang = $this->_getHelper()->getLocalCodeFromRicardoLanguageId($soldArticle->getMainLanguageId());
        $transactionData = array(
            'bid_id' => $transaction->getBidId(),
            'website_id' => $this->_getListing()->getWebsiteId(),
            'customer_id' => $customer->getId(),
            'address_id' => $address->getId(),
            'ricardo_customer_id' => $customer->getRicardoId(),
            'ricardo_article_id' => $soldArticle->getArticleId(),
            'qty' => $transaction->getBuyerQuantity(),
            'view_count' => $soldArticle->getViewCount(),
            'shipping_fee' => $soldArticle->getDeliveryCost(),
            'shipping_text' => $soldArticle->getDeliveryText(), // @fixme - if bought in FR and the API use the DE key, text will in DE. I have no solution now
            'shipping_method' => $soldArticle->getDeliveryId(),
            'shipping_cumulative_fee' => (int)$soldArticle->getIsCumulativeShipping(),
            'language_id' => $soldArticle->getMainLanguageId(),
            'payment_methods' => implode(',', $soldArticle->getPaymentMethodIds()->getData()),
            'shipping_description' => $productItem->getShippingPaymentRule()->getShippingDescription($lang),
            'payment_description' => $productItem->getShippingPaymentRule()->getPaymentDescription($lang),
            'total_bid_price' => $soldArticle->getWinningBidPrice(),
            'product_id' => $productItem->getProductId(),
            'raw_data' => Mage::helper('core')->jsonEncode($rawData),
            'sold_at' => $this->_getHelper()->getJsonTimestamp($soldArticle->getEndDate())
        );

        $salesTransaction = Mage::getModel('diglin_ricento/sales_transaction')
            ->addData($transactionData)
            ->save();

        return $salesTransaction;
    }

    /**
     * Create new orders for transactions done more than 30 min in past
     *
     * @return $this
     */
    protected function _proceedAfter()
    {
        $customerTransactions = array();
        $mergeOrder = Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_MERGE_ORDER);

        $delay = ($mergeOrder) ? 30 : 0;

        /**
         * Get transaction older than 30 or 1 minutes and when no order was created
         * Those will be merged in one order if the customer is the same
         */
        $transactionCollection = Mage::getResourceModel('diglin_ricento/sales_transaction_collection');
        $transactionCollection
            ->getSelect()
            ->where('order_id IS NULL')
            ->where('UNIX_TIMESTAMP(sold_at) + ( ? * 60) < UNIX_TIMESTAMP(now())', (int) $delay); // 30 or 1 min past

        $inc = 0;
        foreach ($transactionCollection->getItems() as $transactionItem) {
            if ($mergeOrder) {
                $customerTransactions[$transactionItem->getCustomerId()][] = $transactionItem;
            } else {
                $customerTransactions[++$inc] = $transactionItem;
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

        /**
         * Stop the list if all products listing items are stopped
         */
        if ($this->_productsListingId) {
            Mage::getResourceModel('diglin_ricento/products_listing')->setStatusStop($this->_productsListingId);
        }

        unset($transactionCollection);
        unset($customerTransactions);

        return $this;
    }

    /**
     * @param array $transactions
     */
    public function createNewOrder($transactions)
    {
        $quote = null;
        $dispatchedTransactions = array();
        $storeId = $shippingTransactionMethod = $shippingMethodFee = $highestShippingFee = 0;
        $shippingText = $shippingDescription = '';
        $paymentMethod = $shippingMethod = Diglin_Ricento_Model_Sales_Method_Payment::PAYMENT_CODE;
        $currency = Mage::getModel('directory/currency')->load(Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY);

        try {
            /**
             * If a customer ordered several articles of the same seller in a short period of time
             * the order will merge all articles.
             */

            /* @var $transaction Diglin_Ricento_Model_Sales_Transaction */
            foreach ($transactions as $transaction) {

                $storeId = $this->_getStoreId($transaction->getWebsiteId());

                Mage::app()->getStore($storeId)->setCurrentCurrency($currency);
                Mage::app()->getLocale()->emulate($storeId);

                /**
                 * 1. Init Quote and define customer and his address
                 */
                if (is_null($quote)) {
                    $customer = Mage::getModel('customer/customer')->load($transaction->getCustomerId());

                    $address = Mage::getModel('customer/address')->load($transaction->getAddressId());
                    $address->setCustomer($customer);

                    $quoteAddress = Mage::getModel('sales/quote_address');
                    $quoteAddress->importCustomerAddress($address);

                    $quote = Mage::getModel('sales/quote');
                    $quote
                        ->setStoreId($storeId)
                        ->assignCustomerWithAddressChange($customer, $quoteAddress, $quoteAddress)
                        ->getBillingAddress()
                        ->setPaymentMethod($paymentMethod);
                }

                /**
                 * 2. Add product and its information to the quote
                 */
                $infoBuyRequest = new Varien_Object();
                $infoBuyRequest
                    ->setQty($transaction->getQty())
                    ->setIsRicardo(true)
                    ->setRicardoTransactionId($transaction->getId())
                    ->setShippingCumulativeFee($transaction->getShippingCumulativeFee())
                    ->setShippingFee($transaction->getShippingFee());

                $product = Mage::getModel('catalog/product')
                    ->setStoreId($storeId)
                    ->load($transaction->getProductId())
                    ->setSkipCheckRequiredOption(true);

                if (!$product->getId()) {
                    continue;
                }

                $quoteItem = $quote->addProduct($product, $infoBuyRequest);

                // Error with a product which is missing or have required options
                if (is_string($quoteItem)) {
                    Mage::throwException($quoteItem);
                }

                $quoteItem
                    // Set unit custom price
                    ->setCustomPrice($transaction->getTotalBidPrice())
                    ->setOriginalCustomPrice($transaction->getTotalBidPrice());

                /**
                 * 3. Set shipping information, price, etc
                 * @todo provide correct language
                 */
                $shippingText = $transaction->getShippingText();
                $shippingDescription = $transaction->getShippingDescription();

                /**
                 * 4. Keep the complete transactions list for later use
                 */
                $dispatchedTransactions[$transaction->getBidId()] = $transaction->getId();
            }

            if ($quote) {
                /**
                 * Define payment method and related information
                 */
                $quote
                    ->setIsRicardo(1)
                    ->setForcedCurrency(Mage::getModel('directory/currency')->load(Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY));

                $payment = $quote->getPayment();
                $payment->importData(array(
                    'method' => $paymentMethod,
                    'additional_data' => Mage::helper('core')->jsonEncode(array(
                        'is_ricardo' => true,
                        'ricardo_payment_methods' => $transaction->getPaymentMethods(),
                        'ricardo_transaction_ids' => implode(',', $dispatchedTransactions),
                        'ricardo_bid_ids' => implode(',', array_keys($dispatchedTransactions)),
                        )
                    )));

                /**
                 * Set Shipping information and price
                 * @see Diglin_Ricento_Model_Sales_Method_Shipping::collectRates
                 */
                Mage::getSingleton('core/session')
                    ->setRicardoShippingDescription($shippingText . "\n" . $shippingDescription)
                    ->setRicardoShippingMethod($shippingTransactionMethod);

                $shipping = $quote->getShippingAddress();
                $shipping->setShippingMethod($shippingMethod . '_' . $shippingTransactionMethod);

                $quote->addData(array(
                        'customer_note_notify' => false,
                        'customer_note' => $this->_getHelper()->__('Order automatically generated by the ricardo.ch Extension.'))
                );

                $quote->collectTotals()->save();

                if ($quote->getId()) {
                    // Session variables needed to create order
                    $this->_getSession()
                        ->setQuoteId($quote->getId())
                        ->setStoreId($quote->getStoreId())
                        ->setCustomer($quote->getCustomer())
                        ->setCustomerId($quote->getCustomer()->getId());

                    /* @var $order Mage_Adminhtml_Model_Sales_Order_Create */
                    $order = $this->_getOrderCreateModel()
                        ->initRuleData()
                        ->collectShippingRates()
                        ->setSendConfirmation(Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_ORDER_CREATION_EMAIL, $storeId))
                        ->createOrder();

                    /**
                     * Define order status
                     */
                    $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Diglin_Ricento_Helper_Data::ORDER_STATUS_PENDING, $this->_getHelper()->__('Payment is pending'), false);

                    // @fixme getIsTransactionCompleted or getIsTransactionCancelled has no value from ricardo.ch side at the moment, wait API update
//                    $rawData = $this->_getHelper()->extractData(Mage::helper('core')->jsonDecode($transaction->getRawData()));
//                    if ($rawData->getTransaction()->getIsTransactionCompleted()) {
//                        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $this->_getHelper()->__('Payment has been completed on ricardo.ch side'), false);
//                    }
//
//                    if ($rawData->getTransaction()->getIsTransactionCancelled()) {
//                        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, Diglin_Ricento_Helper_Data::ORDER_STATUS_CANCEL, $this->_getHelper()->__('Order canceled on ricardo.ch side'), false);
//                    }

                    $quote
                        ->setIsActive(false)
                        ->save();

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

                Mage::app()->getLocale()->revert();
            }
        } catch (Exception $e) {

            if (!isset($transaction) || !($transaction instanceof Diglin_Ricento_Model_Sales_Transaction)) {
                $transaction = new Varien_Object();
            }
            if (!isset($product) || !($product instanceof Mage_Catalog_Model_Product)) {
                $product = new Varien_Object();
            }

            $message = 'Error with ricardo Transaction ID: ' . $transaction->getBidId() . ' - Product ID:' . $product->getId() . "\n" . $e->__toString();

            // We store and send the exception but don't block the rest of the process
            Mage::log($message, Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            Mage::helper('diglin_ricento/tools')->sendAdminNotification($message);

            //@todo set that the job has an error and save the information in the product listing log

            /* @var $errors Mage_Core_Model_Message_Collection */
            $errors = $this->_getSession()->getMessages(true);
            if ($errors) {
                Mage::log($errors, Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            }

            // Deactivate the last quote if a problem occur to prevent cart display in frontend to the customer
            $quote = $this->_getSession()->getQuote();
            $quote->setIsActive(false)
                ->setReservedOrderId(NULL)
                ->save();

            Mage::app()->getLocale()->revert();
        }

        // force to cleanup model, session and rule_data for the next orders to generate otherwise conflicts will occur
        Mage::unregister('_singleton/adminhtml/sales_order_create');
        Mage::unregister('_singleton/adminhtml/session_quote');
        Mage::unregister('rule_data');
    }

    /**
     * Retrieve session object
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Retrieve order create model
     *
     * @return Mage_Adminhtml_Model_Sales_Order_Create
     */
    protected function _getOrderCreateModel()
    {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    /**
     * @param $countryRicardoId
     * @return string
     * @throws Exception
     */
    protected function _getCountryId($countryRicardoId)
    {
        $countryName = '';
        $countries = Mage::getSingleton('diglin_ricento/api_services_system')
            ->setCurrentWebsite($this->_getListing()->getWebsiteId())
            ->getCountries();

        foreach ($countries as $country) {
            if ($country['CountryId'] == $countryRicardoId) {
                $countryName = $country['CountryName'];
                break;
            }
        }

        $code = $this->_translateCountryNameToCode($countryName);
        if (!$code) {
            throw new Exception(Mage::helper('diglin_ricento')->__('Country Code is not available. Please contact the author of this extension or support.'));
        }
        $directory = Mage::getModel('directory/country')->loadByCode($code);
        return $directory->getCountryId();
    }

    /**
     * VERY TEMPORARY SOLUTION until ricardo provide an API method to get the correct value
     * @todo remove it as soon the API has implemented the method to get it
     *
     * @param $countryName
     * @return string
     */
    protected function _translateCountryNameToCode($countryName)
    {
        $countryCode = array(
            'Schweiz' => 'CH',
            'Suisse' => 'CH',
            'Liechtenstein' => 'LI', // ok for both lang
            'Österreich' => 'AT',
            'Autriche' => 'AT',
            'Deutschland' => 'DE',
            'Allemagne' => 'DE',
            'Frankreich' => 'FR',
            'France' => 'FR',
            'Italien' => 'IT',
            'Italie' => 'IT',
        );

        return (isset($countryCode[$countryName])) ? $countryCode[$countryName] : false;
    }

    /**
     * @param int $websiteId
     * @return int
     */
    protected function _getStoreId($websiteId)
    {
        return Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
    }
}
