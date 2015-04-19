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
 * Class Diglin_Ricento_Block_Adminhtml_Products_Category_Mapping_Tree
 *
 *
 * @method int getCategoryId()
 * @method array getSuggestedCategoriesId()
 * @method Diglin_Ricento_Block_Adminhtml_Products_Category_Mapping_Tree setSuggestedCategoriesId(array $suggestedCategoriesId)
 * @method Diglin_Ricento_Block_Adminhtml_Products_Category_Mapping_Tree setCategoryId(int $categoryId)
 */
class Diglin_Ricento_Block_Adminhtml_Products_Category_Mapping_Tree extends Mage_Core_Block_Template
{
    protected $_template = 'ricento/products/category/mapping/tree.phtml';

    protected function _beforeToHtml()
    {
        $this->setChild('toplevel',
            $this->getLayout()
                ->createBlock('diglin_ricento/adminhtml_products_category_children')
                ->setLevel(1)
                ->setCategoryId(1)
                ->setSuggestedCategoriesId($this->getSuggestedCategoriesId())
        );

        $this->setChild('sublevel',
            $this->getLayout()->createBlock('core/text_list')
        );

        if ($this->getCategoryId() != Diglin_Ricento_Model_Products_Category_Mapping::ROOT_CATEGORY_ID) {

            /* @var $mapping Diglin_Ricento_Model_Products_Category_Mapping */
            $mapping = Mage::getModel('diglin_ricento/products_category_mapping');
            $category = $mapping->getCategory($this->getCategoryId());

            if ($this->getParentBlock()) {
                $this->getParentBlock()->setLevels(($category ? $category->getLevel() : 1));
            }

            $this->setLevels(($category ? $category->getLevel() : 1));

            while ($category && $category->getParentId() != Diglin_Ricento_Model_Products_Category_Mapping::ROOT_CATEGORY_ID) {
                $this->getChild('sublevel')->insert(
                    $this->getLayout()
                        ->createBlock('diglin_ricento/adminhtml_products_category_children')
                        ->setLevel($category->getLevel())
                        ->setCategoryId($category->getParentId())
                        ->setSelectedCategoryId($category->getId())
                        ->setSuggestedCategoriesId($this->getSuggestedCategoriesId()),
                    '', false, 'sublevel-' . $category->getLevel()
                );
                $category = $mapping->getCategory($category->getParentId()); //TODO reference parent from child
            }

            if ($category) {
                $this->getChild('toplevel')->setSelectedCategoryId($category->getId());
            }
        }
        return parent::_beforeToHtml();
    }
}