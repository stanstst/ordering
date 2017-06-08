<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 12:04
 */

namespace app\domain\order;

use app\components\AssocEntityRepo;
use app\components\EntityPersistor;
use app\models\Order;
use app\models\Product;
use yii\db\Exception;
use yii\db\Query;

class Creator
{
    /**
     * @var EntityPersistor
     */
    private $entityPersistor;
    /**
     * @var []
     */
    private $request;
    /**
     * @var AssocEntityRepo
     */
    private $entityRepo;
    /**
     * @var PriceCalculatorDiscountProductQuantity
     */
    private $priceCalculator;
    /**
     * @var ViewModel
     */
    private $viewModel;

    /**
     * @param EntityPersistor $entityPersistor
     * @param AssocEntityRepo $entityRepo
     * @param PriceCalculatorDiscountProductQuantity $priceCalculator
     * @param ViewModel $viewModel
     */
    public function __construct(
        EntityPersistor $entityPersistor,
        AssocEntityRepo $entityRepo,
        PriceCalculatorDiscountProductQuantity $priceCalculator,
        ViewModel $viewModel
    ) {
        $this->entityPersistor = $entityPersistor;
        $this->order = new Order();
        $this->entityRepo = $entityRepo;
        $this->priceCalculator = $priceCalculator;
        $this->viewModel = $viewModel;
    }

    public function create($request)
    {
        $this->request = $request;
        $this->setOrderAttributes();
        $this->viewModel->order = $this->order;

        if (!$this->entityPersistor->save($this->order)) {
            return false;
        }
        return true;
    }

    private function setOrderAttributes()
    {
        $productAssoc = $this->entityRepo->getById(Product::tableName(), $this->request['productId']);
        $additionalAttributes = [
            'productPrice' => $productAssoc['price'],
            'totalPrice' => $this->priceCalculator->get($productAssoc, $this->request['quantity']),
        ];
        $this->order->setAttributes(array_merge($this->request, $additionalAttributes));
    }

    /**
     * @param $viewData
     * @return static
     */
    public static function instance($viewData)
    {
        /**
         * Could be done via DIC
         */
        $configDiscount = [3 => ['quantity' => 3, 'percent' => 20]];

        return new static(new EntityPersistor(), new AssocEntityRepo(new Query()),
            new PriceCalculatorDiscountProductQuantity($configDiscount), $viewData);
    }

}