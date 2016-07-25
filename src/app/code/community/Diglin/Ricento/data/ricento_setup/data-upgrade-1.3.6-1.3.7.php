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

$connection = $installer->getConnection()->update(
    $installer->getTable('sales/order_status_state'),
    array('state' => Mage_Sales_Model_Order::STATE_PENDING_PAYMENT),
    'status = "'. Diglin_Ricento_Helper_Data::ORDER_STATUS_PENDING .'"'
);

$installer->endSetup();