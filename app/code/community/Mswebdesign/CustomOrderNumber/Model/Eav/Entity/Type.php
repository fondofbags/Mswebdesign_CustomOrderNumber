<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Mswebdesign
 * @package    Mswebdesign_Mswebdesign_CustomOrderNumber
 * @copyright  Copyright (c) 2013 münster-webdesign.net (http://www.muenster-webdesign.net)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Christian Grugel <cgrugel@muenster-webdesign.net>
 */
class Mswebdesign_CustomOrderNumber_Model_Eav_Entity_Type extends Mage_Eav_Model_Entity_Type
{

    /**
     * @var int
     */
    protected $_storeId;

    /**
     * @var string
     */
    protected $_entityTypeCode;

    /**
     * @var array
     */
    protected $_entityStoreConfig = array();

    /**
     * @var string
     */
    protected $_datePrefix = '';

    /**
     * @var string
     */
    protected $_incrementId = '';

    /**
     * @var object
     */
    protected $_incrementInstance;

    /**
     * @var array
     */
    protected $_processedEntityTypeCodes = array(
        'order',
        'invoice',
        'shipment',
        'creditmemo'
    );

    /**
     * Retreive new incrementId
     *
     * @param int $storeId
     * @return string
     */
    public function fetchNewIncrementId($storeId = null)
    {
        if(!in_array($this->_entityTypeCode = $this->getEntityTypeCode(), $this->_processedEntityTypeCodes)) {
            return parent::fetchNewIncrementId($storeId);
        }

        if (!$this->getIncrementModel()) {
            return false;
        }

        $this->_storeId = $storeId;

        if (!$this->getIncrementPerStore() || ($this->_storeId === null)) {
            /**
             * store_id null we can have for entity from removed store
             */
            $this->_storeId = 0;
        }

        // Start transaction to run SELECT ... FOR UPDATE
        $this->_getResource()->beginTransaction();

        $this->_entityStoreConfig = Mage::getModel('eav/entity_store')
            ->loadByEntityStore($this->getId(), $this->_storeId);

        if (!$this->_entityStoreConfig->getId()) {
            $this->_entityStoreConfig
                ->setEntityTypeId($this->getId())
                ->setStoreId($this->_storeId)
                ->setIncrementPrefix($this->_storeId)
                ->save();
        }

        $this->_incrementInstance = Mage::getModel($this->getIncrementModel())
            ->setPrefix($this->_getIncrementPrefix())
            ->setPadLength($this->_getIncrementPadLength())
            ->setPadChar($this->getIncrementPadChar())
            ->setLastId($this->_getIncrementLastId())
            ->setEntityTypeId($this->_entityStoreConfig->getEntityTypeId())
            ->setStoreId($this->_entityStoreConfig->getStoreId());

        $this->_incrementId = $this->_incrementInstance->getNextId();
        if(false === $this->_isIncrementIdUinique()) {
                $this->_generateUniqueIncrementId();
        }


        $this->_entityStoreConfig->setIncrementLastId($this->_incrementId);
        $this->_entityStoreConfig->setIncrementPrefix($this->_getIncrementPrefix());
        $this->_entityStoreConfig->save();

        // Commit increment_last_id changes
        $this->_getResource()->commit();

        return $this->_incrementId;
    }

    /**
     * @return mixed|string
     */
    protected function _getIncrementPrefix()
    {
        $prefix = Mage::getStoreConfig('mswebdesign_customordernumber/'.$this->_entityTypeCode.'/prefix', $this->_storeId);
        $datePrefix = Mage::getStoreConfig('mswebdesign_customordernumber/'.$this->_entityTypeCode.'/date_prefix', $this->_storeId);

        if('' !== $datePrefix) {
            return $this->_datePrefix = date($datePrefix);
        }

        if('' !== $prefix) {
            return $prefix;
        }

        return null;
    }

    /**
     * @return int
     */
    protected function _getIncrementPadLength()
    {
        return intval(Mage::getStoreConfig('mswebdesign_customordernumber/'.$this->_entityTypeCode.'/padding_length', $this->_storeId));
    }

    /**
     * @return int
     */
    protected function _getIncrementLastId()
    {
        if('' === $this->_datePrefix || 0 === intval(Mage::getStoreConfig('mswebdesign_customordernumber/'.$this->_entityTypeCode.'/date_prefix_reset_enabled', $this->_storeId))) {
            return $this->_entityStoreConfig->getIncrementLastId();
        }

        return $this->_resetIncrementLastIfDateHasChanged();
    }

    /**
     * @return int
     */
    protected function _resetIncrementLastIfDateHasChanged()
    {
        if($this->_entityStoreConfig->getIncrementPrefix() !== $this->_datePrefix) {
            return 0;
        }
    }

    /**
     * @return bool
     */
    protected function _isIncrementIdUinique()
    {
        switch($this->_entityTypeCode) {
            case('order'):
                $collection = Mage::getSingleton('sales/'.$this->_entityTypeCode)->getCollection();
                break;
            default:
                $collection = Mage::getSingleton('sales/order_'.$this->_entityTypeCode)->getCollection();
        }

        $collection->clear();
        $count = $collection->addAttributeToFilter('increment_id', $this->_incrementId)->count();
        return ($count == 0)? true:false;
    }

    protected function _generateUniqueIncrementId()
    {
        do {
            $this->_incrementInstance->setLastId($this->_incrementId);
            $this->_incrementId = $this->_incrementInstance->getNextId();
        } while (false === $this->_isIncrementIdUinique());
    }
}