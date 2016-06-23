<?php

/**
 * Copyright (C) 2012 Vizualizer All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Naohisa Minagawa <info@vizualizer.jp>
 * @copyright Copyright (c) 2010, Vizualizer
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @since PHP 5.3
 * @version   1.0.0
 */

/**
 * 注文詳細のモデルです。
 *
 * @package VizualizerStock
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerStock_Model_OrderDetail extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("stock");
        parent::__construct($loader->loadTable("OrderDetails"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $order_detail_id 商品構成資材ID
     */
    public function findByPrimaryKey($order_detail_id)
    {
        $this->findBy(array("order_detail_id" => $order_detail_id));
    }

    /**
     * 注文IDでデータを取得する。
     */
    public function findAllByOrderId($order_id)
    {
        return $this->findAllBy(array("order_id" => $order_id));
    }

    /**
     * 注文データ
     */
    public function order()
    {
        $loader = new Vizualizer_Plugin("stock");
        $model = $loader->loadModel("Order");
        $model->findByPrimaryKey($this->order_id);
        return $model;
    }

    /**
     * 注文詳細のデータを元に在庫の引き当てを実施
     */
    public function provision()
    {
        $loader = new Vizualizer_Plugin("stock");
        $menu = $loader->loadModel("Menu");
        $menu->findBy(array("set_id" => $this->set_id, "choice_id" => $this->choice_id));
        if ($menu->menu_id > 0 && $menu->fixed_flg == "1") {
            // トランザクションの開始
            $connection = Vizualizer_Database_Factory::begin("stock");

            try {

                // メニューが確定されている場合は引き当てを実行
                $components = $menu->components();
                foreach($components as $component) {
                    if ($component->quantity <= 0) {
                        break;
                    }
                    $quantity = $component->quantity;
                    $purchase = $loader->loadModel("Purchase");
                    $purchases = $purchase->findAllBy(array("material_id" => $component->material_id, "purchase_status" => "stocked"), "production_date", false);
                    foreach ($purchases as $purchase) {
                        if ($quantity < $purchase->volume - $purchase->consumed) {
                            $purchase->consumed += $quantity;
                            $quantity = 0;
                        } else {
                            $quantity -= ($purchase->volume - $purchase->consumed);
                            $purchase->consumed = $purchase->volume;
                            $purchase->purchase_status = "consumed";
                        }
                        $purchase->save();
                    }
                }
                $this->provision_flg = 1;
                $this->save();

                Vizualizer_Database_Factory::commit($connection);
            } catch (Exception $e) {
                Vizualizer_Database_Factory::rollback($connection);
                throw new Vizualizer_Exception_Database($e);
            }
        }
    }

}
