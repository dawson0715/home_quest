<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\controllers\ApiController;
use app\models\TaskTemplate;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Suggested tasks catalog (global, read-only).
 */
class TaskTemplateController extends ApiController
{
    /** GET /task-templates?age=7 */
    public function actionIndex(): ActiveDataProvider
    {
        Yii::$app->get('familyContext')->requireParent();
        $query = TaskTemplate::find()->orderBy(['sort_order' => SORT_ASC]);
        if (($age = Yii::$app->request->get('age')) !== null) {
            $query->andWhere(['<=', 'min_age', (int) $age])->andWhere(['>=', 'max_age', (int) $age]);
        }
        return new ActiveDataProvider(['query' => $query, 'pagination' => false]);
    }
}
