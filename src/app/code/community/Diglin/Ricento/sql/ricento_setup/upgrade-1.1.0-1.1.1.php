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

$apiTokenTable = $installer->getTable('diglin_ricento/api_token');

$installer->getConnection()->addColumn($apiTokenTable, 'merchant_notified', array(
    'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'length' => 4,
    'nullable' => false,
    'unsigned' => false,
    'default' => 0,
    'after' => 'session_expiration_date',
    'comment' => 'Merchant is notified'));

$installer->endSetup();