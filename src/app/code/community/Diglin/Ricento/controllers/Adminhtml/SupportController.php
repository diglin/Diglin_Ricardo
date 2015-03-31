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
        // @todo
    }
}