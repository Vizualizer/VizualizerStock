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

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

putenv("AWS_ACCESS_KEY_ID=".Vizualizer_Configure::get("AWS_ACCESS_KEY_ID"));
putenv("AWS_SECRET_ACCESS_KEY=".Vizualizer_Configure::get("AWS_SECRET_ACCESS_KEY"));

/**
 * レジからの注文データを取得するためのバッチです。
 *
 * @package VizualizerTrade
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerStock_Batch_Import extends Vizualizer_Plugin_Batch
{
    public function getName(){
        return "Order Import";
    }

    public function getFlows(){
        return array("importOrders");
    }

    /**
     * レジからの注文データを取り込む。
     * @param $params バッチ自体のパラメータ
     * @param $data バッチで引き回すデータ
     * @return バッチで引き回すデータ
     */
    protected function importOrders($params, $data){
        try {
            // Use the us-west-2 region and latest version of each client.
            $sharedConfig = array(
                'region'  => 'ap-northeast-1',
                'version' => 'latest'
            );

            // Create an SDK class used to share configuration across clients.
            $sdk = new Aws\Sdk($sharedConfig);

            // Create an Amazon S3 client using the shared configuration data.
            $client = $sdk->createS3();
            if (count($params) > 3) {
                if ($params[3] == "today") {
                    $time = time();
                } elseif($params[3] == "yesterday") {
                    $time = strtotime("-1 day");
                } else {
                    $time = strtotime($params[3]);
                }
            } else {
                $time = time();
            }
            // Get the object
            $result = $client->getObject(array(
                'Bucket' => "oder-report",
                'Key'    => "150/".date("Ym", $time)."/sales-".date("Y-m-d", $time).".csv"
            ));

            Vizualizer_Logger::writeDebug($result['Body']);

            $lines = explode("\n", $result['Body']);
            $columns = array();
            $data = array();

            foreach($lines as $index => $line) {
                if (!empty($line)) {
                    if ($index > 0) {
                        $item = str_getcsv($line);
                        $record = array();
                        foreach($columns as $i => $key) {
                            $record[$key] = $item[$i];
                        }
                        $data[] = $record;
                    } else {
                        $columns = str_getcsv($line);
                    }
                }
            }

            Vizualizer_Logger::writeDebug(print_r($data, true));

            $sets = array();
            foreach ($data as $item) {
                if ($item["type"] == "summary") {
                    // トランザクションの開始
                    $connection = Vizualizer_Database_Factory::begin("stock");
                    try {
                        $loader = new Vizualizer_Plugin("stock");

                        $model = $loader->loadModel("Order");
                        $model->findByPrimaryKey($item["order_id"]);
                        if (!($model->order_id > 0)) {
                            $model = $loader->loadModel("Order", array("order_id" => $item["order_id"]));
                        }
                        $model->user_id = $item["user_id"];
                        $model->payment_type = $item["payment_type"];
                        $model->order_date = $item["purchase_date"];
                        $model->price = $item["price"];
                        $model->save();

                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }
                }
                if ($item["type"] == "set") {
                    $sets[$item["order_id"]."-".$item["set_id"]] = $item;

                    // トランザクションの開始
                    $connection = Vizualizer_Database_Factory::begin("stock");
                    try {
                        $loader = new Vizualizer_Plugin("stock");

                        $model = $loader->loadModel("OrderDetail");
                        $model->order_id = $item["order_id"];
                        $model->set_id = $item["set_id"];
                        $model->set_menu_name = $item["set_menu_name"];
                        $model->order_date = $item["purchase_date"];
                        $model->price = $item["price"];
                        $model->quantity = $item["count"];
                        $model->save();

                        $model = $loader->loadModel("Menu");
                        $model->findBy(array("set_id" => $item["set_id"], "choice_id" => 0));
                        $model->set_id = $item["set_id"];
                        $model->set_menu_name = $sets[$item["order_id"]."-".$item["set_id"]]["set_menu_name"];
                        $model->menu_name = $item["menu_name"];
                        $model->price = $item["price"];
                        $model->fixed_flg = "1";
                        $model->save();

                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }

                    // 在庫の引き当てを実施
                    $model = $loader->loadModel("OrderDetail");
                    $models = $model->findAllBy(array("set_id" => $item["set_id"], "ne:provision_flg" => "1"));
                    foreach ($models as $model) {
                        $model->provision();
                    }
                }
                if ($item["type"] == "choice") {
                    // トランザクションの開始
                    $connection = Vizualizer_Database_Factory::begin("stock");
                    try {
                        $loader = new Vizualizer_Plugin("stock");

                        $model = $loader->loadModel("OrderDetail");
                        $model->order_id = $item["order_id"];
                        $model->set_id = $item["set_id"];
                        $model->choice_id = $item["choice_id"];
                        $model->set_menu_name = $sets[$item["order_id"]."-".$item["set_id"]]["set_menu_name"];
                        $model->menu_name = $item["menu_name"];
                        $model->price = $item["price"];
                        $model->quantity = $sets[$item["order_id"]."-".$item["set_id"]]["count"];
                        $model->save();

                        $model = $loader->loadModel("Menu");
                        $model->findBy(array("set_id" => $item["set_id"], "choice_id" => $item["choice_id"]));
                        $model->set_id = $item["set_id"];
                        $model->choice_id = $item["choice_id"];
                        $model->set_menu_name = $sets[$item["order_id"]."-".$item["set_id"]]["set_menu_name"];
                        $model->menu_name = $item["menu_name"];
                        $model->price = $item["price"];
                        $model->fixed_flg = "1";
                        $model->save();

                        // エラーが無かった場合、処理をコミットする。
                        Vizualizer_Database_Factory::commit($connection);
                    } catch (Exception $e) {
                        Vizualizer_Database_Factory::rollback($connection);
                        throw new Vizualizer_Exception_Database($e);
                    }

                    // 在庫の引き当てを実施
                    $model = $loader->loadModel("OrderDetail");
                    $models = $model->findAllBy(array("set_id" => $item["set_id"], "choice_id" => $item["choice_id"], "ne:provision_flg" => "1"));
                    foreach ($models as $model) {
                        $model->provision();
                    }
                }
            }
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }

        return $data;
    }
}
