<?php

declare(strict_types=1);

namespace app\forms;

use app\models\Family;
use yii\base\Model;

/**
 * PATCH /family: partial update of name and settings (settings are merged).
 */
class FamilyUpdateForm extends Model
{
    public mixed $name = null;
    public mixed $settings = null;

    public function __construct(private readonly Family $family, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            // type checks only: value rules (length, settings keys) live in Family
            ['name', 'string'],
            ['settings', 'validateIsObject'],
        ];
    }

    public function validateIsObject(string $attribute): void
    {
        if ($this->$attribute !== null && !is_array($this->$attribute)) {
            $this->addError($attribute, 'Settings must be an object.');
        }
    }

    /**
     * Applies the changes to the family; on failure the errors are copied to this form.
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        if ($this->name !== null) {
            $this->family->name = (string) $this->name;
        }
        if (is_array($this->settings)) {
            $this->family->settings = array_merge($this->family->settings ?? [], $this->settings);
        }
        if (!$this->family->save()) {
            $this->addErrors($this->family->getErrors());
            return false;
        }
        return true;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }
}
