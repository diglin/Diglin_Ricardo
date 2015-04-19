<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2015 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Diglin_Ricento_Block_Adminhtml_Dashboard_Banner
 */
class Diglin_Ricento_Block_Adminhtml_Dashboard_Banner extends Mage_Core_Block_Template
{
    /**
     * @var bool
     */
    protected $_xml = false;

    /**
     * @return bool|string
     */
    public function getBannerXml()
    {
        if (!$this->_xml) {

            try {
                $url = Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_BANNER_XML);
                $xml = file_get_contents($url);

                if (!$xml) {
                    return false;
                }

                $this->_xml = new SimpleXMLElement($xml);

            } catch (Exception $e) {
                return new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?>');
            }
        }
        return $this->_xml;
    }

    /**
     * @return bool|string
     */
    public function getBannerSrc()
    {
        $url = false;
        $bannerXml = $this->getBannerXml();

        $helper = Mage::helper('diglin_ricento');
        $lang = strtolower($helper->getDefaultSupportedLang());

        if ($bannerXml) {
            $url = trim($bannerXml->$lang->url);
            if (strpos($url, 'http') !== false) {
                return $url;
            }
        }

        return ($url) ? $url : false;
    }

    public function getBannerUrl()
    {
        $url = false;
        $bannerXml = $this->getBannerXml();

        $helper = Mage::helper('diglin_ricento');
        $lang = strtolower($helper->getDefaultSupportedLang());

        if ($bannerXml) {
            $url = trim($bannerXml->$lang->link);
            if (strpos($url, 'http') !== false) {
                return $url;
            }
        }

        return ($url) ? $url : '#';
    }

    public function getBannerTitle()
    {
        $url = false;
        $bannerXml = $this->getBannerXml();

        $helper = Mage::helper('diglin_ricento');
        $lang = strtolower($helper->getDefaultSupportedLang());

        if ($bannerXml) {
            $url = trim($bannerXml->$lang->title);
            if (strpos($url, 'http') !== false) {
                return $url;
            }
        }

        return ($url) ? $url : '#';
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_BANNER);
    }
}