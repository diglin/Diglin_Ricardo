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

$installer->getConnection()->addColumn($salesOptionsTable, 'stock_management_qty_type', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 10,
    'nullable' => false,
    'unsigned' => false,
    'default' => Diglin_Ricento_Helper_Data::INVENTORY_QTY_TYPE_FIX,
    'after' => 'stock_management',
    'comment' => 'Stock Management Quantity Type'));

$installer->endSetup();