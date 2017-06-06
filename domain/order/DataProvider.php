<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 18:14
 */

namespace app\domain\order;

use app\models\Order;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class DataProvider
{
    private $activeQuery;
    /**
     * @var Order
     */
    private $order;

    /**
     * DataProvider constructor.
     * @param ActiveQuery $activeQuery
     */
    public function __construct(ActiveQuery $activeQuery)
    {
        $this->activeQuery = $activeQuery;
    }

    /**
     * @return ActiveDataProvider
     */
    public function get()
    {
        $this->activeQuery->with(['user', 'product'])
            ->andWhere('(DATEDIFF(CURDATE(), dateCreated)) < 7');

        return new ActiveDataProvider([
            'query' => $this->activeQuery,
        ]);

    }
}