<?php

declare(strict_types=1);

namespace tests\functional\meals;

use app\models\FamilyMember;
use app\models\Ingredient;
use app\models\MealEntry;
use app\models\MealPlanWeek;
use app\models\MealSlot;
use app\models\Recipe;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class RecipeCest
{
    private FamilyMember $parent;

    public function _before(FunctionalTester $I): void
    {
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $I->amAuthenticatedAs($this->parent->user);
    }

    private function ingredientId(string $name): int
    {
        return (int) Ingredient::find()->where(['family_id' => null, 'name' => $name])->select('id')->scalar();
    }

    public function createWithIngredientsByIdAndByName(FunctionalTester $I): void
    {
        $I->sendPost('recipes', [
            'title' => 'Pasta al pomodoro', 'servings' => 4, 'prep_minutes' => 20, 'tags' => ['veloce', 'preferito_bimbi'],
            'ingredients' => [
                ['ingredient_id' => $this->ingredientId('Spaghetti'), 'quantity' => 320, 'unit' => 'g'],
                ['ingredient_id' => $this->ingredientId('Passata di pomodoro'), 'quantity' => 400, 'unit' => 'g'],
                ['name' => 'Basilico', 'unit' => 'qb', 'quantity' => 3],
                ['name' => 'Salsa segreta della nonna', 'quantity' => 1, 'unit' => 'cucchiaio', 'note' => 'facoltativa'],
            ],
        ]);

        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => [
            'title' => 'Pasta al pomodoro', 'servings' => 4, 'tags' => ['veloce', 'preferito_bimbi'], 'image_url' => null,
            'ingredients' => [
                ['name' => 'Spaghetti', 'quantity' => 320, 'unit' => 'g'],
                ['name' => 'Passata di pomodoro'],
                ['name' => 'Basilico', 'quantity' => null, 'unit' => 'qb'],
                ['name' => 'Salsa segreta della nonna', 'note' => 'facoltativa'],
            ],
        ]]);
        $I->seeRecord(Ingredient::class, ['family_id' => $this->parent->family_id, 'name' => 'Salsa segreta della nonna']);
        $I->dontSeeRecord(Ingredient::class, ['family_id' => $this->parent->family_id, 'name' => 'Basilico']);
    }

    public function validation(FunctionalTester $I): void
    {
        $I->sendPost('recipes', ['title' => '', 'servings' => 0, 'tags' => ['piccante'], 'ingredients' => [['unit' => 'g']]]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->assertEqualsCanonicalizing(['title', 'servings', 'tags', 'ingredients'], $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field'));
    }

    public function listSearchUpdateAndDelete(FunctionalTester $I): void
    {
        $I->sendPost('recipes', ['title' => 'Risotto alla zucca', 'tags' => ['vegetariano'], 'ingredients' => [['ingredient_id' => $this->ingredientId('Riso Carnaroli'), 'quantity' => 300, 'unit' => 'g']]]);
        $risotto = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->sendPost('recipes', ['title' => 'Cotolette', 'is_favorite' => true]);

        $I->sendGet('recipes');
        $I->assertSame(['Cotolette', 'Risotto alla zucca'], $I->grabDataFromResponseByJsonPath('$.data[*].title'));
        $I->sendGet('recipes?q=zuc');
        $I->assertSame(['Risotto alla zucca'], $I->grabDataFromResponseByJsonPath('$.data[*].title'));
        $I->sendGet('recipes?tag=vegetariano');
        $I->assertSame([$risotto], $I->grabDataFromResponseByJsonPath('$.data[*].id'));

        $I->sendPatch("recipes/$risotto", ['servings' => 2, 'ingredients' => [['name' => 'Zucca', 'quantity' => 1, 'unit' => 'kg']]]);
        $I->seeResponseContainsJson(['data' => ['servings' => 2, 'ingredients' => [['name' => 'Zucca', 'quantity' => 1, 'unit' => 'kg']]]]);
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.data.ingredients[*]'));

        // a planned dish keeps the title as free text when the recipe is deleted
        $week = new MealPlanWeek(['week_start' => '2026-11-16']);
        $week->family_id = $this->parent->family_id;
        $week->save(false);
        $slot = new MealSlot(['week_id' => $week->id, 'day_index' => 0, 'meal_type' => 'dinner', 'servings' => 4]);
        $slot->save(false);
        (new MealEntry(['slot_id' => $slot->id, 'recipe_id' => $risotto, 'status' => 'planned']))->save(false);

        $I->sendDelete("recipes/$risotto");
        $I->seeResponseCodeIs(204);
        $I->dontSeeRecord(Recipe::class, ['id' => $risotto]);
        $I->seeRecord(MealEntry::class, ['slot_id' => $slot->id, 'recipe_id' => null, 'free_text' => 'Risotto alla zucca']);
    }

    public function imageUploadIsReencoded(FunctionalTester $I): void
    {
        $I->sendPost('recipes', ['title' => 'Torta']);
        $id = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $png = codecept_output_dir('test.png');
        $img = imagecreatetruecolor(2400, 1200);
        imagepng($img, $png);
        $txt = codecept_output_dir('fake.png');
        file_put_contents($txt, 'not an image');

        $I->deleteHeader('Content-Type');
        $I->sendPost("recipes/$id/image", [], ['image' => ['name' => 'fake.png', 'type' => 'image/png', 'error' => UPLOAD_ERR_OK, 'size' => filesize($txt), 'tmp_name' => $txt]]);
        $I->seeErrorEnvelope(422, 'image_invalid');

        // Yii caches $_FILES statically; the test harness reuses the process between requests
        \yii\web\UploadedFile::reset();
        $I->sendPost("recipes/$id/image", [], ['image' => ['name' => 'test.png', 'type' => 'image/png', 'error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'tmp_name' => $png]]);
        $I->seeResponseCodeIs(200);
        $url = $I->grabDataFromResponseByJsonPath('$.data.image_url')[0];
        $I->assertMatchesRegularExpression('#^/uploads/recipes/[0-9a-f]{32}\.jpg$#', $url);
        $file = Yii::getAlias('@webroot') . $url;
        [$w, $h, $type] = getimagesize($file) ?: [0, 0, 0];
        $I->assertSame([1600, 800, IMAGETYPE_JPEG], [$w, $h, $type]);

        $I->sendDelete("recipes/$id/image");
        $I->seeResponseContainsJson(['data' => ['image_url' => null]]);
        $I->assertFileDoesNotExist($file);
    }

    public function ingredientsAutocompleteAndCustom(FunctionalTester $I): void
    {
        $I->sendGet('ingredients?q=pomo');
        $names = $I->grabDataFromResponseByJsonPath('$.data[*].name');
        $I->assertSame('Pomodori', $names[0]);
        $I->assertContains('Passata di pomodoro', $names);

        $I->sendGet('shopping-categories');
        $I->assertSame('frutta_verdura', $I->grabDataFromResponseByJsonPath('$.data[0].code')[0]);
        $categoryId = $I->grabDataFromResponseByJsonPath('$.data[2].id')[0];

        $I->sendPost('ingredients', ['name' => 'Kefir', 'default_unit' => 'ml', 'shopping_category_id' => $categoryId]);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['name' => 'Kefir', 'is_custom' => true]]);
        $I->sendPost('ingredients', ['name' => 'Pomodori', 'default_unit' => 'g']);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function isolationAndChildren(FunctionalTester $I): void
    {
        $other = new Recipe(['title' => 'Segreta']);
        $other->family_id = FamilyFactory::family('Bianchi')->id;
        $other->save(false);
        $custom = new Ingredient(['family_id' => $other->family_id, 'name' => 'Spezia segreta', 'default_unit' => 'g', 'shopping_category_id' => 1]);
        $custom->save(false);

        $I->sendGet('recipes');
        $I->assertNotContains('Segreta', $I->grabDataFromResponseByJsonPath('$.data[*].title'));
        $I->sendGet("recipes/{$other->id}");
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendGet('ingredients?q=Spezia');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->sendPost('recipes', ['title' => 'X', 'ingredients' => [['ingredient_id' => $custom->id, 'unit' => 'g']]]);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPost('recipes', ['title' => 'Pizza']);
        $I->amChild(FamilyFactory::child($this->parent->family));
        $I->sendGet('recipes');
        $I->assertSame(['Pizza'], $I->grabDataFromResponseByJsonPath('$.data[*].title'));
        $I->sendPost('recipes', ['title' => 'Solo dolci']);
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendGet('ingredients');
        $I->seeErrorEnvelope(403, 'forbidden');
    }
}
