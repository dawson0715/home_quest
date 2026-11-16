<?php

declare(strict_types=1);

namespace app\models;

use app\components\FamilyScopedQuery;
use Yii;
use yii\base\InvalidCallException;
use yii\db\ActiveRecord;

/**
 * Base for every domain table with a family_id column.
 *
 * - find() returns a FamilyScopedQuery: always chain ->forFamily() in controllers/services.
 * - New records get family_id from the authenticated user's family when not set.
 * - family_id is never mass-assignable (do not add it to rules()) and cannot change.
 * - On insert, unset attributes get the DB column defaults.
 *
 * @property int $family_id
 */
abstract class FamilyScopedRecord extends ActiveRecord
{
    /**
     * @return FamilyScopedQuery<static>
     */
    public static function find(): FamilyScopedQuery
    {
        /** @var FamilyScopedQuery<static> $query */
        $query = new FamilyScopedQuery(static::class);
        return $query;
    }

    public function beforeValidate(): bool
    {
        if ($this->isNewRecord && empty($this->family_id) && Yii::$app->has('familyContext')) {
            $context = Yii::$app->get('familyContext');
            if ($context->isAvailable()) {
                $this->family_id = $context->getId();
            }
        }
        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        if ($insert) {
            // DB defaults (e.g. level = 1) must be visible in the API response after create
            $this->loadDefaultValues(true);
        }
        if (!$insert && $this->isAttributeChanged('family_id')) {
            throw new InvalidCallException('family_id cannot be changed.');
        }
        return parent::beforeSave($insert);
    }
}
