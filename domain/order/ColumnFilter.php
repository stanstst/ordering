<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 18:38
 */

namespace app\domain\order;

use yii\db\ActiveQuery;

class ColumnFilter implements ListFilter
{

    /**
     * @var string
     */
    private $columnName;

    /**
     * ColumnFilter constructor.
     * @param string $columnName
     */
    public function __construct($columnName)
    {
        $this->columnName = $columnName;
    }

    /**
     * @param ActiveQuery $activeQuery
     * @param [] $request
     * @return mixed
     */
    public function apply(ActiveQuery $activeQuery, array $request)
    {
        if (!$this->isValidRequest($request)) return;

        $filterValue = $request[self::LIST_FILTER_REQUEST_KEY][$this->columnName];
        $activeQuery->andWhere([$this->columnName => $filterValue]);
    }

    private function isValidRequest($request)
    {
        $valueExists = array_key_exists(self::LIST_FILTER_REQUEST_KEY, $request) &&
            array_key_exists($this->columnName, $request[self::LIST_FILTER_REQUEST_KEY]);

        return $valueExists && is_numeric($request[self::LIST_FILTER_REQUEST_KEY][$this->columnName]);
    }
}