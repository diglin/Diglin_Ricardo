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
 * Class Diglin_Ricento_Block_Adminhtml_Dashboard_Renderer_Price
 */
class Diglin_Ricento_Block_Adminhtml_Dashboard_Renderer_Price extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $websiteId = $row->getWebsiteId();

        if (Mage::registry('products_listing')) {
            $websiteId = Mage::registry('products_listing')->getWebsiteId();
        }

        $value = $this->_getValue($row);
        if (!empty($value)) {
            return implode(' / ', Mage::helper('diglin_ricento/price')->formatDoubleCurrency($value, $websiteId));
        }
        return $value;
    }
}