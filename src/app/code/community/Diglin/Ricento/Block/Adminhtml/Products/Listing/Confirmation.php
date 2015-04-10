<?php

/**
 * Diglin GmbH - Switzerland
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    Ricento
 * @package     Ricento
 * @copyright   Copyright (c) 2011-2014 Diglin (http://www.diglin.com)
 */

use Diglin\Ricardo\Enums\Article\PromotionCode;

/**
 * Class Diglin_Ricento_Block_Adminhtml_Products_Listing_Confirmation
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Confirmation extends Mage_Core_Block_Template
{
    /**
     * @var string
     */
    protected $_template = 'ricento/products/listing/confirmation.phtml';

    /**
     * @var int
     */
    protected $_totalFee = 0;

    /**
     * @var array
     */
    protected $_listingFees = array('total_price' => 0, 'qty' => 0);

    /**
     * @return array
     */
    public function getArticleFees()
    {
        $preparedArticleFees = array();
        $articleFees = $this->getData('article_fees');
        if (count($articleFees)) {
            foreach ($articleFees as $fee) {
                $this->_listingFees['total_price'] += $fee['ListingFee'];
                $this->_listingFees['qty'] += 1;
                $this->_totalFee += $fee['TotalFee'];

                foreach ($fee['PromotionFees'] as $promotionFees) {
                    if ($promotionFees['PromotionFee'] > 0) {
                        $preparedArticleFees[$promotionFees['PromotionId']]['label'] = $this->__(PromotionCode::getLabel($promotionFees['PromotionId']));
                        @$preparedArticleFees[$promotionFees['PromotionId']]['total_price'] += $promotionFees['PromotionFee'];
                        @$preparedArticleFees[$promotionFees['PromotionId']]['qty'] += 1;
                        $preparedArticleFees[$promotionFees['PromotionId']]['unit_price'] = $this->getUnitPrice($preparedArticleFees[$promotionFees['PromotionId']]['total_price'], $preparedArticleFees[$promotionFees['PromotionId']]['qty']);
                    }
                }
            }

            $this->_listingFees['unit_price'] = $this->getUnitPrice($this->_listingFees['total_price'], $this->_listingFees['qty']);
        }

        return $preparedArticleFees;
    }

    /**
     * @param $price
     * @param $qty
     * @return float
     */
    public function getUnitPrice($price, $qty)
    {
        return round($price / $qty, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @return string
     */
    public function getFeesRulesUrl()
    {
        return Mage::helper('diglin_ricento')->getFeesRulesUrl();
    }

    /**
     * @return string
     */
    public function getTermsUrl()
    {
        return Mage::helper('diglin_ricento')->getTermsUrl();
    }

    /**
     * @return string
     */
    public function getPrivacyUrl()
    {
        return Mage::helper('diglin_ricento')->getPrivacyUrl();
    }

    /**
     * @return int
     */
    public function getProductsListingId()
    {
        return Mage::registry('products_listing')->getId();
    }

    public function getFormUrl()
    {
        return $this->getUrl('*/*/checkandlist');
    }

    /**
     * @return array
     */
    public function getListingFees()
    {
        return $this->_listingFees;
    }

    /**
     * @return int
     */
    public function getTotalFee()
    {
        return $this->formatPrice($this->_totalFee);
    }

    /**
     * @param $price
     * @return float
     * @throws Exception
     */
    public function formatPrice($price)
    {
        $helperPrice = Mage::helper('diglin_ricento/price');
        return implode(' / ', $helperPrice->formatDoubleCurrency($price, Mage::registry('products_listing')->getWebsiteId(), Diglin_Ricento_Helper_Data::ALLOWED_CURRENCY));
    }
}
