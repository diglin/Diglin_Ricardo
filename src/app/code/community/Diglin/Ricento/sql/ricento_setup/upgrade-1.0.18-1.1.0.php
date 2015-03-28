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
$itemTable = $installer->getTable('diglin_ricento/products_listing_item');
$apiTokenTable = $installer->getTable('diglin_ricento/api_token');

$installer->getConnection()->modifyColumn($salesOptionsTable, 'sales_auction_increment', "decimal(12,4) NOT NULL DEFAULT '1.0000' COMMENT 'Sales_auction_increment'");
$installer->getConnection()->addColumn($salesOptionsTable, 'stock_management_qty_type', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 10,
    'nullable' => false,
    'unsigned' => false,
    'default' => Diglin_Ricento_Helper_Data::INVENTORY_QTY_TYPE_FIX,
    'after' => 'stock_management',
    'comment' => 'Stock Management Quantity Type'));

$installer->getConnection()->addColumn($apiTokenTable, 'merchant_notified', array(
    'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'length' => 4,
    'nullable' => false,
    'unsigned' => false,
    'default' => 0,
    'after' => 'session_expiration_date',
    'comment' => 'Merchant is notified'));

$installer->getConnection()->addColumn($itemTable, 'type', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 255,
    'nullable' => false,
    'unsigned' => false,
    'default' => 'simple',
    'after' => 'product_id',
    'comment' => 'Type of product'));

$installer->endSetup();