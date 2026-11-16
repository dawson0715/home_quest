<?php

declare(strict_types=1);

namespace app\services;

use app\components\ApiException;
use app\components\ErrorCode;
use app\models\Ingredient;
use app\models\MealEntry;
use app\models\Recipe;
use app\models\RecipeIngredient;
use app\models\ShoppingCategory;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Recipes with their ingredient rows, and recipe photos.
 */
class RecipeService
{
    public const MAX_INGREDIENTS = 60;
    public const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
    public const IMAGE_MAX_SIDE = 1600;
    public const IMAGE_DIR = 'uploads/recipes';

    /**
     * Saves recipe fields + ingredients (replaced when `ingredients` is present).
     * Returns the recipe or a model carrying validation errors.
     *
     * @param array<string, mixed> $data
     */
    public function save(Recipe $recipe, array $data, ?int $userId): Recipe|Model
    {
        foreach (['title', 'servings', 'prep_minutes', 'instructions', 'tags', 'is_favorite'] as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $recipe->$attribute = $data[$attribute];
            }
        }
        $errors = new Model();
        if (!$recipe->validate()) {
            $errors->addErrors($recipe->getErrors());
        }
        $rows = null;
        if (array_key_exists('ingredients', $data)) {
            $rows = $this->validateIngredients($data['ingredients'], (int) $recipe->family_id, $errors);
        }
        if ($errors->hasErrors()) {
            return $errors;
        }

        $tx = Recipe::getDb()->beginTransaction();
        try {
            if ($recipe->isNewRecord) {
                $recipe->created_by = $userId;
            }
            $recipe->save(false);
            if ($rows !== null) {
                RecipeIngredient::deleteAll(['recipe_id' => $recipe->id]);
                foreach ($rows as $order => $row) {
                    $ingredientId = $row['ingredient_id'] ?? $this->findOrCreateIngredient((int) $recipe->family_id, (string) $row['name'], $row['unit']);
                    $line = new RecipeIngredient([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingredientId,
                        'quantity' => $row['quantity'],
                        'unit' => $row['unit'],
                        'note' => $row['note'],
                        'sort_order' => $order,
                    ]);
                    $line->save(false);
                }
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        $recipe->refresh();
        return $recipe;
    }

    /** Deletes a recipe; planned dishes keep its title as free text. */
    public function delete(Recipe $recipe): void
    {
        $tx = Recipe::getDb()->beginTransaction();
        try {
            MealEntry::updateAll(['free_text' => mb_substr($recipe->title, 0, 120), 'recipe_id' => null], ['recipe_id' => $recipe->id]);
            $this->removeImageFile($recipe);
            $recipe->delete();
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * Re-encodes the photo (drops EXIF/GPS metadata), max 1600 px, JPEG.
     */
    public function setImage(Recipe $recipe, ?UploadedFile $file): Recipe
    {
        if ($file === null || (int) $file->error !== UPLOAD_ERR_OK) {
            throw new ApiException(ErrorCode::IMAGE_INVALID, 'Send a jpeg/png/webp file in the "image" field.', 422, 'image');
        }
        if ($file->size > self::IMAGE_MAX_BYTES) {
            throw new ApiException(ErrorCode::IMAGE_INVALID, 'The image must be at most 5 MB.', 422, 'image');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->tempName);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new ApiException(ErrorCode::IMAGE_INVALID, 'Only jpeg, png and webp images are accepted.', 422, 'image');
        }
        $source = @imagecreatefromstring((string) file_get_contents($file->tempName));
        if ($source === false) {
            throw new ApiException(ErrorCode::IMAGE_INVALID, 'The image cannot be read.', 422, 'image');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::IMAGE_MAX_SIDE / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($w, $h);
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        $dir = Yii::getAlias('@webroot') . '/' . self::IMAGE_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $relative = self::IMAGE_DIR . '/' . bin2hex(random_bytes(16)) . '.jpg';
        imagejpeg($target, Yii::getAlias('@webroot') . '/' . $relative, 82);

        $this->removeImageFile($recipe);
        $recipe->updateAttributes(['image_path' => $relative, 'updated_at' => time()]);
        return $recipe;
    }

    public function removeImage(Recipe $recipe): Recipe
    {
        $this->removeImageFile($recipe);
        $recipe->updateAttributes(['image_path' => null, 'updated_at' => time()]);
        return $recipe;
    }

    /**
     * @return list<array{ingredient_id: int|null, name: string|null, quantity: float|null, unit: string, note: string|null}>|null
     */
    private function validateIngredients(mixed $items, int $familyId, Model $errors): ?array
    {
        if (!is_array($items) || count($items) > self::MAX_INGREDIENTS) {
            $errors->addError('ingredients', 'Ingredients must be a list (max ' . self::MAX_INGREDIENTS . ').');
            return null;
        }
        $visible = array_map('intval', Ingredient::visibleTo($familyId)->select('id')->column());
        $rows = [];
        foreach (array_values($items) as $i => $item) {
            if (!is_array($item)) {
                $errors->addError('ingredients', "Row $i: invalid.");
                continue;
            }
            $id = isset($item['ingredient_id']) ? (int) $item['ingredient_id'] : null;
            $name = isset($item['name']) ? trim((string) $item['name']) : null;
            $unit = (string) ($item['unit'] ?? '');
            $quantity = $item['quantity'] ?? null;
            $note = isset($item['note']) && $item['note'] !== '' ? trim((string) $item['note']) : null;
            if ($id === null && ($name === null || $name === '' || mb_strlen($name) > 80)) {
                $errors->addError('ingredients', "Row $i: ingredient_id or name (max 80) required.");
            } elseif ($id !== null && !in_array($id, $visible, true)) {
                $errors->addError('ingredients', "Row $i: unknown ingredient.");
            } elseif (!in_array($unit, Ingredient::UNITS, true)) {
                $errors->addError('ingredients', "Row $i: invalid unit.");
            } elseif ($quantity !== null && (!is_numeric($quantity) || (float) $quantity <= 0 || (float) $quantity > 99999)) {
                $errors->addError('ingredients', "Row $i: invalid quantity.");
            } elseif ($note !== null && mb_strlen($note) > 80) {
                $errors->addError('ingredients', "Row $i: note too long.");
            } else {
                $rows[] = [
                    'ingredient_id' => $id,
                    'name' => $name,
                    'quantity' => $quantity === null || $unit === 'qb' ? null : round((float) $quantity, 2),
                    'unit' => $unit,
                    'note' => $note,
                ];
            }
        }
        return $rows;
    }

    private function findOrCreateIngredient(int $familyId, string $name, string $unit): int
    {
        $existing = Ingredient::visibleTo($familyId)->andWhere(['name' => $name])
            ->orderBy(['family_id' => SORT_DESC])->select('id')->scalar();
        if ($existing !== false && $existing !== null) {
            return (int) $existing;
        }
        $ingredient = new Ingredient([
            'family_id' => $familyId,
            'name' => $name,
            'default_unit' => $unit,
            'shopping_category_id' => ShoppingCategory::fallbackId(),
        ]);
        $ingredient->save(false);
        return (int) $ingredient->id;
    }

    private function removeImageFile(Recipe $recipe): void
    {
        if ($recipe->image_path !== null && str_starts_with($recipe->image_path, self::IMAGE_DIR . '/')) {
            $path = Yii::getAlias('@webroot') . '/' . $recipe->image_path;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
