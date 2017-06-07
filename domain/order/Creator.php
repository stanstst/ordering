<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 17:03
 */

namespace app\domain\order;

use app\components\AssocEntityRepo;
use app\models\Product;
use app\models\User;
use yii\db\ActiveQuery;
use yii\db\Query;

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
     * @var AssocEntityRepo
     */
    private $entityRepo;

    /**
     * Creator constructor.
     * @param ListDataProvider $dataProvider
     * @param AssocEntityRepo $entityRepo
     */
    public function __construct(ListDataProvider $dataProvider, AssocEntityRepo $entityRepo)
    {
        $this->viewModel = new ViewModel();
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

        return $this->viewModel;
    }

    /**
     * @return static
     */
    public static function instance()
    {
        /**
         * Filters can be added in runtime without modifying ListDataProvider.
         * This could be implemented via Chain of commands pattern as well.
         */
        $filters = [
            new DaysPastFilter(),
            new ColumnFilter('productId'),
            new ColumnFilter('userId'),
        ];

        return new static(new ListDataProvider(new ActiveQuery('app\models\Order'), $filters),
            new AssocEntityRepo(new Query()));
    }
}