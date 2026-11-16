<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\models\Family;
use app\models\Ingredient;
use app\models\MealEntry;
use app\models\MealPlanWeek;
use app\models\MealSlot;
use app\models\Recipe;
use app\models\ShoppingItem;
use app\models\ShoppingList;
use app\services\ShoppingListService;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;
use Yii;

final class ShoppingGenerateTest extends Unit
{
    private Family $family;
    private MealPlanWeek $week;
    private ShoppingList $list;
    private ShoppingListService $service;

    protected function _before(): void
    {
        $this->family = FamilyFactory::family();
        $this->week = new MealPlanWeek(['week_start' => '2026-11-16']);
        $this->week->family_id = $this->family->id;
        $this->week->save(false);
        $this->list = new ShoppingList(['name' => 'Spesa', 'is_default' => true]);
        $this->list->family_id = $this->family->id;
        $this->list->save(false);
        $this->service = ShoppingListService::for($this->family);
    }

    private function ingredient(string $name): int
    {
        return (int) Ingredient::find()->where(['family_id' => null, 'name' => $name])->select('id')->scalar();
    }

    /**
     * @param list<array{0: string, 1: float|null, 2: string}> $lines
     */
    private function recipe(string $title, int $servings, array $lines): Recipe
    {
        $recipe = new Recipe(['title' => $title, 'servings' => $servings]);
        $recipe->family_id = $this->family->id;
        $recipe->save(false);
        foreach ($lines as $i => [$name, $qty, $unit]) {
            Yii::$app->db->createCommand()->insert('{{%recipe_ingredient}}', [
                'recipe_id' => $recipe->id, 'ingredient_id' => $this->ingredient($name), 'quantity' => $qty, 'unit' => $unit, 'sort_order' => $i,
            ])->execute();
        }
        return $recipe;
    }

    private function plan(int $day, string $meal, int $servings, Recipe ...$recipes): void
    {
        $slot = MealSlot::findOne(['week_id' => $this->week->id, 'day_index' => $day, 'meal_type' => $meal])
            ?? new MealSlot(['week_id' => $this->week->id, 'day_index' => $day, 'meal_type' => $meal]);
        $slot->servings = $servings;
        $slot->save(false);
        foreach ($recipes as $recipe) {
            (new MealEntry(['slot_id' => $slot->id, 'recipe_id' => $recipe->id, 'status' => 'planned']))->save(false);
        }
    }

    /** @return array<string, array{0: float|null, 1: string|null, 2: bool}> label => [qty, unit, checked] */
    private function items(): array
    {
        $out = [];
        foreach (ShoppingItem::find()->where(['list_id' => $this->list->id])->orderBy('id')->all() as $i) {
            $out[$i->label] = [$i->quantity === null ? null : (float) $i->quantity, $i->unit, $i->checked_at !== null];
        }
        return $out;
    }

    public function testScalesSumsAndNormalizesUnits(): void
    {
        $pasta = $this->recipe('Pasta', 4, [['Spaghetti', 400, 'g'], ['Passata di pomodoro', 0.5, 'kg'], ['Basilico', null, 'qb']]);
        $risotto = $this->recipe('Risotto', 2, [['Brodo vegetale', 1, 'l'], ['Parmigiano', 50, 'g'], ['Uova', 1, 'pz']]);
        $this->plan(0, 'dinner', 6, $pasta);          // ×1.5
        $this->plan(2, 'lunch', 4, $pasta);           // ×1
        $this->plan(3, 'dinner', 3, $risotto);        // ×1.5
        $proposed = new MealEntry(['slot_id' => MealSlot::findOne(['week_id' => $this->week->id, 'day_index' => 0])?->id, 'recipe_id' => $risotto->id, 'status' => 'proposed']);
        $proposed->save(false); // proposals are not bought

        $result = $this->service->generateFromWeek($this->list, $this->week);

        $this->assertSame(['added' => 6, 'updated' => 0, 'removed' => 0], $result);
        $this->assertEquals([
            'Spaghetti' => [1.0, 'kg', false],                 // 600 g + 400 g
            'Passata di pomodoro' => [1.25, 'kg', false],      // 750 g + 500 g
            'Basilico' => [null, 'qb', false],
            'Brodo vegetale' => [1.5, 'l', false],
            'Parmigiano' => [75.0, 'g', false],
            'Uova' => [2.0, 'pz', false],                      // 1.5 → 2
        ], $this->items());
        $this->assertGreaterThan(0, (int) ShoppingList::findOne($this->list->id)?->version);
    }

    public function testRegenerationKeepsManualAndCheckedAndDropsStale(): void
    {
        $pasta = $this->recipe('Pasta', 4, [['Spaghetti', 400, 'g'], ['Pecorino', 40, 'g']]);
        $salad = $this->recipe('Insalata', 4, [['Insalata', 1, 'pz']]);
        $this->plan(0, 'dinner', 4, $pasta, $salad);
        $this->service->generateFromWeek($this->list, $this->week);
        $this->service->addItem($this->list, ['label' => 'Detersivo piatti']);
        $spaghetti = ShoppingItem::findOne(['list_id' => $this->list->id, 'label' => 'Spaghetti']);
        $this->service->setChecked($spaghetti ?? $this->fail('missing'), true, ['type' => 'user', 'id' => 1]);

        // menu changes: more people, no more salad
        MealEntry::deleteAll(['recipe_id' => $salad->id]);
        MealSlot::updateAll(['servings' => 8], ['week_id' => $this->week->id]);
        $result = $this->service->generateFromWeek($this->list, $this->week);

        $this->assertSame(['added' => 0, 'updated' => 1, 'removed' => 1], $result);
        $items = $this->items();
        $this->assertSame([400.0, 'g', true], $items['Spaghetti'], 'checked items are left alone');
        $this->assertSame([80.0, 'g', false], $items['Pecorino']);
        $this->assertSame([null, null, false], $items['Detersivo piatti']);
        $this->assertArrayNotHasKey('Insalata', $items);
    }

    public function testPantryExclusionsAreSkipped(): void
    {
        $pasta = $this->recipe('Pasta', 4, [['Spaghetti', 400, 'g'], ['Olio extravergine di oliva', 30, 'ml']]);
        $this->plan(1, 'lunch', 4, $pasta);
        $this->service->setPantry($this->list, $this->ingredient('Olio extravergine di oliva'), true);

        $this->service->generateFromWeek($this->list, $this->week);

        $this->assertSame(['Spaghetti'], array_keys($this->items()));
    }
}
