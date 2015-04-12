<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2014 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Diglin_Ricento_Block_Adminhtml_Dashboard_Banner
 */
class Diglin_Ricento_Block_Adminhtml_Dashboard_Banner extends Mage_Core_Block_Template
{
    /**
     * @return bool|string
     */
    public function getBannerUrlFromXml()
    {
        try {
            $url = Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_BANNER_XML);
            if (strpos($url, 'http') === false) {
                return false;
            }
            $bannerXML = new SimpleXMLElement($url, 0, true);

        } catch (Exception $e) {
            $bannerXML  = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?>');
        }

        $helper = Mage::helper('diglin_ricento');
        $lang = strtoupper($helper->getDefaultSupportedLang());

        if ($bannerXML) {
            $url = $bannerXML->item[0]->$lang->imagePath;
            if (strpos($url, 'http') !== false) {
                return $url;
            }
        }
        return false;
    }

    /**
     * @return bool|string
     */
    public function getBannerUrl()
    {
        $banner = $this->getBannerUrlFromXml();
        return ($banner) ? $banner : $this->getSkinUrl('ricento/images/banner.png');
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_BANNER);
    }
}