<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 17:03
 */

namespace app\domain\order;

use yii\db\ActiveQuery;

class Creator
{
    /**
     * @var ViewModel
     */
    private $viewModel;

    /**
     * @var ListDataProvider
     */
    private $dataProvider;

    /**
     * Creator constructor.
     * @param ListDataProvider $dataProvider
     * @param ViewModel $viewModel
     */
    public function __construct(ListDataProvider $dataProvider, ViewModel $viewModel)
    {
        $this->viewModel = $viewModel;
        $this->dataProvider = $dataProvider;
    }

    public function loadRecords($request)
    {

        $this->viewModel->setDataProvider($this->dataProvider->get($request));

        return $this->viewModel;
    }

    /**
     * @return static
     */
    public static function instance()
    {
        $filters = [
            new DaysPastFilter(),
        ];

        return new static(new ListDataProvider(new ActiveQuery('app\models\Order'), $filters), new ViewModel());
    }
}