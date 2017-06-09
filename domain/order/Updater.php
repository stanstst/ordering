<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 09/06/17
 * Time: 10:46
 */

namespace app\domain\order;

use app\components\AssocEntityRepo;
use app\components\EntityPersistor;
use app\models\Order;
use app\models\Product;
use app\models\User;
use yii\db\Query;
use yii\web\NotFoundHttpException;

class Updater
{
    /**
     * @var AssocEntityRepo
     */
    private $entityRepo;
    /**
     * @var ViewModel
     */
    private $viewModel;
    /**
     * @var int
     */
    private $orderId;
    /**
     * @var Order
     */
    private $order;
    /**
     * @var []
     */
    private $request;
    /**
     * @var PriceCalculatorDiscountProductQuantity
     */
    private $priceCalculator;
    /**
     * @var EntityPersistor
     */
    private $entityPersistor;

    /**
     * Updater constructor.
     * @param AssocEntityRepo $entityRepo
     * @param PriceCalculatorDiscountProductQuantity $priceCalculator
     * @param EntityPersistor $entityPersistor
     * @param ViewModel $viewModel
     */
    public function __construct(
        AssocEntityRepo $entityRepo,
        PriceCalculatorDiscountProductQuantity $priceCalculator,
        EntityPersistor $entityPersistor,
        ViewModel $viewModel
    ) {
        $this->entityRepo = $entityRepo;
        $this->viewModel = $viewModel;
        $this->priceCalculator = $priceCalculator;
        $this->entityPersistor = $entityPersistor;
    }

    /**
     * @param $orderId
     * @param $request
     * @return bool
     */
    public function update($orderId, $request)
    {
        $this->orderId = $orderId;
        $this->viewModel->products = $this->entityRepo->getList(Product::tableName(), ['id', 'name']);
        $this->viewModel->users = $this->entityRepo->getList(User::tableName(), ['id', 'firstName', 'lastName']);
        $this->findOrder();
        $this->viewModel->order = $this->order;

        if (array_key_exists('Order', $request)) {
            $this->request = $request['Order'];
            $this->setOrderAttributes();
            if (!$this->entityPersistor->save($this->order)) {
                return false;
            }
            return true;
        }
        return false;
    }

    private function findOrder()
    {
        $this->order = Order::findOne($this->orderId);
        if (!$this->order) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
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
        $configDiscount = [3 => ['quantity' => 3, 'percent' => 20]];
        return new static(new AssocEntityRepo(new Query()), new PriceCalculatorDiscountProductQuantity($configDiscount),
            new EntityPersistor(), $viewData);
    }

}