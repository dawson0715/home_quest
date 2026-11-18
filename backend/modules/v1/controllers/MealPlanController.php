<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\ChildProfile;
use app\models\MealEntry;
use app\models\MealPlanWeek;
use app\services\MealPlanService;
use app\services\MenuSuggestionService;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Weekly meal planner, templates, votes and children's proposals.
 */
class MealPlanController extends ApiController
{
    /**
     * GET /meal-plans/{week} (parents and children; created on first access)
     *
     * @return array<string, mixed>
     */
    public function actionView(string $week): array
    {
        $context = $this->context();
        $service = $this->service();
        return $service->serialize($service->getOrCreateWeek($week), MealPlanService::voterOf($context));
    }

    /**
     * PATCH /meal-plans/{week} {notes}
     *
     * @return array<string, mixed>
     */
    public function actionUpdate(string $week): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $plan = $service->getOrCreateWeek($week);
        $notes = Yii::$app->request->getBodyParam('notes');
        $plan->notes = $notes === null ? null : (string) $notes;
        if (!$plan->validate(['notes'])) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, (string) $plan->getFirstError('notes'), 422, 'notes');
        }
        $plan->save(false, ['notes', 'updated_at']);
        return $service->serialize($plan, MealPlanService::voterOf($this->context()));
    }

    /**
     * PUT /meal-plans/{week}/slots/{day}/{meal} {servings?, entries: [{id?, recipe_id?, free_text?}]}
     *
     * @return array<string, mixed>
     */
    public function actionSlot(string $week, int $day, string $meal): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $plan = $service->getOrCreateWeek($week);
        $service->putSlot($plan, $day, $meal, (array) Yii::$app->request->getBodyParams());
        return $this->slotJson($service, $plan, $day, $meal);
    }

    /**
     * POST /meal-plans/{week}/copy-from {source_week_start | template_id}
     *
     * @return array<string, mixed>
     */
    public function actionCopyFrom(string $week): array
    {
        $context = $this->context();
        $context->requireParent();
        $service = $this->service();
        $target = $service->getOrCreateWeek($week);
        $body = Yii::$app->request;
        if (($templateId = $body->getBodyParam('template_id')) !== null) {
            $source = MealPlanWeek::find()->forFamily($context->getId())->andWhere(['id' => (int) $templateId, 'is_template' => true])->one();
        } else {
            $sourceStart = (string) $body->getBodyParam('source_week_start', '');
            $service->assertWeekStart($sourceStart);
            $source = MealPlanWeek::find()->forFamily($context->getId())->andWhere(['week_start' => $sourceStart, 'is_template' => false])->one();
        }
        if (!$source instanceof MealPlanWeek) {
            throw new NotFoundHttpException('Source week not found.');
        }
        $service->copy($source, $target);
        return $service->serialize($target, MealPlanService::voterOf($context));
    }

    /**
     * POST /meal-plans/{week}/save-as-template {name} → 201 {id, name}
     *
     * @return array{id: int, name: string|null}
     */
    public function actionSaveAsTemplate(string $week): array
    {
        $this->context()->requireParent();
        $service = $this->service();
        $template = $service->saveAsTemplate($service->getOrCreateWeek($week), (string) Yii::$app->request->getBodyParam('name', ''));
        Yii::$app->response->statusCode = 201;
        return ['id' => (int) $template->id, 'name' => $template->template_name];
    }

    /**
     * POST /meal-plans/{week}/proposals {day, meal_type, recipe_id?|free_text} (children) → 201 entry
     *
     * @return array<string, mixed>
     */
    public function actionPropose(string $week): array
    {
        $context = $this->context();
        $context->requireRole(FamilyContext::ROLE_CHILD);
        $service = $this->service();
        /** @var ChildProfile $child */
        $child = $context->getChild();
        $entry = $service->propose($service->getOrCreateWeek($week), $child, (array) Yii::$app->request->getBodyParams());
        Yii::$app->response->statusCode = 201;
        return $service->entryJson($entry);
    }

    /**
     * POST /meal-plans/{week}/suggest {note?} (parents) → preview of dishes for the empty slots, nothing saved
     *
     * @return array{source: string, fallback_reason: string|null, suggestions: list<array<string, mixed>>}
     */
    public function actionSuggest(string $week): array
    {
        $context = $this->context();
        $context->requireParent();
        $plan = $this->service()->getOrCreateWeek($week);
        $note = Yii::$app->request->getBodyParam('note');
        if ($note !== null && !is_string($note)) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'note must be a string.', 422, 'note');
        }
        return MenuSuggestionService::create($context->getFamily())->suggest($plan, (string) $note);
    }

    /**
     * POST /meal-entries/{id}/vote {value: 1|-1|0} (parents and children)
     *
     * @return array<string, mixed>
     */
    public function actionVote(int $id): array
    {
        $context = $this->context();
        $service = $this->service();
        $entry = $service->findEntry($id);
        $voter = MealPlanService::voterOf($context);
        $value = Yii::$app->request->getBodyParam('value');
        $service->vote($entry, $voter, is_numeric($value) ? (int) $value : 99);
        return $this->entryWithVotes($service, $entry, $voter);
    }

    /**
     * POST /meal-entries/{id}/accept|reject (parents)
     *
     * @return array<string, mixed>
     */
    public function actionReview(int $id, string $decision): array
    {
        $context = $this->context();
        $context->requireParent();
        $service = $this->service();
        $entry = $service->findEntry($id);
        $service->review($entry, $decision === 'accept');
        return $this->entryWithVotes($service, $entry, MealPlanService::voterOf($context));
    }

    /**
     * GET /meal-templates
     *
     * @return list<array{id: int, name: string|null, dishes: int}>
     */
    public function actionTemplates(): array
    {
        $context = $this->context();
        $context->requireParent();
        $templates = MealPlanWeek::find()->forFamily($context->getId())->andWhere(['is_template' => true])->orderBy(['template_name' => SORT_ASC])->all();
        return array_map(fn (MealPlanWeek $t): array => [
            'id' => (int) $t->id,
            'name' => $t->template_name,
            'dishes' => (int) MealEntry::find()->innerJoinWith('slot s', false)->where(['s.week_id' => $t->id])->count(),
        ], $templates);
    }

    /** DELETE /meal-templates/{id} */
    public function actionDeleteTemplate(int $id): void
    {
        $context = $this->context();
        $context->requireParent();
        $template = MealPlanWeek::find()->forFamily($context->getId())->andWhere(['id' => $id, 'is_template' => true])->one();
        if (!$template instanceof MealPlanWeek) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        $template->delete();
        Yii::$app->response->statusCode = 204;
    }

    /**
     * @return array<string, mixed>
     */
    private function slotJson(MealPlanService $service, MealPlanWeek $plan, int $day, string $meal): array
    {
        foreach ($service->serialize($plan, MealPlanService::voterOf($this->context()))['slots'] as $slot) {
            if ($slot['day_index'] === $day && $slot['meal_type'] === $meal) {
                return $slot;
            }
        }
        // meal type disabled in settings: still return the saved slot
        return ['day_index' => $day, 'meal_type' => $meal];
    }

    /**
     * @param array{type: string, id: int} $voter
     * @return array<string, mixed>
     */
    private function entryWithVotes(MealPlanService $service, MealEntry $entry, array $voter): array
    {
        $entry->refresh();
        $week = $entry->slot->week;
        foreach ($service->serialize($week, $voter, true)['slots'] as $slot) {
            foreach ($slot['entries'] as $json) {
                if ($json['id'] === (int) $entry->id) {
                    return $json;
                }
            }
        }
        return $service->entryJson($entry);
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }

    private function service(): MealPlanService
    {
        return new MealPlanService($this->context()->getFamily());
    }
}
