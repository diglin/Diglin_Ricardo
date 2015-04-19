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
 * Class Diglin_Ricento_Model_Products_Category
 *
 * Represents a Ricardo category
 *
 * @method string getCategoryId()
 * @method string getCategoryName()
 * @method string getIsFinal()
 * @method string getLevel()
 * @method string getParentId()
 * @method string getPath()
 */
class Diglin_Ricento_Model_Products_Category extends Varien_Object
{
    /**
     * Name of object id field
     *
     * @var string
     */
    protected $_idFieldName = 'category_id';

    /**
     * @var Diglin_Ricento_Model_Products_Category[]
     */
    protected $_children = array();

    /**
     * Initialize object based on raw API data. Convert CamelCase to under_score, to make getters and setters work
     *
     * @param array $rawData Data as array of strings, as from Ricardo API
     *  Example: [ 'ArticleTypeId' => '3',
     *     'CategoryId' => '74974',
     *     'CategoryName' => 'Sonstiger Werkstattbedarf',
     *     'CategoryNameRewritten' => 'sonstiger-werkstattbedarf',
     *     'CategoryTypeId' => '11',
     *     'IsBranding' => '1',
     *     'IsFinal' => '1',
     *     'Level' => '3',
     *     'ParentId' => '74971',
     *     'PartialUrl' => '/kaufen/fahrzeugzubehoer/werkstattbedarf/sonstiger-werkstattbedarf/b/cn74974/'
     * ]
     *
     */
    public function setDataFromApi(array $rawData)
    {
        foreach ($rawData as $key => $value) {
            $this->setDataUsingMethod($key, $value);
        }
    }

    /**
     * @return Diglin_Ricento_Model_Products_Category[]
     */
    public function getChildren()
    {
        return $this->_children;
    }

    /**
     * @param Diglin_Ricento_Model_Products_Category $category
     */
    public function addChild(Diglin_Ricento_Model_Products_Category $category)
    {
        $this->_children[$category->getCategoryId()] = $category;
    }
}