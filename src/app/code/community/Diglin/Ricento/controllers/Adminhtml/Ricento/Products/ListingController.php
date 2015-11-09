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
 * Class Diglin_Ricento_Adminhtml_Ricento_Products_ListingController
 */
class Diglin_Ricento_Adminhtml_Ricento_Products_ListingController extends Diglin_Ricento_Controller_Adminhtml_Products_Listing
{
    /**
     * @var string
     */
    protected $_successMessage = '';

    /**
     * Show the products listing
     */
    public function indexAction()
    {
        $title = $this->__('Products Listing');

        $this->loadLayout()
            ->_setActiveMenu('ricento/products_listing')
            ->_addBreadcrumb($title, $title);

        $this->_title($this->__('Products Listing'));
        $this->_addContent($this->getLayout()->createBlock('diglin_ricento/adminhtml_products_listing', 'products_listing'));
        $this->renderLayout();
    }

    /**
     * Used for Ajax call
     */
    public function productsGridAction()
    {
        $noRender = false;
        if (!$this->_initListing()) {
            $noRender = true;
        }

        $this->loadLayout();

        if ($noRender) {
            $content = json_encode(array('success' => false));
        } else {
            $content = $this->getLayout()->createBlock('diglin_ricento/adminhtml_products_listing_edit_tabs_products')->toHtml();
        }

        $this->getResponse()->setBody($content);
    }

    public function addProductsPopupAction()
    {
        if (!$this->_initListing()) {
            $this->_redirect($this->_getIndexUrl());
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Used for ajax version of the action addProductsPopupAction
     */
    public function addProductsGridAction()
    {
        $noRender = false;
        if (!$this->_initListing()) {
            $noRender = true;
        }

        $this->loadLayout();

        if ($noRender) {
            $content = json_encode(array('success' => false));
        } else {
            $content = $this->getLayout()->createBlock('diglin_ricento/adminhtml_products_listing_edit_tabs_products_add')->toHtml();
        }

        $this->getResponse()->setBody($content);
    }

    /**
     * Edit a product listing item
     */
    public function editAction()
    {
        $productsListing = $this->_initListing();
        if (!$productsListing) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        try {
            $this->_title($this->__('Edit List "%s"', $productsListing->getTitle()));

            $this->loadLayout();
            $this->renderLayout();
        } catch (Diglin_Ricento_Exception $e) {
            $this->_getSession()->addError($this->__('The action you try to do, is not possible. You must authorize the API token. Please, go the <a href="%s">ricardo.ch Authorization</a> page to do the authorization process', $e->getValidationUrl()));
            $this->_redirectUrl($this->_getIndexUrl());
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirectUrl($this->_getIndexUrl());
        }
    }

    /**
     * Create empty product listing based on name and website_id
     */
    public function createAction()
    {
        $listingTitle = (string)$this->getRequest()->getPost('listing_title');
        $websiteId = (int)$this->getRequest()->getPost('website_id');

        if (empty($listingTitle) || empty($websiteId)) {
            $this->_getSession()->addError($this->__('Listing name and website must be specified.'));
            $this->_redirect('*/*/index');
            return;
        }

        /* Detect Language settings */

        $helper = Mage::helper('diglin_ricento');
        $languages = $helper->getSupportedLang();
        $baseLanguage = $languages[0]; // We set per default german language
        $storeLanguages = array();
        $websiteLocale = explode('_', $helper->getWebsiteConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $websiteId));
        $websiteLang = strtolower($websiteLocale[0]);
        if (in_array($websiteLang, $languages)) {
            $baseLanguage = $websiteLang;
        }

        $storeIds = Mage::app()->getWebsite($websiteId)->getStoreIds();
        foreach ($storeIds as $store) {
            $locale = explode('_', Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store));
            $storeLang = strtolower($locale[0]);
            if (in_array($storeLang, $languages) && $websiteLang != $storeLang) {
                $storeLanguages[$storeLang] = $store;
            }
        }

        $this->_getSession()->addNotice($this->__('We have detected and set for you the language configuration. Please, review it before to go further.'));

        /* @var $salesOptions Diglin_Ricento_Model_Sales_Options */
        $salesOptions = Mage::getModel('diglin_ricento/sales_options');
        $salesOptions->setDataChanges(true)->save();

        /* @var $rule Diglin_Ricento_Model_Rule */
        $rule = Mage::getModel('diglin_ricento/rule');
        $rule->setDataChanges(true)->save();

        /* @var $listing Diglin_Ricento_Model_Products_Listing */
        $listing = Mage::getModel('diglin_ricento/products_listing');
        $listing->setTitle($listingTitle)
            ->setWebsiteId($websiteId)
            ->setSalesOptionsId($salesOptions->getId())
            ->setRuleId($rule->getId())
            ->setPublishLanguages((!empty($storeLanguages)) ? Diglin_Ricento_Helper_Data::LANG_ALL : $baseLanguage)
            ->setDefaultLanguage($baseLanguage);

        foreach ($storeLanguages as $storeLang => $storeId) {
            call_user_func(array($listing, 'setLangStoreId' . ucwords($storeLang)), $storeId);
        }

        $listing->save();

        $this->_redirect('*/*/edit', array('id' => $listing->getId()));
    }

    /**
     * Save a product listing
     */
    public function saveAction()
    {
        $error = false;

        if ($data = $this->getRequest()->getPost()) {
            $listing = $this->_initListing();
            if (!$listing) {
                $this->_getSession()->addError('Products Listing not found.');
                $this->_redirectUrl($this->_getIndexUrl());
                return $this;
            }
            $listing->addData($data['product_listing']);
            try {
                $listing->save();
                if ($this->saveConfiguration($data)) {
                    $this->_prepareConfigurableProduct();
                    $this->_warmupPictures();
                    $this->_getSession()->addSuccess($this->__('The listing has been saved.'));
                } else {
                    $error = true;
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $error = true;
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addException($e, $this->__('An error occurred while saving the listing.'));
                $error = true;
            }
        }

        $this->_redirectUrl($this->_getEditUrl());

        // To block chaining, we return the error
        if ($error) {
            return !$error;
        }

        return true;
    }

    protected function _savingAllowed()
    {
        return $this->_getListing()->getStatus() !== Diglin_Ricento_Helper_Data::STATUS_LISTED;
    }

    protected function _getSalesOptions()
    {
        if (!$this->_salesOptionsCollection) {
            $this->_salesOptionsCollection = new Varien_Data_Collection();
            $this->_salesOptionsCollection->addItem($this->_getListing()->getSalesOptions());
        }
        return $this->_salesOptionsCollection;
    }

    protected function _getShippingPaymentRule()
    {
        if (!$this->_shippingPaymentCollection) {
            $this->_shippingPaymentCollection = new Varien_Data_Collection();
            $this->_shippingPaymentCollection->addItem($this->_getListing()->getShippingPaymentRule());
        }
        return $this->_shippingPaymentCollection;
    }

    protected function _getEditUrl()
    {
        return $this->getUrl('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
    }

    protected function _getIndexUrl()
    {
        return $this->getUrl('*/*/index');
    }

    /**
     * Delete a product listing
     */
    public function deleteAction()
    {
        if (!$this->_initListing()) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        if ($this->_getListing()->getStatus() === Diglin_Ricento_Helper_Data::STATUS_LISTED) {
            $this->_getSession()->addError($this->__('Listed listings cannot be deleted. Stop the listing first.'));
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        $this->_getListing()->delete();
        $this->_getSession()->addSuccess($this->__('Listing deleted'));
        $this->_redirect('*/*/index');
    }

    /**
     * Add product(s) into a product listing item
     */
    public function addProductAction()
    {
        if (!$this->_initListing()) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }
        $productIds = (array)$this->getRequest()->getPost('product', array());
        $productsAdded = 0;
        foreach ($productIds as $productId) {
            if ($this->_getListing()->addProduct((int)$productId)) {
                ++$productsAdded;
            }
        }

        $this->_prepareConfigurableProduct();
        $this->_warmupPictures();

        $this->_getSession()->addSuccess($this->__('%d product(s) added to the listing', $productsAdded));
        $this->_redirect('*/*/edit', array('id' => $this->_getListing()->getId()));
    }

    /**
     * Remove product(s) from product listing
     */
    public function removeProductAction()
    {
        if (!$this->_initListing()) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        if ($this->getRequest()->isPost()) {
            $itemIds = array_map('intval', (array)$this->getRequest()->getPost('item', array()));
        } else {
            $itemIds = array_map('intval', (array)$this->getRequest()->getParam('item', array()));
        }

        list($productsRemoved, $productsNotRemoved) = $this->_getListing()->removeProductsByItemIds($itemIds);

        if ($productsRemoved) {
            $this->_getSession()->addSuccess($this->__('%d products removed from listing', $productsRemoved));
        }

        if ($productsNotRemoved) {
            $this->_getSession()->addNotice($this->__('%d products are listed and could not be removed', $productsNotRemoved));
        }

        if (!$productsRemoved && !$productsNotRemoved) {
            $this->_getSession()->addWarning($this->__('No product found'));
        }

        $this->_redirect('*/*/edit', array('id' => $this->_getListing()->getId()));
    }

    /**
     * @param string $jobType
     * @param int $totalItems
     */
    protected function _startJobList($jobType, $totalItems)
    {
        $productListing = $this->_getListing();

        if (!$this->_isApiReady()) {
            $this->_getSession()->addError($this->__('The API token and configuration are not ready to allow this action. Please, check that your token is enabled and not going to expire.'));
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        try {
            /**
             * Cleanup old pending job before to create a new similar one
             */
            Mage::getResourceModel('diglin_ricento/sync_job')->cleanupPendingJob($jobType, $productListing->getId());

            /**
             * Do nothing if the same job is already running
             */
            $collection = Mage::getResourceModel('diglin_ricento/sync_job_collection');
            $collection
                ->getSelect()
                ->join(array('jl' => $collection->getTable('diglin_ricento/sync_job_listing')),
                    'jl.job_id = main_table.job_id', '*')
                ->where('jl.products_listing_id = ?', $productListing->getId())
                ->where('job_Type = ?', $jobType)
                ->where('progress IN (?)', array(Diglin_Ricento_Model_Sync_Job::PROGRESS_CHUNK_RUNNING, Diglin_Ricento_Model_Sync_Job::PROGRESS_RUNNING));

            if ($collection->count() > 0) {
                $this->_getSession()->addNotice($this->__('A similar job is already running. This job won\'t be executed.'));
                $this->_redirect('*/ricento_products_listing/edit', array('id' => $productListing->getId()));
                return;
            }

            /**
             * Create a job to prepare the sync to Ricardo.ch
             */
            $job = Mage::getModel('diglin_ricento/sync_job');
            $job
                ->setJobType($jobType)
                ->setProgress(Diglin_Ricento_Model_Sync_Job::PROGRESS_PENDING)
                ->setJobMessage((is_array($job->getJobMessage())) ? $job->getJobMessage() : array($job->getJobMessage()))
                ->save();

            $jobListing = Mage::getModel('diglin_ricento/sync_job_listing');
            $jobListing
                ->setProductsListingId($productListing->getId())
                ->setTotalCount($totalItems)
                ->setTotalProceed(0)
                ->setJobId($job->getId())
                ->save();

            $this->_getSession()->addSuccess($this->_successMessage);
            $this->_redirect('*/ricento_log/sync', array('id' => $job->getId()));
            return;
        } catch (Diglin_Ricento_Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('It\'s s not possible to start this job. You must authorize the API token. Please, go the <a href="%s">ricardo.ch Authorization</a> page to do the authorization process', $e->getValidationUrl()));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('An error occurred while starting this job. Please check your log file.'));
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @return string
     */
    protected function _getSuccessMesageList()
    {
        return $this->__('The job to check your products listing will start in few minutes. You can check the progression below.')
        . '<br>'
        . $this->__('If it finishes with success, your products will be listed automatically otherwise you will have to choose if you want to keep going to list or fix potential issues.');
    }

    /**
     * Start to list the product listing on ricardo platform if not already listed
     */
    public function checkAndListAction()
    {
        $productListing = $this->_initListing();

        if (!$productListing) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        $countPendingItems = Mage::getResourceModel('diglin_ricento/products_listing_item')->countPendingItems($productListing->getId());

        if ($countPendingItems == 0) {
            $this->_getSession()->addError($this->__('There is no pending product to check.'));
            $this->_redirect('*/*/edit', array('id' => $productListing->getId()));
            return;
        }

        $this->_successMessage = $this->_getSuccessMesageList();
        $this->_startJobList(Diglin_Ricento_Model_Sync_Job::TYPE_CHECK_LIST, $countPendingItems);
    }

    /**
     * Start to check list after the display of the job progress
     */
    public function checkAjaxAction()
    {
        $return = true;
        try {
            Mage::getSingleton('diglin_ricento/dispatcher')
                ->dispatch(Diglin_Ricento_Model_Sync_Job::TYPE_CHECK_LIST)
                ->proceed();

        } catch (Exception $e) {
            Mage::logException($e);
            $return = false;
        }

        $response = array(
            'status' => $return
        );

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
     * Action used to list the products to ricardo.ch when the cron job which check the products listing finished with warnings
     */
    public function listAction()
    {
        $productListing = $this->_initListing();

        if (!$productListing) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        $countReadyToList = Mage::getResourceModel('diglin_ricento/products_listing_item')->coundReadyTolist($productListing->getId());

        if ($countReadyToList <= 0) {
            $this->_getSession()->addError($this->__('There is no product ready to be listed. Please, add products to your products listing "%s".', $productListing->getTitle()));
            $this->_redirect('*/*/edit', array('id' => $productListing->getId()));
            return;
        }

        $this->_successMessage = $this->__('The job to list your products listing will start in few minutes.')
            . '<br>'
            . $this->__('You can check the progression below.');
        $this->_startJobList(Diglin_Ricento_Model_Sync_Job::TYPE_LIST, $countReadyToList);
    }

    /**
     * Stop to list all items belonging to a product list from ricardo platform
     */
    public function stopAction()
    {
        $listing = $this->_initListing();
        if (!$listing) {
            $this->_getSession()->addError('Products Listing not found.');
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        $countListedItems = Mage::getResourceModel('diglin_ricento/products_listing_item')->countListedItems($this->_getListing()->getId());
        $countSoldItems = Mage::getResourceModel('diglin_ricento/products_listing_item')->countSoldItems($this->_getListing()->getId());

        if (($countListedItems + $countSoldItems) == 0 && $listing->getStatus() == Diglin_Ricento_Helper_Data::STATUS_LISTED) {
            $listing
                ->setStatus(Diglin_Ricento_Helper_Data::STATUS_STOPPED)
                ->save();

            $this->_getSession()->addSuccess($this->__('The product list has been stopped.'));
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        if (($countListedItems + $countSoldItems) == 0) {
            $this->_getSession()->addError($this->__('Only listed product items can be stopped.'));
            $this->_redirectUrl($this->_getRefererUrl());
            return;
        }

        $this->_successMessage = $this->__('The job to stop to list your products will start in few minutes.') . '&nbsp;' . $this->__('You can check the progression below.');
        $this->_startJobList(Diglin_Ricento_Model_Sync_Job::TYPE_STOP, ($countListedItems + $countSoldItems) );
    }

    /**
     * Delete one or more product listing via mass action
     * Maybe redirected to the method deleteAction with variable changes
     */
    public function massDeleteAction()
    {
        $productListings = $this->getRequest()->getParam('products_listing');

        try {
            if (is_array($productListings)) {
                $productListingsCollection = Mage::getResourceModel('diglin_ricento/products_listing_collection');
                $productListingsCollection
                    ->addFieldToFilter('entity_id', array('in' => $productListings))
                    ->addFieldToFilter('status', array('neq' => Diglin_Ricento_Helper_Data::STATUS_LISTED));

                $goingToBeDeleted = $productListingsCollection->getAllIds();

                $productListingsCollection->walk('delete');
                $this->_getSession()->addSuccess($this->__('Products listing(s) is/are successfully deleted.'));

                $notDeleted = array_diff($productListings, $goingToBeDeleted);
                if ($notDeleted) {
                    $this->_getSession()->addNotice($this->__("The following products listings IDs have not been deleted because they are still listed on ricardo.ch: %s", implode(',', $notDeleted)));
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('An error occurred while trying to delete the products listing(s). Please, check your exception log.'));
        }

        $this->_redirect('*/*/index');
    }

    /**
     * Display the confirmation window before to check and list
     */
    public function confirmationAction()
    {
        $error = false;

        $listing = $this->_initListing();
        if (!$listing) {
            $this->_getSession()->addError($this->__('Products Listing not found.'));
            $error = true;
        }

        try {

            if ($listing->getStatus() != Diglin_Ricento_Helper_Data::STATUS_LISTED && !$this->saveAction()) {
                $error = true;
            }

            // reset the header came from the saveAction
            $this->getResponse()->clearHeader('Location')->setHttpResponseCode(200);

            if (!$error) {
                $i = $j = 1;
                $fees = array();
                $articleDetailsBucket = array();

                $itemsCollection = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
                $itemsCollection
                    ->addFieldToFilter('products_listing_id', $listing->getId())
                    ->addFieldToFilter('status', array(
                        'nin' => array(
                            Diglin_Ricento_Helper_Data::STATUS_LISTED,
                            Diglin_Ricento_Helper_Data::STATUS_SOLD)
                    ));

                /* @var $item Diglin_Ricento_Model_Products_Listing_Item */
                foreach ($itemsCollection->getItems() as $item) {
                    if ($item->getType() != Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                        $articleDetailsBucket[$j][] = $item->getArticleFeeDetails();

                        if ($i >= Diglin_Ricento_Helper_Data::MAX_AMOUNT_PUSH) {
                            $i = 1;
                            $j++;
                        } else {
                            $i++;
                        }
                    }
                }

                $sell = Mage::getSingleton('diglin_ricento/api_services_sell')->setCanUseCache(false);
                foreach ($articleDetailsBucket as $articleDetails) {
                    $fees = array_merge($sell->getArticlesFee($articleDetails), $fees);
                }
                $sell->setCanUseCache(true);

                if ($fees) {
                    $storeCurrency = Mage::app()->getWebsite($this->_getListing()->getWebsiteId())->getDefaultStore()->getBaseCurrencyCode();
                    if ($storeCurrency !== Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY) {

                        $rate = Mage::helper('diglin_ricento/price')
                            ->getCurrency($storeCurrency)
                            ->getRate(Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY);

                        if (empty($rate)) {
                            $this->_getSession()->addError($this->__('Currency Rate not configured'));
                        }
                    }

                    $this->_initLayoutMessages('adminhtml/session');
                    $block = $this->getLayout()
                        ->createBlock(
                            'diglin_ricento/adminhtml_products_listing_confirmation',
                            'fees_confirmation',
                            array('article_fees' => $fees)
                        );

                    $this->getResponse()->setBody($block->toHtml());
                    return;
                } else {
                    $this->_getSession()->addError($this->__('Sorry, no product found for fees calculation.'));
                    $error = true;
                }
            }

            if ($error) {
                $this->_getSession()->addNotice($this->__('Please, close this popup window and fix the errors before to be allowed to list your products on ricardo.ch.'));
                $this->_initLayoutMessages('adminhtml/session');
                $this->getResponse()->setBody($this->getLayout()->getMessagesBlock()->toHtml());
                return;
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('An error occurred %s', $e->getMessage()));
            $this->_initLayoutMessages('adminhtml/session');
            $this->getResponse()->setBody($this->getLayout()->getMessagesBlock()->toHtml());
            return;
        }
    }
}
