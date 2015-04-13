<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Ricento
 * @package     Ricento_
 * @copyright   Copyright (c) 2014 ricardo.ch AG (http://www.ricardo.ch)
 */ 
class Diglin_Ricento_Adminhtml_SupportController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->_redirect('*/*/contact');
    }

    public function contactAction()
    {
        $supportLabel = $this->__('Ricento Extension Support');

        $block = $this->getLayout()->createBlock('core/template');

        $block
            ->setTemplate('ricento/support.phtml')
            ->setTitle($supportLabel);

        $this->_title($supportLabel);

        $this->loadLayout()
            ->_setActiveMenu('ricento/support')
            ->_addBreadcrumb($supportLabel, $supportLabel)
            ->_addContent($block)
            ->renderLayout();
    }

    public function exportAction()
    {
        $folder = Mage::getBaseDir('tmp') . DS . 'export' . DS . 'ricento';
        $io = new Varien_Io_File();
        $io->mkdir($folder);

        $this->_getPhpInfo($folder);
        $this->_getPhpExtensions($folder);
        $this->_getRicentoConfig($folder);
        $this->_getDeveloperConfig($folder);
        $this->_getRicentoTables($folder);
        $this->_getMagentoModules($folder);
        $this->_getMagentoConfig($folder);

        $exceptionFile = Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_LOG_EXCEPTION_FILE);
//        $systemFile = Mage::getStoreConfig('dev/log/file');

        $io->cp(Mage::getBaseDir('var') . DS . 'log' . DS . Diglin_Ricento_Helper_Data::LOG_FILE, $folder . DS . Diglin_Ricento_Helper_Data::LOG_FILE);
        $io->cp(Mage::getBaseDir('var') . DS . 'log' . DS . $exceptionFile, $folder . DS . $exceptionFile);

        $destination = $folder . 'tar';
        $tar = new Mage_Archive_Tar();
        $tar->pack($folder, $destination);

        $gzDestination = $destination . '.gz';
        $tar = new Mage_Archive_Gz();
        $tar->pack($destination, $gzDestination);

        $io->rmdir($folder, true);

        return $this->_prepareDownloadResponse('ricardo_support.tar.gz', file_get_contents($gzDestination));
    }

    /**
     * @param $destination
     * @return mixed
     */
    protected function _getPhpInfo($destination)
    {
        $phpinfo = new Varien_Object(Mage::helper('diglin_ricento/support')->getPhpInfoArray());
        return $this->_writeFile($destination, 'phpinfo.json', $phpinfo->toJson());
    }

    /**
     * @param $destination
     * @return mixed
     */
    protected function _getRicentoConfig($destination)
    {
        $xml = Mage::getConfig()->getNode('default/ricento')->asNiceXml();
        return $this->_writeFile($destination, 'ricento_config.xml', $xml);
    }

    /**
     * @param $destination
     * @return mixed
     */
    protected function _getDeveloperConfig($destination)
    {
        $xml = Mage::getConfig()->getNode('default/dev')->asNiceXml();
        return $this->_writeFile($destination, 'config_dev.xml', $xml);
    }

    /**
     * @param $destination
     * @return array
     */
    protected function _getRicentoTables($destination)
    {
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');

        $tables = array(
            'entity_id_001'         => $resource->getTableName('diglin_ricento/api_token'),
            'entity_id_002'         => $resource->getTableName('diglin_ricento/products_listing'),
            'item_id_003'           => $resource->getTableName('diglin_ricento/products_listing_item'),
            'log_id_004'            => $resource->getTableName('diglin_ricento/listing_log'),
            'entity_id_005'         => $resource->getTableName('diglin_ricento/sales_options'),
            'transaction_id_006'    => $resource->getTableName('diglin_ricento/sales_transaction'),
            'rule_id_007'           => $resource->getTableName('diglin_ricento/shipping_payment_rule'),
            'job_id_008'            => $resource->getTableName('diglin_ricento/sync_job'),
            'job_listing_id_009'    => $resource->getTableName('diglin_ricento/sync_job_listing'),
            'entity_id_010'         => $resource->getTableName('sales/quote'),
            'item_id_011'           => $resource->getTableName('sales/quote_item'),
            'entity_id_012'         => $resource->getTableName('sales/order'),
            'item_id_013'           => $resource->getTableName('sales/order_item'),
            'entity_id_014'         => $resource->getTableName('sales/order_payment')
        );

        $files = array();
        foreach ($tables as $key => $table) {

            $select = $read
                ->select()
                ->from($table)
                ->limit(500)
                ->order(array( substr($key, 0, -4) . ' DESC'));

            $object = new Varien_Object($read->fetchAll($select));
            $files[] = $this->_writeFile($destination, $table . '.csv', $object->toArray(), true);
        }

        return $files;
    }

    /**
     * @param $destination
     * @return mixed
     */
    protected function _getPhpExtensions($destination)
    {
        $content = implode("\n", get_loaded_extensions());
        return $this->_writeFile($destination, 'php_extensions.txt', $content);
    }

    /**
     * @param $destination
     * @return mixed
     */
    protected function _getMagentoConfig($destination)
    {
        $adminSession = Mage::getSingleton('admin/session');
        $content = 'Current Backend User: ' . $adminSession->getUser()->getUsername() . ' - Email: ' . $adminSession->getUser()->getEmail() . "\n";
        $content .= 'Magento Version: ' . Mage::getVersion() . ' ' . Mage::getEdition() . ' Edition' . "\n";

        return $this->_writeFile($destination, 'magento_info.txt', $content);
    }

    /**
     * @param $destination
     * @return mixed
     */
    protected function _getMagentoModules($destination)
    {
        $modules = (array) Mage::getConfig()->getModuleConfig();

        $list = array();
        foreach ($modules as $key => $module) {
            $list[$key] = array('version' => $module->version, 'code_pool' => $module->codePool);
        }

        $content = new Varien_Object($list);

        return $this->_writeFile($destination, 'magento_modules.json', $content->toJson());
    }

    /**
     * @param $destination
     * @param $content
     * @return mixed
     * @throws Exception
     */
    protected function _writeFile($destination, $filename, $content, $csv = false)
    {
        $file = $destination . DS . $filename;
        $ioFile = new Varien_Io_File();
        $ioFile->cd($destination);
        $ioFile->streamOpen($file);
        if ($csv) {
            foreach ($content as $row) {
                $ioFile->streamWriteCsv($row);
            }
        } else {
            $ioFile->streamWrite($content);
        }
        $ioFile->streamClose();

        return $file;
    }
}