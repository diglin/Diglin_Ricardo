<?php
/**
 * ricardo.ch AG - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    Diglin
 * @package     Diglin_Ricento
 * @copyright   Copyright (c) 2014 ricardo.ch AG (http://www.ricardo.ch)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Diglin_Ricento_Block_Adminhtml_Config_Source_Hint
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = '<p><a href="http://www.diglin.com/?utm_source=magento&utm_medium=extension&utm_campaign=zopim">'
            .'<img style="vertical-align:middle" src="data:image/png;base64,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">'
            .'</a> <a href="http://www.diglin.com/?utm_source=magento&utm_medium=extension&utm_campaign=ricento">Diglin GmbH</a> | Rütistrasse 14, 8952 Schlieren - Switzerland | <a href="mailto:support@diglin.com?subject=Support Diglin ricardo.ch Extension">support@diglin.com</a></p>';

        $buttonSignUp = '';
        $website = $this->getRequest()->getParam('website');
        $websiteId = Mage::app()->getWebsite($website)->getId();
        $helper = Mage::helper('diglin_ricento');

        if (!$helper->isConfigured($websiteId)) {
            $buttonSignUp = $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                'label'     => $this->__('Sign Up to ricardo.ch API'),
                'onclick'   => "window.open('" . Mage::helper('diglin_ricento')->getRicardoSignupApiUrl() . "', '_blank');",
                'class'     => 'go',
                'type'      => 'button',
                'id'        => 'ricardo-account',
            ))
            ->toHtml();
        }

        $buttonDashboard  = $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
            'label'     => $this->__('ricardo.ch Assistant'),
            'onclick'   => "window.open('". Mage::helper('diglin_ricento')->getRicardoAssistantUrl() ."', '_blank');",
            'class'     => 'go',
            'type'      => 'button',
            'id'        => 'ricardo-assistant',
        ))
            ->toHtml();

        $buttonAuthorize = null;

        try {
            if ($helper->isConfigured($websiteId) && $helper->isEnabled($websiteId)) {
                $buttonAuthorize  = $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                    'label'     => $this->__('API Authorization'),
                    'onclick'   => "window.open('". Mage::helper('diglin_ricento/api')->getValidationUrl($websiteId) ."', '_blank');",
                    'class'     => 'go',
                    'type'      => 'button',
                    'id'        => 'ricardo-api-authorization',
                ))
                    ->toHtml();
            }
        } catch (Exception $e) {
            // do nothing just don't display it as the key may be not yet configured.
        }

        $buttonSend = '<button type="button" onclick="if(confirm(\'' . $this->__('Do you want to send us your configuration information for support?') . '\')) {window.location.href=\''
            . $this->getUrl('ricento/support/send')
            . '\'}"><span><span>'
            . $this->__('Send us your configuration')
            . '</span></span></button>';

        $buttonExport = '<button type="button" onclick="window.location.href=\''
            . $this->getUrl('ricento/support/export')
            . '\'"><span><span>'
            . $this->__('Export your configuration')
            . '</span></span></button>';

        return $html
        . '<p>'
        . $buttonSignUp . '&nbsp;'
        . $buttonDashboard . '&nbsp;'
        . $buttonAuthorize
        .' - <strong>Diglin_Ricento Version: '
        . Mage::getConfig()->getModuleConfig('Diglin_Ricento')->version
        .' </strong>'
        . '&nbsp;' . $buttonSend . '&nbsp;' . $buttonExport
        . '</p>';
    }
}
