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
 * Class Diglin_Ricento_Model_Config_Source_Watermark
 */
class Diglin_Ricento_Model_Config_Source_Watermark
{
    const NO = 0;
    const YES = 1;
    const DEFAULT_CONFIG = 2;

    /**
     * This method is used for the massaction for example
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('diglin_ricento');

        return array(

            array(
                'value' => 0,
                'label' => $helper->__('No')
            ),
            array(
                'value' => 1,
                'label' => $helper->__('Yes')
            ),
            array(
                'value' => 2,
                'label' => $helper->__('Use default store configuration')
            )
        );
    }
}