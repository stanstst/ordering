<?php

use app\domain\order\DaysPastFilter;
use app\domain\order\ListFilter;
use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Orders';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="order-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::beginForm(Url::to(['order/index']), 'get'); ?>
        <?php $daysFieldName =
            ListFilter::LIST_FILTER_REQUEST_KEY . '[' . DaysPastFilter::FILTER_KEY . ']';

        $daysOptions = [
            'any' => 'Any',
            7 => '7 days ago',
            0 => 'Today',
        ];
        echo Html::dropDownList($daysFieldName, 'any', $daysOptions);
        ?>
        <?= Html::submitButton('Filter') ?>
        <?= Html::endForm() ?>
    </p>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'attribute' => 'user',
                'value' => 'user.firstName'
            ],
            [
                'attribute' => 'product',
                'value' => 'product.name'
            ],
            'productPrice',
            'quantity',
            'totalPrice',
            'dateCreated',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
</div>
