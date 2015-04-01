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
 * Class Diglin_Ricento_Model_Config_Source_Sync_Type
 */
class Diglin_Ricento_Model_Config_Source_Sync_Type extends Diglin_Ricento_Model_Config_Source_Abstract
{
    /**
     * @return array
     */
    public function toOptionHash()
    {
        $helper = Mage::helper('diglin_ricento');

        return array(
            Diglin_Ricento_Model_Sync_Job::TYPE_CHECK_LIST => $helper->__('Products Check Job'),
            Diglin_Ricento_Model_Sync_Job::TYPE_LIST => $helper->__('List Job'),
            Diglin_Ricento_Model_Sync_Job::TYPE_STOP => $helper->__('Stop List Job'),
            Diglin_Ricento_Model_Sync_Job::TYPE_ORDER => $helper->__('Sync Order Job'),
//            Diglin_Ricento_Model_Sync_Job::TYPE_SYNCLIST => $helper->__('Sync List Job'), // Hide to user
//            Diglin_Ricento_Model_Sync_Job::TYPE_CLOSED => $helper->__('Closed items'), // Hide to user
        );
    }
}