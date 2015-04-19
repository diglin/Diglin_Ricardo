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
 * Class Diglin_Ricento_Model_Sync_Job_Listing
 *
 * @method int getJobId()
 * @method string getJobMessage()
 * @method int getProductsListingId()
 * @method int getLastItemId()
 * @method int getTotalCount()
 * @method int getTotalProceed()
 * @method int getTotalSuccess()
 * @method int getTotalError()
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 *
 * @method Diglin_Ricento_Model_Sync_Job_Listing setJobId(int $id)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setJobMessage(string $message)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setProductsListingId(int $id)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setLastItemId(int $id)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setTotalCount(int $total)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setTotalProceed(int $proceed)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setTotalSuccess(int $success)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setTotalError(int $error)
 * @method Diglin_Ricento_Model_Sync_Job_Listing setUpdatedAt(string $date)
 */

/**
 * Class Diglin_Ricento_Model_Sync_Job_Listing
 */
class Diglin_Ricento_Model_Sync_Job_Listing extends Diglin_Ricento_Model_Sync_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'sync_job_listing';

    /**
     * Parameter name in event
     *
     * @var string
     */
    protected $_eventObject = 'sync_job_listing';

    protected function _construct()
    {
        $this->_init('diglin_ricento/sync_job_listing');
    }
}