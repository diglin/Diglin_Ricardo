<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_
 * @copyright   Copyright (c) 2011-2016 Diglin (http://www.diglin.com)
 */

/**
 * Class Diglin_Ricento_Model_Image
 */
class Diglin_Ricento_Model_Image extends Varien_Image
{
    public function destruct()
    {
        $adapter = $this->_getAdapter();
        if ($adapter instanceof Varien_Image_Adapter_Gd2 && method_exists($adapter, 'destruct')) {
            $adapter->destruct();
        }

        return $this;
    }
}