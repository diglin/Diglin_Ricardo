<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Ricento
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2014 ricardo.ch AG (http://www.ricardo.ch)
 */
/* @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$salesOptionsTable = $installer->getTable('diglin_ricento/sales_options');
$installer->getConnection()->modifyColumn($salesOptionsTable, 'sales_auction_increment', "decimal(12,4) NOT NULL DEFAULT '1.0000' COMMENT 'Sales_auction_increment'");

$installer->endSetup();