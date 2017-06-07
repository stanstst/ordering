<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 11:44
 */

namespace app\domain\order;

use yii\db\ActiveQuery;

interface ListFilter
{
    const LIST_FILTER_REQUEST_KEY = 'listFilter';

    /**
     * @param ActiveQuery $activeQuery
     * @param [] $request
     * @return mixed
     */
    public function apply(ActiveQuery $activeQuery, array $request);
}