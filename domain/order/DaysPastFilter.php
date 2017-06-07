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
     * @var ViewModel
     */
    private $viewModel;

    /**
     * DaysPastFilter constructor.
     * @param ViewModel $viewModel
     */
    public function __construct(ViewModel $viewModel)
    {
        $this->viewModel = $viewModel;
    }

    /**
     * @param ActiveQuery $activeQuery
     * @param [] $request
     * @return mixed
     */
    public function apply(ActiveQuery $activeQuery, array $request)
    {
        if (!$this->isValidRequest($request)) {
            return;
        }

        $days = (integer)$request[self::LIST_FILTER_REQUEST_KEY][self::FILTER_KEY];
        $activeQuery->andWhere('(DATEDIFF(CURDATE(), dateCreated)) <= ' . $days);
        $this->viewModel->filtersState[self::FILTER_KEY] = $days;
    }

    /**
     * @param array $request
     * @return bool
     */
    private function isValidRequest(array $request)
    {
        $valueExists = array_key_exists(self::LIST_FILTER_REQUEST_KEY, $request) &&
            array_key_exists(self::FILTER_KEY, $request[self::LIST_FILTER_REQUEST_KEY]);

        return $valueExists && is_numeric($request[self::LIST_FILTER_REQUEST_KEY][self::FILTER_KEY]);
    }
}