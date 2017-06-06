<?php

use yii\db\Migration;

class m170606_102825_insert_users extends Migration
{
    public function safeUp()
    {
        $this->batchInsert('user', ['id', 'firstName', 'lastName'], [
            [1, 'John', 'Doe'],
            [2, 'Stan', 'Stef'],
            [3, 'Tom', 'Smith'],
        ]);
    }

    public function safeDown()
    {
        $this->delete('user', ['in', 'id', [1, 2, 3]]);
    }
}
