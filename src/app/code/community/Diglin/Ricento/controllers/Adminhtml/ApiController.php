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
use Diglin\Ricardo\Services\ServiceAbstract;

class Diglin_Ricento_Adminhtml_ApiController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('ricento/config');
    }

    public function confirmationAction()
    {
        $success = (int)$this->getRequest()->getParam('success', 0);

        if ($success) {
            $temporaryToken = $this->getRequest()->getParam('temporarytoken');
            $websiteId = (int)$this->getRequest()->getParam('website');

            if (!is_numeric($websiteId) || is_null($websiteId)) {
                $this->_getSession()->addError($this->__('The website code returned from ricardo.ch is not correct! Your authorization has not been saved on our side.'));
                $this->_redirect('adminhtml');
                return;
            }

            $securityService = Mage::getSingleton('diglin_ricento/api_services_security');
            /* @var $securityServiceModel Diglin\Ricardo\Managers\Security */
            $securityServiceModel = $securityService
                ->setCurrentWebsite($websiteId)
                ->getServiceModel(); // Get Service Model instead to use __call method, prevent caching but also some logic

            try {
                // Save the temporary token created after to got the validation url from Diglin_Ricento_Model_Api_Services_Security
                $apiTokenTemp = Mage::getModel('diglin_ricento/api_token')
                    ->loadByWebsiteAndTokenType(ServiceAbstract::TOKEN_TYPE_TEMPORARY, $websiteId);

                $apiTokenTemp
                    ->setWebsiteId($websiteId)
                    ->setToken($temporaryToken)
                    ->setTokenType(ServiceAbstract::TOKEN_TYPE_TEMPORARY)
                    ->save();

                $expirationDate = new DateTime($apiTokenTemp->getExpirationDate());

                // Initialize the Security Model with the required variable
                $securityServiceModel
                    ->setTemporaryTokenExpirationDate($expirationDate->getTimestamp())
                    ->setTemporaryToken($temporaryToken);

                // Save the credential token for future use
                $apiToken = Mage::getModel('diglin_ricento/api_token')
                    ->loadByWebsiteAndTokenType(ServiceAbstract::TOKEN_TYPE_IDENTIFIED, $websiteId);

                $apiToken
                    ->setWebsiteId($websiteId)
                    ->setToken($securityServiceModel->getCredentialToken())
                    ->setTokenType(ServiceAbstract::TOKEN_TYPE_IDENTIFIED)
                    ->setExpirationDate($securityServiceModel->getCredentialTokenExpirationDate())
                    ->setSessionDuration($securityServiceModel->getCredentialTokenSessionDuration())
                    ->setSessionExpirationDate(
                        Mage::helper('diglin_ricento/api')->calculateSessionExpirationDate(
                            $securityServiceModel->getCredentialTokenSessionDuration(),
                            $securityServiceModel->getCredentialTokenSessionStart()))
                    ->save();

                // Cleanup as we do not need it and in any case we will have to generate it again.
                $apiTokenTemp->delete();

                $this->_getSession()->addSuccess($this->__('Your ricardo.ch account has been authorized to get access to the API.'));

            } catch (Exception $e) {
                Mage::logException($e);
                Mage::log($securityService->getLastApiDebug($websiteId), Zend_Log::DEBUG, Diglin_Ricento_Helper_Data::LOG_FILE);
                $this->_getSession()->addError($this->__('An error occurred while saving the token. Please, check your log files.'));
            }
        } else {
            $this->_getSession()->addError($this->__('Authorization was not successful on ricardo.ch side. Please, contact ricardo.ch to find out the reason.'));
        }

        $this->_redirect('adminhtml');
    }

    public function unlinkTokenAction()
    {
        $entityId = $this->getRequest()->getParam('entity_id');
        if (!empty($entityId)) {

            $token = Mage::getModel('diglin_ricento/api_token')
                ->load($entityId);

            if ($token->getId()) {
                $token->delete();
            }
        }

        $this->_redirect('ricento/dashboard');
    }
}