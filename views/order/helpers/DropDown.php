<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 11:14
 */

namespace app\views\order\helpers;

class DropDown
{

    public static function getUserOptions(array $users)
    {
        $userOptions = [];
        foreach ($users as $user) {
            $userOptions[$user['id']] = $user['firstName'] . ' ' . $user['lastName'];
        }
        return $userOptions;
    }

    public static function getProductOptions(array $products)
    {
        $productOptions = [];
        foreach ($products as $product) {
            $productOptions[$product['id']] = $product['name'];
        }
        return $productOptions;
    }
}