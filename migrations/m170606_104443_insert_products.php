<?php

use yii\db\Migration;

class m170606_104443_insert_products extends Migration
{
    public function safeUp()
    {
        $this->batchInsert('product', ['id', 'name', 'price', 'currency'], [
            [1, 'Fanta', 1.50, 'EUR'],
            [2, 'Coca Cola', 1.80, 'EUR'],
            [3, 'Pepsi Cola', 1.60, 'EUR'],
        ]);
    }

    public function safeDown()
    {
        $this->delete('product', ['in', 'id', [1, 2, 3]]);
    }
}
