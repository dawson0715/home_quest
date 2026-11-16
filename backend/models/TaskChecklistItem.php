<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $task_id
 * @property string $label
 * @property string|null $icon
 * @property int $sort_order
 */
class TaskChecklistItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%task_checklist_item}}';
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [['label', 'icon'], 'trim'],
            ['label', 'required'],
            ['label', 'string', 'max' => 120],
            ['icon', 'string', 'max' => 40],
            ['icon', 'default', 'value' => null],
            ['sort_order', 'integer'],
        ];
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return ['id' => fn (self $i): int => (int) $i->id, 'label', 'icon'];
    }
}
