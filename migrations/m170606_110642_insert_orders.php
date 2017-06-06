<?php

use yii\db\Migration;

class m170606_110642_insert_orders extends Migration
{
    public function safeUp()
    {
        $this->batchInsert('order', ['id', 'userId', 'productId', 'productPrice', 'quantity', 'totalPrice'], [
            [1, 1, 1, 1.50, 3, 4.50],
            [2, 2, 2, 1.80, 2, 3.60],
            [3, 3, 3, 1.80, 4, 6.40],
        ]);
    }

    public function safeDown()
    {
        $this->delete('order', ['in', 'id', [1, 2, 3]]);
    }
}
