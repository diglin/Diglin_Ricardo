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
 * Class Diglin_Ricento_Block_Adminhtml_Products_Category_Mapping
 * Mapping from the Ricardo categories
 *
 * @method int getLevels() getLevels()
 * @method Diglin_Ricento_Block_Adminhtml_Products_Category_Children setLevels() setLevels(int $levels)
 *
 */
class Diglin_Ricento_Block_Adminhtml_Products_Category_Mapping extends Mage_Adminhtml_Block_Template
{
    /**
     * @var string
     */
    protected $_template = 'ricento/products/category/mapping.phtml';

    /**
     * Determine if the columns have to be resized
     *
     * @return bool
     */
    public function shouldResize()
    {
        /* @var $mapping Diglin_Ricento_Model_Products_Category_Mapping */
        $mapping = Mage::getModel('diglin_ricento/products_category_mapping');
        $category = $mapping->getCategory($this->getCategoryId());

        $this->setLevels(($category ? $category->getLevel() : 1));

        return ($this->getLevels() >= 5);
    }

    /**
     * @return string
     */
    public function getSuggestUrl()
    {
        return $this->getUrl('*/products_category/suggest');
    }
}