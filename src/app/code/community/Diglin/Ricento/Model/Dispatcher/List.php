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

mb_internal_encoding('UTF-8');

use \Diglin\Ricardo\Managers\Sell\Parameter\InsertArticlesParameter;

/**
 * Class Diglin_Ricento_Model_Dispatcher_List
 */
class Diglin_Ricento_Model_Dispatcher_List extends Diglin_Ricento_Model_Dispatcher_Abstract
{
    /**
     * @var int
     */
    protected $_logType = Diglin_Ricento_Model_Products_Listing_Log::LOG_TYPE_LIST;

    /**
     * @var string
     */
    protected $_jobType = Diglin_Ricento_Model_Sync_Job::TYPE_LIST;

    /**
     * @return $this
     */
    protected function _proceed()
    {
        $job = $this->_currentJob;

        $sell = Mage::getSingleton('diglin_ricento/api_services_sell');
        $sell->setCurrentWebsite($this->_getListing()->getWebsiteId());

        $i = 1;
        $flush = false;
        $totalBulkToInsert = 5; // to limit memory usage
        $correlationItems = array();
        $insertArticles = new InsertArticlesParameter();

        /**
         * Status of the collection must be the same as Diglin_Ricento_Model_Resource_Products_Listing_Item::countReadyTolist
         */
        $itemCollection = $this->_getItemCollection(array(Diglin_Ricento_Helper_Data::STATUS_READY), $this->_currentJobListing->getLastItemId());
        $totalItems = $itemCollection->getSize();

        if (!$totalItems) {
            $job->setJobMessage(array($this->_getNoItemMessage()));
            $this->_progressStatus = Diglin_Ricento_Model_Sync_Job::PROGRESS_COMPLETED;
            return $this;
        }

        /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
        foreach ($itemCollection->getItems() as $item) {

            $baseInsert = $item->getBaseInsertArticleWithTracking();
            $correlationItems[$baseInsert->getCorrelationKey()] = array(
                'item_id'       => $item->getId(),
                'product_title' => $item->getProductTitle(),
                'product_id'    => $item->getProductId(),
                'product_qty'   => $item->getProductQty(),
                'skipped'       => (bool) ($item->getRicardoArticleId())
            );

            if (!$item->getRicardoArticleId()) { // skip still live article
                $insertArticles->setArticles($baseInsert, $flush);
            }

            $flush = false;
            $totalItems--;

            if ($i == $totalBulkToInsert || !$totalItems) {
                try {
                    $insertedArticleResult = $sell->insertArticles($insertArticles);
                    $flush = true;
                    $i = 1;

                    foreach ($insertedArticleResult as $insertedArticle) {
                        if (isset($insertedArticle['CorrelationKey'])) {
                            $correlationItems[$insertedArticle['CorrelationKey']] += $insertedArticle;
                        }
                    }
                } catch (Exception $e) {
                    Mage::log("\n" . $e->__toString(), Zend_Log::ERR, Diglin_Ricento_Helper_Data::LOG_FILE, true);
                    Mage::log($sell->getLastApiDebug(), Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);

                    $message = $this->_getHelper()->__($e->getMessage());
                    $message = function_exists('mb_strcut') ? mb_strcut($message, 0, 1024 * 1024) : substr($message, 0, 1024 * 1024);

                    foreach ($correlationItems as $key => $correlationItem) {
                        $correlationItems[$key]['ErrorCodes'] = $e->getCode();
                        $correlationItems[$key]['ErrorMessage'] = $message;
                    }

                    $e = null;
                    // keep going for the next items - no break
                }

                $this->_saveCurrentStatus($correlationItems);
                $correlationItems = array();
            }
            $i++;
        }

        if (Mage::helper('diglin_ricento')->isDebugEnabled()) {
            Mage::log('Max Memory Usage After Total Insert ' . $this->_getHelper()->getMemoryUsage() . ' bytes', Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);
        }

        unset($insertArticles);
        unset($itemCollection);

        return $this;
    }

    /**
     * @param array $correlationItems
     * @return $this
     * @throws Exception
     */
    protected function _saveCurrentStatus(array $correlationItems)
    {
        $hasSuccess = false;
        $job = $this->_currentJob;
        $itemResource = Mage::getResourceModel('diglin_ricento/products_listing_item');

        foreach ($correlationItems as $correlationItem) {
            $articleId = null;
            $isPlanned = false;

            if (!$correlationItem['skipped']) {
                if (!empty($correlationItem['PlannedArticleId'])) {
                    $articleId = $correlationItem['PlannedArticleId'];
                    $isPlanned = true;
                } else if (!empty($correlationItem['ArticleId'])) {
                    $articleId = $correlationItem['ArticleId'];
                    $isPlanned = false;
                }
            }

            if (!empty($articleId)) {
                // Must be set at first in case of error
                $itemResource->saveCurrentItem($correlationItem['item_id'], array(
                    'ricardo_article_id' => $articleId,
                    'status' => Diglin_Ricento_Helper_Data::STATUS_LISTED,
                    'is_planned' => (int) $isPlanned,
                    'qty_inventory' => $correlationItem['product_qty']
                ));
                $this->_itemStatus = Diglin_Ricento_Model_Products_Listing_Log::STATUS_SUCCESS;
                $this->_itemMessage = array('inserted_article' => $correlationItem);
                $hasSuccess = true;
                $this->_jobHasSuccess = true;
                ++$this->_totalSuccess;
            } else if ($correlationItem['skipped']) {
                $this->_itemStatus = Diglin_Ricento_Model_Products_Listing_Log::STATUS_NOTICE;
                $this->_itemMessage = array('notice' => $this->_getHelper()->__('This item is already listed or has already a ricardo article Id. No insert done to ricardo.ch'));
                $this->_jobHasSuccess = true;
                ++$this->_totalSuccess;
                // no change needed for the item status
            } else {
                ++$this->_totalError;
                $this->_jobHasError = true;
                if (isset($correlationItem['ErrorMessage']) || $correlationItem['ErrorCodes']) {
                    $this->_itemMessage = array('errors' => array(
                        $this->_getHelper()->__('Error Code: %d', (isset($correlationItem['ErrorCodes'])) ? implode(',', $correlationItem['ErrorCodes']) : ''),
                        (isset($correlationItem['ErrorMessage']))
                            ? $this->_getHelper()->__($correlationItem['ErrorMessage'])
                            : implode(' - ', $this->_handleErrorCodes($correlationItem['ErrorCodesType'], $correlationItem['ErrorCodes']))
                    ));
                }
                $this->_itemStatus = Diglin_Ricento_Model_Products_Listing_Log::STATUS_ERROR;
                $itemResource->saveCurrentItem($correlationItem['item_id'], array('status' => Diglin_Ricento_Helper_Data::STATUS_ERROR));
            }

            /**
             * Save item information and eventual error messages
             */
            $this->_getListingLog()->saveLog(array(
                'job_id' => $job->getId(),
                'product_title' => $correlationItem['product_title'],
                'products_listing_id' => $this->_productsListingId,
                'product_id' => $correlationItem['product_id'],
                'message' => (is_array($this->_itemMessage)) ? $this->_jsonEncode($this->_itemMessage) : $this->_itemMessage,
                'log_status' => $this->_itemStatus,
                'log_type' => $this->_logType,
                'created_at' => Mage::getSingleton('core/date')->gmtDate()
            ));

            /**
             * Save the current information of the process to allow live display via ajax call
             */
            $this->_currentJobListing->saveCurrentJob(array(
                'total_proceed' => ++$this->_totalProceed,
                'total_success' => $this->_totalSuccess,
                'total_error' => $this->_totalError,
                'last_item_id' => $correlationItem['item_id']
            ));

            $this->_itemMessage = null;
            $this->_itemStatus = null;
        }

        if ($hasSuccess) {
            $listing = Mage::getModel('diglin_ricento/products_listing')->load($this->_productsListingId);
            if ($listing->getId()) {
                $listing
                    ->setStatus(Diglin_Ricento_Helper_Data::STATUS_LISTED)
                    ->save();
            }
        }

        return $this;
    }

    /**
     * @param string $jobStatus
     * @return string
     */
    protected function _getStatusMessage($jobStatus)
    {
        return Mage::helper('diglin_ricento')->__('Report: %d success, %d error(s)', $this->_totalSuccess, $this->_totalError);
    }
}