<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    Ricento
 * @package     Ricento
 * @copyright   Copyright (c) 2011-2014 Diglin (http://www.diglin.com)
 */

use \Diglin\Ricardo\Managers\SellerAccount\Parameter\OpenArticlesParameter;

/**
 * Class Diglin_Ricento_Model_Dispatcher_Closed
 */
class Diglin_Ricento_Model_Dispatcher_Closed extends Diglin_Ricento_Model_Dispatcher_Abstract
{
    /**
     * @var int
     */
    protected $_logType = Diglin_Ricento_Model_Products_Listing_Log::LOG_TYPE_CLOSED;

    /**
     * @var string
     */
    protected $_jobType = Diglin_Ricento_Model_Sync_Job::TYPE_CLOSED;

    /**
     * @var array
     */
    protected $_openRicardoArticleIds = array();

    public function proceed()
    {
        $plResource = Mage::getResourceModel('diglin_ricento/products_listing');
        $readConnection = $plResource->getReadConnection();
        $select = $readConnection
            ->select()
            ->from($plResource->getTable('diglin_ricento/products_listing'), 'entity_id');

        $listingIds = $readConnection->fetchCol($select);

        foreach ($listingIds as $listingId) {
            $select = $readConnection
                ->select()
                ->from(array('pli' => $plResource->getTable('diglin_ricento/products_listing_item')), 'item_id')
                ->where('type <> ?', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                ->where('products_listing_id = :id AND status = :status AND is_planned = 0');

            $binds = array('id' => $listingId, 'status' => Diglin_Ricento_Helper_Data::STATUS_LISTED);
            $countListedItems = count($readConnection->fetchAll($select, $binds));

            if ($countListedItems == 0) {
                continue;
            }

            /**
             * Check that there is not already running job instead of creating a new one
             */
            Mage::getResourceModel('diglin_ricento/sync_job')->cleanupPendingJob($this->_jobType, $listingId);

            // pending progress doesn't make sense here as we cleanup before but keep it to be sure everything ok
            $job = Mage::getModel('diglin_ricento/sync_job');
            $job->loadByTypeListingIdProgress($this->_jobType, $listingId, array(
                Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING,
                Diglin_Ricento_Model_Sync_Job::PROGRESS_CHUNK_RUNNING
            ));

            if ($job->getId()) {
                continue;
            }

            $job
                ->setJobType($this->_jobType)
                ->setProgress(Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING)
                ->setJobMessage(array($job->getJobMessage(true)))
                ->save();

            $jobListing = Mage::getModel('diglin_ricento/sync_job_listing');
            $jobListing
                ->setProductsListingId($listingId)
                ->setTotalCount($countListedItems)
                ->setTotalProceed(0)
                ->setJobId($job->getId())
                ->save();
        }

        unset($listingIds);
        unset($readConnection);
        unset($job);
        unset($jobListing);

        return parent::proceed();
    }

    /**
     * @return mixed
     */
    protected function _proceed()
    {
        /**
         * Status of the collection must be the same as Diglin_Ricento_Model_Resource_Products_Listing_Item::countReadyTolist
         */
        $itemCollection = $this->_getItemCollection(array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD), $this->_currentJobListing->getLastItemId());
        $itemCollection->addFieldToFilter('is_planned', 0);

        $totalItems = $itemCollection->getSize();
        $ricardoArticleIds = $itemCollection->getColumnValues('ricardo_article_id');
        $lastItem = $itemCollection->getLastItem();
        $openArticlesResult = $stoppedArticles = array();

        if (!$totalItems) {
            $this->_currentJob->setJobMessage(array($this->_getNoItemMessage()));
            $this->_progressStatus = Diglin_Ricento_Model_Sync_Job::PROGRESS_COMPLETED;
            return $this;
        }

        $sellerAccountService = Mage::getSingleton('diglin_ricento/api_services_selleraccount')->setCanUseCache(false);
        $sellerAccountService->setCurrentWebsite($this->_getListing()->getWebsiteId());

        try {
            $openArticlesParameter = new OpenArticlesParameter();
            $openArticlesParameter
                ->setPageSize($this->_limit) // if not defined, default is 10
                ->setArticleIdsFilter($ricardoArticleIds);

            $openArticlesResult = $sellerAccountService->getOpenArticles($openArticlesParameter);
        } catch (Exception $e) {
            $this->_handleException($e);
            $e = null;
            // keep going for the next item - no break
        }

        if (isset($openArticlesResult['OpenArticles'])) {

            $this->_openRicardoArticleIds = $openArticlesResult['OpenArticles'];
            $stoppedArticles = array_filter($ricardoArticleIds, array($this, 'pullArticleToClose'));

            if (count($stoppedArticles)) {
                $itemCollection = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
                $itemCollection->addFieldToFilter('ricardo_article_id', array('in' => $stoppedArticles));

                foreach ($itemCollection->getItems() as $item) {

                    try {
                        // Check if the article is really stopped - Article Id may change if product has been sold but reactivated
                        $openArticlesParameter = new OpenArticlesParameter();
                        $openArticlesParameter->setInternalReferenceFilter($item->getInternalReference());

                        $openArticleResult = $sellerAccountService->getOpenArticles($openArticlesParameter);
                    } catch (Exception $e) {
                        $this->_handleException($e);
                        $e = null;
                        continue;
                        // keep going for the next item - no break
                    }

                    // We do not stop anything if the article ID has just been changed and the product is still open
                    if (isset($openArticleResult['TotalLines']) && $openArticleResult['TotalLines'] > 0) {
                        $articleId = $openArticleResult['OpenArticles'][0]['ArticleId'];
                        if ($item->getRicardoArticleId() != $articleId) {
                            $item
                                ->setRicardoArticleId($articleId)
                                ->save();
                        }
                    } else {
                        $item
                            ->setRicardoArticleId(null)
                            ->setQtyInventory(null)
                            ->setIsPlanned(null)
                            ->setStatus(Diglin_Ricento_Helper_Data::STATUS_STOPPED)
                            ->save();

                        $this->_getListingLog()->saveLog(array(
                            'job_id' => $this->_currentJob->getId(),
                            'product_id' => $item->getProductId(),
                            'product_title' => $item->getProductTitle(),
                            'products_listing_id' => $this->_productsListingId,
                            'message' => $this->_jsonEncode(array('success' => $this->_getHelper()->__('The product has been stopped'))),
                            'log_status' => Diglin_Ricento_Model_Products_Listing_Log::STATUS_SUCCESS,
                            'log_type' => $this->_logType,
                            'created_at' => Mage::getSingleton('core/date')->gmtDate()
                        ));
                    }
                }
            }
        }

        /**
         * Save the current information of the process to allow live display via ajax call
         */
        $this->_totalProceed = $totalItems;
        $this->_currentJobListing->saveCurrentJob(array(
            'total_proceed' => $this->_totalProceed,
            'last_item_id' => $lastItem->getId()
        ));

        /**
         * Stop the list if all products listing items are stopped
         */
        if ($this->_productsListingId) {
            $listResource = Mage::getResourceModel('diglin_ricento/products_listing');
            $listResource->setStatusStop($this->_productsListingId);
        }

        unset($itemCollection);

        return $this;
    }

    /**
     * @param $var
     * @return bool
     */
    public function pullArticleToClose($var)
    {
        $return = true;
        foreach ($this->_openRicardoArticleIds as $articleId) {
            if ($var == $articleId['ArticleId']) {
                return false;
            }
        }
        return $return;
    }
}