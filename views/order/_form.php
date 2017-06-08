<?php

use app\views\order\helpers\DropDown;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\Order */
/* @var $form yii\widgets\ActiveForm */
/* @var $users [] */
/* @var $products [] */
?>

<div class="order-form">

    <?php $form = ActiveForm::begin(['method' => 'post']); ?>

    <?= $form->field($model, 'userId')
        ->label('User')
        ->dropDownList(DropDown::getUserOptions($users), ['prompt' => '']) ?>

    <?= $form->field($model, 'productId')
        ->label('Product')
        ->dropDownList(DropDown::getProductOptions($products), ['prompt' => '']) ?>

    <?= $form->field($model, 'quantity')
        ->textInput() ?>


    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update',
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
