<?php

namespace app\controllers;

use app\domain\order\Creator;
use app\domain\order\ListPanel;
use app\domain\order\Updater;
use app\domain\order\ViewModel;
use Yii;
use app\models\Order;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * OrderController implements the CRUD actions for Order model.
 */
class OrderController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Order models.
     * @return mixed
     */
    public function actionIndex()
    {
        $viewData = ListPanel::instance()
            ->loadRecords(Yii::$app->request->get());
        $creator = Creator::instance($viewData);

        if (Yii::$app->request->post()) {
            if ($creator->create(Yii::$app->request->post()['Order'])) {
                return $this->redirect(array_merge(['index'], Yii::$app->request->get()));
            }
        }

        return $this->render('index', (array)$viewData);
    }

    /**
     * Displays a single Order model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Updates an existing Order model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $viewData = new ViewModel();
        $updater = Updater::instance($viewData);
        $isOrderUpdated = $updater->update($id, Yii::$app->request->post());

        if ($isOrderUpdated) {
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('update', (array)$viewData);
        }
    }

    /**
     * @todo Delete method must be implemented in the Domain namespace. OrderFinder must be shared across Update and Delete actions.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)
            ->delete();

        return $this->redirect(['index']);
    }

    /**
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Order the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Order::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
