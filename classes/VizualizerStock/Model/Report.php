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
 * 日報のモデルです。
 *
 * @package VizualizerStock
 * @author Naohisa Minagawa <info@vizualizer.jp>
 */
class VizualizerStock_Model_Report extends Vizualizer_Plugin_Model
{

    /**
     * コンストラクタ
     *
     * @param $values モデルに初期設定する値
     */
    public function __construct($values = array())
    {
        $loader = new Vizualizer_Plugin("stock");
        parent::__construct($loader->loadTable("Reports"), $values);
    }

    /**
     * 主キーでデータを取得する。
     *
     * @param $material_id 資材ID
     */
    public function findByPrimaryKey($report_id)
    {
        $this->findBy(array("report_id" => $report_id));
    }

    public function details()
    {
        $loader = new Vizualizer_Plugin("stock");
        $model = $loader->loadModel("ReportDetail");
        $condition = array("report_id" => $this->report_id);
        return $model->findAllBy($condition);
    }

    private function updateDetails()
    {
        $loader = new Vizualizer_Plugin("stock");
        foreach ($this->details as $purchase_id => $volume) {
            $model = $loader->loadModel("ReportDetail");
            $model->findBy(array("report_id" => $this->report_id, "purchase_id" => $purchase_id));
            $purchase = $loader->loadModel("Purchase");
            $purchase->findByPrimaryKey($purchase_id);
            if (!($model->report_detail_id > 0)) {
                $model->report_id = $this->report_id;
                $model->purchase_id = $purchase_id;
                $model->original_volume = $purchase->volume - $purchase->consumed;
            }
            $model->fixed_volume = $volume;
            $model->save();
            $purchase->consumed = $purchase->volume - $volume;
            if ($purchase->volume == $purchase->consumed) {
                $purchase->purchase_status = "consumed";
            }
            $purchase->save();
        }
    }

    /**
     * レコードが作成可能な場合に、レコードを作成します。
     */
    public function create()
    {
        $result = parent::create();

        $this->updateDetails();

        return $result;
    }

    /**
     * レコードが更新可能な場合に、レコードを更新します。
     */
    public function update()
    {
        $result = parent::update();

        $this->updateDetails();

        return $result;
    }
}
