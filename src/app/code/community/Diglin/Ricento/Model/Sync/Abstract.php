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
 * Class Diglin_Ricento_Model_Sync_Abstract
 */
abstract class Diglin_Ricento_Model_Sync_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * Set date of last update, convert payment method array to string
     *
     * @return Diglin_Ricento_Model_Sync_Abstract
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $this->setUpdatedAt(Mage::getSingleton('core/date')->gmtDate());

        if ($this->isObjectNew()) {
            $this->setCreatedAt(Mage::getSingleton('core/date')->gmtDate());
        }

        return $this;
    }

    /**
     * Save the current status of a job
     *
     * @param array $bind
     * @return int|bool
     */
    public function saveCurrentJob($bind)
    {
        $jobId = $this->getId();

        if (is_null($jobId)) {
            return false;
        }

        return $this->getResource()->saveCurrentJob($jobId, $bind);
    }
}