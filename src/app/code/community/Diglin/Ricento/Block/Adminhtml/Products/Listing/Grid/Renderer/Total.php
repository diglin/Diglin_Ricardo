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
 * Class Diglin_Ricento_Block_Adminhtml_Products_Listing_Grid_Renderer_Total
 *
 * Renderer for detailed status information
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Grid_Renderer_Total
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $resourceModel = Mage::getResourceModel('diglin_ricento/products_listing_item');
        $readAdapter = $resourceModel->getReadConnection();

        $total = $readAdapter->select()
            ->from($resourceModel->getTable('diglin_ricento/products_listing_item'), new Zend_Db_Expr('COUNT(*)'))
            ->where('products_listing_id = ?', $row->getId())
            ->where('type <> ?', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        return $readAdapter->fetchOne($total);
    }
}