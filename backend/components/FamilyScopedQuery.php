<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\db\ActiveQuery;

/**
 * ActiveQuery for tables with a family_id column.
 *
 * @template T of \yii\db\ActiveRecord
 * @extends ActiveQuery<T>
 */
class FamilyScopedQuery extends ActiveQuery
{
    /**
     * Restricts the query to one family (default: the authenticated user's family).
     */
    public function forFamily(?int $familyId = null): static
    {
        $familyId ??= Yii::$app->get('familyContext')->getId();
        [, $alias] = $this->getTableNameAndAlias();

        return $this->andWhere(["$alias.family_id" => $familyId]);
    }
}
