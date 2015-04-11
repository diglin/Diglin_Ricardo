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

/**
 * Class Diglin_Ricento_Model_Sales_Method_Shipping
 */
class Diglin_Ricento_Model_Sales_Method_Shipping
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    const SHIPPING_CODE = 'ricento';
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = self::SHIPPING_CODE;

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * Collect and get rates
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result|bool|null
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        $helper = Mage::helper('diglin_ricento');

        if (!$this->getConfigFlag('active') || !$helper->isEnabled()) {
            return false;
        }

        $calculationMethod = $helper->getShippingCalculationMethod();
        $shippingPrice = 0;
        $isRicardo = false;

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                $infoBuyRequest = $item->getOptionByCode('info_buyRequest');
                if (!($infoBuyRequest instanceof Varien_Object)) {
                    break;
                }

                $value = $infoBuyRequest->getValue();
                $params = unserialize($value);
                $price = 0;

                if (isset($params['is_ricardo'])) {
                    $isRicardo = true;
                }
                if (isset($params['shipping_fee'])) {
                    $price = $params['shipping_fee'];
                }
                if (isset($params['shipping_cumulative_fee']) && $params['shipping_cumulative_fee']) {
                    $price *= $item->getQty();
                }

                if ($calculationMethod == Diglin_Ricento_Model_Config_Source_Rules_Shipping_Calculation::HIGHEST_PRICE) {
                    if ($shippingPrice <= $price) {
                        $shippingPrice = $price;
                    }
                } else {
                    $shippingPrice += $price;
                }
            }
        }

        if (!$isRicardo) {
            return false;
        }

        /** @var Mage_Shipping_Model_Rate_Result $result */
        $result = Mage::getModel('shipping/rate_result');


        $description = $helper->getRicardoShippingRegistry()->getRicardoShippingDescription();
        $shippingMethod = $helper->getRicardoShippingRegistry()->getRicardoShippingMethod();

        $shippingPrice = $this->getFinalPriceWithHandlingFee($shippingPrice);

        if ($shippingPrice !== false) {
            $method = Mage::getModel('shipping/rate_result_method');

            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));

            $method->setMethod($shippingMethod);
            $method->setMethodTitle((strlen($description) > 0) ? $description : '');

            $method->setPrice($shippingPrice);
            $method->setCost($shippingPrice);

            $result->append($method);
        }

        return $result;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return Mage::getSingleton('diglin_ricento/config_source_rules_shipping')->toOptionHash();
    }
}
