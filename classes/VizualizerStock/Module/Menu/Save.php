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
 * メニューのデータを保存する。
 *
 * @package VizualizerTrade
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerStock_Module_Menu_Save extends Vizualizer_Plugin_Module_Save
{

    function execute($params)
    {
        $this->executeImpl("Stock", "Menu", "menu_id");

        $post = Vizualizer::request();
        $loader = new Vizualizer_Plugin("stock");
        $model = $loader->loadModel("Menu");
        $model->findByPrimaryKey($post["menu_id"]);

        if ($model->menu_id > 0 && $model->fixed_flg == "1") {
            $orderDetail = $loader->loadModel("OrderDetail");
            $orderDetails = $orderDetail->findAllBy(array("set_id" => $model->set_id, "choice_id" => $model->choice_id, "ne:provision_flg" => "1"));

            foreach ($orderDetails as $orderDetail) {
                $orderDetail->provision();
            }
        }
    }
}
