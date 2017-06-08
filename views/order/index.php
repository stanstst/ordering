<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $products [] */
/* @var $users [] */
/* @var $filtersState [] */
/* @var $order \app\models\Order */

$this->title = 'Orders';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="order-index">

    <div class="order-create">
        <h3>Create order</h3>
        <?= $this->render('_form', [
            'model' => $order,
            'products' => $products,
            'users' =>$users,
        ]) ?>

    </div>

    <h3>Filter</h3>
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
