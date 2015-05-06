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

        if ($attributeName == 'image' && Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_ENABLED) == Diglin_Ricento_Model_Config_Source_Watermark::YES) {
            $this->setWatermark(
                Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK)
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
        } else if ($attributeName == 'image' && Mage::getStoreConfig(Diglin_Ricento_Helper_Data::CFG_WATERMARK_ENABLED) == Diglin_Ricento_Model_Config_Source_Watermark::NO) {
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
     * @param $filepath
     * @return string
     */
    public function prepareRicardoPicture($filepath)
    {
        if ($filepath == 'no_selection') {
            return false;
        }

        return $this->init(new Mage_Catalog_Model_Product(), 'image', $filepath)
            ->keepAspectRatio(true)
            ->keepFrame(false)
            ->setQuality(90)
            ->resize(600)
            ->__toString();
    }
}