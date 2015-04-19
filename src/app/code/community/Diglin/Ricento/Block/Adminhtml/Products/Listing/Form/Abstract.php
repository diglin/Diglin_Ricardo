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
 * Class Diglin_Ricento_Block_Adminhtml_Products_Listing_Form_Abstract
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Form_Abstract extends Mage_Adminhtml_Block_Widget_Form
{
    public function isReadonlyForm()
    {
        return $this->_getListing()->getStatus() === Diglin_Ricento_Helper_Data::STATUS_LISTED;
    }

    public function getReadonlyNote()
    {
        return $this->__('Listed listings cannot be modified. Stop the listing first to make changes.');
    }

    /**
     * @return Diglin_Ricento_Model_Products_Listing
     */
    protected function _getListing()
    {
        $listing = Mage::registry('products_listing');
        if (!$listing) {
            Mage::throwException('Products listing not loaded');
        }
        return $listing;
    }

    protected function _initFormValues()
    {
        if ($this->isReadonlyForm()) {
            $this->getForm()->addField('readonly_note', 'note', array(
                'text' => '<ul class="messages"><li class="warning-msg"><span>' .
                    $this->getReadonlyNote() .
                    '</span></li></ul>'
            ), '^');
            Mage::helper('diglin_ricento')->disableForm($this->getForm());
        }
        return parent::_initFormValues();
    }

    /**
     * Get countable text for js event
     *
     * @param string $prefix
     * @return string
     */
    public function getCountableText($prefix)
    {
        return '<strong><span id="' . $prefix . '_result__all">0</span></strong>';
    }
}