<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\TaskOccurrence;
use app\services\TaskOccurrenceService;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Task occurrences: children check/submit their own, parents review.
 */
class OccurrenceController extends ApiController
{
    /**
     * GET /occurrences?date=&child_id=&status= (parents). Without date: today,
     * unless a status is given (e.g. all `submitted` waiting for approval).
     *
     * @return list<array<string, mixed>>
     */
    public function actionIndex(): array
    {
        $context = $this->context();
        $context->requireParent();
        $request = Yii::$app->request;

        $query = TaskOccurrence::find()->alias('o')->forFamily($context->getId())
            ->with(['task.checklistItems', 'child'])
            ->orderBy(['o.due_date' => SORT_DESC, 'o.submitted_at' => SORT_ASC, 'o.id' => SORT_ASC])
            ->limit(500);
        $date = $request->get('date');
        $status = $request->get('status');
        if ($date !== null || $status === null) {
            $query->andWhere(['o.due_date' => $date ?? Yii::$app->get('clock')->today()]);
        }
        if ($status !== null) {
            $query->andWhere(['o.status' => explode(',', (string) $status)]);
        }
        if (($childId = $request->get('child_id')) !== null) {
            $query->andWhere(['o.child_id' => (int) $childId]);
        }

        return array_map(fn (TaskOccurrence $o): array => $o->toArray([], ['child']), $query->all());
    }

    /** PATCH /occurrences/{id}/checklist {item_id, done} (child, own occurrence) */
    public function actionChecklist(string $id): TaskOccurrence
    {
        $occurrence = $this->findForChild($id);
        $request = Yii::$app->request;
        return $this->service()->setChecklistItem(
            $occurrence,
            (int) $request->getBodyParam('item_id'),
            (bool) $request->getBodyParam('done', true),
        );
    }

    /**
     * POST /occurrences/{id}/submit (child, own occurrence)
     *
     * @return array<string, mixed>
     */
    public function actionSubmit(string $id): array
    {
        return $this->service()->submit($this->findForChild($id))->toArray();
    }

    /**
     * POST /occurrences/{id}/approve {note?} (parents)
     *
     * @return array<string, mixed>
     */
    public function actionApprove(string $id): array
    {
        $occurrence = $this->findForParent($id);
        return $this->service()->approve($occurrence, $this->parentId(), $this->note())->toArray();
    }

    /** POST /occurrences/{id}/reject {note?} (parents) */
    public function actionReject(string $id): TaskOccurrence
    {
        return $this->service()->reject($this->findForParent($id), $this->parentId(), $this->note());
    }

    /**
     * POST /occurrences/bulk-approve {ids: []} (parents). Not-submitted ids are skipped.
     *
     * @return array{approved: list<int>, skipped: list<int>}
     */
    public function actionBulkApprove(): array
    {
        $context = $this->context();
        $context->requireParent();
        $ids = Yii::$app->request->getBodyParam('ids');
        if (!is_array($ids) || $ids === [] || count($ids) > 200) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'ids must be a non-empty list (max 200).', 422, 'ids');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $occurrences = TaskOccurrence::find()->forFamily($context->getId())->andWhere(['id' => $ids])->indexBy('id')->all();

        $approved = $skipped = [];
        $service = $this->service();
        foreach ($ids as $id) {
            $occurrence = $occurrences[$id] ?? null;
            if ($occurrence === null || $occurrence->status !== TaskOccurrence::STATUS_SUBMITTED) {
                $skipped[] = $id;
                continue;
            }
            try {
                $service->approve($occurrence, $this->parentId(), null);
                $approved[] = $id;
            } catch (ApiException) {
                $skipped[] = $id;
            }
        }
        return ['approved' => $approved, 'skipped' => $skipped];
    }

    private function findForChild(string $id): TaskOccurrence
    {
        $context = $this->context();
        $context->requireRole(FamilyContext::ROLE_CHILD);
        $child = $context->getChild();
        $occurrence = TaskOccurrence::find()->forFamily($context->getId())
            ->andWhere(['id' => (int) $id, 'child_id' => $child?->id])->one();
        if (!$occurrence instanceof TaskOccurrence) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        return $occurrence;
    }

    private function findForParent(string $id): TaskOccurrence
    {
        $context = $this->context();
        $context->requireParent();
        $occurrence = TaskOccurrence::find()->forFamily($context->getId())->andWhere(['id' => (int) $id])->one();
        if (!$occurrence instanceof TaskOccurrence) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        return $occurrence;
    }

    private function note(): ?string
    {
        $note = Yii::$app->request->getBodyParam('note');
        if ($note === null || $note === '') {
            return null;
        }
        if (!is_string($note) || mb_strlen($note) > 255) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Note must be at most 255 characters.', 422, 'note');
        }
        return trim($note);
    }

    private function parentId(): int
    {
        return (int) $this->context()->getMember()->user_id;
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }

    private function service(): TaskOccurrenceService
    {
        return TaskOccurrenceService::create();
    }
}
