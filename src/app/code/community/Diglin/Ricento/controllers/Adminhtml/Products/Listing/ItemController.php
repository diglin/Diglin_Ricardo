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
 * Class Diglin_Ricento_Adminhtml_Products_Listing_ItemController
 */
class Diglin_Ricento_Adminhtml_Products_Listing_ItemController extends Diglin_Ricento_Controller_Adminhtml_Products_Listing
{
    protected $_itemIds = array();

    /**
     * @return Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection
     */
    protected function _initItems()
    {
        /* @var $itemCollection Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection */
        $itemCollection = Mage::getModel('diglin_ricento/products_listing_item')->getCollection();

        if ($this->getRequest()->isPost()) {
            $this->_itemIds = array_map('intval', (array) $this->getRequest()->getPost('item', array()));
        } else {
            if ($this->getRequest()->getParam('item')) {
                $this->_itemIds = array_map('intval', explode(',', $this->getRequest()->getParam('item')));
            }
        }

        if ($this->_itemIds) {
            $itemCollection
                ->addFieldToFilter('products_listing_id', $this->_getListing()->getId())
                ->addFieldToFilter('item_id', array('in' => $this->_itemIds));
        } elseif ($this->getRequest()->getParam('product_id')) {
            $itemCollection
                ->addFieldToFilter('products_listing_id', $this->_getListing()->getId())
                ->addFieldToFilter('product_id', array('in' => $this->getRequest()->getParam('product_id')));
        } else {
            $itemCollection
                ->addFieldToFilter('item_id', array('in' => explode(',', $this->getRequest()->getPost('item_ids'))));
            $this->_itemIds = $itemCollection->getColumnValues('item_id');
        }

        Mage::register('selected_items', $itemCollection->load());

        return $itemCollection;
    }

    /**
     * Returns items that are selected to be configured
     *
     * @return Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection
     */
    public function getSelectedItems()
    {
        return Mage::registry('selected_items');
    }

    /**
     * Configure individually or a group of products belonging to a products listing
     */
    public function configureAction()
    {
        if (!$this->_initListing()) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        if ($this->_initItems()->count() == 0) {
            $this->_getSession()->addError($this->__('No products selected.'));
            $this->_redirect('*/products_listing/edit', array('id' => $this->_getListing()->getId()));
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Save the configuration of sales options and rules
     */
    public function saveAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            if (!$this->_initListing()) {
                $this->_getSession()->addError('Products Listing not found.');
                $this->_redirectUrl($this->_getRefererUrl());
                return;
            }
            $this->_initItems();
            try {
                if ($this->saveConfiguration($data)) {
                    foreach ($this->getSelectedItems() as $item) {
                        /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
                        if (!isset($data['sales_options']['use_products_list_settings'])) {
                            $item->setSalesOptionsId($item->getSalesOptions()->getId());
                        } else {
                            $item->setSalesOptionsId(new Zend_Db_Expr('null'));
                        }
                        if (!isset($data['rules']['use_products_list_settings'])) {
                            $item->setRuleId($item->getShippingPaymentRule()->getId());
                        } else {
                            $item->setRuleId(new Zend_Db_Expr('null'));
                        }

                        if ($item->hasDataChanges()) {
                            $item->save();
                        }
                    }
                    $this->_getSession()->addSuccess($this->__('The configuration has been saved successfully.'));
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addException($e, $this->__('An error occurred while saving the configuration. Please, check your log files for more details.'));
            }
        }
        $this->_redirectUrl($this->_getEditUrl());

    }

    /**
     * @return bool
     */
    protected function _savingAllowed()
    {
        foreach ($this->getSelectedItems() as $item) {
            /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
            if ($item->getStatus() === Diglin_Ricento_Helper_Data::STATUS_LISTED || $item->getStatus() === Diglin_Ricento_Helper_Data::STATUS_SOLD) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return Varien_Data_Collection
     */
    protected function _getSalesOptions()
    {
        if (!$this->_salesOptionsCollection) {
            $this->_salesOptionsCollection = new Varien_Data_Collection();
            foreach ($this->getSelectedItems() as $item) {
                /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
                $this->_salesOptionsCollection->addItem($item->getSalesOptions());
            }
        }
        return $this->_salesOptionsCollection;
    }

    /**
     * @return Varien_Data_Collection
     */
    protected function _getShippingPaymentRule()
    {
        if (!$this->_shippingPaymentCollection) {
            $this->_shippingPaymentCollection = new Varien_Data_Collection();
            foreach ($this->getSelectedItems() as $item) {
                /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
                $this->_shippingPaymentCollection->addItem($item->getShippingPaymentRule());
            }
        }
        return $this->_shippingPaymentCollection;
    }

    /**
     * @return string
     */
    protected function _getEditUrl()
    {
        return $this->getUrl('*/*/configure', array('id' => $this->getRequest()->getParam('id'), 'item' => implode(',', $this->_itemIds)));
    }

    /**
     * @return string
     */
    protected function _getIndexUrl()
    {
        return $this->getUrl('*/products_listing/edit', array('id' => $this->getRequest()->getParam('id')));
    }

    /**
     * Show preview page of the product published on ricardo.ch
     */
    public function previewAction()
    {
        if (!$this->_initListing()) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirect('*/products_listing/index');
            return;
        }

        if ($this->_initItems()->count() == 0) {
            $this->_getSession()->addError($this->__('No products selected.'));
            $this->_redirect('*/products_listing/edit', array('id' => $this->_getListing()->getId()));
            return;
        }

        $this->_getSession()->addNotice($this->__('It\'s just a preview. Please, be aware that the display on ricardo.ch might be slightly different.'));

        $this->loadLayout();
        $this->renderLayout();
    }
}