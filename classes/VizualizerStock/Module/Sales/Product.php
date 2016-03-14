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
 * 商品別売上のリストを取得する。
 *
 * @package VizualizerTrade
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerStock_Module_Sales_Product extends Vizualizer_Plugin_Module
{

    function execute($params)
    {
        $loader = new Vizualizer_Plugin("stock");
        $order = $loader->loadModel("OrderDetail");
        $post = Vizualizer::request();
        $month = $post["ym"];
        if (empty($month) || preg_match("/[0-9]{4}-[0-9]{2}/", $month) == 0) {
            $month = date("Y-m");
        }
        $query = "SELECT stock_order_details.set_id, stock_order_details.choice_id, stock_order_details.set_menu_name, stock_order_details.menu_name, SUM(stock_order_details.price * stock_order_details.quantity) AS price ";
        $query .= "FROM stock_order_details, stock_orders ";
        $query .= "WHERE stock_orders.order_id = stock_order_details.order_id AND stock_orders.order_date LIKE '".$month."-%' ";
        $query .= "GROUP BY stock_order_details.set_id, stock_order_details.choice_id ";
        $query .= "HAVING SUM(stock_order_details.price * stock_order_details.quantity) > 0 ";
        $query .= "ORDER BY stock_order_details.set_id, stock_order_details.choice_id";
        $orders = $order->queryAllBy($query);
        $attr = Vizualizer::attr();
        $attr["sales"] = $orders;
        $attr["thismonth"] = date("Y-m-01", strtotime($month."-01"));
        $attr["nextmonth"] = date("Y-m", strtotime("+1 month", strtotime($month."-01")));
        $attr["lastmonth"] = date("Y-m", strtotime("-1 month", strtotime($month."-01")));
    }
}
