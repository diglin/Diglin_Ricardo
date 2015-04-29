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

/**
 * Class Diglin_Ricento_Helper_Support
 */
class Diglin_Ricento_Helper_Support extends Mage_Core_Helper_Abstract
{
    /**
     * @return string
     */
    public function exportAll()
    {
        $folder = Mage::getBaseDir('tmp') . DS . 'export' . DS . 'ricento';
        $io = new Varien_Io_File();
        $io->mkdir($folder);

        $this->getPhpInfo($folder);
        $this->getPhpExtensions($folder);
        $this->getRicentoConfig($folder);
        $this->getDeveloperConfig($folder);
        $this->getRicentoTables($folder);
        $this->getMagentoModules($folder);
        $this->getMagentoConfig($folder);

        $exceptionFile = Mage::getStoreConfig(Mage_Core_Model_Session_Abstract::XML_PATH_LOG_EXCEPTION_FILE);
        $systemFile = Mage::getStoreConfig('dev/log/file');

        $io->cp(Mage::getBaseDir('var') . DS . 'log' . DS . Diglin_Ricento_Helper_Data::LOG_FILE, $folder . DS . Diglin_Ricento_Helper_Data::LOG_FILE);
        $io->cp(Mage::getBaseDir('var') . DS . 'log' . DS . $exceptionFile, $folder . DS . $exceptionFile);
        $io->cp(Mage::getBaseDir('var') . DS . 'log' . DS . $systemFile, $folder . DS . $systemFile);

        $destination = $folder . 'tar';
        $tar = new Mage_Archive_Tar();
        $tar->pack($folder, $destination);

        $gzDestination = $destination . '.gz';
        $tar = new Mage_Archive_Gz();
        $tar->pack($destination, $gzDestination);

        $io->rmdir($folder, true);

        return $gzDestination;
    }

    /**
     * @param string $destination
     * @return mixed
     */
    public function getPhpInfo($destination)
    {
        $phpinfo = new Varien_Object(Mage::helper('diglin_ricento/support')->getPhpInfoArray());
        return $this->writeFile($destination, 'phpinfo.txt', $phpinfo->toJson());
    }

    /**
     * @param string $destination
     * @return mixed
     */
    public function getRicentoConfig($destination)
    {
        $xml = Mage::getConfig()->getNode('default/ricento')->asNiceXml();
        return $this->writeFile($destination, 'ricento_config.xml', $xml);
    }

    /**
     * @param string $destination
     * @return mixed
     */
    public function getDeveloperConfig($destination)
    {
        $xml = Mage::getConfig()->getNode('default/dev')->asNiceXml();
        return $this->writeFile($destination, 'config_dev.xml', $xml);
    }

    /**
     * @param string $destination
     * @return array
     */
    public function getRicentoTables($destination)
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

            // Anonymize
            $cols = $read->fetchCol('describe ' . $table);
            $found = array_search('customer_email', $cols);
            if ($found !== false) {
                unset($cols[$found]);
            }
            $found = array_search('customer_firstname', $cols);
            if ($found !== false) {
                unset($cols[$found]);
            }
            $found = array_search('customer_lastname', $cols);
            if ($found !== false) {
                unset($cols[$found]);
            }

            $select = $read
                ->select()
                ->from($table, $cols)
                ->limit(2000)
                ->order(array( substr($key, 0, -4) . ' DESC'));

            $object = new Varien_Object($read->fetchAll($select));
            $this->writeFile($destination, $table . '.csv', array($cols), true);
            $files[] = $this->writeFile($destination, $table . '.csv', $object->toArray(), true);
        }

        return $files;
    }

    /**
     * @param string $destination
     * @return mixed
     */
    public function getPhpExtensions($destination)
    {
        $content = implode("\n", get_loaded_extensions());
        return $this->writeFile($destination, 'php_extensions.txt', $content);
    }

    /**
     * @param string $destination
     * @return mixed
     */
    public function getMagentoConfig($destination)
    {
        $countSimpleProduct = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId(0)
            ->addFieldToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->getSize();

        $countConfigurableProduct = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId(0)
            ->addFieldToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            ->getSize();

        $countGroupedProduct = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId(0)
            ->addFieldToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_GROUPED)
            ->getSize();

        $user = Mage::getSingleton('admin/session')->getUser();
        $content = 'Current Backend User: ' . $user->getUsername() . ' - Email: ' . $user->getEmail() . "\n";
        $content .= 'Magento Version: ' . Mage::getVersion() . ' ' . Mage::getEdition() . ' Edition' . "\n";
        $content .= "Qty of: Simple Products #$countSimpleProduct - Configurable Products: #$countConfigurableProduct - Grouped Product: #$countGroupedProduct";

        return $this->writeFile($destination, 'magento_info.txt', $content);
    }

    /**
     * @param string $destination
     * @return mixed
     */
    public function getMagentoModules($destination)
    {
        $modules = (array) Mage::getConfig()->getModuleConfig();

        $list = array();
        foreach ($modules as $key => $module) {
            $list[$key] = array('version' => $module->version, 'code_pool' => $module->codePool);
        }

        $content = new Varien_Object($list);

        return $this->writeFile($destination, 'magento_modules.json', $content->toJson());
    }

    /**
     * @param string $destination
     * @param $content
     * @return mixed
     * @throws Exception
     */
    public function writeFile($destination, $filename, $content, $csv = false)
    {
        $file = $destination . DS . $filename;
        $ioFile = new Varien_Io_File();
        $ioFile->cd($destination);
        $ioFile->streamOpen($file, 'a+');
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
    
    /**
     * @return array|mixed
     */
    public function getPhpInfoArray()
    {
        try {

            ob_start();
            phpinfo(INFO_ALL);

            $pi = preg_replace(
                array(
                    '#^.*<body>(.*)</body>.*$#m', '#<h2>PHP License</h2>.*$#ms',
                    '#<h1>Configuration</h1>#',  "#\r?\n#", "#</(h1|h2|h3|tr)>#", '# +<#',
                    "#[ \t]+#", '#&nbsp;#', '#  +#', '# class=".*?"#', '%&#039;%',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a><h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
                    '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
                    "# +#", '#<tr>#', '#</tr>#'),
                array(
                    '$1', '', '', '', '</$1>' . "\n", '<', ' ', ' ', ' ', '', ' ',
                    '<h2>PHP Configuration</h2>'."\n".'<tr><td>PHP Version</td><td>$2</td></tr>'.
                    "\n".'<tr><td>PHP Egg</td><td>$1</td></tr>',
                    '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
                    '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
                    '<tr><td>Zend Egg</td><td>$1</td></tr>', ' ', '%S%', '%E%'
                ), ob_get_clean()
            );

            $sections = explode('<h2>', strip_tags($pi, '<h2><th><td>'));
            unset($sections[0]);

            $pi = array();
            foreach ($sections as $section) {
                $n = substr($section, 0, strpos($section, '</h2>'));
                preg_match_all(
                    '#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#',
                    $section,
                    $askapache,
                    PREG_SET_ORDER
                );
                foreach ($askapache as $m) {
                    if (!isset($m[0]) || !isset($m[1]) || !isset($m[2])) {
                        continue;
                    }
                    $pi[$n][$m[1]]=(!isset($m[3])||$m[2]==$m[3])?$m[2]:array_slice($m,2);
                }
            }

        } catch (Exception $exception) {
            return array();
        }

        return $pi;
    }

    /**
     * @param $content
     * @param string $filename
     * @param string $replyTo
     * @param string $message
     */
    public function sendConfigurationFile($content, $filename = 'ricardo_support.tar.gz', $replyTo = '', $message = '')
    {
        $template = 'ricento_support';

        try {
            if (! Mage::getConfig()->getNode(Mage_Core_Model_Email_Template::XML_PATH_TEMPLATE_EMAIL . '/' . $template)) {
                Mage::throwException(Mage::helper('diglin_ricento')->__('Wrong transactional support email template.'));
            }

            $recipient = array(
                'name' => 'Support Diglin',
                'email' => $this->getSupportEmail()
            );

            $translate = Mage::getSingleton('core/translate');
            $translate->setTranslateInline(false);

            /* @var $emailTemplate Mage_Core_Model_Email_Template */
            $emailTemplate = Mage::getModel('core/email_template')
                ->setDesignConfig(array('area' => 'adminhtml', 'store' => 0));

            if (!empty($replyTo)) {
                $emailTemplate
                    ->setReplyTo($replyTo)
                    ->setReturnPath($replyTo);
            }

            $variables = array(
                'shopname' => Mage::getBaseUrl(),
                'message' => $message
            );

            $attachment = $emailTemplate->getMail()->createAttachment($content);
            $attachment->type = 'application/octet-stream';
            $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
            $attachment->encoding = Zend_Mime::ENCODING_BASE64;
            $attachment->filename = $filename;

            $emailTemplate->sendTransactional($template, // xml path email template
                Mage::getStoreConfig('contacts/email/sender_email_identity'),//sender - normally general contact
                $recipient['email'],
                $recipient['name'],
                $variables,
                0);

            $translate->setTranslateInline(true);

        } catch (Exception $e) {
            Mage::logException($e);
        }

        return;
    }

    /**
     * @return mixed
     */
    public function getSupportEmail()
    {
        return str_replace('[/at/]', '@', Mage::getStoreConfig('support/email'));
    }
}