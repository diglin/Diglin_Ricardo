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
 * Resource Model of Products_Listing
 */
class Diglin_Ricento_Model_Resource_Products_Listing extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Products_Listing Resource Constructor
     * @return void
     */
    protected function _construct()
    {
        $this->_init('diglin_ricento/products_listing', 'entity_id');
    }

    /**
     * Get product ids of listing items
     *
     * @param Diglin_Ricento_Model_Products_Listing $listing
     * @param bool $withChildren
     * @return array
     */
    public function getProductIds($listing, $withChildren = true)
    {
        $readerConnection = $this->_getReadAdapter();

        $select = $readerConnection->select()
            ->from($this->getTable('diglin_ricento/products_listing_item'), 'product_id')
            ->where('products_listing_id = :listing_id');

        $bind = array('listing_id' => (int) $listing->getId());

        if (!$withChildren) {
            $select->where('parent_product_id IS NULL');
        }

        return $readerConnection->fetchCol($select, $bind);
    }

    /**
     * Stop product lists if all items are stopped
     *
     * @param $productsListingId
     * @return $this
     */
    public function setStatusStop($productsListingId)
    {
        $readerConnection = $this->_getReadAdapter();

        $select = $readerConnection->select()
            ->from(array('pl' => $this->getTable('diglin_ricento/products_listing')))
            ->where('pl.entity_id = :id')
            ->joinLeft(
                array('pli' => $this->getTable('diglin_ricento/products_listing_item')),
                'pli.products_listing_id = pl.entity_id AND pl.status = "'. Diglin_Ricento_Helper_Data::STATUS_LISTED .'"',
                array('item_status' => 'pli.status')
            );

        $binds  = array('id' => $productsListingId);

        $rows = $readerConnection->fetchAll($select, $binds);
        $lists = array();

        foreach ($rows as $row) {
            if ($row['item_status'] == Diglin_Ricento_Helper_Data::STATUS_STOPPED) {
                $lists[$row['entity_id']]['stopped'] = true;
            }
            if ($row['item_status'] == Diglin_Ricento_Helper_Data::STATUS_LISTED) {
                $lists[$row['entity_id']]['listed'] = true;
            }
        }

        foreach ($lists as $key => $list) {
            if (!isset($lists[$key]['listed']) && isset($lists[$key]['stopped'])) {
                $this->saveCurrentList($key, array('status' => Diglin_Ricento_Helper_Data::STATUS_STOPPED));
            }
        }

        return $this;
    }

    /**
     * @param int $listId
     * @param array $bind
     * @return int
     */
    public function saveCurrentList($listId, $bind)
    {
        $writeConection = $this->_getWriteAdapter();

        return $writeConection->update(
            $this->getMainTable(),
            $bind,
            array($this->getIdFieldName() . ' = ?' => $listId));
    }

    /**
     * Removes items by product id
     *
     * @param array $productIds
     * @param int $itemId
     * @return int[] Returns two values: [number of removed products, number of not removed listed products]
     */
    public function removeProductsByProductIds(array $productIds, $itemId)
    {
        /** @var $items Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection */
        $items = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
        $items
            ->addFieldToFilter('product_id', array('in' => $productIds))
            ->addFieldToFilter('parent_product_id', new Zend_Db_Expr('NULL'))
            ->addFieldToFilter('type', array('neq' => Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));

        $itemsToRemove = clone $items;

        $numberOfListedItems = $items->addFieldToFilter('products_listing_id', $itemId)
            ->addFieldToFilter('status', array('eq' => Diglin_Ricento_Helper_Data::STATUS_LISTED))
            ->getSize();

        $numberOfItemsToDelete = $itemsToRemove->addFieldToFilter('products_listing_id', $itemId)
            ->addFieldToFilter('status', array('neq' => Diglin_Ricento_Helper_Data::STATUS_LISTED))
            ->count();

        $this->beginTransaction();

        $productsIdRemoved = array();
        if ($numberOfItemsToDelete) {
            $productsIdRemoved = $itemsToRemove->getColumnValues('product_id');
            $itemsToRemove->walk('delete');
        }

        $this->commit();

        /**
         * Configurable Products
         */
        $productIds = array_diff($productIds, $productsIdRemoved);

        foreach ($productIds as $productId) {
            /** @var $items Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection */
            $items = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');

            $childrenCount = $items->addFieldToFilter('products_listing_id', $itemId)
                ->addFieldToFilter('parent_product_id', $productId)
                ->addFieldToFilter('status', array('eq' => Diglin_Ricento_Helper_Data::STATUS_LISTED))
                ->getSize();


            if (!$childrenCount) {
                $this->beginTransaction();

                $readConnection = $this->getReadConnection();

                $select = $readConnection
                    ->select()
                    ->from(array('pli' => $this->getTable('diglin_ricento/products_listing_item')), 'item_id')
                    ->where('product_id = ?', $productId)
                    ->deleteFromSelect('pli');

                if (!empty($select) && !is_numeric($select)) {
                    $readConnection->query($select);
                }

                $items->walk('delete');

                $this->commit();

                $numberOfItemsToDelete++;
            }

            $numberOfListedItems += $childrenCount;
        }

        return array($numberOfItemsToDelete, $numberOfListedItems);
    }

    /**
     * Removes items by item id
     *
     * @param array $itemIds
     * @param int $itemId
     * @return int[] Returns two values: [number of removed products, number of not removed listed products]
     */
    public function removeProductsByItemIds(array $itemIds, $itemId)
    {
        /** @var $items Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection */
        $items = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
        $items
            ->addFieldToFilter('item_id', array('in' => $itemIds))
            ->addFieldToFilter('parent_product_id', new Zend_Db_Expr('NULL'))
            ->addFieldToFilter('type', array('neq' => Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));

        $itemsToRemove = clone $items;

        $numberOfListedItems = $items->addFieldToFilter('products_listing_id', $itemId)
            ->addFieldToFilter('status', array('eq' => Diglin_Ricento_Helper_Data::STATUS_LISTED))
            ->getSize();

        $numberOfItemsToDelete = $itemsToRemove->addFieldToFilter('products_listing_id', $itemId)
            ->addFieldToFilter('status', array('neq' => Diglin_Ricento_Helper_Data::STATUS_LISTED))
            ->count();

        $this->beginTransaction();

        $itemsIdRemoved = array();
        if ($numberOfItemsToDelete) {
            $itemsIdRemoved = $itemsToRemove->getColumnValues('item_id');
            $itemsToRemove->walk('delete');
        }

        $this->commit();

        /**
         * Configurable Products
         */
        $itemIds = array_diff($itemIds, $itemsIdRemoved);

        foreach ($itemIds as $itemId) {
            /** @var $items Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection */
            $items = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');

            $childrenCount = $items->addFieldToFilter('products_listing_id', $itemId)
                ->addFieldToFilter('parent_item_id', $itemId)
                ->addFieldToFilter('status', array('eq' => Diglin_Ricento_Helper_Data::STATUS_LISTED))
                ->getSize();

            if (!$childrenCount) {
                $this->beginTransaction();

                $readConnection = $this->getReadConnection();
                $select = $readConnection
                    ->select()
                    ->from(array('pli' => $this->getTable('diglin_ricento/products_listing_item')), 'item_id')
                    ->where('item_id = ?', $itemId)
                    ->deleteFromSelect('pli');

                if (!empty($select) && !is_numeric($select)) {
                    $readConnection->query($select);
                }

                $items->walk('delete');

                $this->commit();

                $numberOfItemsToDelete++;
            }

            $numberOfListedItems += $childrenCount;
        }

        return array($numberOfItemsToDelete, $numberOfListedItems);
    }
}