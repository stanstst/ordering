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
use app\domain\order\ViewModel;
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
    /**
     * @var ViewModel
     */
    private $view;

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

        $this->view = new ViewModel();
        $this->object = new Creator($this->persistorMock, $this->entityRepo, $this->priceCalculatorMock, $this->view);
    }

    /**
     * @test
     */
    public function setsExpectedOrderAttributesAndCallsSave()
    {
        $productPrice = 1.1;
        $orderCalculatedAttributes = [
            'productPrice' => $productPrice,
            'totalPrice' => 5.5

        ];
        $this->entityRepo->expects($this->once())
            ->method('getById')
            ->with(Product::tableName(), $this->orderRequestAttributes['productId'])
            ->willReturn(['price' => $productPrice]);

        $this->priceCalculatorMock->expects($this->once())
            ->method('get')
            ->willReturn(5.5);

        $expectedOrderAttributes = array_merge($this->orderRequestAttributes, $orderCalculatedAttributes);
        $expectedOrder = new Order();
        $expectedOrder->setAttributes($expectedOrderAttributes);

        $this->persistorMock->expects($this->once())
            ->method('save')
            ->with($expectedOrder)
            ->willReturn(true);

        $this->assertTrue($this->object->create($this->orderRequestAttributes));
    }

    /**
     * @test
     */
    public function returnsFalseIfUnableToSaveOrder()
    {
        $this->persistorMock->expects($this->once())
            ->method('save')
            ->willReturn(false);

        $this->assertFalse($this->object->create($this->orderRequestAttributes));
    }
}
