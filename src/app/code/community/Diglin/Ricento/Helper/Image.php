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

/**
 * Class Diglin_Ricento_Helper_Image
 */
class Diglin_Ricento_Helper_Image extends Mage_Catalog_Helper_Image
{
    /**
     * @param Mage_Catalog_Model_Product $product
     * @param string $attributeName
     * @param null $imageFile
     * @return $this
     */
    public function init(Mage_Catalog_Model_Product $product, $attributeName, $imageFile = null)
    {
        parent::init($product, $attributeName, $imageFile);

        if ($attributeName == 'image'
            && Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_ENABLED) == Diglin_Ricento_Model_Config_Source_Watermark::YES) {
            $this->setWatermark(
                'ricento/' . Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK)
            );
            $this->setWatermarkImageOpacity(
                Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_OPACITY)
            );
            $this->setWatermarkPosition(
                Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_POSITION)
            );
            $this->setWatermarkSize(
                Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_SIZE)
            );
        } else if ($attributeName == 'image'
            && Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_ENABLED) == Diglin_Ricento_Model_Config_Source_Watermark::NO) {
            $this->setWatermark(null);
        }

        return $this;
    }

    /**
     * @return int
     */
    protected function getWatermarkImageOpacity()
    {
        if ($this->_watermarkImageOpacity) {
            return (int)$this->_watermarkImageOpacity;
        }

        return (int)$this->_getModel()->getWatermarkImageOpacity();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $file = '';

        try {
            $model = $this->_getModel();

            if ($this->getImageFile()) {
                $model->setBaseFile($this->getImageFile());
            } else {
                $model->setBaseFile($this->getProduct()->getData($model->getDestinationSubdir()));
            }

            if ($model->isCached()) {
                return $model->getNewFile();
            } else {
                $processor = new Diglin_Ricento_Model_Image($this->_getModel()->getBaseFile());
                $this->_getModel()->setImageProcessor($processor);

                if ($processor->getOriginalWidth() > 1800 || $processor->getOriginalHeight() > 1800) {
                    $this->resize(1800, 1800);
                }

                if ($this->_scheduleRotate) {
                    $model->rotate($this->getAngle());
                }

                if ($this->_scheduleResize) {
                    $model->resize();
                }

                if ($this->getWatermark()) {
                    $model->setWatermark($this->getWatermark());
                }

                $file = $model->saveFile()->getNewFile();
            }
        } catch (Exception $e) {
            Mage::logException($e);

            if (Mage::getStoreConfigFlag(Diglin_Ricento_Helper_Data::CFG_IMAGE_PLACEHOLDER)) {
                $file = Mage::getDesign()->getSkinUrl($this->getPlaceholder());
            }
        }

        // Free memory
        if (isset($processor)) {
            $processor->destruct();
        }

        return $file;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return parent::__toString();
    }

    /**
     * @deprecated
     * @param string $filepath
     * @param Mage_Catalog_Model_Product $product
     * @return bool|string
     */
    public function prepareRicardoPicture($filepath, Mage_Catalog_Model_Product $product = null)
    {
        if ($filepath == 'no_selection') {
            return false;
        }

        if (is_null($product)) {
            $product = new Mage_Catalog_Model_Product();
        }

        return $this->init($product, 'image', $filepath)
            ->keepAspectRatio(true)
            ->keepFrame(false)
            ->setQuality(90)
            ->__toString();
    }
}