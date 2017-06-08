<?php
use app\domain\order\DaysPastFilter;
use app\domain\order\ListFilter;
use app\views\order\helpers\DropDown;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $products [] */
/* @var $users [] */
/* @var $filtersState [] */

/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 20:52
 */
?>

<?= Html::beginForm(Url::to(['order/index']), 'get'); ?>
<?php $daysFieldName =
    ListFilter::LIST_FILTER_REQUEST_KEY . '[' . DaysPastFilter::FILTER_KEY . ']';

$daysOptions = [
    'any' => 'Any',
    7 => '7 days ago',
    0 => 'Today',
];
$selected = array_key_exists(DaysPastFilter::FILTER_KEY,
    $filtersState) ? $filtersState[DaysPastFilter::FILTER_KEY] : 'any';
echo Html::label('Date Created: ', 'dateCreated-select');
echo Html::dropDownList($daysFieldName, $selected, $daysOptions, ['id' => 'dateCreated-select']);
?>
<?php $productFieldName =
    ListFilter::LIST_FILTER_REQUEST_KEY . '[productId]';

$productOptions = array_merge(['any' => 'Any'], DropDown::getProductOptions($products));
foreach ($products as $product) {
    $productOptions[$product['id']] = $product['name'];
}
$selected = array_key_exists('productId',
    $filtersState) ? $filtersState['productId'] : 'any';
echo Html::label('Product: ', 'product-select');
echo Html::dropDownList($productFieldName, $selected, $productOptions, ['id' => 'product-select']);
?>

<?php $userFieldName =
    ListFilter::LIST_FILTER_REQUEST_KEY . '[userId]';

$userOptions = array_merge(['any' => 'Any'], DropDown::getUserOptions($users));
foreach ($users as $user) {
    $userOptions[$user['id']] = $user['firstName'] . ' ' . $user['lastName'];
}
$selected = array_key_exists('userId',
    $filtersState) ? $filtersState['userId'] : 'any';
echo Html::label('User: ', 'user-select');
echo Html::dropDownList($userFieldName, $selected, $userOptions, ['id' => 'user-select']);
?>

<?= Html::submitButton('Filter') ?>
<?= Html::endForm() ?>
