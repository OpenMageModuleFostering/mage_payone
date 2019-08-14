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
 */
class Payone_Core_Model_Domain_Resource_Config_PaymentMethod_Collection
    extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     *
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('payone_core/domain_config_paymentMethod');
    }

    public function addItem(Varien_Object $item)
    {
        /** @var $item Payone_Core_Model_Domain_Config_PaymentMethod */
        $item->afterLoadPrepareData();
        return parent::addItem($item);
    }

    /**
     * if activated, the result will only return not deleted methods
     */
    public function filterExcludeDeleted()
    {
        $this->addFilterIsDeleted(0);
    }

    /**
     * 0 => deleted methods are excluded
     * 1 => deleted methods are included
     *
     * @param int $isDeleted
     */
    protected function addFilterIsDeleted($isDeleted = 0)
    {
        $this->addFieldToFilter('is_deleted', array('eq' => $isDeleted));
    }

    /**
     * if used, all paymentmethod-configs with scope 'default' and 'websites' were returned
     */
    public function filterExcludeStoresScope()
    {
        $this->addFilterScope('websites');
    }

    /**
     * @param $scope
     */
    protected function addFilterScope($scope)
    {
        // OR-Statement
        $this->addFieldToFilter('scope',
            array(
                array('attribute' => 'scope', 'eq' => 'default'),
                array('attribute' => 'scope', 'eq' => $scope)
            ));
    }

    /**
     * @param $store Mage_Core_Model_Store
     */
    public function filterByStore(Mage_Core_Model_Store $store)
    {
        $this->filterExcludeDeleted();
        $this->addFieldToFilter('scope_id', $store->getWebsiteId());
    }

    /**
     * @param string $order
     * @param string $orderDir
     */
    public function addSortOrder($order = 'sort_order', $orderDir = self::SORT_ORDER_ASC)
    {
        $this->addOrder($order, $orderDir);
    }

    /**
     * @param $storeId int
     * @param bool $removeParent
     * @return Payone_Core_Model_Domain_Resource_Config_PaymentMethod_Collection
     */
    public function getCollectionByStoreId($storeId, $removeParent = false)
    {
        // Add Filter is_deleted = 0
        $store = Mage::app()->getStore($storeId);
        $scopeId = $store->getId();

        // Add Filter (scope_id = 0) OR (scope_id = $this->getScopeId())
        $this->addFieldToFilter('scope_id',
            array(
                array('attribute' => 'scope_id', 'eq' => 0),
                array('attribute' => 'scope_id', 'eq' => $scopeId)
            )
        );

        foreach ($this->getItems() as $key => $data) {
            /**@var $data Payone_Core_Model_Domain_Config_PaymentMethod  */
            if ($data->getScope() == 'stores' && $data->getScopeId() == $scopeId) {
                $parentScope = 'websites';
            }
            elseif ($data->getScope() == 'websites' && $data->getScopeId() == $scopeId) {
                $parentScope = 'default';
            }
            else {
                continue;
            }

            $parentField = 'parent_' . $parentScope . '_id';
            $parentId = $data->getData($parentField);
            /** @var $parentItem Payone_Core_Model_Domain_Config_PaymentMethod */
            $parentItem = $this->getItemById($parentId);
            //check for parent payment_config
            if ($parentItem) {
                $removeId = $parentId;
                $grandParentScope = '';
                if ($parentItem->getScope() == 'websites') {
                    $grandParentScope = 'default';
                    if ($removeParent) {
                        $this->removeItemByKey($parentId);
                    }
                }
                $grandParentField = 'parent_' . $grandParentScope . '_id';
                $grandParentId = $parentItem->getData($grandParentField);
                /** @var $grandParentItem Payone_Core_Model_Domain_Config_PaymentMethod */
                $grandParentItem = $this->getItemById($grandParentId);
                // check for grandparent payment_config
                if ($grandParentItem) {
                    $removeId = $grandParentId;
                    $this->mergeData($parentItem, $grandParentItem);
                }

                $this->mergeData($data, $parentItem);

                // necessary to remove items from the result-collection, otherwise they items won't be removed
                $item = $this->getItemById($removeId);
                if ($removeParent) {
                    $this->removeItemByKey($removeId);
                }
            }
        }
        return $this;
    }

    /**
     * @param Payone_Core_Model_Domain_Config_PaymentMethod $child
     * @param Payone_Core_Model_Domain_Config_PaymentMethod $parent
     * @return Payone_Core_Model_Domain_Config_PaymentMethod
     */
    protected function mergeData(
        Payone_Core_Model_Domain_Config_PaymentMethod $child,
        Payone_Core_Model_Domain_Config_PaymentMethod $parent
    )
    {
        foreach ($child->getData() as $key => $value) {
            if ($value === null || $value === false) {
                $child->setData($key, $parent->getData($key));
            }
        }
        return $child;
    }
}