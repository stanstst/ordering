<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 16:35
 */

namespace tests\domain\order;

use app\domain\order\PriceCalculatorDiscountProductQuantity;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    /**
     * @var PriceCalculatorDiscountProductQuantity
     */
    private $object;

    public function setUp()
    {
        parent::setUp();

        $configDiscount = [33 => ['quantity' => 3, 'percent' => 20]];

        $this->object = new PriceCalculatorDiscountProductQuantity($configDiscount);
    }

    /**
     * @test
     */
    public function returnsTotalPriceWithoutDiscount()
    {
        $productAssoc = [
            'id' => 11,
            'price' => 2.2,
        ];
        $quantity = 3;
        $this->assertEquals(6.6, $this->object->get($productAssoc, $quantity));
    }

    /**
     * @test
     */
    public function returnsTotalPriceWithDiscount()
    {
        $productAssoc = [
            'id' => 33,
            'price' => 20,
        ];
        $quantity = 5;
        $expectedDiscountedPrice = 80;
        $this->assertEquals($expectedDiscountedPrice, $this->object->get($productAssoc, $quantity));
    }
}
