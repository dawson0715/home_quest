<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\Badge;
use app\models\ChildProfile;
use Yii;
use yii\db\Query;

/**
 * Badge catalog with the earned date for one child.
 */
class BadgeController extends ApiController
{
    /**
     * GET /badges (child: own) · GET /badges?child_id= (parents)
     *
     * @return list<array{code: string, icon: string, earned_at: string|null}>
     */
    public function actionIndex(): array
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $child = $context->getChild();
        if ($child === null) {
            $context->requireParent();
            $child = ChildProfile::find()->forFamily($context->getId())
                ->andWhere(['id' => (int) Yii::$app->request->get('child_id')])->one();
            if (!$child instanceof ChildProfile) {
                throw new ApiException(ErrorCode::VALIDATION_FAILED, 'child_id is required.', 422, 'child_id');
            }
        }
        $earned = (new Query())->select(['earned_at', 'badge_id'])->from('{{%child_badge}}')
            ->where(['child_id' => $child->id])->indexBy('badge_id')->column();

        return array_map(fn (Badge $b): array => [
            'code' => $b->code,
            'icon' => $b->icon,
            'earned_at' => $earned[$b->id] ?? null,
        ], Badge::find()->orderBy(['sort_order' => SORT_ASC])->all());
    }
}
