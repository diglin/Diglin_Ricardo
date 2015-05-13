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

$salesOrderGrid = $installer->getTable('sales/order_grid');
$salesOrder = $installer->getTable('sales/order');

$installer->run("UPDATE " . $salesOrderGrid . " SET is_ricardo = 1 WHERE entity_id IN (SELECT entity_id FROM ". $salesOrder ." WHERE is_ricardo = 1)");

$installer->endSetup();