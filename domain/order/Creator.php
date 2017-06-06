<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 17:03
 */

namespace app\domain\order;

use app\models\Order;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Query;

class Creator
{
    /**
     * @var ViewModel
     */
    private $viewModel;
    /**
     * @var DataProvider
     */
    private $dataProvider;

    /**
     * Creator constructor.
     * @param DataProvider $dataProvider
     * @param ViewModel $viewModel
     */
    public function __construct(DataProvider $dataProvider, ViewModel $viewModel)
    {
        $this->viewModel = $viewModel;
        $this->dataProvider = $dataProvider;
    }

    public function loadRecords($request)
    {

        $this->viewModel->setDataProvider($this->dataProvider->get());

        return $this->viewModel;
    }

    /**
     * @return static
     */
    public static function instance()
    {
        return new static(new DataProvider(new ActiveQuery('app\models\Order')), new ViewModel());
    }
}