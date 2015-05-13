<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Ricento
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2015 ricardo.ch AG (http://www.ricardo.ch)
 */
/* @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$orderGridTable = $installer->getTable('sales/order_grid');
$installer->getConnection()->addColumn($orderGridTable, 'is_ricardo', array(
        'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'unsigned' => true,
        'nullable' => true,
        'default'  => 0,
        'comment' => 'Is ricardo.ch Transaction'
    )
);

$installer->endSetup();