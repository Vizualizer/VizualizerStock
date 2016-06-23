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
 * 日別売上のリストを取得する。
 *
 * @package VizualizerTrade
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerStock_Module_Sales_Daily extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        // パラメータを調整
        $post = Vizualizer::request();
        $month = $post["ym"];
        if (empty($month) || preg_match("/[0-9]{4}-[0-9]{2}/", $month) == 0) {
            $month = date("Y-m");
        }

        // クエリを生成
        $loader = new Vizualizer_Plugin("stock");
        $orders = $loader->loadTable("Orders");
        $select = new Vizualizer_Query_Select($orders);
        $select->addColumn("SUBSTR(".$orders->order_date.", 1, 10)", "order_date");
        $select->addColumn("SUM(".$orders->price.")", "price");
        $select->where("order_date LIKE ?", array($month."-%"));
        $select->group("SUBSTR(".$orders->order_date.", 1, 10)");

        // 生成したクエリに対して検索を実行し、結果をモデルとして取得
        $order = $loader->loadModel("Order");
        $orders = $order->queryAllBy($select);

        // 結果を属性に設定
        $attr = Vizualizer::attr();
        $attr["sales"] = $orders;
        $attr["thismonth"] = date("Y-m-01", strtotime($month."-01"));
        $attr["nextmonth"] = date("Y-m", strtotime("+1 month", strtotime($month."-01")));
        $attr["lastmonth"] = date("Y-m", strtotime("-1 month", strtotime($month."-01")));
    }
}
