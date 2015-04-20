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
     * @return int
     */
    protected function getWatermarkImageOpacity()
    {
        if ($this->_watermarkImageOpacity) {
            return (int) $this->_watermarkImageOpacity;
        }

        return (int) $this->_getModel()->getWatermarkImageOpacity();
    }

    /**
     * @return string
     */
    public function __toString()
    {
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
            $file = Mage::getDesign()->getSkinUrl($this->getPlaceholder());
        }
        return $file;
    }
}