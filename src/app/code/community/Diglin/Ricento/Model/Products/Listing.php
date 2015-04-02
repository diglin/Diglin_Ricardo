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

/**
 * Products Listing Model
 *
 * @method string getTitle()
 * @method string getStatus()
 * @method int    getSalesOptionsId()
 * @method int    getWebsiteId()
 * @method int    getRuleId()
 * @method string    getPublishLanguages()
 * @method string    getDefaultLanguage()
 * @method int    getLangStoreIdDe()
 * @method int    getLangStoreIdFr()
 * @method DateTime getCreatedAt()
 * @method DateTime getUpdatedAt()
 * @method Diglin_Ricento_Model_Products_Listing setTitle(string $title)
 * @method Diglin_Ricento_Model_Products_Listing setStatus(string $status)
 * @method Diglin_Ricento_Model_Products_Listing setSalesOptionsId(int $salesOptionsId)
 * @method Diglin_Ricento_Model_Products_Listing setWebsiteId(int $websiteId)
 * @method Diglin_Ricento_Model_Products_Listing setRuleId(int $ruleId)
 * @method Diglin_Ricento_Model_Products_Listing setPublishLanguages(string $language)
 * @method Diglin_Ricento_Model_Products_Listing setDefaultLanguage(string $language)
 * @method Diglin_Ricento_Model_Products_Listing setLangStoreIdDe(int $storeId)
 * @method Diglin_Ricento_Model_Products_Listing setLangStoreIdFr(int $storeId)
 * @method Diglin_Ricento_Model_Products_Listing setCreatedAt(DateTime $createdAt)
 * @method Diglin_Ricento_Model_Products_Listing setUpdatedAt(DateTime $updatedAt)
 */
class Diglin_Ricento_Model_Products_Listing extends Mage_Core_Model_Abstract
{
    /**
     * @var Diglin_Ricento_Model_Sales_Options
     */
    protected $_salesOptions;

    /**
     * @var Diglin_Ricento_Model_Rule
     */
    protected $_shippingPaymentRule;

    /**
     * Prefix of model events names
     * @var string
     */
    protected $_eventPrefix = 'products_listing';

    /**
     * Parameter name in event
     * In observe method you can use $observer->getEvent()->getObject() in this case
     * @var string
     */
    protected $_eventObject = 'products_listing';

    /**
     * Products_Listing Constructor
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('diglin_ricento/products_listing');
    }

    /**
     * Set date of last update
     *
     * @return Diglin_Ricento_Model_Products_Listing
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if ($this->hasDataChanges() && $this->getStatus() != Diglin_Ricento_Helper_Data::STATUS_LISTED) {
            $this->setStatus(Diglin_Ricento_Helper_Data::STATUS_PENDING);

            // Be aware doing that doesn't trigger Magento events but it's faster
            $this->getProductsListingItemCollection()->updateStatusToAll(Diglin_Ricento_Helper_Data::STATUS_PENDING);
        }

        $this->setUpdatedAt(Mage::getSingleton('core/date')->gmtDate());

        if ($this->isObjectNew()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->gmtDate());
        }

        return $this;
    }

    /**
     * @return $this|Mage_Core_Model_Abstract
     */
    protected function _beforeDelete()
    {
        parent::_beforeDelete();

        // We must not use the FK constrains cause of the need to delete other values at item level
        $this->getProductsListingItemCollection()->walk('delete');
        return $this;
    }

    /**
     * @return $this|Mage_Core_Model_Abstract
     */
    protected function _afterDeleteCommit()
    {
        $this->getSalesOptions()->delete();
        $this->getShippingPaymentRule()->delete();

        parent::_afterDeleteCommit();
        return $this;
    }

    /**
     * @return Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection
     */
    public function getProductsListingItemCollection()
    {
        return Mage::getResourceModel('diglin_ricento/products_listing_item_collection')
            ->addFieldToFilter('products_listing_id', array('eq' => $this->getId()));
    }

    /**
     * Retrieve array of product id's for listing
     *
     * @param bool $withChildren
     * @return array
     */
    public function getProductIds($withChildren = true)
    {
        if (!$this->getId()) {
            return array();
        }

        $array = $this->getData('product_ids');
        if (is_null($array)) {
            $array = $this->getResource()->getProductIds($this, $withChildren);
            $this->setData('product_ids', $array);
        }
        return $array;
    }

    /**
     * Adds new item by product id
     *
     * @param $productId
     * @return bool true if product has been added
     */
    public function addProduct($productId)
    {
        $readConnection = $this->getResource()->getReadConnection();
        $select = $readConnection
            ->select()
            ->from($this->getResource()->getTable('catalog/product'), array('entity_id', 'type_id'))
            ->where('entity_id = ?', $productId);

        $productTable = $readConnection->fetchRow($select);

        if (count($productTable)) {
            /** @var $productListingItem Diglin_Ricento_Model_Products_Listing_Item */
            $productListingItem = Mage::getModel('diglin_ricento/products_listing_item');
            $productListingItem
                ->setProductsListingId($this->getId())
                ->setProductId($productId)
                ->setType($productTable['type_id'])
                ->save();
            return true;
        }
        return false;
    }

    /**
     * @param array $productIds
     * @return int[]
     */
    public function removeProductsByProductIds(array $productIds)
    {
        return $this->getResource()->removeProductsByProductIds($productIds, $this->getId());
    }

    /**
     * @param array $productIds
     * @return int[]
     */
    public function removeProductsByItemIds(array $productIds)
    {
        return $this->getResource()->removeProductsByItemIds($productIds, $this->getId());
    }

    /**
     * @return Diglin_Ricento_Model_Sales_Options
     */
    public function getSalesOptions()
    {
        if (!$this->_salesOptions) {
            $this->_salesOptions = Mage::getModel('diglin_ricento/sales_options')->load($this->getSalesOptionsId());
        }
        return $this->_salesOptions;
    }

    /**
     * @return Diglin_Ricento_Model_Rule
     */
    public function getShippingPaymentRule()
    {
        if (!$this->_shippingPaymentRule) {
            $this->_shippingPaymentRule = Mage::getModel('diglin_ricento/rule')->load($this->getRuleId());
        }
        return $this->_shippingPaymentRule;
    }
}
