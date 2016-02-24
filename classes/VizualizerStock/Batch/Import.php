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
        $s3 = S3Client::factory();

        try {
            // Get the object
            $result = $s3->getObject(array(
                'Bucket' => "oder-report",
                'Key'    => "/150/201602/sales-2016-02-18.csv"
            ));

            // Display the object in the browser
            header("Content-Type: {$result['ContentType']}");
            echo $result['Body'];
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }

        return $data;
    }
}
