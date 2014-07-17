<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2011-2014 Diglin (http://www.diglin.com)
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Item_Edit_Tabs_Selloptions
    extends Diglin_Ricento_Block_Adminhtml_Products_Listing_Edit_Tabs_Selloptions

{
    protected function _prepareForm()
    {
        parent::_prepareForm();
        $this->getForm()->addField('use_products_list_settings', 'checkbox', array(
            'name'    => 'sales_options[use_product_list_settings]',
            'label'   => 'Use Product List Settings',
            'onclick' => "var self = this; this.form.getElements().each(function(element) { if (element!=self && element.id.startsWith('{$this->getForm()->getHtmlIdPrefix()}')) element.disabled=self.checked; })"
        ), '^');
        return $this;
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
     * Returns sales options model
     *
     * @return Diglin_Ricento_Model_Sales_Options
     */
    public function getSalesOptions()
    {
        if ($this->_model) {
            return $this->_model;
        }
        if (count($this->getSelectedItems()) === 1) {
            $this->_loadSalesOptionsFromItem($this->getSelectedItems()->getFirstItem());
        }
        if (!$this->_model) {
            $this->_model = $this->_getListing()->getSalesOptions();
        }
        return $this->_model;
    }

    protected function _loadSalesOptionsFromItem(Diglin_Ricento_Model_Products_Listing_Item $item)
    {
        if ($item->getSalesOptionsId()) {
            $this->_model = $item->getSalesOptions();
        }
    }
}