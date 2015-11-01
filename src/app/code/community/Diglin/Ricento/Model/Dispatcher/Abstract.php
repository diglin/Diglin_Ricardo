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

use \Diglin\Ricardo\Managers\SellerAccount\Parameter\UnsoldArticlesParameter;

/**
 * Class Diglin_Ricento_Model_Dispatcher_Abstract
 */
abstract class Diglin_Ricento_Model_Dispatcher_Abstract
{
    /**
     * @var int
     */
    protected $_limit = 200;

    /**
     * @var string
     */
    protected $_progressStatus = Diglin_Ricento_Model_Sync_Job::PROGRESS_RUNNING;

    /**
     * @var Diglin_Ricento_Model_Resource_Products_Listing_Log
     */
    protected $_listingLog;

    /**
     * @var Diglin_Ricento_Model_Sync_Job
     */
    protected $_currentJob;

    /**
     * @var Diglin_Ricento_Model_Sync_Job_Listing
     */
    protected $_currentJobListing;

    /**
     * @var bool
     */
    protected $_jobHasSuccess = false;

    /**
     * @var bool
     */
    protected $_jobHasError = false;

    /**
     * @var bool
     */
    protected $_jobHasWarning = false;

    /**
     * @var string
     */
    protected $_itemStatus;

    /**
     * @var string
     */
    protected $_itemMessage = null;

    /**
     * @var int
     */
    protected $_productsListingId;

    /**
     * @var null
     */
    protected $_jobType = null;

    /**
     * @var int
     */
    protected $_logType = 0;

    /**
     * @var int
     */
    protected $_totalProceed = 0;

    /**
     * @var int
     */
    protected $_totalSuccess = 0;

    /**
     * @var int
     */
    protected $_totalError = 0;

    /**
     * @var null | Mage_Directory_Model_Currency
     */
    protected $_currency = null;

    /**
     * @return $this
     */
    public function proceed()
    {
        $this->_runJobs();
        return $this;
    }

    /**
     * @return mixed
     */
    abstract protected function _proceed();

    /**
     * @param string $type
     * @param array $progress
     * @return Diglin_Ricento_Model_Resource_Sync_Job_Collection
     */
    protected function _getJobCollection($type, $progress)
    {
        $jobsCollection = Mage::getResourceModel('diglin_ricento/sync_job_collection');
        $jobsCollection
            ->addFieldToFilter('job_type', $type)
            ->addFieldToFilter('progress', array('in' => (array) $progress));

        return $jobsCollection;
    }

    /**
     * @return $this
     */
    protected function _startJob()
    {
        $this->_currentJob
            ->setStartedAt(Mage::getSingleton('core/date')->gmtDate())
            ->setProgress($this->_progressStatus)
            ->save();

        return $this;
    }

    /**
     * @return Diglin_Ricento_Model_Resource_Products_Listing_Log
     */
    protected function _getListingLog()
    {
        if (!$this->_listingLog) {
            $this->_listingLog = Mage::getResourceModel('diglin_ricento/products_listing_log');
        }
        return $this->_listingLog;
    }

    /**
     * @param array $statuses
     * @param null $lastItemId
     * @return Diglin_Ricento_Model_Resource_Products_Listing_Item_Collection
     */
    protected function _getItemCollection(array $statuses, $lastItemId = null)
    {
        $itemCollection = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
        $itemCollection
            ->addFieldToFilter('status', array('in' => $statuses))
            ->addFieldToFilter('products_listing_id', array('eq' => $this->_productsListingId))
            ->addFieldToFilter('item_id', array('gt' => (int) $lastItemId))
            ->addFieldToFilter('type', array('nin' => Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));

        if ($this->_limit) {
            $itemCollection->setPageSize($this->_limit);
        }

        return $itemCollection;
    }

    /**
     * @return $this
     */
    protected function _runJobs()
    {
        /**
         * Get all pending jobs of specified type
         * The risk is very low to have for this collection a big quantity of data
         */
        $jobsCollection = $this->_getJobCollection($this->_jobType,
            array(Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING, Diglin_Ricento_Model_Sync_Job::PROGRESS_CHUNK_RUNNING));

        $helper = $this->_getHelper();

        try {
            /* @var $job Diglin_Ricento_Model_Sync_Job */
            foreach ($jobsCollection->getItems() as $this->_currentJob) {

                $message = array();

                $this->_currentJobListing = Mage::getModel('diglin_ricento/sync_job_listing')->load($this->_currentJob->getId(), 'job_id');
                $this->_productsListingId = (int) $this->_currentJobListing->getProductsListingId();
                $this->_totalProceed = (int) $this->_currentJobListing->getTotalProceed();
                $this->_totalSuccess = (int) $this->_currentJobListing->getTotalSuccess();
                $this->_totalError = (int) $this->_currentJobListing->getTotalError();

                if (!$this->_productsListingId) {
                    return $this;
                }

                /**
                 * We set the status to block any parallel process to execute the same job. In case of recoverable error, the job status is reverted
                 */
                if ($this->_currentJob->getProgress() == Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING) {
                    $this->_startJob();
                }

                /**
                 * Cleanup in case of running again the same job
                 */
                if (!$this->_currentJobListing->getLastItemId()) {
                    $this->_getListingLog()->cleanSpecificJob($this->_currentJob->getId());
                }

                /**
                 * All the Magic is here ...
                 */
                $start = microtime(true);
                $this->_proceed();
                $end = microtime(true);

                if ($helper->isDebugEnabled()) {
                    Mage::log('Time to run the job id ' . $this->_currentJob->getId() . ' in ' . ($end - $start) . ' sec', Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);
                }

                if ($this->_jobHasError || $this->_currentJob->getJobStatus() == Diglin_Ricento_Model_Sync_Job::STATUS_ERROR) {
                    $typeError = $helper->__('errors');
                    $jobStatus = Diglin_Ricento_Model_Sync_Job::STATUS_ERROR;
                } else if ($this->_jobHasWarning || $this->_currentJob->getJobStatus() == Diglin_Ricento_Model_Sync_Job::STATUS_WARNING) {
                    $typeError = $helper->__('warnings');
                    $jobStatus = Diglin_Ricento_Model_Sync_Job::STATUS_WARNING;
                } else {
                    $typeError = $helper->__('success');
                    $jobStatus = Diglin_Ricento_Model_Sync_Job::STATUS_SUCCESS;
                }

                /**
                 * In case we proceed chunk of data or not
                 */
                $endedAt = null;

                if ($this->_totalProceed >= $this->_currentJobListing->getTotalCount()) {
                    $this->_progressStatus = Diglin_Ricento_Model_Sync_Job::PROGRESS_COMPLETED;
                    $endedAt = Mage::getSingleton('core/date')->gmtDate();

                    $this->_currentJobListing
                        ->setLastItemId(null)
                        ->setTotalError($this->_totalError)
                        ->setTotalSuccess($this->_totalSuccess)
                        ->setTotalProceed($this->_totalProceed)
                        ->save();

                    $completedMessage = $helper->__('The Job has finished with %s.', $typeError);
                    if ($this->_jobHasError) {
                        $completedMessage .= ' ' . $helper->__('Please, view the <a href="%s">log</a> for details.', $this->_getLogListingUrl());
                    }

                    $message[] = $completedMessage;

                } else {
                    $message[] = $this->_currentJob->getJobMessage(true);
                    $this->_progressStatus = Diglin_Ricento_Model_Sync_Job::PROGRESS_CHUNK_RUNNING;
                }

                $statusMessage = $this->_getStatusMessage($jobStatus);
                if (!empty($statusMessage)) {
                    $message[] = $statusMessage;
                }

                $this->_currentJob->getResource()->saveCurrentJob($this->_currentJob->getId(), array(
                    'job_message' => $this->_jsonEncode($message),
                    'job_status' => $jobStatus,
                    'ended_at' => $endedAt,
                    'progress' => $this->_progressStatus,
                ));

                $this->_proceedAfter();

                $this->_currentJob = null;
                $this->_currentJobListing = null;
                $this->_itemMessage = null;
                $this->_itemStatus = false;
                $this->_productsListingId = null;
                $this->_totalError = 0;
                $this->_totalProceed = 0;
                $this->_totalSuccess = 0;
                $this->_jobHasError = false;
                $this->_jobHasSuccess = false;
                $this->_jobHasWarning = false;
            }
        } catch (Exception $e) {
            Mage::log("\n" . $e->__toString(), Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            $this->_currentJob = (isset($this->_currentJob)) ? $this->_currentJob : null;
            $this->_setJobError($e);
        }

        unset($jobsCollection);

        return $this;
    }

    /**
     * @param string $method
     * @return string
     */
    protected function _getProceedMethod($method)
    {
        return '_proceed' . str_replace(' ', '', ucwords(str_replace('_', ' ', $method)));
    }

    /**
     * @return Diglin_Ricento_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('diglin_ricento');
    }

    /**
     * @return string
     */
    protected function _getNoItemMessage()
    {
        return $this->_getHelper()->__('No item is ready for this job. No action has been done.');
    }

    /**
     * @param mixed $content
     * @return string
     */
    protected function _jsonEncode($content)
    {
        return Mage::helper('core')->jsonEncode($content);
    }

    /**
     * We build an url which will be replaced during its display in the log grid cause of issue with secure url key
     *
     * @return string
     */
    protected function _getLogListingUrl()
    {
        return '{{adminhtml url="*/ricento_log/listing/" _query_id=' . $this->_productsListingId . ' _query_job_id=' . $this->_currentJob->getId() . '}}';
    }

    /**
     * We build an url which will be replaced during its display in the log grid cause of issue with secure url key
     *
     * @return string
     */
    protected function _getProductListingEditUrl()
    {
        return '{{adminhtml url="*/ricento_products_listing/edit/" _query_id=' . $this->_productsListingId . '}}';
    }

    /**
     * We build an url which will be replaced during its display in the log grid cause of issue with secure url key
     *
     * @return string
     */
    protected function _getListUrl()
    {
        return '{{adminhtml url="*/ricento_products_listing/list/" _query_id=' . $this->_productsListingId . '}}';
    }

    /**
     * @param $jobStatus
     * @return $this
     */
    protected function _getStatusMessage($jobStatus)
    {
        return '';
    }

    /**
     * @return $this
     */
    protected function _proceedAfter()
    {
        return $this;
    }

    /**
     * @param Exception $e
     * @return $this
     */
    protected function _setJobError(Exception $e)
    {
        if (isset($this->_currentJob) && $this->_currentJob instanceof Diglin_Ricento_Model_Sync_Job && $this->_currentJob->getId()) {
            $this->_currentJob
                ->setProgress(Diglin_Ricento_Model_Sync_Job::PROGRESS_CHUNK_RUNNING)
                ->setJobStatus(Diglin_Ricento_Model_Sync_Job::STATUS_ERROR)
                ->setJobMessage(array($e->__toString()))
                ->save();
        }
        return $this;
    }

    /**
     * @param Exception $e
     * @param null|Diglin_Ricento_Model_Api_Services_Abstract $lastService
     * @return $this
     */
    protected function _handleException(Exception $e, $lastService = null)
    {
        Mage::log("\n" . $e->__toString(), Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);

        if ($lastService instanceof Diglin_Ricento_Model_Api_Services_Abstract) {
            Mage::log($lastService->getLastApiDebug(), Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);
        }

        $message = $this->_getHelper()->__($e->getMessage());
        $this->_itemMessage = array('errors' =>
            array(
                $this->_getHelper()->__('Error Code: %d', $e->getCode()),
                function_exists('mb_strcut') ? mb_strcut($message, 0, 1024 * 1024) : $message
            ));

        $this->_jobHasError = true;
        $this->_itemStatus = Diglin_Ricento_Model_Products_Listing_Log::STATUS_ERROR;
        ++$this->_totalError;

        return $this;
    }

    /**
     * @param $errorType
     * @param array $errorCodes
     * @return array
     */
    protected function _handleErrorCodes($errorType, array $errorCodes)
    {
        $labels = array();

        /* @var $classname Diglin\Ricardo\Enums\AbstractEnums */
        $classname = '\Diglin\Ricardo\Enums\\' . $errorType;
        if (class_exists($classname)) {
            foreach ($errorCodes as $errorCode) {
                $labels[] = $classname::getLabel($errorCode);
            }
        }

        return $labels;
    }

    /**
     * @param int $productsListingId
     * @return $this
     */
    public function setProductsListingId($productsListingId)
    {
        $this->_productsListingId = $productsListingId;
        return $this;
    }

    /**
     * @return int
     */
    public function getProductsListingId()
    {
        return $this->_productsListingId;
    }

    /**
     * @return Diglin_Ricento_Model_Products_Listing
     */
    protected function _getListing()
    {
        return Mage::getModel('diglin_ricento/products_listing')->load($this->_productsListingId);
    }

    /**
     * @param $item
     * @return null|Varien_Object
     */
    protected function _getUnsoldArticles($item)
    {
        $article = null;
        $unsoldArticlesParameter = new UnsoldArticlesParameter();

        $unsoldArticlesParameter
            ->setInternalReferenceFilter($item->getInternalReference())
            ->setMinimumEndDate($this->_getHelper()->getJsonDate(time() - (1 * 24 * 60 * 60)));

        $articles = $this->_getSellerAccount()->getUnsoldArticles($unsoldArticlesParameter);
        if (!is_null($articles) && is_array($articles['UnsoldArticles']) && isset($articles['UnsoldArticles'][0])) {
            $article = $this->_getHelper()->extractData($articles['UnsoldArticles'][0]);
        }

        return $article;
    }

    /**
     * @return Diglin_Ricento_Model_Api_Services_Selleraccount
     */
    protected function _getSellerAccount()
    {
        return Mage::getSingleton('diglin_ricento/api_services_selleraccount')
            ->setCanUseCache(false)
            ->setCurrentWebsite($this->_getListing()->getWebsiteId());
    }

    /**
     * @return Mage_Directory_Model_Currency
     */
    protected function _getCurrency()
    {
        if (!$this->_currency) {
            $this->_currency = Mage::getModel('directory/currency')->load(Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY);
        }

        return $this->_currency;
    }
}