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

use \Diglin\Ricardo\Services\Security;

/**
 * Class Diglin_Ricento_Helper_Api
 */
class Diglin_Ricento_Helper_Api extends Mage_Core_Helper_Abstract
{
    /**
     * Cache type for ricardo API
     */
    const CACHE_TYPE        = 'ricardo_api';

    /**
     * Cache tag for ricardo API
     */
    const CACHE_TAG         = 'RICARDO_API';

    /**
     * Cache lifetime
     */
    const CACHE_LIFETIME    = 86400;

    /**
     * Get if the token credential is going to expire or even not exist
     *
     * @param int|string|Mage_Core_Model_Website $website
     * @return bool
     */
    public function apiTokenCredentialGoingToExpire($website = 0)
    {
        $dayDelay = Mage::helper('diglin_ricento')->getExpirationNotificationDelay();
        $expirationDate = $this->getExpirationDate($website);

        if (empty($expirationDate) ||
            isset($expirationDate) && time() >= (Mage::getSingleton('core/date')->timestamp($expirationDate) - ($dayDelay * 24 * 3600))
        ) {
            return true;
        }

        return false;
    }

    /**
     * The API token can be validated X days before expiration
     *
     * @param int $website
     * @return bool
     */
    public function apiTokenCredentialValidation($website = 0)
    {
        $dayDelay = Mage::helper('diglin_ricento')->getExpirationNotificationValidationDelay();

        $expirationDate = $this->getExpirationDate($website);
        if (empty($expirationDate) ||
            isset($expirationDate) && time() >= (Mage::getSingleton('core/date')->timestamp($expirationDate) - ($dayDelay * 24 * 3600))
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param int $website
     * @return bool
     */
    public function apiExpired($website = 0)
    {
        $expirationDate = $this->getExpirationDate($website);
        if (empty($expirationDate) ||
            isset($expirationDate) && time() >= (Mage::getSingleton('core/date')->timestamp($expirationDate))
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param int $website
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getExpirationDate($website = 0)
    {
        $token = Mage::getModel('diglin_ricento/api_token')
            ->loadByWebsiteAndTokenType(Security::TOKEN_TYPE_IDENTIFIED, Mage::app()->getWebsite($website)->getId());

        return $token->getExpirationDate();
    }

    /**
     * Get if the token credential exists
     *
     * @param int|string|Mage_Core_Model_Website $website
     * @return bool
     */
    public function apiTokenCredentialExists($website = 0)
    {
        $token = Mage::getModel('diglin_ricento/api_token')
            ->loadByWebsiteAndTokenType(Security::TOKEN_TYPE_IDENTIFIED, Mage::app()->getWebsite($website)->getId());
        return ($token->getId()) ? true : false;
    }

    /**
     * @param int $sessionDuration in minutes
     * @param null $time
     * @return int|null
     */
    public function calculateSessionExpirationDate($sessionDuration, $time = null)
    {
        $sessionDuration *= 60;
        if (is_null($time)) {
            return time() + $sessionDuration;
        }

        return $time + $sessionDuration;
    }

    /**
     * Calculate the session start time
     *
     * @param int $sessionDuration in minutes
     * @param null $time
     * @return int|null
     */
    public function calculateSessionStart($sessionDuration, $time)
    {
        return strtotime($time) - ($sessionDuration * 60);
    }

    /**
     * @param int $websiteId
     * @return string
     */
    public function getValidationUrl($websiteId = 0)
    {
        return Mage::getSingleton('diglin_ricento/api_services_security')
            //@fixme there is issue with getting credential token in multi shop so for real website support start to fix here - not planned at the moment
            //            ->setCurrentWebsite($websiteId)
            ->getValidationUrl();
    }

    /**
     * @param int $website
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function isMerchantNotifiedApiAuthorization($website = 0)
    {
        $token = Mage::getModel('diglin_ricento/api_token')
            ->loadByWebsiteAndTokenType(Security::TOKEN_TYPE_IDENTIFIED, Mage::app()->getWebsite($website)->getId());

        if ($token->getId() && $token->getMerchantNotified()) {
            return true;
        }

        return false;
    }

    /**
     * @param int $website
     * @return string
     */
    public function getAntiforgeryToken($website = 0)
    {
        return Mage::getSingleton('diglin_ricento/api_services_security')
            ->setCurrentWebsite($website)
            ->getServiceModel()
            ->getAntiforgeryToken();
    }
}