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
 * Class Diglin_Ricento_Test_Model_Feed
 */
class Diglin_Ricento_Test_Model_Feed extends EcomDev_PHPUnit_Test_Case
{
    /**
     * @var Diglin_Ricento_Model_Feed
     */
    protected $_feed;

    protected function setUp()
    {
        $this->_feed = Mage::getModel('diglin_ricento/feed');
        parent::setUp();
    }

    /**
     * @test
     * loadExpectations
     * dataProvider dataProvider
     */
    public function testFeedData()
    {
//        var_dump($this->_feed->getFeedData());
    }
}
