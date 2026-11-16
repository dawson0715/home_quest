<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\FamilyContext;
use app\controllers\ApiController;
use app\forms\FamilyUpdateForm;
use app\models\Family;
use app\models\FamilyMember;
use app\services\FamilyDataService;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * The authenticated user's family (singleton resource).
 */
class FamilyController extends ApiController
{
    /** Any family actor (parents, devices, children: they need settings such as meal types). */
    public function actionView(): Family
    {
        return $this->context()->getFamily();
    }

    public function actionUpdate(): Family|FamilyUpdateForm
    {
        $context = $this->context();
        $context->requireParent();

        $form = new FamilyUpdateForm($context->getFamily());
        $form->load((array) Yii::$app->request->getBodyParams(), '');

        return $form->save() ? $form->getFamily() : $form;
    }

    /**
     * GET /family/export (admin): all the family's data as JSON (download).
     *
     * @return array<string, mixed>
     */
    public function actionExport(): array
    {
        $context = $this->context();
        $context->requireRole(FamilyMember::ROLE_ADMIN);
        $family = $context->getFamily();
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($family->name)) ?: 'famiglia';
        Yii::$app->response->headers->set('Content-Disposition', "attachment; filename=\"homequest-$slug.json\"");
        return (new FamilyDataService($family))->export();
    }

    /** DELETE /family {confirm_name} (admin): definitive deletion. */
    public function actionDelete(): void
    {
        $context = $this->context();
        $context->requireRole(FamilyMember::ROLE_ADMIN);
        (new FamilyDataService($context->getFamily()))->delete((string) Yii::$app->request->getBodyParam('confirm_name', ''));
        Yii::$app->response->statusCode = 204;
    }

    public function actionMembers(): ActiveDataProvider
    {
        $context = $this->context();
        $context->requireParent();
        $query = FamilyMember::find()
            ->forFamily($context->getId())
            ->with('user')
            ->orderBy(['id' => SORT_ASC]);

        return new ActiveDataProvider(['query' => $query, 'pagination' => false]);
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }
}
