<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * A family recipe. Ingredients are written through RecipeService.
 *
 * @property int $id
 * @property int $family_id
 * @property string $title
 * @property string|null $image_path relative to web/ (e.g. uploads/recipes/abc.jpg)
 * @property int $servings
 * @property int|null $prep_minutes
 * @property string|null $instructions
 * @property list<string>|null $tags
 * @property bool|int $is_favorite
 * @property int|null $created_by
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read RecipeIngredient[] $recipeIngredients
 */
class Recipe extends FamilyScopedRecord
{
    public const TAGS = ['veloce', 'vegetariano', 'preferito_bimbi'];

    public static function tableName(): string
    {
        return '{{%recipe}}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            [['title', 'instructions'], 'trim'],
            ['title', 'required'],
            ['title', 'string', 'max' => 120],
            ['servings', 'default', 'value' => 4],
            ['servings', 'integer', 'min' => 1, 'max' => 50],
            ['prep_minutes', 'default', 'value' => null],
            ['prep_minutes', 'integer', 'min' => 1, 'max' => 1440],
            ['instructions', 'default', 'value' => null],
            ['instructions', 'string', 'max' => 10000],
            ['tags', 'default', 'value' => []],
            ['tags', 'validateTags'],
            ['is_favorite', 'default', 'value' => false],
            ['is_favorite', 'boolean'],
            ['is_favorite', 'filter', 'filter' => 'boolval'],
        ];
    }

    public function validateTags(string $attribute): void
    {
        $tags = $this->$attribute;
        if (!is_array($tags) || array_diff($tags, self::TAGS) !== []) {
            $this->addError($attribute, 'Tags must be a subset of: ' . implode(', ', self::TAGS) . '.');
            return;
        }
        $this->$attribute = array_values(array_unique($tags));
    }

    public function getRecipeIngredients(): ActiveQuery
    {
        return $this->hasMany(RecipeIngredient::class, ['recipe_id' => 'id'])
            ->with('ingredient')->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getImageUrl(): ?string
    {
        return $this->image_path === null ? null : '/' . $this->image_path;
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields(): array
    {
        return [
            'id' => fn (self $r): int => (int) $r->id,
            'title',
            'image_url' => fn (self $r): ?string => $r->getImageUrl(),
            'servings' => fn (self $r): int => (int) $r->servings,
            'prep_minutes' => fn (self $r): ?int => $r->prep_minutes === null ? null : (int) $r->prep_minutes,
            'instructions',
            'tags' => fn (self $r): array => $r->tags ?? [],
            'is_favorite' => fn (self $r): bool => (bool) $r->is_favorite,
            'ingredients' => fn (self $r): array => array_map(
                fn (RecipeIngredient $i): array => $i->toArray(),
                $r->recipeIngredients
            ),
        ];
    }

    /**
     * Compact shape for lists and meal entries.
     *
     * @return array{id: int, title: string, image_url: string|null, tags: list<string>, is_favorite: bool, prep_minutes: int|null}
     */
    public function toSummary(): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'image_url' => $this->getImageUrl(),
            'tags' => $this->tags ?? [],
            'is_favorite' => (bool) $this->is_favorite,
            'prep_minutes' => $this->prep_minutes === null ? null : (int) $this->prep_minutes,
        ];
    }
}
