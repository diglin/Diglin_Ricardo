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

use \Diglin\Ricardo\Managers\Search\Parameter\GetCategoryBestMatchParameter;
use \Diglin\Ricardo\Exceptions\SearchException;
use \Diglin\Ricardo\Enums\SearchErrors;

/**
 * Class Diglin_Ricento_Adminhtml_Products_CategoryController
 */
class Diglin_Ricento_Adminhtml_Products_CategoryController extends Diglin_Ricento_Controller_Adminhtml_Action
{
    public function mappingAction()
    {
        $suggestedCategoriesId = $this->_getSession()->getData('suggested_categories');

        $this->loadLayout();
        $this->getLayout()->getBlock('category_tree')
            ->setCategoryId($this->getRequest()->getParam('id', 1))
            ->setSuggestedCategoriesId($suggestedCategoriesId);

        $this->renderLayout();
    }

    public function childrenAction()
    {
        $suggestedCategoriesId = $this->_getSession()->getData('suggested_categories');

        $this->loadLayout();
        $this->getLayout()->getBlock('category_children')
            ->setCategoryId($this->getRequest()->getParam('id', 1))
            ->setLevel($this->getRequest()->getParam('level', 0))
            ->setSuggestedCategoriesId($suggestedCategoriesId);

        $this->renderLayout();
    }

    public function suggestAction()
    {
        $helper = Mage::helper('diglin_ricento');
        $sentence = (string) $this->getRequest()->getParam('sentence');

        $categoryBestMatchParameter = new GetCategoryBestMatchParameter();
        $categoryBestMatchParameter
            ->setNumberMaxOfResult(5)
            ->setLanguageId($helper->getRicardoLanguageIdFromLocaleCode($helper->getDefaultSupportedLang()))
            ->setSentence($sentence);

        $categories = array();
        $response = new Varien_Object();
        $suggestedCategoriesId = array();

        try {
            $searchService = Mage::getSingleton('diglin_ricento/api_services_search');
            $searchService->setCanUseCache(false);
            $categories = $searchService->getCategoryBestMatch($categoryBestMatchParameter);
        } catch (SearchException $e) {
            $errorMessage = '<li class="error-msg">';
            $errorMessage .= SearchErrors::getLabel($e->getCode());
            $errorMessage .= '</li>';

            $response->setError($errorMessage);
        }

        if (count($categories) > 0) {

            $mapping = Mage::getModel('diglin_ricento/products_category_mapping');
            $categoryId = $categories[0]['CategoryId'];

            foreach ($categories as $category) {
                $suggestedCategoriesId = array_merge($suggestedCategoriesId, explode('/', $mapping->getCategory($category['CategoryId'])->getPath()));
            }

            $block = $this->getLayout()->createBlock('diglin_ricento/adminhtml_products_category_mapping_tree');
            $block
                ->setCategoryId($categoryId)
                ->setSuggestedCategoriesId($suggestedCategoriesId);

//            array_shift($categories);

            $response
                ->setCategoryId($categoryId)
//                ->setOtherSuggestions($categories)
                ->setContent($block->toHtml())
                ->setLevels($block->getLevels()) // must be after $block->toHtml()
                ->setChildrenUrl($this->getUrl('ricento/products_category/children', array('id' => '#ID#', 'level' => '#LVL#')));
        }

        $this->_getSession()->setData('suggested_categories', $suggestedCategoriesId);

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody($response->toJson());
    }

    /**
     * Show the categories tree to add products related to them into a product listing item
     */
    public function showCategoriesTreeAction()
    {
        $noContentRender = false;

        if (!$this->_initListing()) {
            $this->_getSession()->addError($this->__('No category will be displayed, the products listing doesn\'t exists. Please, close the window.'));
            $noContentRender = true;
        }

        if (!$this->_savingAllowed()) {
            $this->_getSession()->addError($this->__('You are not allowed to save the products listing, so you cannot add products from a category. Please, close the window.'));
            $noContentRender = true;
        }

        $this->loadLayout();

        if ($noContentRender) {
            $this->getLayout()->getBlock('content')->unsetChild('products_listing_categories');
        }

        $this->renderLayout();
    }

    /**
     * Use for ajax call to load new categories in the tree
     */
    public function categoriesJsonAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('diglin_ricento/adminhtml_products_category_tree_categories')
                ->getCategoryChildrenJson($this->getRequest()->getParam('category'))
        );
    }

    public function saveProductsToAddAction()
    {
        if (!$this->_initListing()) {
            $this->_getSession()->addError($this->__('Product(s) from the selected categories cannot be saved. The products listing doesn\'t exists.'));
            $this->_redirect('*/products_listing/index');
            return;
        }

        if (!$this->_savingAllowed()) {
            $this->_getSession()->addError($this->__('You are not allowed to save the products listing, so you cannot add products from a category.'));
            $this->_redirect('*/products_listing/index');
            return;
        }

        $categoryIds = $this->getRequest()->getParam('category_ids', array());

        try {
            $productsAdded = 0;
            $categoryIds = array_unique(explode(',', $categoryIds));
            if (!empty($categoryIds)) {
                $supportedTypes = Mage::helper('diglin_ricento')->getAllowedProductTypes();

                $productsListingItemIds = (array)$this->_getListing()
                    ->getProductsListingItemCollection()
                    ->getColumnValues('product_id');

                $categories = Mage::getResourceModel('catalog/category_collection')
                    ->addFieldToFilter('entity_id', array('in' => $categoryIds));

                foreach ($categories->getItems() as $category) {
                    /* Only supported products type, not already in the current & other list */
                    $productCollection = Mage::getResourceModel('catalog/product_collection')
                        ->addWebsiteFilter($this->_getListing()->getWebsiteId())
                        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                        ->addFieldToFilter('type_id', array('in' => $supportedTypes))
                        //->addFieldToFilter('visibility', array('neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE ))
                        ->joinField('in_other_list',
                            'diglin_ricento/products_listing_item',
                            new Zend_Db_Expr('products_listing_id IS NOT NULL'),
                            'product_id=entity_id',
                            'products_listing_id !=' . (int)$this->_getListing()->getId(),
                            'left'
                        )
                        ->addFieldToFilter('in_other_list', array('eq' => 0))
                        ->groupByAttribute('entity_id')
                        ->addCategoryFilter($category);

                    if (!empty($productsListingItemIds)) {
                        $productCollection->addFieldToFilter('entity_id', array('nin' => $productsListingItemIds));
                    }

                    $productIds = $productCollection->getAllIds();
                    $productsListingItemIds = array_merge($productsListingItemIds, $productIds);
                    foreach ($productIds as $productId) {
                        if ($this->_getListing()->addProduct((int)$productId)) {
                            ++$productsAdded;
                        }
                    }
                }
            }

            $this->_prepareConfigurableProduct();

            $this->_getSession()->addSuccess($this->__('%d product(s) added to the listing', $productsAdded));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Error occurred while saving the product(s) from the selected categories. Please check your exception log.'));
        }
        $this->_redirect('*/products_listing/edit', array('id' => $this->_getListing()->getId()));
    }

    /**
     * @return boolean
     */
    protected function _savingAllowed()
    {
        return $this->_getListing()->getStatus() !== Diglin_Ricento_Helper_Data::STATUS_LISTED;
    }
}
