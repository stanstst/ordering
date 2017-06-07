<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 18:14
 */

namespace app\domain\order;

use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class ListDataProvider
{
    /**
     * @var []
     */
    protected $request;
    protected $activeQuery;
    /**
     * @var ListFilter[]
     */
    private $filters;

    /**
     * DataProvider constructor.
     * @param ActiveQuery $activeQuery
     * @param array $filters
     */
    public function __construct(ActiveQuery $activeQuery, array $filters = [])
    {
        $this->activeQuery = $activeQuery;
        $this->filters = $filters;
    }

    /**
     * @param array $request
     * @return ActiveDataProvider
     */
    public function get(array $request)
    {
        $this->request = $request;
        $this->activeQuery->with(['user', 'product']);

        $this->applyFilters();

        return new ActiveDataProvider([
            'query' => $this->activeQuery,
        ]);
    }

    protected function applyFilters()
    {
        foreach ($this->filters as $filter) {
            $filter->apply($this->activeQuery, $this->request);
        }
    }
}