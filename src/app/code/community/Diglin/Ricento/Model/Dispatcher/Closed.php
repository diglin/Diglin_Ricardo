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

use Diglin\Ricardo\Enums\Customer\TransitionStatus;
use Diglin\Ricardo\Managers\SellerAccount\Parameter\GetInTransitionArticlesParameter;
use \Diglin\Ricardo\Managers\SellerAccount\Parameter\OpenArticlesParameter;

/**
 * Class Diglin_Ricento_Model_Dispatcher_Closed
 */
class Diglin_Ricento_Model_Dispatcher_Closed extends Diglin_Ricento_Model_Dispatcher_Abstract
{
    const SLEEP_REACTIVATION_TIME = 900; // 15 min in sec
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
                ->where('products_listing_id = :id AND is_planned = 0')
                ->where('status IN (?)', array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD));

            $binds = array('id' => $listingId);
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
        $itemCollection = $this->_getItemCollection(
            array(
                Diglin_Ricento_Helper_Data::STATUS_LISTED,
                Diglin_Ricento_Helper_Data::STATUS_SOLD
            ),
            $this->_currentJobListing->getLastItemId()
        );

        $itemCollection->addFieldToFilter('is_planned', 0);

        $totalItems = $itemCollection->getSize();
        if (!$totalItems) {
            $this->_currentJob->setJobMessage(array($this->_getNoItemMessage()));
            $this->_progressStatus = Diglin_Ricento_Model_Sync_Job::PROGRESS_COMPLETED;

            return $this;
        }

        $ricardoArticleIds = $itemCollection->getColumnValues('ricardo_article_id');
        $lastItem = $itemCollection->getLastItem();
        $openArticlesResult = $stoppedArticles = array();

        $sellerAccountService = Mage::getSingleton('diglin_ricento/api_services_selleraccount')->setCanUseCache(false);
        $sellerAccountService->setCurrentWebsite($this->_getListing()->getWebsiteId());

        try {
            $openArticlesParameter = new OpenArticlesParameter();
            $openArticlesParameter
                ->setPageSize($this->_limit)// if not defined, default is 10, currently is 200
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

                    /**
                     * Close Articles when:
                     *
                     * - sales option "until sold" is enabled + qty_inventory < 1
                     * - number of reactivation has been reached (date published * number_reaction * duration in days) - not implemented here
                     * - all is sold
                     * - Not found as openArticle (cause of manual stop on ricardo side or due to a moment where the reactivation break openArticle )
                     *
                     * Warning: article ID may change between reactivation (e.g. if some articles are sold), there is also a phase where articles in reactivation
                     * are not visible in getOpenArticles
                     */

                    try {
                        // Check if the article is really stopped - Article Id may change if product has been sold but reactivated
                        $inTransitionArticleParameter = new GetInTransitionArticlesParameter();
                        $inTransitionArticleParameter
                            ->setTransitionStatusFilter(TransitionStatus::INREACTIVATION)
                            ->setInternalReferenceFilter($item->getInternalReference());
                        $inTransitionArticlesResult = $sellerAccountService->getTransitionArticles($inTransitionArticleParameter);
                    } catch (Exception $e) {
                        $this->_handleException($e);
                        $e = null;
                        continue;
                        // keep going for the next item - no break
                    }

                    $skip = false;
                    // We do not stop anything if the article ID has just been changed and the product is still open
                    if (isset($inTransitionArticlesResult['TotalLines']) && $inTransitionArticlesResult['TotalLines'] > 0) {
                        $articleId = $inTransitionArticlesResult['InTransitionArticles'][0]['ArticleId'];
                        $qtyAvailable = $inTransitionArticlesResult['InTransitionArticles'][0]['AvailableQuantity'];
                        if ($item->getRicardoArticleId() != $articleId) {
                            $item->setRicardoArticleId($articleId);
                        }
                        $item
                            ->setQtyInventory($qtyAvailable)
                            ->save();

                        $message = json_encode(array(
                            'item_id'             => $item->getId(),
                            'previous_article_id' => $item->getRicardoArticleId(),
                            'ricardo_article_id'  => $articleId,
                            'mode'                => 'inReactivation'
                        ));

                        Mage::log($message, Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);

                        $skip = true;
                        unset($inTransitionArticlesResult);
                    }

                    /**
                     * @todo Temporary code for testing purpose and be sure it works, then needed to be factorized.
                     * Maybe again open, we missed it
                     */
                    if (!$skip) {
                        try {
                            $openArticlesParameter = new OpenArticlesParameter();
                            $openArticlesParameter->setInternalReferenceFilter($item->getInternalReference());

                            $openArticlesResult = $sellerAccountService->getOpenArticles($openArticlesParameter);
                        } catch (Exception $e) {
                            $this->_handleException($e);
                            $e = null;
                            // keep going for the next item - no break
                        }

                        if (isset($openArticlesResult['TotalLines']) && $openArticlesResult['TotalLines'] > 0) {
                            $articleId = $openArticlesResult['OpenArticles'][0]['ArticleId'];
                            $qtyAvailable = $openArticlesResult['OpenArticles'][0]['AvailableQuantity'];

                            if ($item->getRicardoArticleId() != $articleId) {
                                $item->setRicardoArticleId($articleId);
                            }

                            if ($qtyAvailable) {
                                $item->setQtyInventory($qtyAvailable);
                            }

                            $item->save();

                            $message = json_encode(array(
                                'item_id'             => $item->getId(),
                                'previous_article_id' => $item->getRicardoArticleId(),
                                'ricardo_article_id'  => $articleId,
                                'mode'                => 'openArticle'
                            ));

                            Mage::log($message, Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);

                            $skip = true;
                            unset($openArticlesResult);
                        }
                    }

                    if (!$skip) {

                        $additionalData = json_encode(array('previous_ricardo_article_id' => $item->getRicardoArticleId()));

                        $item
                            ->setRicardoArticleId(null)
                            ->setQtyInventory(null)
                            ->setIsPlanned(null)
                            ->setAdditionalData($additionalData)
                            ->setStatus(Diglin_Ricento_Helper_Data::STATUS_STOPPED)
                            ->save();

                        $this->_getListingLog()->saveLog(array(
                            'job_id'              => $this->_currentJob->getId(),
                            'product_id'          => $item->getProductId(),
                            'product_title'       => $item->getProductTitle(),
                            'products_listing_id' => $this->_productsListingId,
                            'message'             => $this->_jsonEncode(array('success' => $this->_getHelper()->__('The product has been stopped'))),
                            'log_status'          => Diglin_Ricento_Model_Products_Listing_Log::STATUS_SUCCESS,
                            'log_type'            => $this->_logType,
                            'created_at'          => Mage::getSingleton('core/date')->gmtDate()
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
            'last_item_id'  => $lastItem->getId()
        ));

        /**
         * Stop the list if all products listing items are stopped
         */
        if ($this->_productsListingId) {
            Mage::getResourceModel('diglin_ricento/products_listing')->setStatusStop($this->_productsListingId);
        }

        unset($itemCollection);

        return $this;
    }

    /**
     * @param $var
     * @return bool
     */
    public
    function pullArticleToClose($var)
    {
        $return = true;
        foreach ($this->_openRicardoArticleIds as $articleId) {
            if ($var == $articleId['ArticleId']) {
                return false;
            }
        }

        return $return;
    }

    /**
     * Workaround to "sleep" a product which can be in reactivation phase before to stop it definitely if really needed
     *
     * @deprecated after 1.3.4.1
     * @param Diglin_Ricento_Model_Products_Listing_Item $item
     * @return bool
     */
    public
    function temporizeReactivationPhase(Diglin_Ricento_Model_Products_Listing_Item $item, $clean = false)
    {
        $temporizeReactivationPhase = true;
        $found = false;

        $reactivationFile = Mage::getBaseDir('tmp') . DS . 'ricardo_reactivation.json';

        if (!file_exists($reactivationFile)) {
            file_put_contents($reactivationFile, '');
            chmod($reactivationFile, 0777);
        }

        $reactivationElements = (array)json_decode(file_get_contents($reactivationFile), true);

        foreach ($reactivationElements as $key => $reactivationElement) {
            if ($reactivationElement['internal_reference'] == $item->getInternalReference()) {
                $found = true;

                if ($reactivationElement['temporary_reactivation_time'] + self::SLEEP_REACTIVATION_TIME < time()) {
                    $temporizeReactivationPhase = false;
                }

                if ($found && (!$temporizeReactivationPhase || $clean)) {
                    unset($reactivationElements[$key]);
                }
            }
        }

        if (!$found) {
            $reactivationElements[] = array(
                'internal_reference'          => $item->getInternalReference(),
                'temporary_reactivation_time' => time()
            );
        }

        file_put_contents($reactivationFile, json_encode($reactivationElements));

        return $temporizeReactivationPhase;
    }
}
