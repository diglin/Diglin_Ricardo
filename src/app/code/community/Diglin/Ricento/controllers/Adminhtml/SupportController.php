<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author Sylvain Rayé <support at diglin.com>
 * @category    Ricento
 * @package     Ricento_
 * @copyright   Copyright (c) 2015 ricardo.ch AG (http://www.ricardo.ch)
 */ 
class Diglin_Ricento_Adminhtml_SupportController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('ricento/support');
    }

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
        if (!Mage::getSingleton('admin/session')->isAllowed('ricento/support/export')) {
            $this->_forward('denied');
            return $this;
        }

        $gzDestination = Mage::helper('diglin_ricento/support')->exportAll();
        return $this->_prepareDownloadResponse('ricardo_support.tar.gz', file_get_contents($gzDestination));
    }

    public function sendAction()
    {
        if (!Mage::getSingleton('admin/session')->isAllowed('ricento/support/export')) {
            $this->_forward('denied');
            return $this;
        }

        $helper = Mage::helper('diglin_ricento/support');

        try {
            $gzDestination = $helper->exportAll();
            $helper->sendConfigurationFile(file_get_contents($gzDestination));
            $this->_getSession()->addSuccess($this->__('Configuration successfully sent. If not already done, please <a href="mailto:%s?subject=ricardo.ch Magento Extension Support">contact us</a> to explain us your issue.', $helper->getSupportEmail()));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError('A problem occured while trying to send your configuration per email. Please, review your log file');
        }

        $this->_redirect('adminhtml/system_config/edit/section/ricento');

    }
}
