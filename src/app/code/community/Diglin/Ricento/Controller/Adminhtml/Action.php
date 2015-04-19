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
abstract class Diglin_Ricento_Controller_Adminhtml_Action extends Mage_Adminhtml_Controller_Action
{
    protected function _construct()
    {
        // Important to get appropriate translation from this module
        $this->setUsedModuleName('Diglin_Ricento');
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('ricento/listing');
    }

    /**
     * @return bool|Diglin_Ricento_Model_Products_Listing
     */
    protected function _initListing()
    {
        $registeredListing = $this->_getListing();
        if ($registeredListing) {
            return $registeredListing;
        }
        $id = (int)$this->getRequest()->getParam('id');
        if (!$id) {
            $this->_getSession()->addError('Products Listing not found.');
            return false;
        }

        $productsListing = Mage::getModel('diglin_ricento/products_listing')->load($id);
        Mage::register('products_listing', $productsListing);

        return $this->_getListing();
    }

    /**
     * @return Diglin_Ricento_Model_Products_Listing
     */
    protected function _getListing()
    {
        return Mage::registry('products_listing');
    }

    protected function _isApiReady()
    {
        $helper = Mage::helper('diglin_ricento');
        $helperApi = Mage::helper('diglin_ricento/api');
        $websiteId = $this->_initListing()->getWebsiteId();

        return $helper->isEnabled($websiteId) && $helper->isConfigured($websiteId) && !$helperApi->apiTokenCredentialGoingToExpire($websiteId);
    }

    /**
     * @return boolean
     */
    protected function _savingAllowed()
    {
        return true;
    }

    /**
     * Create products listing items children for configurable product
     *
     * @return $this
     */
    protected function _prepareConfigurableProduct()
    {
        $productListingId = $this->_initListing()->getId();
        $statuses = array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD);

        /**
         * Delete not listed children products of configurable product
         */
        $collectionListingItemChildren = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
        $collectionListingItemChildren
            ->addFieldToFilter('parent_product_id', array('notnull' => 1))
            ->addFieldToFilter('status', array('nin' => $statuses))
            ->addFieldToFilter('products_listing_id', $productListingId);

        $collectionListingItemChildren->walk('delete');

        /**
         * Get listed children products of configurable product
         */
        $collectionListingItemChildren = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
        $collectionListingItemChildren
            ->addFieldToFilter('parent_product_id', array('notnull' => 1))
            ->addFieldToFilter('status', array('in' => $statuses))
            ->addFieldToFilter('products_listing_id', $productListingId);

        $listedChildrenIds = $collectionListingItemChildren->getColumnValues('product_id');

        /**
         * Get the list of configurable products
         */
        $collectionListingItem = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
        $collectionListingItem
            ->addFieldToFilter('type', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            ->addFieldToFilter('status', array('nin' => $statuses))
            ->addFieldToFilter('products_listing_id', $productListingId);

        /**
         * Get all products of configurable products for a list
         *
         * @var $item Diglin_Ricento_Model_Products_Listing_Item
         */
        foreach ($collectionListingItem->getItems() as $item) {
            /**
             * Get all children products
             */
            $collection = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect('sku')
                ->addFilterByRequiredOptions()
                ->addFieldToFilter('entity_id', array('in' => $item->getProduct()->getUsedProductIds(), 'nin' => array('nin' => $listedChildrenIds)));

            $attributes = $item->getProduct()->getConfigurableAttributes();

            foreach ($attributes as $attribute) {
                $collection->addAttributeToSelect($attribute->getProductAttribute()->getAttributeCode());
                $collection->addAttributeToFilter($attribute->getProductAttribute()->getAttributeCode(), array('notnull' => 1));
            }

            foreach ($collection->getItems() as $childProduct) {

                $configurableChild = array();

                foreach ($attributes as $attribute) {

                    $productAttribute = $attribute->getProductAttribute();
                    $attributeValueId = $childProduct->getData($productAttribute->getAttributeCode());
                    if ($attributeValueId) {

                        $priceVariation = array();
                        $subtitle = $productAttribute->getFrontendLabel() . ': ';

                        /**
                         * Get price variation
                         */
                        $prices = $attribute->getData('prices');
                        foreach ($prices as $price) {
                            if ($price['pricing_value'] != 0 && $price['value_index'] == $attributeValueId) {
                                $priceVariation = array('pricing_value' => $price['pricing_value'], 'is_percent' => $price['is_percent']);
                                break;
                            }
                        }

                        /**
                         * Get attribute label to be used as subtitle
                         */
                        foreach ($productAttribute->getSource()->getAllOptions() as $option) {
                            if ($attributeValueId == $option['value']) {
                                $subtitle .= $option['label'];
                            }
                        }

                        $configurableChild['options'][$attributeValueId] = array_merge(array('subtitle' => $subtitle), $priceVariation);
                    }
                }

                /**
                 * Save as new products listing item
                 */
                $itemChild = clone $item;
                $itemChild
                    ->setId(null)
                    ->setCreatedAt(Mage::getSingleton('core/date')->gmtDate())
                    ->setUpdatedAt(null)
                    ->setProductId($childProduct->getId())
                    ->setAdditionalData(Mage::helper('core')->jsonEncode($configurableChild))
                    ->setParentItemId($item->getId())
                    ->setParentProductId($item->getProductId())
                    ->setType(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                    ->save();
            }
        }

        return $this;
    }
}