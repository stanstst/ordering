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
     * Creator constructor.
     * @param EntityPersistor $entityPersistor
     * @param AssocEntityRepo $entityRepo
     * @param PriceCalculatorDiscountProductQuantity $priceCalculator
     */
    public function __construct(
        EntityPersistor $entityPersistor,
        AssocEntityRepo $entityRepo,
        PriceCalculatorDiscountProductQuantity $priceCalculator
    ) {
        $this->entityPersistor = $entityPersistor;
        $this->order = new Order();
        $this->entityRepo = $entityRepo;
        $this->priceCalculator = $priceCalculator;
    }

    public function create($request)
    {
        $this->request = $request;
        $this->setOrderAttributes();
        $this->entityPersistor->save($this->order);
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

    public static function instance()
    {
        /**
         * Could be done via DIC
         */
        $configDiscount = [3 => ['quantity' => 3, 'percent' => 20]];

        return new static(new EntityPersistor(), new AssocEntityRepo(new Query()),
            new PriceCalculatorDiscountProductQuantity($configDiscount));
    }

}