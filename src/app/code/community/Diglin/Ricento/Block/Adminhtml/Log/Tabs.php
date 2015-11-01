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
class Diglin_Ricento_Block_Adminhtml_Log_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    const TAB_LISTING            = 'listing';
    const TAB_ORDER              = 'order';
    const TAB_SYNCHRONIZATION    = 'synchronization';

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('widget/tabshoriz.phtml');
        $this->setId('ricentoLogTabs');
        $this->setDestElementId('tabs_container');
    }

    protected function _prepareLayout()
    {
        $this->addTab(self::TAB_SYNCHRONIZATION, $this->prepareTabSynchronization());
        $this->addTab(self::TAB_LISTING, $this->prepareTabListing());
        //$this->addTab(self::TAB_ORDER, $this->prepareTabOrder());

        $this->setActiveTab($this->getData('active_tab'));

        return parent::_prepareLayout();
    }

    protected function prepareTabListing()
    {
        $helper = Mage::helper('diglin_ricento');
        $tab = array(
            'label' => $helper->__('Listing'),
            'title' => $helper->__('Listing')
        );

        if ($this->getData('active_tab') == self::TAB_LISTING) {
            $tab['content'] = $this->getLayout()->createBlock('diglin_ricento/adminhtml_log_listing_grid', 'ricento_listing_log')->toHtml();
        } else {
            $tab['url'] = $this->getUrl('*/ricento_log/listing');
        }

        return $tab;
    }

    protected function prepareTabSynchronization()
    {
        $helper = Mage::helper('diglin_ricento');
        $tab = array(
            'label' => $helper->__('Synchronization'),
            'title' => $helper->__('Synchronization')
        );

        if ($this->getData('active_tab') == self::TAB_SYNCHRONIZATION) {
            $tab['content'] = $this->getLayout()->createBlock('diglin_ricento/adminhtml_log_sync_grid', 'ricento_sync_log')->toHtml();
        } else {
            $tab['url'] = $this->getUrl('*/ricento_log/sync');
        }

        return $tab;
    }

    protected function prepareTabOrder()
    {
        $helper = Mage::helper('diglin_ricento');
        $tab = array(
            'label' => $helper->__('Orders'),
            'title' => $helper->__('Orders')
        );

        if ($this->getData('active_tab') == self::TAB_ORDER) {
            $tab['content'] = $this->getLayout()->createBlock('diglin_ricento/adminhtml_order_log_grid', 'ricento_order_log')->toHtml();
        } else {
            $tab['url'] = $this->getUrl('*/ricento_log/order');
        }

        return $tab;
    }
}