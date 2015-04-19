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
 * Class Diglin_Ricento_Block_Adminhtml_Products_Listing_Edit_Tabs_General
 */
class Diglin_Ricento_Block_Adminhtml_Products_Listing_Edit_Tabs_General
    extends Diglin_Ricento_Block_Adminhtml_Products_Listing_Form_Abstract
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $htmlIdPrefix = 'product_listing_';
        $form->setHtmlIdPrefix($htmlIdPrefix);

        $fieldsetMain = $form->addFieldset('fieldset_main', array('legend' => $this->__('General')));
        $fieldsetMain->addField('entity_id', 'hidden', array(
            'name' => 'product_listing[entity_id]',
        ));
        $fieldsetMain->addField('title', 'text', array(
            'name'  => 'product_listing[title]',
            'label' => $this->__('Title')
        ));
        $fieldsetMain->addField('status', 'select', array(
            'label'    => $this->__('Status'),
            'disabled' => true,
            'values'   => Mage::getSingleton('diglin_ricento/config_source_status')->getAllOptions()
        ));
        $fieldsetMain->addField('website_id', 'select', array(
            'label'    => $this->__('Website'),
            'disabled' => true,
            'values'   => Mage::getSingleton('adminhtml/system_store')->getWebsiteValuesForForm(true, false)
        ));

        $languages = Mage::helper('diglin_ricento')->getSupportedLang();

        $fieldsetLang = $form->addFieldset('fieldset_lang', array('legend' => $this->__('Language')));
        $fieldsetLang->addField('publish_languages', 'select', array(
            'name'      => 'product_listing[publish_languages]',
            'label'    => $this->__('Product languages to synchronize to ricardo.ch'),
            'note'     => $this->__('ricardo.ch supports only two languages at the moment: German and French. You can set in which language you want to publish your product content (title, subtitle, description, etc).'),
            'values'   => Mage::getSingleton('diglin_ricento/config_source_languages')->getAllOptions(),
            'onchange' => 'generalForm.onChangeInput(this, [\''. implode('\',\'', $languages) .'\']);',
            'required' => true

        ));
        $fieldsetLang->addField('default_language', 'select', array(
            'name'     => 'product_listing[default_language]',
            'label'    => $this->__('Default language to publish'),
            'note'     => $this->__('Which language to publish by default to ricardo.ch when the product content is not available in a language?'),
            'values'   => Mage::getSingleton('diglin_ricento/config_source_languages')->getAllOptions(false),
            'required' => true
        ));

        foreach ($languages as $lang) {
            $title = Mage::helper('catalog')->__('Store View for %s', ucwords(Mage::app()->getLocale()->getTranslation($lang, 'language')));
            $fieldsetLang->addField('lang_store_id_'. $lang, 'select', array(
                'name'      => 'product_listing[lang_store_id_' . $lang . ']',
                'label'     => $title,
                'title'     => $title,
                'class'     => 'lang_store_id',
                'values'    => Mage::getSingleton('diglin_ricento/config_source_store')
                        ->setWebsiteId($this->_getListing()->getWebsiteId())
                        ->getStoreValuesForForm(false, true),
                'required'  => true,
            ));
        }


        $this->setForm($form);

        return parent::_prepareForm();
    }

    protected function _initFormValues()
    {
        $this->getForm()->setValues($this->_getListing());

        $publishLanguages = $this->_getListing()->getPublishLanguages();

        if ($publishLanguages != Diglin_Ricento_Helper_Data::LANG_ALL) {
            $this->getForm()->getElement('default_language')->setDisabled(true);
            $languages = Mage::helper('diglin_ricento')->getSupportedLang();
            foreach ($languages as $lang) {
                if ($publishLanguages != $lang) {
                    $this->getForm()->getElement('lang_store_id_' . strtolower($lang))->setDisabled(true);
                }
            }
        }

        return parent::_initFormValues();
    }

    protected function _afterToHtml($html)
    {
        $languages = Mage::helper('diglin_ricento')->getSupportedLang();
        $htmlIdPrefix = $this->getForm()->getHtmlIdPrefix();

        $html .= '<script type="text/javascript">';
        $html .= 'var generalForm = new Ricento.GeneralForm("' . $htmlIdPrefix . '");';
        !$this->isReadonlyForm() && $html .= 'setTimeout(function(){generalForm.onChangeInput($('
            . $htmlIdPrefix .'publish_languages), [\''. implode('\',\'', $languages) .'\'])}, 3000);';
        $html .= '</script>';
        return parent::_afterToHtml($html);
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('General');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('General');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }

    protected function _getListing()
    {
        return Mage::registry('products_listing');
    }
}
