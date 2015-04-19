<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    Ricento
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2015 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

// Customer Attributes
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_id');
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_username');

// Product Attributes
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_category');
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_title');
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_subtitle');
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_description');
$installer->deleteTableRow($installer->getTable('eav_attribute'), 'attribute_code', 'ricardo_condition');

// Sales Quote Columns
$installer->getConnection()->dropColumn($installer->getTable('sales/quote'), 'is_ricardo');
$installer->getConnection()->dropColumn($installer->getTable('sales/quote'), 'customer_ricardo_id');
$installer->getConnection()->dropColumn($installer->getTable('sales/quote'), 'customer_ricardo_username');

// Sales Order Columns
$installer->getConnection()->dropColumn($installer->getTable('sales/order'), 'is_ricardo');
$installer->getConnection()->dropColumn($installer->getTable('sales/order'), 'customer_ricardo_id');
$installer->getConnection()->dropColumn($installer->getTable('sales/order'), 'customer_ricardo_username');

// Remove all ricento tables
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/api_token'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/products_listing'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/products_listing_item'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/listing_log'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/sales_options'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/shipping_payment_rule'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/sync_job'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/sales_transaction'));
$installer->getConnection()->dropTable($installer->getTable('diglin_ricento/sync_job_listing'));

$installer->getConnection()->delete($installer->getTable('sales/order_status'), 'status = "ricardo_payment_canceled"');
$installer->getConnection()->delete($installer->getTable('sales/order_status'), 'status = "ricardo_payment_pending"');

$installer->endSetup();

/*
DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_id';
DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_username';

DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_category';
DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_title';
DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_subtitle';
DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_description';
DELETE FROM MYPREFIX_eav_attribute WHERE attribute_code = 'ricardo_condition';

ALTER TABLE MYPREFIX_sales_flat_quote DROP COLUMN is_ricardo, DROP COLUMN customer_ricardo_id, DROP COLUMN customer_ricardo_username;
ALTER TABLE MYPREFIX_sales_flat_order DROP COLUMN is_ricardo, DROP COLUMN customer_ricardo_id, DROP COLUMN customer_ricardo_username;

DROP TABLE MYPREFIX_api_token;
DROP TABLE MYPREFIX_products_listing;
DROP TABLE MYPREFIX_products_listing_item;
DROP TABLE MYPREFIX_listing_log;
DROP TABLE MYPREFIX_sales_options;
DROP TABLE MYPREFIX_shipping_payment_rule;
DROP TABLE MYPREFIX_sync_job;
DROP TABLE MYPREFIX_sales_transaction;
DROP TABLE MYPREFIX_sync_job_listing;

DELETE FROM MYPREFIX_sales_order_status WHERE status = 'ricardo_payment_canceled';
DELETE FROM MYPREFIX_sales_order_status WHERE status = 'ricardo_payment_pending';
*/