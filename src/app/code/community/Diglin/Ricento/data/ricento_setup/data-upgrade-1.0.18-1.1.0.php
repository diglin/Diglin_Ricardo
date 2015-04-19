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
/* @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$productsListingTable = $installer->getTable('diglin_ricento/sales_options');

$installer->run("UPDATE " . $productsListingTable . " SET stock_management_qty_type = 'fix' ");

$itemTable = $installer->getTable('diglin_ricento/products_listing_item');
$productTable = $installer->getTable('catalog/product');

$installer->run("UPDATE $itemTable SET type = 'simple' WHERE product_id IN (SELECT entity_id FROM $productTable WHERE type_id = 'simple')");
$installer->run("UPDATE $itemTable SET type = 'configurable' WHERE product_id IN (SELECT entity_id FROM $productTable WHERE type_id = 'configurable')");
$installer->run("UPDATE $itemTable SET type = 'grouped' WHERE product_id IN (SELECT entity_id FROM $productTable WHERE type_id = 'grouped')");


$installer->endSetup();