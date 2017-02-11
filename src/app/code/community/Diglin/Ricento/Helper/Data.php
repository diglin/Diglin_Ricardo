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

use Diglin\Ricardo\Enums\System\LanguageId;
use Diglin\Ricardo\Enums\PaymentMethods;

/**
 * Class Diglin_Ricento_Helper_Data
 */
class Diglin_Ricento_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * API config
     */
    const CFG_ENABLED                       = 'ricento/api_config/enabled';
    const CFG_ASSISTANT_URL                 = 'ricento/api_config/assistant_url';
    const CFG_ASSISTANT_URL_DEV             = 'ricento/api_config/assistant_url_dev';
    const CFG_RICARDO_SIGNUP_API_URL        = 'ricento/api_config/signup_url';
    const CFG_DEV_MODE                      = 'ricento/api_config/dev_mode';
    const CFG_DEBUG_MODE                    = 'ricento/api_config/debug';
    const CFG_API_HOST                      = 'ricento/api_config/host';
    const CFG_API_HOST_DEV                  = 'ricento/api_config/host_dev';
    const CFG_SIMULATE_AUTH                 = 'ricento/api_config/simulate_authorization';
    const CFG_RICARDO_USERNAME              = 'ricento/api_config/ricardo_username';
    const CFG_RICARDO_PASSWORD              = 'ricento/api_config/ricardo_password';
    const CFG_RICARDO_PARTNERKEY            = 'ricento/api_config/partner_key_';
    const CFG_RICARDO_PARTNERPASS           = 'ricento/api_config/partner_pass_';
    const CFG_EXPIRATION_NOTIFICATION_DELAY = 'ricento/api_config/expiration_notification_delay'; // in day
    const CFG_EXPIRATION_NOTIFICATION_VALIDATION_DELAY = 'ricento/api_config/expiration_notification_validation_delay'; // in day
    const CFG_EMAIL_NOTIFICATION            = 'ricento/api_config/email_notification';

    const CFG_SUPPORTED_LANG                = 'ricento/api_config/lang';
    const DEFAULT_SUPPORTED_LANG            = 'de';
    const LANG_ALL                          = 'all';

    const ALLOWED_CURRENCY                  = 'CHF';
    const DEFAULT_COUNTRY_CODE              = 'CH';

    /**
     * Global config
     */
    const CFG_SHIPPING_CALCULATION          = 'ricento/global/shipping_calculation';
    const CFG_ACCOUNT_CREATION_EMAIL        = 'ricento/global/email_account_creation';
    const CFG_ORDER_CREATION_EMAIL          = 'ricento/global/email_order_creation';
    const CFG_MERGE_ORDER                   = 'ricento/global/merge_order';
    const CFG_DECREASE_INVENTORY            = 'ricento/global/decrease_inventory';
    const CFG_BANNER                        = 'ricento/global/banner/enabled';
    const CFG_BANNER_XML                    = 'ricento/global/banner/xml';
    const CFG_STATS                         = 'ricento/global/stats';
    const CFG_STATS_TEST_MODE               = 'ricento/global/stats_test_mode';
    const CFG_STATS_APPID                   = 'ricento/global/stats_app_id';
    const CFG_STATS_APPID_TEST              = 'ricento/global/stats_app_id_test';
    const CFG_UPDATE_NOTIFICATION           = 'ricento/global/update_notification';
    const CFG_IMPORT_TRANSACTION            = 'ricento/global/import_transaction';

    /**
     * Listing config
     */
    const CFG_MERGE_DESCRIPTIONS            = 'ricento/listing/merge_descriptions';
    const CFG_NL2BR                         = 'ricento/listing/nl2br';
    const CFG_WATERMARK_ENABLED             = 'ricento/listing/watermark_enabled';
    const CFG_WATERMARK                     = 'ricento/listing/watermark_image';
    const CFG_WATERMARK_OPACITY             = 'ricento/listing/watermark_imageOpacity';
    const CFG_WATERMARK_POSITION            = 'ricento/listing/watermark_position';
    const CFG_WATERMARK_SIZE                = 'ricento/listing/watermark_size';
    const CFG_IMAGE_PLACEHOLDER             = 'ricento/listing/placeholder_allowed';

    /**
     * Cleanup Job config
     */
    const CFG_CLEAN_JOBS_ENABLED            = 'ricento/cleanup_jobs/enabled';
    const CFG_CLEAN_JOBS_KEEP_DAYS          = 'ricento/cleanup_jobs/keep_days';

    /**
     * Common statuses for products listing and products listing item
     */
    const STATUS_PENDING    = 'pending';
    const STATUS_LISTED     = 'listed';
    const STATUS_STOPPED    = 'stopped';
    const STATUS_READY      = 'ready';
    const STATUS_ERROR      = 'error';
    const STATUS_SOLD       = 'sold';

    const LOG_FILE          = 'ricento.log';

    const RICARDO_URL                      = 'http://www.ricardo.ch';
    const RICARDO_URL_HELP_PROMOTION_DE    = 'http://www.ricardo.ch/ueber-uns/gebühren/einstelloptionen';
    const RICARDO_URL_HELP_PROMOTION_FR    = 'http://www.fr.ricardo.ch/ueber-uns/fr-fr/frais/optionsdepublication';
    const RICARDO_URL_TERMS_DE             = 'http://www.ricardo.ch/ueber-uns/de-ch/reglemente.aspx';
    const RICARDO_URL_TERMS_FR             = 'http://www.fr.ricardo.ch/ueber-uns/fr-fr/règlements';
    const RICARDO_URL_PRIVACY_DE           = self::RICARDO_URL_TERMS_DE;
    const RICARDO_URL_PRIVACY_FR           = self::RICARDO_URL_TERMS_FR;
    const RICARDO_URL_FEES_DE              = self::RICARDO_URL_TERMS_DE;
    const RICARDO_URL_FEES_FR              = self::RICARDO_URL_TERMS_FR;

    const NODE_DISPATCHER_TYPES     = 'global/ricento/dispatcher/types';
    const NODE_PRODUCT_TYPES        = 'global/ricento/allow_product_types';

    /**
     * Payment Config
     */
    const PAYMENT_BANK_INFO         = 'payment/ricento/bank_transfer_instructions';

    /**
     * Order Status
     */
    const ORDER_STATUS_PENDING      = 'ricardo_payment_pending';
    const ORDER_STATUS_CANCEL       = 'ricardo_payment_canceled';

    /**
     * Stock Management
     */
    const INVENTORY_QTY_TYPE_FIX        = 'fix';
    const INVENTORY_QTY_TYPE_PERCENT    = 'percent';

    const MAX_AMOUNT_PUSH               = 200;

    /**
     * @var Mage_Directory_Model_Currency
     */
    protected $_oldCurrency;

    /**
     * Is the extension enabled for the current website
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return bool
     */
    public function isEnabled($website = 0)
    {
        return (bool) Mage::getConfig()->getModuleConfig('Diglin_Ricento')->active
        && self::getWebsiteConfigFlag(self::CFG_ENABLED, $website);
    }

    /**
     * @param int $website
     * @return bool
     */
    public function isEnabledConfigured($website = 0)
    {
        return $this->isEnabled($website) && $this->isConfigured($website);
    }

    /**
     * Returns product types that are available in Ricento
     *
     * @return array [ type_id => type_id ]
     */
    public function getAllowedProductTypes()
    {
        $allowProductTypes = array();
        foreach (Mage::getConfig()
                     ->getNode(self::NODE_PRODUCT_TYPES)->children() as $type) {
            $allowProductTypes[$type->getName()] = $type->getName();
        }
        return $allowProductTypes;
    }

    /**
     * Get the configuration Ricardo url
     *
     * @param int|null|Mage_Core_Model_Website
     * @return string
     */
    public function getConfigurationUrl($website = null)
    {
        $params = array();

        if ($website instanceof Mage_Core_Model_Website) {
            $websiteId = $website->getId();
        } else {
            $websiteId = $website;
        }

        if (!is_null($website) && $websiteId != 0) {
            $params = array('website' => Mage::app()->getWebsite($website)->getCode());
        }
        return Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit/section/ricento', $params);
    }

    /**
     * Get the Ricardo Assistant Url
     *
     * @return string
     */
    public function getRicardoAssistantUrl()
    {
        if ($this->isDevMode()) {
            $urlConfig = self::CFG_ASSISTANT_URL_DEV;
        } else {
            $urlConfig = self::CFG_ASSISTANT_URL;
        }

        return Mage::getStoreConfig($urlConfig);
    }

    /**
     * Get the Ricardo Signup Form URL
     *
     * @param boolean $intern
     * @return string
     */
    public function getRicardoSignupApiUrl($intern = true)
    {
        if ($intern) {
            return Mage::helper('adminhtml')->getUrl('*/ricento_account/signup');
        }
        return Mage::getStoreConfig(self::CFG_RICARDO_SIGNUP_API_URL);
    }

    /**
     * Is Development Mode enabled
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return bool
     */
    public function isDevMode($website = 0)
    {
        return self::getWebsiteConfigFlag(self::CFG_DEV_MODE, $website);
    }

    /**
     * Check if Ricardo API is configured correctly
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return bool
     */
    public function isConfigured($website = 0)
    {
        $configured = false;
        $configuredAccount = (!$this->canSimulateAuthorization() || ($this->canSimulateAuthorization() && $this->getRicardoUsername($website) && $this->getRicardoPass($website))) ? true : false;

        foreach ($this->getSupportedLang() as $lang) {
            if ($this->getPartnerKey($lang, $website) && $this->getPartnerPass($lang, $website)) {
                $configured = true;
                break;
            }
        }

        if ($configured && $configuredAccount) {
            return true;
        }

        return false;
    }

    /**
     * Is Debug Enabled
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return bool
     */
    public function isDebugEnabled($website = 0)
    {
        return self::getWebsiteConfigFlag(self::CFG_DEBUG_MODE, $website);
    }

    /**
     * Get which method to use to calculate the shipping costs
     *
     * @param int|null|Mage_Core_Model_Store $storeId
     * @return mixed
     */
    public function getShippingCalculationMethod($storeId = null)
    {
        return Mage::getStoreConfig(self::CFG_SHIPPING_CALCULATION, $storeId);
    }

    /**
     * Can simulate authorization process
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return bool
     */
    public function canSimulateAuthorization($website = 0)
    {
        return self::getWebsiteConfigFlag(self::CFG_SIMULATE_AUTH, $website);
    }

    /**
     * Get the Ricardo API Partner ID Configuration
     *
     * @param string|null $lang
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return string
     */
    public function getPartnerKey($lang = null, $website = 0)
    {
        $dev = ($this->isDevMode($website)) ? 'dev_' : '';
        $lang = $this->_getLocaleCodeForApiConfig($lang);
        return self::getWebsiteConfig(self::CFG_RICARDO_PARTNERKEY . $dev . $lang, $website);
    }

    /**
     * Get the Ricardo API Partner Pass Configuration
     *
     * @param string|null $lang
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return string
     */
    public function getPartnerPass($lang = null, $website = 0)
    {
        $dev = ($this->isDevMode($website)) ? 'dev_' : '';
        $lang = $this->_getLocaleCodeForApiConfig($lang);
        return Mage::helper('core')->decrypt(self::getWebsiteConfig(self::CFG_RICARDO_PARTNERPASS. $dev . $lang, $website));
    }

    /**
     * Get the partner url to get the confirmation
     *
     * @param int $websiteId
     * @return string
     */
    public function getPartnerUrl($websiteId = 0)
    {
        return Mage::helper('adminhtml')->getUrl('*/ricento_api/confirmation', array('website' => (int) $websiteId));
    }

    /**
     * Normalize the locale to get only two first letters code or the Germand default
     *
     * @param string $lang
     * @return string
     */
    protected function _getLocaleCodeForApiConfig($lang = null)
    {
        if (empty($lang)) {
            $lang = Mage::app()->getLocale()->getLocaleCode();
        }

        if ($lang) {
            $lang = substr(strtolower($lang), 0, 2);
        }

        if (!in_array($lang, $this->getSupportedLang())) {
            $lang = self::DEFAULT_SUPPORTED_LANG;
        }

        return $lang;
    }

    /**
     * Get the list of supported API language
     *
     * @return array
     */
    public function getSupportedLang()
    {
        return explode(',', strtolower(Mage::getStoreConfig(self::CFG_SUPPORTED_LANG)));
    }

    /**
     * @param null|string $lang
     * @return int
     */
    public function getRicardoLanguageIdFromLocaleCode($lang = null)
    {
        $lang = $this->_getLocaleCodeForApiConfig($lang);
        switch ($lang) {
            case 'de':
                $langId = LanguageId::GERMAN;
                break;
            case 'fr':
                $langId = LanguageId::FRENCH;
                break;
            default:
                $langId = LanguageId::NONE;
                break;
        }

        return $langId;
    }

    /**
     * @param $id
     * @return string
     */
    public function getLocalCodeFromRicardoLanguageId($id)
    {
        switch ($id) {
            case LanguageId::FRENCH:
                $locale = 'fr';
                break;
            default:
            case LanguageId::GERMAN:
                $locale = 'de';
                break;
        }

        return $locale;
    }

    /**
     * Get the default supported lang depending if the partner key is set or not
     *
     * @return string
     */
    public function getDefaultSupportedLang()
    {
        $lang = $this->_getLocaleCodeForApiConfig();
        if ($this->getPartnerKey($lang)) {
            return $lang;
        }

        foreach($this->getSupportedLang() as $lang) {
            if ($this->getPartnerKey($lang)) {
                return $lang;
            }
        }

        return self::DEFAULT_SUPPORTED_LANG;
    }

    /**
     * Get the Ricardo customer username
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return string
     */
    public function getRicardoUsername($website = 0)
    {
        return self::getWebsiteConfig(self::CFG_RICARDO_USERNAME, $website);
    }

    /**
     * Get the Ricardo customer username
     *
     * @param null|string|bool|int|Mage_Core_Model_Website $website
     * @return string
     */
    public function getRicardoPass($website = 0)
    {
        return Mage::helper('core')->decrypt(self::getWebsiteConfig(self::CFG_RICARDO_PASSWORD, $website));
    }

    /**
     * Get the delay in days to notify the owner that the API credentials will expire
     *
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     * @return int
     */
    public function getExpirationNotificationDelay($store = 0)
    {
        return Mage::getStoreConfig(self::CFG_EXPIRATION_NOTIFICATION_DELAY, $store);
    }

    /**
     * Get the delay in days to notify the owner that the API authorisation must be triggered
     *
     * @param int $store
     * @return mixed
     */
    public function getExpirationNotificationValidationDelay($store = 0)
    {
        return Mage::getStoreConfig(self::CFG_EXPIRATION_NOTIFICATION_VALIDATION_DELAY, $store);
    }

    /**
     * Disable all elements in a form recursively
     *
     * @param Varien_Data_Form_Abstract $form
     */
    public function disableForm(Varien_Data_Form_Abstract $form)
    {
        foreach ($form->getElements() as $element) {
            /* @var $element Varien_Data_Form_Element_Abstract */
            $element->setDisabled(true);
            if ($element->getType() === 'button') {
                $element->addClass('disabled');
            }
            $this->disableForm($element);
        }
    }

    /**
     * @param $path
     * @param null|int|string|Mage_Core_Model_Website $website
     * @return mixed
     */
    public static function getWebsiteConfig($path, $website = null)
    {
        return Mage::app()->getWebsite($website)->getConfig($path);
    }

    /**
     * @param $path
     * @param null|int|string|Mage_Core_Model_Website $website
     * @return mixed
     */
    public static function getWebsiteConfigFlag($path, $website = null)
    {
        $flag = strtolower(self::getWebsiteConfig($path, $website));
        if (!empty($flag) && 'false' !== $flag) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the store list of a products listing based on supported language
     *
     * @param int|Diglin_Ricento_Model_Products_Listing $productsListing
     * @return array
     */
    public function getStoresFromListing($productsListing)
    {
        if (is_numeric($productsListing)) {
            $productsListing = Mage::getModel('diglin_ricento/products_listing')->load($productsListing);
        } else if (!$productsListing instanceof Diglin_Ricento_Model_Products_Listing) {
            return array();
        }

        $stores = array();
        $baseMethod = 'getLangStoreId';
        $defaultLang = $productsListing->getDefaultLanguage();
        $publishLang = $productsListing->getPublishLanguages();

        if ($publishLang != Diglin_Ricento_Helper_Data::LANG_ALL) {
            $method =  $baseMethod . ucwords($publishLang);
            $store = (int) $productsListing->$method();
            if (!empty($store)) {
                $stores[] = $store;
            }
        } else {
            $supportedLang = Mage::helper('diglin_ricento')->getSupportedLang();

            // We set default lang at first position
            $method = $baseMethod . ucwords($defaultLang);
            $stores[] = (int) $productsListing->$method();

            foreach ($supportedLang as $lang) {

                // Prevent to have the default language twice
                if (strtolower($lang) == strtolower($defaultLang)) {
                    continue;
                }

                $method = $baseMethod . ucwords($lang);
                $stores[] = (int) $productsListing->$method();
            }
        }

        return $stores;
    }

    /**
     * @return string
     */
    public function getDateTimeIsoFormat()
    {
        $locale = Mage::app()->getLocale();
        return $locale->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT)
            . ' ' . $locale->getTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);
    }

    /**
     * @fixme potential problem with missing Mage::getSingleton('diglin_ricento/api_services_selleraccount')->setCurrentWebsite
     * not fixed at the moment bc Diglin_Ricento_Model_Validate_Rules_Methods use this helper and may be not in a context
     * of having relevant information about which website to use (default is used at the moment)
     *
     * @return bool
     */
    public function isCardPaymentAllowed()
    {
        $creditCardAvailable = false;
        $paymentOptions = (array) Mage::getSingleton('diglin_ricento/api_services_selleraccount')->getPaymentOptions();

        if (isset($paymentOptions['CardPaymentOptionAvailable'])) {
            $creditCardAvailable = (bool) $paymentOptions['CardPaymentOptionAvailable'];
        }
        return $creditCardAvailable;
    }

    /**
     * Is the ricardo payment method ID is Credit Card
     *
     * @param $paymentMethodId
     * @return bool
     */
    public function isCreditCard($paymentMethodId)
    {
        if ($paymentMethodId == PaymentMethods::TYPE_CREDIT_CARD) {
            return true;
        }

        return false;
    }

    /**
     * Generate the Internal Reference - Must be unique to allow the mapping with ricardo
     * Returned value will be similar to "123456#PID#987654"
     *
     * @param Diglin_Ricento_Model_Products_Listing_Item $item
     * @param int $productId
     * @return string
     */
    public function generateInternalReference(Diglin_Ricento_Model_Products_Listing_Item $item, $productId = null)
    {
        if (is_null($productId)) {
            $productId = $item->getProductId();
        }

        return $item->getId() . '#PID#' . $productId;
    }

    /**
     * Extract the item Id and the product Id from the Internal Reference exposed from the ricardo API
     *
     * @param $internalReference
     * @return Varien_Object
     */
    public function extractInternalReference($internalReference)
    {
        if (strpos($internalReference, '#PID#') === false) {
            return $internalReference;
        }

        list ($itemId, $productId) = explode('#PID#', $internalReference);

        return new Varien_Object(array('item_id' => $itemId, 'product_id' => $productId));
    }

    /**
     * @param array $data
     * @return Varien_Object
     */
    public function extractData(array $data)
    {
        $object = new Varien_Object();

        foreach ($data as $key => $value) {
            if (is_array($value) && !is_numeric($key)) {
                $value = $this->extractData($value);
            }
            $object->setDataUsingMethod($key, $value);
        }

        return $object;
    }

    /**
     * @param $unixtimestamp
     * @return string
     */
    public function getJsonDate($unixtimestamp = null)
    {
        return \Diglin\Ricardo\Core\Helper::getJsonDate($unixtimestamp);
    }

    /**
     * @param $date
     * @return string
     */
    public function getJsonTimestamp($date)
    {
        return \Diglin\Ricardo\Core\Helper::getJsonTimestamp($date);
    }

    /**
     * @return bool
     */
    public function getDecreaseInventory()
    {
        return Mage::getStoreConfigFlag(self::CFG_DECREASE_INVENTORY);
    }

    /**
     * Get allowed HTML Tags from the API
     *
     * @return array
     */
    public function getAllowedTags()
    {
        $allowedTags = array();
        $partnerConfiguration = (array) Mage::getSingleton('diglin_ricento/api_services_system')->getPartnerConfigurations();
        if (isset($partnerConfiguration['GrantedDescriptionTags'])) {
            foreach ($partnerConfiguration['GrantedDescriptionTags'] as $tag) {
                $allowedTags[] = '<'.$tag.'>';
            }
        }

        return $allowedTags;
    }

    /**
     * @param string $file
     * @return bool
     */
    public function checkMemory($file)
    {
        return $this->getMemoryLimit() > ($this->getMemoryUsage() + $this->getNeedMemoryForFile($file)) || $this->getMemoryLimit() == -1;
    }

    /**
     * @return int
     */
    public function getMemoryLimit()
    {
        $memoryLimit = trim(strtoupper(ini_get('memory_limit')));

        if (!isset($memoryLimit[0])){
            $memoryLimit = "128M";
        }

        if (substr($memoryLimit, -1) == 'K') {
            return substr($memoryLimit, 0, -1) * 1024;
        }
        if (substr($memoryLimit, -1) == 'M') {
            return substr($memoryLimit, 0, -1) * 1024 * 1024;
        }
        if (substr($memoryLimit, -1) == 'G') {
            return substr($memoryLimit, 0, -1) * 1024 * 1024 * 1024;
        }
        return (int) $memoryLimit;
    }

    /**
     * @return int
     */
    public function getMemoryUsage()
    {
        if (function_exists('memory_get_usage')) {
            return memory_get_usage();
        }
        return 0;
    }

    /**
     * @param string $file
     * @return float|int
     */
    public function getNeedMemoryForFile($file)
    {
        if (!file_exists($file) || !is_file($file)) {
            return 0;
        }

        $imageInfo = getimagesize($file);

        if (!isset($imageInfo[0]) || !isset($imageInfo[1])) {
            return 0;
        }
        if (!isset($imageInfo['channels'])) {
            // if there is no info about this parameter lets set it for maximum
            $imageInfo['channels'] = 4;
        }
        if (!isset($imageInfo['bits'])) {
            // if there is no info about this parameter lets set it for maximum
            $imageInfo['bits'] = 8;
        }
        return round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2, 16)) * 1.65);
    }

    /**
     * @param int $storeId
     * @return mixed
     */
    public function canMergeDescriptions($storeId = 0)
    {
        return Mage::getStoreConfigFlag(self::CFG_MERGE_DESCRIPTIONS, $storeId);
    }

    /**
     * @param Diglin_Ricento_Model_Products_Listing_Item $item
     * @return int|string
     */
    public function getStartingDate(Diglin_Ricento_Model_Products_Listing_Item $item)
    {
        if ($item->getSalesOptions()->getScheduleOverwriteProductDateStart()) {
            $startDate = $item->getProductsListing()->getSalesOptions()->getScheduleDateStart();
        } else {
            $startDate = $item->getSalesOptions()->getScheduleDateStart();
        }

        if (!is_null($startDate)) {
            $startDate = strtotime($startDate);
        }

        // ricardo.ch constrains, starting date must be in 1 hour in future
        if (!is_null($startDate) && $startDate <= time() + 3600) {
            $startDate = time() + 3600;
        }

        return $startDate;
    }

    /**
     * @return string
     */
    public function getFeesRulesUrl()
    {
        if ($this->_getLocaleCodeForApiConfig() == 'fr') {
            return self::RICARDO_URL_FEES_FR;
        } else {
            return self::RICARDO_URL_FEES_DE;
        }
    }

    /**
     * @return string
     */
    public function getTermsUrl()
    {
        if ($this->_getLocaleCodeForApiConfig() == 'fr') {
            return self::RICARDO_URL_TERMS_FR;
        } else {
            return self::RICARDO_URL_TERMS_DE;
        }
    }

    /**
     * @return string
     */
    public function getPrivacyUrl()
    {
        if ($this->_getLocaleCodeForApiConfig() == 'fr') {
            return self::RICARDO_URL_PRIVACY_FR;
        } else {
            return self::RICARDO_URL_PRIVACY_DE;
        }
    }

    /**
     * @return string
     */
    public function getHelpPromotion()
    {
        if ($this->_getLocaleCodeForApiConfig() == 'fr') {
            return self::RICARDO_URL_HELP_PROMOTION_FR;
        } else {
            return self::RICARDO_URL_HELP_PROMOTION_DE;
        }
    }

    /**
     * @return Varien_Object
     */
    public function getRicardoShippingRegistry()
    {
        if(!Mage::registry('ricardo_shipping')) {
            Mage::register('ricardo_shipping', new Varien_Object());
        };

        return Mage::registry('ricardo_shipping');
    }

    /**
     * @return bool
     */
    public function canSendEmailNotification()
    {
        return Mage::getStoreConfigFlag(self::CFG_EMAIL_NOTIFICATION);
    }

    /**
     * @param null $websiteId
     * @return Mage_Core_Model_Store
     * @throws Mage_Core_Exception
     */
    public function getDefaultStore($websiteId = null)
    {
        $store = Mage::app()->getWebsite($websiteId)->getDefaultStore();
        if (is_null($store)) {
            $storeIds = Mage::app()->getWebsite($websiteId)->getStoreIds();
            $store = Mage::app()->getStore($storeIds[0]);
        }

        return $store;
    }

    /**
     * @param null $store
     * @return bool
     */
    public function canNotifyUpdate($store = null)
    {
        return Mage::getStoreConfigFlag(self::CFG_UPDATE_NOTIFICATION, $store);
    }

    /**
     * @param null $store
     * @return bool
     */
    public function canImportTransaction($store = null)
    {
        return Mage::getStoreConfigFlag(self::CFG_IMPORT_TRANSACTION, $store);
    }
}