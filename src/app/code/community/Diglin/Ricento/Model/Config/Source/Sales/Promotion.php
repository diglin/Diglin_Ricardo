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
 * Class Diglin_Ricento_Model_Config_Source_Sales_Promotion
 */
class Diglin_Ricento_Model_Config_Source_Sales_Promotion extends Diglin_Ricento_Model_Config_Source_Abstract
{
    protected $_promotions = array();

    /**
     * Return options as value => label array
     *
     * @return array
     */
    public function toOptionHash()
    {
        if (empty($this->_promotions) && Mage::helper('diglin_ricento')->isConfigured()) {
            $promotions = (array)Mage::getSingleton('diglin_ricento/api_services_system')->getPromotions(
                Mage::helper('diglin_ricento')->getJsonDate(), \Diglin\Ricardo\Enums\System\CategoryArticleType::ALL, 1, 1
            );

            if (empty($promotions)) {
                return array();
            }

            $websiteId = 0;
            // Listing exists in a context of products listing edition
            $listing = Mage::registry('products_listing');
            if ($listing->getWebsiteId()) {
                $websiteId = $listing->getWebsiteId();
            }

            $helper = Mage::helper('diglin_ricento/price');

            $this->_promotions = array(0 => $helper->__('No package'));

            foreach ($promotions as $promotion) {
                if ($promotion['GroupId'] == \Diglin\Ricardo\Enums\Article\PromotionCode::PREMIUMCATEGORY) {
                    $this->_promotions[$promotion['PromotionId']] = $helper->__($promotion['PromotionLabel'])
                        . ' - '
                        . implode(' / ', $helper->formatDoubleCurrency($promotion['PromotionFee'], $websiteId, Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY));
                }
            }
        }

        return $this->_promotions;
    }

}