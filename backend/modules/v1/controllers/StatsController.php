<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\FamilyContext;
use app\controllers\ApiController;
use app\services\StatsService;
use Yii;

/**
 * GET /stats?from=&to=&child_id= (parents). Default: the last 7 days.
 */
class StatsController extends ApiController
{
    /**
     * @return array{from: string, to: string, children: list<array<string, mixed>>}
     */
    public function actionIndex(): array
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $context->requireParent();
        $request = Yii::$app->request;
        $today = Yii::$app->get('clock')->today();
        $to = (string) $request->get('to', $today);
        $from = (string) $request->get('from', (new \DateTimeImmutable($to))->modify('-6 days')->format('Y-m-d'));
        $childId = $request->get('child_id');

        return (new StatsService($context->getId()))->compute($from, $to, $childId === null ? null : (int) $childId);
    }
}
