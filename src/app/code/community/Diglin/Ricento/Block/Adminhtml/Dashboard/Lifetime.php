<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2015 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Diglin_Ricento_Block_Adminhtml_Dashboard_Lifetime
 */
class Diglin_Ricento_Block_Adminhtml_Dashboard_Lifetime extends Mage_Adminhtml_Block_Template
{
    /**
     * Returns formatted sum of all transaction prices
     * 
     * @return string
     */
    public function getValueHtml()
    {
        /** @var Diglin_Ricento_Model_Resource_Sales_Transaction $transactionResource */
        $transactionResource = Mage::getResourceModel('diglin_ricento/sales_transaction');
        return implode(' / ', Mage::helper('diglin_ricento/price')->formatDoubleCurrency($transactionResource->getTotalSalesValue(), null, Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY));
    }
}
