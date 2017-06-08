<?php

use yii\db\Migration;

class m170606_105521_create_order extends Migration
{
    public function safeUp()
    {
        $this->createTable('order', [
            'id' => $this->primaryKey(),
            'userId' => $this->integer()->notNull(),
            'productId' => $this->integer()->notNull(),
            'productPrice' => $this->decimal(7, 2)->notNull(),
            'quantity' => $this->integer()->notNull(),
            'totalPrice'=> $this->decimal(7, 2)->notNull(),
            'dateCreated'=>$this->timestamp()->notNull(),
        ]);

        $this->addForeignKey('order_user', 'order', 'userId', 'user', 'id');
        $this->addForeignKey('order_product', 'order', 'productId', 'product', 'id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('order_user', 'order');
        $this->dropForeignKey('order_product', 'order');

        $this->dropTable('order');
    }
}
