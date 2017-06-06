<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 10:43
 */

namespace tests\domain\order;

use \PHPUnit\Framework\TestCase;

use app\domain\order\Creator;

class CreatorTest extends TestCase
{
    public function setUp()
    {
        $order = new Creator();

        parent::setUp();
    }
}
