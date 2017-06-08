<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 12:04
 */

namespace app\domain\order;

use app\models\Order;

class Creator
{

    public function __construct()
    {

    }

    public static function instance()
    {
        return new static();
    }
}