<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 11:51
 */

namespace app\domain\order;

use yii\db\ActiveQuery;

class DaysPastFilter implements ListFilter
{
    const FILTER_KEY = 'daysPast';

    /**
     * DaysPastFilter constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param ActiveQuery $activeQuery
     * @param [] $request
     * @return mixed
     */
    public function apply(ActiveQuery $activeQuery, array $request)
    {
        if (!array_key_exists(self::LIST_FILTER_REQUEST_KEY, $request) ||
            !array_key_exists(self::FILTER_KEY, $request[self::LIST_FILTER_REQUEST_KEY])
        ) {
            return;
        }

        $days = $request[self::LIST_FILTER_REQUEST_KEY][self::FILTER_KEY];
        $activeQuery->andWhere('(DATEDIFF(CURDATE(), dateCreated)) < ' . $days);
    }
}