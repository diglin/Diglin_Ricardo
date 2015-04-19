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

use \Diglin\Ricardo\Services\ServiceAbstract;

/**
 * Class Diglin_Ricento_Model_Api_Services_Security
 */
class Diglin_Ricento_Model_Api_Services_Security extends Diglin_Ricento_Model_Api_Services_Abstract
{
    /**
     * @var string
     */
    protected $_serviceName = 'Security';

    /**
     * Overwritten because the Service Manager has already instanciated the Security Manager model
     *
     * Be aware that using directly this method to use the methods of the object instead of using
     * the magic methods of the abstract (__call, __get, __set) will prevent to use the cache of Magento
     *
     * @return \Diglin\Ricardo\Managers\Security
     */
    public function getServiceModel()
    {
        $key = $this->_serviceName . $this->getCurrentWebsite()->getId();

        if (!Mage::registry($key)) {
            Mage::register($key, $this->getServiceManager()->getSecurityManager());
        }

        return Mage::registry($key);
    }

    /**
     * Get the validation Url necessary if simulation of authorization process is not allowed
     *
     * @return string
     */
    public function getValidationUrl()
    {
        try {
            $websiteId = $this->getCurrentWebsite()->getId();
            $serviceModel = $this->getServiceModel();
            $validationUrl = $serviceModel->getValidationUrl();

            // Refresh the database cause of new data after getting validation url
            $apiToken = Mage::getModel('diglin_ricento/api_token')->loadByWebsiteAndTokenType(ServiceAbstract::TOKEN_TYPE_TEMPORARY, $websiteId);
            $apiToken
                ->setWebsiteId($websiteId)
                ->setToken($serviceModel->getTemporaryToken())
                ->setExpirationDate($serviceModel->getTemporaryTokenExpirationDate())
                ->setTokenType(ServiceAbstract::TOKEN_TYPE_TEMPORARY)
                ->save();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('diglin_ricento')->__('Security error occurred with the ricardo API. Please, check your log files.'));
            $validationUrl = null;
        }
        return $validationUrl;
    }
}