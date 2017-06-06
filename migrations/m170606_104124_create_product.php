<?php

use yii\db\Migration;

class m170606_104124_create_product extends Migration
{
    public function safeUp()
    {
        $this->createTable('product', [
            'id' => $this->primaryKey(),
            'name' => $this->string(50),
            'price' => $this->decimal(7, 2),
            'currency' => $this->string(5),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('product');
    }
}
