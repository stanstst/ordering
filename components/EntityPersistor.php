<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 08/06/17
 * Time: 12:10
 */

namespace app\components;

use yii\db\ActiveRecord;

class EntityPersistor
{

    /**
     * @param ActiveRecord $record
     * @return bool
     */
    public function save(ActiveRecord $record)
    {
        $record->save();
        return $record->hasErrors();
    }
}