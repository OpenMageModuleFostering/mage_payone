<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License (GPL 3)
 * that is bundled with this package in the file LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Payone_Core to newer
 * versions in the future. If you wish to customize Payone_Core for your
 * needs please refer to http://www.payone.de for more information.
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Domain
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @author          Matthias Walter <info@noovias.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 */

/**
 *
 * @category        Payone
 * @package         Payone_Core_Model
 * @subpackage      Domain
 * @copyright       Copyright (c) 2012 <info@noovias.com> - www.noovias.com
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.noovias.com
 *
 * @method setId($id)
 * @method int getId()
 * @method setScope($scope)
 * @method string getScope()
 * @method setScopeId($scopeId)
 * @method int getScopeId()
 * @method setCode($code)
 * @method string getCode()
 * @method setName($name)
 * @method string getName()
 * @mehtod setSortOrder($sortOrder)
 * @method int getSortOrder()
 * @method setEnabled($enabled)
 * @method int getEnabled()
 * @method setFeeConfig($config)
 * @method setMode($mode)
 * @method string getMode()
 * @method setUseGlobal($useGlobal)
 * @method int getUseGlobal()
 * @method setMid($mid)
 * @method int getMid()
 * @method setAid($aid)
 * @method int getAid()
 * @method setPortalid($portalid)
 * @method int getPortalid()
 * @method setKey($key)
 * @method string getKey()
 * @method setRequestType($requestType)
 * @method string getRequestType()
 * @method setAllowspecific($allowspecific)
 * @method int getAllowspecific()
 * @method setSpecificcountry($specificcountry)
 * @method setInvoiceTransmit($invoiceTransmit)
 * @method int getInvoiceTransmit()
 * @method setTypes($types)
 * @method setCheckCvc($checkCvc)
 * @method int getCheckCvc()
 * @method setCheckBankAccount($checkBankaccount)
 * @method int getCheckBankAccount()
 * @method setMinOrderTotal($minOrderTotal)
 * @method float getMinOrderTotal()
 * @method setMaxOrderTotal($maxOrderTotal)
 * @method float getMaxOrderTotal()
 * @method setParentDefaultId($id)
 * @method int getParentDefaultId()
 * @method setParentWebsitesId($id)
 * @method int getParentWebsitesId()
 * @method setIsDeleted($isDeleted)
 * @method int getIsDeleted()
 * @method setCreatedAt(string $dateTime)
 * @method string getCreatedAt()
 * @method setUpdatedAt(string $dateTime)
 * @method string getUpdatedAt()
 *
 * @method setStore($store)
 * @method string getStore()
 * @method setWebsite($website)
 * @method string getWebsite()
 * @method setGroups($groups)
 * @method array getGroups()
 */
class Payone_Core_Model_Domain_Config_PaymentMethod extends Mage_Core_Model_Abstract
{
    /**
     *
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('payone_core/config_paymentMethod');
    }

    /**
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    protected function _beforeSave()
    {
        $this->validate();
        $this->prepareScope();

        $groups = $this->getGroups();
        if (is_array($groups)) {
            $data = $this->initDataObject($groups);
            $this->addData($data);
        }

        $originModel = $this->loadOriginPaymentMethodConfig();
        if ($originModel
                && ($this->getScope() != $originModel->getScope()
                        || $this->getScopeId() != $originModel->getScopeId()
                )
        ) {
            $parentScope = '';
            if ($this->getScope() == 'stores') {
                $parentScope = 'websites';
            }
            elseif ($this->getScope() == 'websites') {
                $parentScope = 'default';
            }

            // only add an empty paymentMethod if we are in store-scope
            if ($originModel->getScope() != $parentScope) {
                $parentField = 'parent_' . $originModel->getScope() . '_id';
                /** @var $dummy Payone_Core_Model_Domain_Config_PaymentMethod */
                $dummy = Mage::getModel('payone_core/domain_config_paymentMethod');
                $dummy->setScope($parentScope);
                $dummy->setScopeId($this->getScopeId());
                $dummy->setCode($originModel->getCode());
                $dummy->setData($parentField, $this->getId());
                $dummy->setMode(null);
                // set because they where shown in the grid
                $dummy->setName($originModel->getName());
                $dummy->save();
            }
            else
            {
                $dummy = null;
            }

            $parentField = 'parent_' . $parentScope . '_id';
            $parentId = $dummy ? $dummy->getId() : $this->getId();
            $this->setData($parentField, $parentId);
            $this->unsetData('id');
        }

        $code = $this->getCode();
        if (empty($code)) {
            $this->setCode($originModel->getCode());
        }

        $this->prepareData();

        if ($this->isObjectNew()) {
            $this->setCreatedAt(date('Y-m-d H:i:s'));
            $this->setUpdatedAt(date('Y-m-d H:i:s'));
        }
        else {
            $this->setUpdatedAt(date('Y-m-d H:i:s'));
        }

        return $this;
    }

    /**
     * Load original PaymentMethod from Database
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    public function loadOriginPaymentMethodConfig()
    {
        if ($this->getId()) {

            /** @var $originModel Payone_Core_Model_Domain_Config_PaymentMethod */
            $originModel = Mage::getModel('payone_core/domain_config_paymentMethod');
            $originModel->load($this->getId());
            $originModel->prepareData();
            return $originModel;
        }
        return null;
    }

    /**
     * Validate Store and Website
     */
    private function validate()
    {
        if (is_null($this->getWebsite())) {
            $this->setWebsite('');
        }
        if (is_null($this->getStore())) {
            $this->setStore('');
        }
    }

    /**
     * Prepare Scope and ScopeId
     */
    private function prepareScope()
    {
        $scope = $this->getScope();
        $scopeId = $this->getScopeId();
        if (!isset($scope) && !isset($scopeId)) {


            if ($this->getStore()) {
                $scope = 'stores';
                $scopeId = (int)Mage::getConfig()->getNode('stores/' . $this->getStore() . '/system/store/id');
            }
            elseif ($this->getWebsite()) {
                $scope = 'websites';
                $scopeId = (int)Mage::getConfig()->getNode('websites/' . $this->getWebsite() . '/system/website/id');
            }
            else {
                $scope = 'default';
                $scopeId = 0;
            }
            $this->setScope($scope);
            $this->setScopeId($scopeId);
        }
    }

    /**
     * Prepares data for ConfigData
     * @param $methodType
     * @param $currentScope
     * @return array|null
     */
    public function initConfigObject($methodType, $currentScope)
    {
        $this->validate();
        $this->prepareScope();
        if ($this->getScope() != $currentScope) {
            return null;
        }
        $data = $this->getData();
        $configData = array();
        $keyPrefix = Payone_Core_Block_Adminhtml_System_Config_Form_Payment_Method::SECTION_PAYONE_PAYMENT . '/';
        $keyPrefix .= Payone_Core_Block_Adminhtml_System_Config_Form_Payment_Method::GROUP_TEMPLATE_PREFIX . $methodType . '/';
        foreach ($data as $key => $value) {
            $configData[$keyPrefix . $key] = $value;
        }

        return $configData;
    }

    /**
     * Prepares the data, before the model could be saved
     * @param $groups
     * @return array
     */
    protected function initDataObject($groups)
    {
        $templateArr = array_pop($groups);
        $data = array_pop($templateArr);
        $mappedData = array();
        foreach ($data as $fieldKey => $fieldValue) {
            $inherit = !empty($fieldValue['inherit']);
            if ($inherit) {
                $mappedData[$fieldKey] = null;
            }
            else {
                $value = array_pop($fieldValue);
                switch ($fieldKey) {
                    case 'fee_config':
                        unset($value['__empty']);
                        $value = empty($value) ? null : $value;
                        break;
                    case 'use_global':
                        if($value){
                            // set data to null if we use global config
                            $mappedData['allowspecific'] = null;
                            $mappedData['specificcountry'] = null;
                            $mappedData['mid'] = null;
                            $mappedData['portalid'] = null;
                            $mappedData['aid'] = null;
                            $mappedData['key'] = null;
                            $mappedData['request_type'] = null;
                            $mappedData['invoice_transmit'] = null;
                        }
                        break;
                    default:
                        if (!isset($value)) {
                            continue 2;
                        }elseif($value == '')
                        {
                            $value = null;
                        }
                        break;
                }
                $mappedData[$fieldKey] = $value;
            }
        }
        return $mappedData;
    }

    /**
     * Loads parent model if it has one
     * @return null|Payone_Core_Model_Domain_Config_PaymentMethod
     */
    public function getParentModel()
    {
        $originModel = $this->loadOriginPaymentMethodConfig();
        if ($originModel) {
            return $this->loadParentModel($originModel);
        }
        return null;
    }

    /**
     * Checks if there is a parent model and returns it
     * @param $model Payone_Core_Model_Domain_Config_PaymentMethod
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    protected function loadParentModel($model)
    {
        $model->validate();
        $model->prepareScope();
        if ($model->getScope() == 'websites') {
            $parentField = 'parent_default_id';
        }
        elseif ($model->getScope() == 'stores') {
            $parentField = 'parent_websites_id';
        }
        else {
            $model->prepareData();
            return $model;
        }
        /** @var $parentModel Payone_Core_Model_Domain_Config_PaymentMethod */
        $parentModel = Mage::getModel('payone_core/domain_config_paymentMethod');
        $parentModel->load($model->getData($parentField));
        $parentModel->prepareData();
        return $parentModel;

    }

    /**
     * Loads originData, checks if it has parent PaymentMethodConfigs and merges the data together
     * @param bool $prepareData
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    public function loadMergedData($prepareData = true)
    {
        $parentModel = $this->getParentModel();
        if ($parentModel) {
            $grandParentModel = $this->loadParentModel($parentModel);
            $parentCleanModel = $this->removeEmptyData($parentModel);
            $currentCleanModel = $this->removeEmptyData($this);
            $mergedArray = array_merge($grandParentModel->getData(), $parentCleanModel->getData(), $currentCleanModel->getData());
            $this->setData($mergedArray);
        }
        // prepare data
        if ($prepareData) {
            $this->afterLoadPrepareData();
        }
        return $this;
    }

    /**
     * Cleans $model. Remove all empty data
     * @param $model Payone_Core_Model_Domain_Config_PaymentMethod
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    private function removeEmptyData($model)
    {
        $data = $model->getData();
        $model->unsetData();
        foreach ($data as $key => $value) {
            if (isset($value)) {
                $model->setData($key, $value);
            }
        }
        return $model;
    }

    /**
     * @return Mage_Core_Model_Abstract | Payone_Core_Model_Domain_Config_PaymentMethod
     */
    protected function _afterLoad()
    {
        $this->afterLoadPrepareData();

        return $this;
    }

    /**
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    public function afterLoadPrepareData()
    {
        // prepare fee_config
        $this->unserializeData('fee_config');
        $this->explodeData('types');
        $this->explodeData('specificcountry');
    }

    /**
     *
     */
    protected function prepareData()
    {
        // prepare types
        $this->implodeData('types');

        // prepare specificcountry
        $this->implodeData('specificcountry');

        // prepare fee_config
        $this->serializeData('fee_config');
    }

    /**
     * @param string $key
     */
    private function serializeData($key)
    {
        $data = $this->getData($key);
        if (is_array($data)) {
            unset($data['__empty']);
            $this->setData($key, serialize($data));
        }
    }

    /**
     * @param $key
     */
    private function unserializeData($key)
    {
        $data = $this->getData($key);
        if (!is_array($data) && $data != '') {
            $this->setData($key, empty($data) ? false : unserialize($data));
        }
    }

    /**
     * @param string $key
     */
    private function implodeData($key)
    {
        $data = $this->getData($key);
        if (is_array($data)) {
            $this->setData($key, implode(',', $data));
        }

    }

    /**
     * @param string $key
     */
    private function explodeData($key)
    {
        $data = $this->getData($key);
        if ($data !== null && !is_array($data)) {
            $this->setData($key, empty($data) ? false : explode(',', $data));
        }
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        $this->explodeData('types');
        return $this->getData('types');
    }

    /**
     * @return array
     */
    public function getFeeConfig()
    {
        $this->unserializeData('fee_config');
        return $this->getData('fee_config');
    }

    /**
     * @return array
     */
    public function getSpecificcountry()
    {
        $this->explodeData('specificcountry');
        return $this->getData('specificcountry');
    }
}