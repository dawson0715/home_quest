<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\controllers\ApiActiveController;
use app\models\Recipe;
use app\services\RecipeService;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Family recipe book. Children can list recipes (to propose dishes).
 */
class RecipeController extends ApiActiveController
{
    public $modelClass = Recipe::class;
    protected array $childActions = ['index'];

    /**
     * @return array<string, mixed>
     */
    public function actions(): array
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function verbs(): array
    {
        return parent::verbs() + ['image' => ['POST', 'DELETE']];
    }

    /**
     * GET /recipes?q=&tag=&favorite=1 → summaries, favorites first.
     *
     * @return list<array<string, mixed>>
     */
    public function actionIndex(): array
    {
        $this->checkAccess('index');
        $request = Yii::$app->request;
        $query = Recipe::find()->forFamily()->orderBy(['is_favorite' => SORT_DESC, 'title' => SORT_ASC])->limit(500);
        if (($q = trim((string) $request->get('q', ''))) !== '') {
            $query->andWhere(['like', 'title', $q]);
        }
        if (($tag = $request->get('tag')) !== null) {
            $query->andWhere(new \yii\db\Expression('JSON_CONTAINS(tags, :tag)', [':tag' => json_encode((string) $tag)]));
        }
        if ($request->get('favorite')) {
            $query->andWhere(['is_favorite' => true]);
        }
        return array_map(fn (Recipe $r): array => $r->toSummary(), $query->all());
    }

    public function actionCreate(): Recipe|Model
    {
        $this->checkAccess('create');
        $recipe = new Recipe();
        $result = (new RecipeService())->save($recipe, (array) Yii::$app->request->getBodyParams(), $this->userId());
        if ($result instanceof Recipe) {
            Yii::$app->response->statusCode = 201;
        }
        return $result;
    }

    public function actionUpdate(string $id): Recipe|Model
    {
        $this->checkAccess('update');
        /** @var Recipe $recipe */
        $recipe = $this->findFamilyModel($id, $this->action);
        return (new RecipeService())->save($recipe, (array) Yii::$app->request->getBodyParams(), $this->userId());
    }

    public function actionDelete(string $id): void
    {
        $this->checkAccess('delete');
        /** @var Recipe $recipe */
        $recipe = $this->findFamilyModel($id, $this->action);
        (new RecipeService())->delete($recipe);
        Yii::$app->response->statusCode = 204;
    }

    /** POST /recipes/{id}/image (multipart "image") · DELETE removes it. */
    public function actionImage(string $id): Recipe
    {
        $this->checkAccess('image');
        /** @var Recipe $recipe */
        $recipe = $this->findFamilyModel($id, $this->action);
        $service = new RecipeService();
        if (Yii::$app->request->isDelete) {
            return $service->removeImage($recipe);
        }
        return $service->setImage($recipe, UploadedFile::getInstanceByName('image'));
    }

    private function userId(): ?int
    {
        $id = Yii::$app->user->id;
        return is_numeric($id) ? (int) $id : null;
    }
}
