<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    Ricento
 * @package     Ricento
 * @copyright   Copyright (c) 2011-2015 Diglin (http://www.diglin.com)
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
