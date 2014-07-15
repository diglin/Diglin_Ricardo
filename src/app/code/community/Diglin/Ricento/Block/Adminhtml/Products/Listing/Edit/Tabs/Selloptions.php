<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2011-2014 Diglin (http://www.diglin.com)
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Edit_Tabs_Selloptions
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface

{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $htmlIdPrefix = 'diglin_ricento_';
        $form->setHtmlIdPrefix($htmlIdPrefix);

        $fieldsetCategory = $form->addFieldset('fieldset_category', array('legend' => Mage::helper('catalog')->__('Category')));
        $fieldsetCategory->addField('ricardo_category_use_mapping', 'radios', array(
            'name'      => 'ricardo_category_use_mapping',
            'label'     => $this->__('Ricardo Category'),
            'separator' => '<br>',
            'values'    => array(
                array('value' => 0, 'label' => $this->__('Use Magento / Ricardo Category mapping (if mapping does not exist, an error message will be triggered while preparing the synchronization to Ricardo)')),
                array('value' => 1, 'label' => $this->__('Select Ricardo Category'))
            )
        ));
        $fieldsetCategory->addType('ricardo_category', Mage::getConfig()->getBlockClassName('diglin_ricento/adminhtml_catalog_category_form_renderer_mapping'));
        $fieldsetCategory->addField('ricardo_category', 'ricardo_category', array(
            'name'  => 'ricardo_category',
            'label' => $this->__('Select the category')
        ));


        $fieldsetType = $form->addFieldset('fieldset_type', array('legend' => $this->__('Type of sales')));
        $fieldsetType->addField('sales_type', 'select', array(
            'name'      => 'sales_type',
            'required'  => true,
            'label'     => $this->__('Type of sales'),
            'options'   => Mage::getModel('diglin_ricento/attribute_sales_type')->getAllOptions(),
            'onchange'  => '' //TODO toggle fieldset
        ));
        $fieldsetTypeFixPrice = $fieldsetType->addFieldset('fieldset_type_fixprice', array('legend' => $this->__('Fix price')));
        $fieldsetTypeFixPrice->addField('price_source_attribute_id', 'select', array(
            'name'    => 'price_source_attribute_id',
            'label'   => $this->__('Source'),
            'options' => Mage::getModel('diglin_ricento/attribute_sales_price_source')->getAllOptions()
        ));
        $fieldsetTypeFixPrice->addType('fieldset_inline', Mage::getConfig()->getBlockClassName('diglin_ricento/adminhtml_form_element_fieldset_inline'));
        $fieldsetPriceChange = $fieldsetTypeFixPrice->addField('fieldset_price_change', 'fieldset_inline', array(
            'label'             => $this->__('Price Change'),
        ));
        $fieldsetPriceChange->addField('price_change_type', 'select', array(
            'name'               => 'price_change_type',
            'after_element_html' => ' +&nbsp;',
            'no_span'            => true,
            'options'            => Mage::getModel('diglin_ricento/attribute_sales_price_method')->getAllOptions()
        ));
        $fieldsetPriceChange->addField('price_change', 'text', array(
            'name'    => 'price_change',
            'no_span' => true
        ));
        $fieldsetTypeFixPrice->addField('sales_auction_direct_buy', 'checkbox', array(
            'name'  => 'sales_auction_direct_buy',
            'label' => $this->__('Allow Direct Buy (in case of auction type of sales)'),
            'value' => '1'
        ));
        $fieldsetTypeFixPrice->addField('fix_currency', 'label', array(
            'name'  => 'fix_currency',
            'label' => $this->__('Currency'),
            'value' => 'CHF'
        ));
        $fieldsetTypeAuction = $fieldsetType->addFieldset('fieldset_type_auction', array('legend' => $this->__('Auction')));
        $fieldsetTypeAuction->addField('sales_auction_start_price', 'text', array(
            'name'  => 'sales_auction_start_price',
            'label' => $this->__('Start price')
        ));
        $fieldsetTypeAuction->addField('sales_auction_increment', 'text', array(
            'name'  => 'sales_auction_increment',
            'label' => $this->__('Increment')
        ));
        $fieldsetTypeAuction->addField('auction_currency', 'label', array(
            'name'  => 'auction_currency',
            'label' => $this->__('Currency'),
            'value' => 'CHF'
        ));


        $fieldsetSchedule = $form->addFieldset('fieldset_schedule', array('legend' => $this->__('Schedule')));
        $fieldsetSchedule->addType('start_date_picker', Mage::getConfig()->getBlockClassName('diglin_ricento/adminhtml_form_element_date_start'));
        $fieldsetSchedule->addType('end_date_picker', Mage::getConfig()->getBlockClassName('diglin_ricento/adminhtml_form_element_date_end'));
        $fieldsetSchedule->addType('cycle_products', Mage::getConfig()->getBlockClassName('diglin_ricento/adminhtml_form_element_cycleproducts'));
        $fieldsetSchedule->addField('schedule_date_start', 'start_date_picker', array(
            'name'  => 'schedule_date_start',
            'label' => $this->__('Start')
        ));
        $fieldsetSchedule->addField('schedule_period_days', 'end_date_picker', array(
            'name'  => 'schedule_period_days',
            'label' => $this->__('End')
        ));
        $fieldsetSchedule->addField('schedule_reactivation', 'select', array(
            'name'    => 'schedule_reactivation',
            'label'   => $this->__('Reactivation'),
            'options' => $this->_getReactivationOptions()
        ));
        $fieldsetSchedule->addField('schedule_cycle_multiple_products', 'cycle_products', array(
            'name'  => 'schedule_cycle_multiple_products',
            'label' => $this->__('Cycle')
        ));
        $fieldsetSchedule->addField('schedule_overwrite_product_date_start', 'checkbox', array(
            'name'  => 'schedule_overwrite_product_date_start',
            'label' => $this->__('Overwrite all products starting date'),
            'value' => '1'
        ));


        $this->setForm($form);

        return parent::_prepareForm();
    }
    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Sell Options');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Sell Options');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }

    protected function _getReactivationOptions()
    {
        //TODO extract to helper or source model
        return array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            null => $this->__('Until sold')
        );
    }
}