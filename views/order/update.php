<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $order app\models\Order */
$this->title = 'Update Order: ' . $order->id;
$this->params['breadcrumbs'][] = ['label' => 'Orders', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $order->id, 'url' => ['view', 'id' => $order->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="order-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $order,
        'products' => $products,
        'users' => $users,
    ]) ?>

</div>
