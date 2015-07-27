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
 * Class Diglin_Ricento_Block_Adminhtml_Products_Listing_Edit_Renderer_Inventory
 *
 * Renderer for column name for configurable product
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Edit_Renderer_Inventory
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @todo display a color depending if the planned inventory is really in stock or not
     * - green: in stock
     * - orange: lower than the wished quantity to be listed
     * - red: not in stock
     *
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
//        $value = $this->_getValue($row);
        if ($row->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $itemCollection = Mage::getResourceModel('diglin_ricento/products_listing_item_collection');
            $itemCollection->addFieldToFilter('parent_item_id', $row->getItemId());

            $value = '';
            $inventory = array();
            foreach ($itemCollection->getItems() as $item) {
                $item->setLoadFallbackOptions(true);
                if (!in_array($item->getStatus(), array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD))) {
                    $qtyInventory = $item->getProductQty();
                } else {
                    $qtyInventory = $item->getQtyInventory();
                }
                if (isset($qtyInventory)) {
                    $inventory[] = '<li>' . round($qtyInventory) . '</li>';
                }
            }

            if (count($inventory)) {
                $value = '<ul><li>&nbsp;</li>' . implode($inventory) . '</ul>';
            }
        } else {
            $item = Mage::getModel('diglin_ricento/products_listing_item')->load($row->getItemId());
            $item->setLoadFallbackOptions(true);
            if (!in_array($item->getStatus(), array(Diglin_Ricento_Helper_Data::STATUS_LISTED, Diglin_Ricento_Helper_Data::STATUS_SOLD))) {
                $value = $item->getProductQty();
            } else {
                $value = $item->getQtyInventory();
            }
            $value = round($value, 0);
        }

        return $value;
    }

}