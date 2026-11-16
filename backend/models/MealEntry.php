<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * A dish in a meal slot: a family recipe or free text. Children's proposals
 * are `proposed` until a parent accepts (planned) or rejects them.
 *
 * @property int $id
 * @property int $slot_id
 * @property int|null $recipe_id
 * @property string|null $free_text
 * @property string $status planned|proposed|rejected
 * @property int|null $proposed_by_child_id
 * @property int $sort_order
 *
 * @property-read MealSlot $slot
 * @property-read Recipe|null $recipe
 * @property-read ChildProfile|null $proposedBy
 */
class MealEntry extends ActiveRecord
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_REJECTED = 'rejected';

    public static function tableName(): string
    {
        return '{{%meal_entry}}';
    }

    public function getSlot(): ActiveQuery
    {
        return $this->hasOne(MealSlot::class, ['id' => 'slot_id']);
    }

    public function getRecipe(): ActiveQuery
    {
        return $this->hasOne(Recipe::class, ['id' => 'recipe_id']);
    }

    public function getProposedBy(): ActiveQuery
    {
        return $this->hasOne(ChildProfile::class, ['id' => 'proposed_by_child_id']);
    }

    public function getTitle(): string
    {
        return $this->recipe->title ?? (string) $this->free_text;
    }
}
