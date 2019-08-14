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
 * Do not edit or add to this file if you wish to upgrade Payone to newer
 * versions in the future. If you wish to customize Payone for your
 * needs please refer to http://www.payone.de for more information.
 *
 * @category        Payone
 * @package         Payone_Api
 * @subpackage      Request
 * @author          Ronny Schröder <www.imk24.de>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 */

/**
 *
 * @category        Payone
 * @package         Payone_Api
 * @subpackage      Request
 * @copyright       Copyright (c) 2014 
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 */
class Payone_Api_Request_Parameter_Paydata_Paydata extends Payone_Api_Request_Parameter_Abstract {

    /**
     * @var Payone_Api_Request_Parameter_Paydata_DataItem[]
     */
    protected $items = array();

    public function toArray() {
        $data = parent::toArray();
        /**
         * @var Payone_Api_Request_Parameter_Paydata_DataItem $item
         */
        foreach ($this->items as $item) {

            $data = array_merge($data, $item->toArray());
        }

        return $data;
    }

    /**
     * @return bool
     */
    public function hasItems() {
        return count($this->items) ? true : false;
    }

    /**
     * @param Payone_Api_Request_Parameter_Paydata_DataItem $item
     */
    public function addItem(Payone_Api_Request_Parameter_Paydata_DataItem $item) {
        $this->items[] = $item;
    }

    /**
     * @param Payone_Api_Request_Parameter_Paydata_DataItem[] $items
     */
    public function setItems($items) {
        $this->items = $items;
    }

    /**
     * @return Payone_Api_Request_Parameter_Paydata_DataItem[]
     */
    public function getItems() {
        return $this->items;
    }

}
