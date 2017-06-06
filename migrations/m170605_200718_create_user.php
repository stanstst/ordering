<?php

use yii\db\Migration;

class m170605_200718_create_user extends Migration
{
    public function safeUp()
    {
        $this->createTable('user', [
            'id' => $this->primaryKey(),
            'firstName' => $this->string(50),
            'lastName' => $this->string(50),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('user');
    }
}
