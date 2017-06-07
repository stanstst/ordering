<?php

use app\domain\order\DaysPastFilter;
use app\domain\order\ListFilter;
use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $products [] */
/* @var $users [] */

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
        echo Html::label('Date Created: ', 'dateCreated-select');
        echo Html::dropDownList($daysFieldName, 'any', $daysOptions,['id' => 'dateCreated-select']);
        ?>
        <?php $productFieldName =
            ListFilter::LIST_FILTER_REQUEST_KEY . '[productId]';

        $productOptions = ['any' => 'Any'];
        foreach ($products as $product) {
            $productOptions[$product['id']] = $product['name'];
        }
        echo Html::label('Product: ', 'product-select');
        echo Html::dropDownList($productFieldName, 'any', $productOptions, ['id' => 'product-select']);
        ?>

        <?php $userFieldName =
            ListFilter::LIST_FILTER_REQUEST_KEY . '[userId]';

        $userOptions = ['any' => 'Any'];
        foreach ($users as $user) {
            $userOptions[$user['id']] = $user['firstName'] . ' ' . $user['lastName'];
        }
        echo Html::label('User: ', 'user-select');
        echo Html::dropDownList($userFieldName, 'any', $userOptions, ['id' => 'user-select']);
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
