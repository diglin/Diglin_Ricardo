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

class Diglin_Ricento_Helper_Dashboard_Turnover extends Mage_Adminhtml_Helper_Dashboard_Abstract
{
    /**
     * @var Diglin_Ricento_Model_Resource_Sales_Transaction_Collection
     */
    protected $_collection;

    /**
     * Load collection with aggregated data for report
     */
    protected function _initCollection()
    {
        $this->_collection = Mage::getResourceModel('diglin_ricento/sales_transaction_collection');
        $this->_collection->prepareReport();
        $this->_collection->load();
    }
}