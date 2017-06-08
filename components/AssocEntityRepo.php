<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 16:12
 */

namespace app\components;

use yii\db\ActiveQuery;
use yii\db\Query;

class AssocEntityRepo
{
    /**
     * @var ActiveQuery
     */
    private $activeQuery;

    /**
     * EntityRepo constructor.
     * @param Query $activeQuery
     */
    public function __construct(Query $activeQuery)
    {
        $this->activeQuery = $activeQuery;
    }

    public function getList($entityName, $columns = ['*'])
    {
        return $this->activeQuery->select($columns)
            ->from($entityName)
            ->all();
    }
    public function getById($entityName, $id, $columns = ['*'])
    {
        return $this->activeQuery->select($columns)
            ->from($entityName)
            ->where(['id' => $id])
            ->one();
    }
}