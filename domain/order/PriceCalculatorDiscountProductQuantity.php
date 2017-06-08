<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 16:27
 */

namespace app\domain\order;

/**
 * Class PriceCalculatorDiscountProductQuantity
 * @package app\domain\order
 *
 * Calculates totalPrice applying specific discount.
 * Method get() can be pulled up in a base class (PriceCalculator) having abstract method calculateDiscount().
 * Different classes can be extended (template pattern)
 * and chained in a calculator chain, each applying specific discounts, chain-of-command.
 */
class PriceCalculatorDiscountProductQuantity
{
    /**
     * @var array
     */
    private $configDiscount;
    /**
     * @var float
     */
    private $discount = 0;
    /**
     * @var []
     */
    private $productAssoc;
    /**
     * @var int
     */
    private $quantity;

    /**
     * PriceCalculatorDiscountProductQuantity constructor.
     * @param array $configDiscount ex: productId: 33, [33 => ['quantity' => 3, 'percent' => 20];
     */
    public function __construct(array $configDiscount)
    {
        $this->configDiscount = $configDiscount;
    }

    /**
     * @param $productAssoc
     * @param $quantity
     * @return float
     */
    public function get($productAssoc, $quantity)
    {
        $this->productAssoc = $productAssoc;
        $this->quantity = $quantity;
        $this->calculateDiscount();
        $grossPrice = $productAssoc['price'] * $quantity;
        return $grossPrice - ($grossPrice * $this->discount);
    }

    private function calculateDiscount()
    {
        if (array_key_exists($this->productAssoc['id'], $this->configDiscount) &&
            $this->quantity >= $this->configDiscount[$this->productAssoc['id']]['quantity']
        ) {
            $this->discount = $this->configDiscount[$this->productAssoc['id']]['percent'] / 100;
        }
    }
}