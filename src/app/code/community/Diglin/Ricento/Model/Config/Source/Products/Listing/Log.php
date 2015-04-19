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
 * Class Diglin_Ricento_Model_Config_Source_Products_Listing_Log
 */
class Diglin_Ricento_Model_Config_Source_Products_Listing_Log extends Diglin_Ricento_Model_Config_Source_Abstract
{
    public function toOptionHash()
    {
        $helper = Mage::helper('diglin_ricento');

        return array(
            Diglin_Ricento_Model_Products_Listing_Log::LOG_TYPE_CHECK => $helper->__('Product item checks'),
            Diglin_Ricento_Model_Products_Listing_Log::LOG_TYPE_LIST => $helper->__('List product item to ricardo.ch'),
            Diglin_Ricento_Model_Products_Listing_Log::LOG_TYPE_STOP => $helper->__('Stop product item to ricardo.ch'),
        );
    }
}