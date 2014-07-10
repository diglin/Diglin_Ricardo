<?php
class Diglin_Ricento_Block_Adminhtml_Products_Listing_New extends Mage_Core_Block_Abstract
{
    /**
     * @return Mage_Core_Block_Abstract|void
     */
    protected function _beforeToHtml()
    {
        $this->setChild('form', $this->getLayout()->createBlock('diglin_ricento/adminhtml_products_listing_new_form'));
    }
    /**
     * @return string
     */
    protected function _toHtml()
    {
        $formAsString = $this->htmlToJsString($this->getChildHtml('form'));
        return
<<<HTML
    <script type='text/javascript'>
    //<![CDATA[
        Ricento.htmlNewListingForm = '{$formAsString}';
    //]]>
    </script>";
HTML;
    }
    /**
     * Converts HTML to a single line string, escaped for JavaScript
     *
     * @param string $html
     * @return string
     */
    protected function htmlToJsString($html)
    {
        return (string) preg_replace('/\s+/', ' ', $this->jsQuoteEscape($html));
    }
}