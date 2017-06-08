<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 17:00
 */

namespace app\domain\order;

use yii\data\ActiveDataProvider;

class ViewModel
{
    public $dataProvider;

    public $order;

    public $products;

    public $users;

    public $filtersState = [];

    public $errors = [];

    /**
     * @param mixed $dataProvider
     */
    public function setDataProvider(ActiveDataProvider $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

}