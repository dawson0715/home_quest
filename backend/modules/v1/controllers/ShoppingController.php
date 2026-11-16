<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\FavoriteItem;
use app\models\MealPlanWeek;
use app\models\ShoppingList;
use app\services\MealPlanService;
use app\services\ShoppingListService;
use Yii;
use yii\web\Response;

/**
 * Shared shopping lists. Children may read and (un)check items only if their
 * profile has can_check_shopping. Mutations return the whole list with its ETag.
 */
class ShoppingController extends ApiController
{
    /**
     * A 304 is sent as-is (no envelope, no body).
     *
     * @param \yii\base\Action<static> $action
     */
    public function afterAction($action, $result): mixed
    {
        return $result instanceof Response ? $result : parent::afterAction($action, $result);
    }

    /**
     * GET /shopping-lists
     *
     * @return list<array<string, mixed>>
     */
    public function actionIndex(): array
    {
        $this->requireReader();
        $service = $this->service();
        return array_map(fn (ShoppingList $l): array => $service->summary($l), $service->lists());
    }

    /**
     * POST /shopping-lists {name}
     *
     * @return array<string, mixed>
     */
    public function actionCreate(): array
    {
        $this->context()->requireParent();
        $list = new ShoppingList(['name' => Yii::$app->request->getBodyParam('name'), 'is_default' => false]);
        $list->family_id = $this->context()->getId();
        if (!$list->validate()) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, (string) $list->getFirstError('name'), 422, 'name');
        }
        $list->save(false);
        Yii::$app->response->statusCode = 201;
        return $this->respond($list);
    }

    /**
     * GET /shopping-lists/{id} — ETag "v{version}", If-None-Match → 304.
     *
     * @return array<string, mixed>|Response
     */
    public function actionView(int $id): array|Response
    {
        $this->requireReader();
        $list = $this->service()->findList($id);
        $response = Yii::$app->response;
        if (Yii::$app->request->headers->get('If-None-Match') === $list->etag()) {
            $response->statusCode = 304;
            $response->headers->set('ETag', $list->etag());
            $response->format = Response::FORMAT_RAW;
            $response->content = '';
            return $response;
        }
        return $this->respond($list);
    }

    /**
     * PATCH /shopping-lists/{id} {name}
     *
     * @return array<string, mixed>
     */
    public function actionUpdate(int $id): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $list = $service->findList($id);
        $list->name = (string) Yii::$app->request->getBodyParam('name', $list->name);
        if (!$list->validate(['name'])) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, (string) $list->getFirstError('name'), 422, 'name');
        }
        $list->save(false, ['name', 'updated_at']);
        $service->bump($list);
        return $this->respond($list);
    }

    /** DELETE /shopping-lists/{id} (the default list cannot be deleted) */
    public function actionDelete(int $id): void
    {
        $this->context()->requireParent();
        $list = $this->service()->findList($id);
        if ((bool) $list->is_default) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'The default list cannot be deleted.', 422, 'id');
        }
        $list->delete();
        Yii::$app->response->statusCode = 204;
    }

    /**
     * POST /shopping-lists/{id}/generate {week_start} → list + meta {added, updated, removed}
     *
     * @return array<string, mixed>
     */
    public function actionGenerate(int $id): array
    {
        $context = $this->context();
        $context->requireParent();
        $service = $this->service();
        $list = $service->findList($id);
        $weekStart = (string) Yii::$app->request->getBodyParam('week_start', '');
        $week = (new MealPlanService($context->getFamily()))->getOrCreateWeek($weekStart);
        $this->responseMeta = $service->generateFromWeek($list, $week);
        return $this->respond($list);
    }

    /**
     * POST /shopping-lists/{id}/items {label?, ingredient_id?, quantity?, unit?, category_id?, client_uuid?}
     *
     * @return array<string, mixed>
     */
    public function actionAddItem(int $id): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $list = $service->findList($id);
        $service->addItem($list, (array) Yii::$app->request->getBodyParams());
        Yii::$app->response->statusCode = 201;
        return $this->respond($list);
    }

    /**
     * PATCH /shopping-items/{id}
     *
     * @return array<string, mixed>
     */
    public function actionUpdateItem(int $id): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $item = $service->updateItem($service->findItem($id), (array) Yii::$app->request->getBodyParams());
        return $this->respond($service->findList((int) $item->list_id));
    }

    /**
     * DELETE /shopping-items/{id}
     *
     * @return array<string, mixed>
     */
    public function actionDeleteItem(int $id): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $item = $service->findItem($id);
        $listId = (int) $item->list_id;
        $service->deleteItem($item);
        return $this->respond($service->findList($listId));
    }

    /**
     * POST /shopping-items/{id}/check|uncheck
     *
     * @return array<string, mixed>
     */
    public function actionCheck(int $id, string $state): array
    {
        $by = $this->requireReader();
        $service = $this->service();
        $item = $service->setChecked($service->findItem($id), $state === 'check', $by);
        return $this->respond($service->findList((int) $item->list_id));
    }

    /**
     * POST /shopping-lists/{id}/clear-checked
     *
     * @return array<string, mixed>
     */
    public function actionClearChecked(int $id): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $list = $service->findList($id);
        $this->responseMeta = ['removed' => $service->clearChecked($list)];
        return $this->respond($list);
    }

    /**
     * POST /shopping-lists/{id}/sync {ops: [...]} — offline queue, idempotent.
     *
     * @return array<string, mixed>
     */
    public function actionSync(int $id): array
    {
        $by = $this->requireReader();
        $ops = Yii::$app->request->getBodyParam('ops');
        if (!is_array($ops) || array_filter($ops, fn ($op): bool => !is_array($op)) !== []) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'ops must be a list of objects.', 422, 'ops');
        }
        $service = $this->service();
        $list = $service->findList($id);
        /** @var list<array<string, mixed>> $queue */
        $queue = array_values($ops);
        $this->responseMeta = $service->sync($list, $queue, $by, $by['type'] === 'child');
        return $this->respond($list);
    }

    /**
     * PUT|DELETE /shopping-lists/{id}/pantry/{ingredient} — "I have it already"
     *
     * @return array<string, mixed>
     */
    public function actionPantry(int $id, int $ingredient): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $list = $service->findList($id);
        $service->setPantry($list, $ingredient, Yii::$app->request->isPut);
        return $this->respond($list);
    }

    /**
     * PUT /shopping-categories/order {ids: [...]}
     *
     * @return array{ids: list<int>}
     */
    public function actionCategoryOrder(): array
    {
        $this->context()->requireParent();
        $ids = Yii::$app->request->getBodyParam('ids');
        if (!is_array($ids) || $ids === []) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'ids must be a non-empty list.', 422, 'ids');
        }
        $service = $this->service();
        $service->setCategoryOrder($ids);
        return ['ids' => array_keys($service->categoryOrder())];
    }

    /**
     * GET /favorite-items (top 30 by use)
     *
     * @return list<FavoriteItem>
     */
    public function actionFavorites(): array
    {
        $context = $this->context();
        $context->requireParent();
        return FavoriteItem::find()->forFamily($context->getId())->orderBy(['use_count' => SORT_DESC, 'label' => SORT_ASC])->limit(30)->all();
    }

    /**
     * Parents, or children allowed to check the shopping list.
     *
     * @return array{type: string, id: int}
     */
    private function requireReader(): array
    {
        $context = $this->context();
        $child = $context->getChild();
        if ($child !== null) {
            if (!(bool) $child->can_check_shopping) {
                throw new ApiException(ErrorCode::FORBIDDEN, 'Ask a parent to enable the shopping list.', 403);
            }
            return ['type' => 'child', 'id' => (int) $child->id];
        }
        $context->requireParent();
        return ['type' => 'user', 'id' => (int) $context->getMember()->user_id];
    }

    /**
     * @return array<string, mixed>
     */
    private function respond(ShoppingList $list): array
    {
        $list->refresh();
        Yii::$app->response->headers->set('ETag', $list->etag());
        return $this->service()->serialize($list);
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }

    private function service(): ShoppingListService
    {
        return ShoppingListService::for($this->context()->getFamily());
    }
}
