<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2011-2017 Diglin (http://www.diglin.com)
 */

/**
 * Class Diglin_Ricento_Helper_Product
 */
class Diglin_Ricento_Helper_Product extends Mage_Core_Helper_Abstract
{
    /**
     * @param Diglin_Ricento_Model_Products_Listing_Item $ricardoProductItem
     * @param Mage_CatalogInventory_Model_Stock_Item|null $stockItem
     */
    public function proceedInventoryUpdate(
        Diglin_Ricento_Model_Products_Listing_Item $ricardoProductItem,
        Mage_CatalogInventory_Model_Stock_Item $stockItem = null
    ) {
        $productList = $ricardoProductItem->getProductsListing();
        $salesOptions = $productList->getSalesOptions();

        if ($salesOptions->getSalesType() != Diglin_Ricento_Model_Config_Source_Sales_Type::BUYNOW) {
            return;
        }

        if (is_null($stockItem)) {
            $stockItem = Mage::getSingleton('cataloginventory/stock_item')->loadByProduct($ricardoProductItem->getProductId());
        }

        if (!$stockItem->getManageStock()) {
            return;
        }

        $realRemainingQty = $stockItem->getQty();

        $newQuantity = null;

        if ($realRemainingQty <= 0) {
            $newQuantity = 0;
        } else if ($realRemainingQty < $ricardoProductItem->getQtyInventory()) {
            $newQuantity = $realRemainingQty;
        } else if ($realRemainingQty >= $ricardoProductItem->getQtyInventory()) {
            return;
        }

        try {
            $sell = Mage::getSingleton('diglin_ricento/api_services_sell');

            if ($newQuantity > 0) {

                $ricardoProductItem->setQtyInventory($newQuantity);

                $sell->updateArticleBuyNowQuantity($ricardoProductItem);

                $ricardoProductItem->save();

                if (Mage::helper('diglin_ricento')->isDebugEnabled()) {
                    Mage::log(sprintf('Product Listing Item ID %s - Qty Inventory updated to %s',
                        $ricardoProductItem->getId(), $newQuantity), Zend_Log::INFO, Diglin_Ricento_Helper_Data::LOG_FILE);
                }

            } else if ($newQuantity <= 0) {
                $dispatcher = Mage::getSingleton('diglin_ricento/dispatcher');

                $dispatcher->dispatch(Diglin_Ricento_Model_Sync_Job::TYPE_SYNCLIST)->proceed();
                $dispatcher->dispatch(Diglin_Ricento_Model_Sync_Job::TYPE_TRANSACTION)->proceed();

                $sell->stopArticle($ricardoProductItem);

                $ricardoProductItem
                    ->setIsPlanned(null)
                    ->setRicardoArticleId(null)
                    ->setQtyInventory(null)
                    ->setStatus(Diglin_Ricento_Helper_Data::STATUS_STOPPED)
                    ->save();

                Mage::log(sprintf('Product Listing Item ID %s - Qty Inventory is 0 - Article is stopped',
                    $ricardoProductItem->getId()), Zend_Log::INFO, Diglin_Ricento_Helper_Data::LOG_FILE);
            }
        } catch (Exception $e) {
            $helper = Mage::helper('diglin_ricento');
            $message = $helper->__('Error while updating quantity on ricardo side %s for the product listing item ID %d',
                $e->getMessage(), $ricardoProductItem->getId());

            if (Mage::app()->getStore()->isAdmin()) {
                Mage::getSingleton('adminhtml/session')->addError($message);
            }

            Mage::log($message, Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE);

            if ($helper->canSendEmailNotification()) {
                Mage::helper('diglin_ricento/tools')->sendAdminNotification($message);
            }

            return;
        }
    }
}