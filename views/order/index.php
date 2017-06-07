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
/* @var $filtersState [] */

$this->title = 'Orders';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="order-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= $this->render('_filter', [
           'products' => $products,
           'users' =>$users,
           'filtersState' => $filtersState,
        ]) ?>
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
