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

use Diglin\Ricardo\Managers\Sell\Parameter\GetArticlesFeeParameter;
use Diglin\Ricardo\Managers\Sell\Parameter\InsertArticlesParameter;
use Diglin\Ricardo\Managers\Sell\Parameter\DeletePlannedArticlesParameter;
use Diglin\Ricardo\Managers\Sell\Parameter\ClosePlannedArticleParameter;
use Diglin\Ricardo\Managers\Sell\Parameter\CloseArticlesParameter;
use Diglin\Ricardo\Managers\Sell\Parameter\UpdateArticleBuyNowQuantityParameter;

use Diglin\Ricardo\Exceptions\ExceptionAbstract;
use Diglin\Ricardo\Exceptions\GeneralException;
use Diglin\Ricardo\Enums\GeneralErrors;

/**
 * Class Diglin_Ricento_Model_Api_Services_Sell
 */
class Diglin_Ricento_Model_Api_Services_Sell extends Diglin_Ricento_Model_Api_Services_Abstract
{
    /**
     * @var string
     */
    protected $_serviceName = 'sell';

    /**
     * @var string
     */
    protected $_model = '\Diglin\Ricardo\Managers\Sell';

    /**
     * @var Diglin_Ricento_Model_Sales_Options
     */
    protected $_salesOptions;

    /**
     * @var Diglin_Ricento_Model_Rule
     */
    protected $_shippingPaymentRule;

    /**
     * Overwritten just to get the class/method auto completion
     * Be aware that using directly this method to use the methods of the object instead of using
     * the magic methods of the abstract (__call, __get, __set) will prevent to use the cache of Magento
     * but also some logic related to the secure token
     *
     * @return \Diglin\Ricardo\Managers\Sell
     */
    public function getServiceModel()
    {
        return parent::getServiceModel();
    }

    /**
     * @param Diglin_Ricento_Model_Products_Listing_Item $item
     * @return array
     */
    public function insertArticle(Diglin_Ricento_Model_Products_Listing_Item $item)
    {
        $articleResult = array();
        $insertArticle = null;

        try {
            $start = microtime(true);
            $insertArticle = $item->getInsertArticleParameter();

            $articleResult = parent::createArticle($insertArticle);

            if (Mage::helper('diglin_ricento')->isDebugEnabled()) {
                Mage::log('Time to insert article ' . (microtime(true) - $start) . ' sec', Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE);
            }
        } catch (ExceptionAbstract $e) {
            if (Mage::helper('diglin_ricento')->isDebugEnabled() && $insertArticle instanceof InsertArticlesParameter) {
                $insertArticle->setPictures(null, true); // remove picture otherwise log is extremely long
                Mage::log($insertArticle->getDataProperties(), Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            }
            Mage::logException($e);
            $this->_updateCredentialToken();
            $this->_handleSecurityException($e);
        }

        unset($insertArticle);

        return $articleResult;
    }

    /**
     * @param InsertArticlesParameter $insertArticlesParameter
     * @return array
     * @throws Diglin_Ricento_Exception
     * @throws Exception
     */
    public function insertArticles(InsertArticlesParameter $insertArticlesParameter)
    {
        $articlesResult = array();

        try {
            $start = microtime(true);

            $insertArticlesParameter->setAntiforgeryToken($this->getServiceManager()->getSecurityManager()->getAntiforgeryToken());
            $articlesResult = parent::createArticles($insertArticlesParameter);

            if (Mage::helper('diglin_ricento')->isDebugEnabled()) {
                Mage::log('Time to insert the articles ' . (microtime(true) - $start) . ' sec', Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);
                Mage::log('Max Memory Usage ' . Mage::helper('diglin_ricento')->getMemoryUsage() . ' bytes', Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE, true);
            }
        } catch (ExceptionAbstract $e) {
            Mage::logException($e);
            $this->_updateCredentialToken();
            $this->_handleSecurityException($e);
        }

        unset($insertArticlesParameter);

        return $articlesResult;
    }

    /**
     * @param Diglin_Ricento_Model_Products_Listing_Item $item
     * @return array|bool
     * @throws Exception
     */
    public function stopArticle(Diglin_Ricento_Model_Products_Listing_Item $item)
    {
        /**
         * If it is a planned article, we have to delete instead to close the article
         */
        if ($item->getIsPlanned()) {
            $parameterMethod = 'getDeleteArticleParameter';
            $serviceMethod = 'deletePlannedArticle';
        } else {
            $parameterMethod = 'getCloseArticleParameter';
            $serviceMethod = 'closeArticle';
        }

        try {
            $parameter = $item->$parameterMethod();
            if (!$parameter) {
                return false;
            }

            $result = $this->$serviceMethod($parameter);

            /**
             * Ricardo API is special here - if article is closed, returned values may be empty !!!
             * If it's not closed/deleted or an error occurred, an exception is triggered
             */
            if (isset($result['IsClosed'])) {
                unset($parameter);
                return true;
            }
        } catch (ExceptionAbstract $e) {
            Mage::logException($e);
            $this->_updateCredentialToken();

            try {
                $this->_handleSecurityException($e);
            } catch (GeneralException $e) {
                switch ($e->getCode()) {
                    case GeneralErrors::DELETEPLANNEDFAILED:
                    case GeneralErrors::CLOSEAUCTIONFAILED:
                        return false;
                    default:
                        break;
                }
                throw $e;
            }
        }

        return false;
    }

    /**
     * @param array $articles
     * @param int $websiteId
     * @return array|bool
     * @throws Diglin_Ricento_Exception
     * @throws Exception
     * @throws \Diglin\Ricardo\Exceptions\GeneralException
     */
    public function stopArticles(array $articles, $websiteId = 0)
    {
        $helperApi = Mage::helper('diglin_ricento/api');
        $results = array();

        if (isset($articles['planned']) && count($articles['planned'])) {
            $deletePlannedArticlesParameter = new DeletePlannedArticlesParameter();
            $deletePlannedArticlesParameter
                ->setAntiforgeryToken($helperApi->getAntiforgeryToken($websiteId));

            foreach ($articles['planned'] as $article) {
                $closePlanned = new ClosePlannedArticleParameter();
                $closePlanned->setPlannedArticleId($article);
                $deletePlannedArticlesParameter->setArticles($closePlanned);
            }

            try {
                $results = parent::deletePlannedArticles($deletePlannedArticlesParameter);
            } catch (ExceptionAbstract $e) {
                Mage::logException($e);
                $this->_updateCredentialToken();

                try {
                    $this->_handleSecurityException($e);
                } catch (GeneralException $e) {
                    switch ($e->getCode()) {
                        case GeneralErrors::DELETEPLANNEDFAILED:
                        case GeneralErrors::CLOSEAUCTIONFAILED:
                            return false;
                        default:
                            break;
                    }
                    throw $e;
                }
            }
        }

        if (isset($articles['live']) && count($articles['live'])) {
            $closesParameter = new CloseArticlesParameter();
            $closesParameter
                ->setAntiforgeryToken($helperApi->getAntiforgeryToken($websiteId));

            foreach ($articles['live'] as $article) {
                $closesParameter->setArticleIds($article);
            }

            try {
                $results = array_merge($results, parent::closeArticles($closesParameter));
            } catch (\Diglin\Ricardo\Exceptions\ExceptionAbstract $e) {
                Mage::logException($e);
                $this->_updateCredentialToken();

                try {
                    $this->_handleSecurityException($e);
                } catch (GeneralException $e) {
                    switch ($e->getCode()) {
                        case GeneralErrors::DELETEPLANNEDFAILED:
                        case GeneralErrors::CLOSEAUCTIONFAILED:
                            return false;
                        default:
                            break;
                    }
                    throw $e;
                }
            }
        }

        return $results;
    }

    /**
     * @param array $articlesDetails of Diglin\Ricardo\Managers\Sell\Parameter\GetArticleFeeParameter
     * @return bool | array
     * @throws Diglin_Ricento_Exception
     * @throws Exception
     */
    public function getArticlesFee(array $articlesDetails)
    {
        try {
            if (empty($articlesDetails)) {
                return false;
            }

            $articlesFeeParameter = new GetArticlesFeeParameter();

            /* @var $articlesDetail Diglin\Ricardo\Managers\Sell\Parameter\GetArticleFeeParameter */
            foreach ($articlesDetails as $articlesDetail) {
                $articlesFeeParameter->setArticlesDetails($articlesDetail);
            }

            $this->setCanUseCache(false);
            $fees = parent::getArticlesFee($articlesFeeParameter);
            $this->setCanUseCache(true);

            return $fees;
        } catch (ExceptionAbstract $e) {
            Mage::logException($e);
            $this->_updateCredentialToken();
            $this->_handleSecurityException($e);
        }

        return false;
    }

    /**
     * @param Diglin_Ricento_Model_Products_Listing_Item $item
     * @return array
     * @throws Diglin_Ricento_Exception
     * @throws Exception
     */
    public function updateArticleBuyNowQuantity(Diglin_Ricento_Model_Products_Listing_Item $item)
    {
        $helperApi = Mage::helper('diglin_ricento/api');
        $result = array();

        $updateParameter = new UpdateArticleBuyNowQuantityParameter();
        $updateParameter
            ->setAntiforgeryToken($helperApi->getAntiforgeryToken())
            ->setQuantity($item->getQtyInventory())
            ->setArticleId($item->getRicardoArticleId());

        try {
            $result = parent::updateArticleBuyNowQuantity($updateParameter);
        } catch (ExceptionAbstract $e) {
            Mage::logException($e);
            $this->_updateCredentialToken();
            $this->_handleSecurityException($e);
        }

        return $result;
    }
}