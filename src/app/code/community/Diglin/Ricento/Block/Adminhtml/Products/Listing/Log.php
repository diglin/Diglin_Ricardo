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
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_products_listing_log';
        $this->_blockGroup = 'diglin_ricento';
        $this->_headerText = $this->__('Products Listing Log "%s"', $this->getProductsListing()->getTitle());

        parent::__construct();

        $this->removeButton('add');

        $this->addButton('show_listing', array(
            'label' => $this->__('Edit "%s"', $this->getProductsListing()->getTitle()),
            'onclick' => 'setLocation(\'' . $this->getEditUrl() .'\')',
        ));
    }

    /**
     * @return string
     */
    public function getEditUrl()
    {
        return $this->getUrl('*/ricento_products_listing/edit', array('id' => $this->getProductsListing()->getId()));
    }

    /**
     * @return Diglin_Ricento_Model_Products_Listing
     */
    public function getProductsListing()
    {
        return Mage::registry('products_listing');
    }
}