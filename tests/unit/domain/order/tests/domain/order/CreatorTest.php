<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 12:12
 */

namespace tests\domain\order;

use app\components\AssocEntityRepo;
use app\components\EntityPersistor;
use app\domain\order\Creator;
use app\domain\order\PriceCalculatorDiscountProductQuantity;
use app\models\Order;
use app\models\Product;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
    private $orderRequestAttributes = [
        'userId' => 1,
        'productId' => 1,
        'quantity' => 5,
    ];

    /**
     * @var Creator
     */
    private $object;
    /**
     * @var EntityPersistor | \PHPUnit_Framework_MockObject_MockObject
     */
    private $persistorMock;
    /**
     * @var AssocEntityRepo | \PHPUnit_Framework_MockObject_MockObject
     */
    private $entityRepo;
    /**
     * @var PriceCalculatorDiscountProductQuantity | \PHPUnit_Framework_MockObject_MockObject
     */
    private $priceCalculatorMock;

    public function setUp()
    {
        parent::setUp();

        $this->persistorMock = $this->getMockBuilder(EntityPersistor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->entityRepo = $this->getMockBuilder(AssocEntityRepo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->priceCalculatorMock = $this->getMockBuilder(PriceCalculatorDiscountProductQuantity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->object = new Creator($this->persistorMock, $this->entityRepo, $this->priceCalculatorMock);
    }

    /**
     * @test
     */
    public function test1()
    {
        $productPrice = 1.1;
        $orderCalculatedAttributes = [
            'productPrice' => $productPrice,
            'totalPrice' => 5.5

        ];
        $this->entityRepo->expects($this->once())
            ->method('getById')
            ->with(Product::class, $this->orderRequestAttributes['productId'])
            ->willReturn(['price' => $productPrice]);

        $this->priceCalculatorMock->expects($this->once())
            ->method('get')
            ->willReturn(5.5);

        $expectedOrderAttributes = array_merge($this->orderRequestAttributes, $orderCalculatedAttributes);
        $expectedOrder = new Order();
        $expectedOrder->setAttributes($expectedOrderAttributes);

        $this->persistorMock->expects($this->once())
            ->method('save')
            ->with($expectedOrder);

        $this->object->create($this->orderRequestAttributes);
    }
}
