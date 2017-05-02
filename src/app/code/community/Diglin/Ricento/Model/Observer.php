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
 * Class Diglin_Ricento_Model_Observer
 *
 * Highly inspired from the Magento Hackathon Project: https://github.com/magento-hackathon/Magento-PSR-0-Autoloader
 * We do our own implementation to merge it in only one extension and to remove useless methods for our case
 *
 */
class Diglin_Ricento_Model_Observer
{
    const CONFIG_PATH_PSR0NAMESPACES = 'global/psr0_namespaces';

    static $shouldAdd = true;

    protected $_isInventoryProceed = null;

    /**
     * @return array
     */
    protected function _getNamespacesToRegister()
    {
        $namespaces = array();
        $node = Mage::getConfig()->getNode(self::CONFIG_PATH_PSR0NAMESPACES);
        if ($node && is_array($node->asArray())) {
            $namespaces = array_keys($node->asArray());
        }

        return $namespaces;
    }

    /**
     * Add PSR-0 Autoloader for our Diglin_Ricardo library
     *
     * Event
     * - resource_get_tablename
     * - add_spl_autoloader
     */
    public function addAutoloader()
    {
        if (!self::$shouldAdd) {
            return $this;
        }

        foreach ($this->_getNamespacesToRegister() as $namespace) {
            $namespace = str_replace('_', '/', $namespace);
            if (is_dir(Mage::getBaseDir('lib') . DS . $namespace)) {
                $args = array($namespace, Mage::getBaseDir('lib') . DS . $namespace);
                $autoloader = Mage::getModel("diglin_ricento/splAutoloader", $args);
                $autoloader->register();
            }
        }

        self::$shouldAdd = false;

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function checkoutSubmitAllAfter(Varien_Event_Observer $observer)
    {
        try {
            $quote = $observer->getEvent()->getQuote();
            if (!$quote->getRicardoInventoryProcessed()) {
                $this->_decreaseInventory($observer);
            }
        } catch (Exception $e) {
            Mage::log("\n" . $e->__toString(), Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            Mage::helper('diglin_ricento/tools')->sendAdminNotification($e->getMessage());
        }

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function salesModelServiceQuoteSubmitBefore(Varien_Event_Observer $observer)
    {
        try {
            $quote = $observer->getEvent()->getQuote();
            if (!$quote->getRicardoInventoryProcessed()) {
                $this->_decreaseInventory($observer);
            }
        } catch (Exception $e) {
            Mage::log("\n" . $e->__toString(), Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            Mage::helper('diglin_ricento/tools')->sendAdminNotification($e->getMessage());
        }

        return $this;
    }

    /**
     * Decrease the inventory on ricardo.ch when an order is passed outside of ricardo.ch
     * Only affect direct sales products
     *
     * Event
     * - checkout_submit_all_after
     * - sales_model_service_quote_submit_before
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    protected function _decreaseInventory(Varien_Event_Observer $observer)
    {
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = $observer->getEvent()->getQuote();

        if (!Mage::helper('diglin_ricento')->getDecreaseInventory() || $quote->getIsRicardo() || $this->_isInventoryProceed) {
            return $this;
        }

        /* @var $item Mage_Sales_Model_Quote_Item */
        foreach ($quote->getAllItems() as $item) {

            $collection = Mage::getResourceModel('diglin_ricento/products_listing_item_collection')
                ->addFieldToFilter('product_id', $item->getProductId())
                ->addFieldToFilter('status', array('in' => array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD)))
                ->addFieldToFilter('ricardo_article_id', array('notnull' => true))
                ->addFieldToFilter('is_planned', 0);

            foreach ($collection->getItems() as $productItem) {
                Mage::helper('diglin_ricento/product')->proceedInventoryUpdate($productItem);
            }
        }

        $this->_isInventoryProceed = true;

        return $this;
    }

    /**
     * Event
     * - cataloginventory_stock_item_save_after
     */
    public function cataloginventoryStockItemSaveAfter(Varien_Event_Observer $observer)
    {
        /* @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
        $stockItem = $observer->getEvent()->getItem();

        $ricardoItem = Mage::getModel('diglin_ricento/products_listing_item');
        $ricardoItem->load($stockItem->getProductId(), 'product_id');

        if ($stockItem->getOrigData('qty') > $stockItem->getQty()
            && $ricardoItem->getId()
            && in_array($ricardoItem->getStatus(), array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD))
            && !$ricardoItem->getIsPlanned()
            && $ricardoItem->getRicardoArticleId()
        ) {
            Mage::helper('diglin_ricento/product')->proceedInventoryUpdate($ricardoItem, $stockItem);
        }
    }

    /**
     * Event
     * - adminhtml_block_html_before
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function disableFormField(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();

        if ($block instanceof Mage_Adminhtml_Block_Customer_Edit_Tab_Account) {
            $block->getForm()->getElement('ricardo_username')->setDisabled(true);
            $block->getForm()->getElement('ricardo_id')->setDisabled(true);
        }

        return $this;
    }

    /**
     * Retrieve payment information from a ricardo.ch order
     *
     * Event
     * - payment_info_block_prepare_specific_information
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function paymentMethodsInformation(Varien_Event_Observer $observer)
    {
        /* @var $payment Mage_Sales_Model_Order_Payment */
        $payment = $observer->getEvent()->getPayment();
        $transport = $observer->getEvent()->getTransport();

        if ($payment->getMethod() == Diglin_Ricento_Model_Sales_Method_Payment::PAYMENT_CODE) {
            $additionalData = Mage::helper('core')->jsonDecode($payment->getAdditionalData(), Zend_Json::TYPE_OBJECT);
            $methods = explode(',', $additionalData->ricardo_payment_methods);

            $label = array();
            $information = '';
            foreach ($methods as $method) {
                if (\Diglin\Ricardo\Enums\PaymentMethods::TYPE_BANK_TRANSFER == $method) {
                    $information = Mage::getStoreConfig(Diglin_Ricento_Helper_Data::PAYMENT_BANK_INFO);
                }
                $label[] = Mage::helper('diglin_ricento')->__(\Diglin\Ricardo\Enums\PaymentMethods::getLabel($method));
            }

            if (!empty($label)) {
                $transport->setData(array(
//                    'bid_ids' => (isset($additionalData->ricardo_bid_ids)) ? $additionalData->ricardo_bid_ids : null,
                    'article_ids' => (isset($additionalData->ricardo_article_ids)) ? $additionalData->ricardo_article_ids : null,
                    'methods'     => $label,
                    'information' => $information));
            }
        }

        return $this;
    }

    /**
     * Event
     * - sales_quote_item_set_product
     *
     * We skipped custom option while processing an order via the ricardo.ch API
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function setSkipppedRequiredOption(Varien_Event_Observer $observer)
    {
        /* @var $product Mage_Catalog_Model_Product */
        $product = $observer->getEvent()->getProduct();

        /* @var $quoteItem Mage_Sales_Model_Quote_Item */
        $quoteItem = $observer->getEvent()->getQuoteItem();

        if ($quoteItem->getQuote() && $quoteItem->getQuote()->getIsRicardo()) {
            $product->setSkipCheckRequiredOption(true);
        }

        return $this;
    }

    /**
     * Event
     * - controller_action_layout_load_before
     *
     * @param Varien_Event_Observer $observer
     */
    public function addLayoutHandle(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        if (strpos($event->getAction()->getFullActionName(), 'ricento') !== false) {
            $event->getLayout()->getUpdate()->addHandle('ricento');
        }
    }

    /**
     * Add on the fly the username attribute to the customer collection
     *
     * Event:
     * - sales_order_grid_collection_load_before
     *
     * @param Varien_Event_Observer $observer Observer
     */
    public function addAttributeToCollection($observer)
    {
        /* @var $collection Mage_Eav_Model_Entity_Collection_Abstract */
        $collection = $observer->getEvent()->getCollection();
        $entity = $collection->getEntity();
        if (!empty($entity) && $entity instanceof Mage_Eav_Model_Entity_Abstract && $entity->getType() == 'customer') {
            $collection->addAttributeToSelect('ricardo_username');
        }
    }

    /**
     * Event
     * - core_block_abstract_to_html_before
     *
     * @param Varien_Event_Observer $observer Observer
     */
    public function addRicardoColumns(Varien_Event_Observer $observer)
    {
        $grid = $observer->getBlock();

        /**
         * Mage_Adminhtml_Block_Sales_Order_Grid
         */
        if ($grid->getId() == 'sales_order_grid' || $grid instanceof Mage_Adminhtml_Block_Sales_Order_Grid) {

            if (!Mage::getStoreConfigFlag('ricento/global/order_grid')) {
                return;
            }

            $grid->addColumnAfter(
                'is_ricardo',
                array(
                    'header'  => Mage::helper('diglin_ricento')->__('Is Ricardo'),
                    'index'   => 'is_ricardo',
                    'type'    => 'options',
                    'options' => array(
                        '1' => Mage::helper('core')->__('Yes'),
                        '0' => Mage::helper('core')->__('No'),
                    ),
                ),
                'status'
            );
        } else if ($grid->getId() == 'customerGrid' || $grid instanceof Mage_Adminhtml_Block_Customer_Grid) {

            if (!Mage::getStoreConfigFlag('ricento/global/customer_grid')) {
                return;
            }

            $grid->addColumnAfter(
                'ricardo_username',
                array(
                    'header' => Mage::helper('diglin_ricento')->__('ricardo.ch Username'),
                    'index'  => 'ricardo_username'
                ),
                'email'
            );
        }
    }
}