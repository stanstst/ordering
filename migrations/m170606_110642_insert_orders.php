<?php

use yii\db\Migration;

class m170606_110642_insert_orders extends Migration
{
    public function safeUp()
    {
        $this->batchInsert('order', ['id', 'userId', 'productId', 'productPrice', 'quantity', 'totalPrice', 'dateCreated'], [
            [1, 1, 1, 1.50, 3, 4.50, '2017-06-06 14:22:12'],
            [2, 2, 2, 1.80, 2, 3.60, '2017-06-06 14:22:12'],
            [3, 3, 3, 1.80, 4, 6.40, '2017-06-06 14:22:12'],
            [4, 3, 3, 1.80, 4, 6.40, '2017-05-06 14:22:12'],
            [5, 3, 3, 1.80, 4, 6.40, '2017-05-06 14:22:12'],
        ]);
    }

    public function safeDown()
    {
        $this->delete('order', ['in', 'id', [1, 2, 3]]);
    }
}
