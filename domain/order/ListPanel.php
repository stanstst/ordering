<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 17:03
 */

namespace app\domain\order;

use app\components\AssocEntityRepo;
use app\models\Order;
use app\models\Product;
use app\models\User;
use yii\db\ActiveQuery;
use yii\db\Query;

class ListPanel
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
     * @var AssocEntityRepo
     */
    private $entityRepo;

    /**
     * Creator constructor.
     * @param ListDataProvider $dataProvider
     * @param AssocEntityRepo $entityRepo
     * @param ViewModel $viewModel
     */
    public function __construct(ListDataProvider $dataProvider, AssocEntityRepo $entityRepo, ViewModel $viewModel)
    {
        $this->viewModel = $viewModel;
        $this->dataProvider = $dataProvider;
        $this->entityRepo = $entityRepo;
    }

    /**
     * @param $request []
     * @return ViewModel
     */
    public function loadRecords(array $request)
    {
        $this->viewModel->dataProvider = $this->dataProvider->get($request);
        $this->viewModel->products = $this->entityRepo->getList(Product::tableName(), ['id', 'name']);
        $this->viewModel->users = $this->entityRepo->getList(User::tableName(), ['id', 'firstName', 'lastName']);
        $this->viewModel->order = new Order();

        return $this->viewModel;
    }

    /**
     * @return static
     */
    public static function instance()
    {
        $viewModel = new ViewModel();
        /**
         * Filters can be added in runtime without modifying ListDataProvider.
         * This could be implemented via Chain of commands pattern as well.
         */
        $filters = [
            /**
             * @todo Passing in ViewModel is not the best idea.
             */
            new DaysPastFilter($viewModel),
            new IntegerColumnFilter('productId', $viewModel),
            new IntegerColumnFilter('userId', $viewModel),
        ];

        return new static(new ListDataProvider(new ActiveQuery('app\models\Order'), $filters),
            new AssocEntityRepo(new Query()), $viewModel);
    }
}