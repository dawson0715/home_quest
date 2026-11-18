<?php

declare(strict_types=1);

namespace tests\functional\meals;

use app\components\AiClient;
use app\components\GeminiClient;
use app\models\FamilyMember;
use app\models\Recipe;
use FunctionalTester;
use tests\_support\FakeAiClient;
use tests\_support\FamilyFactory;
use Yii;

final class MenuSuggestionCest
{
    private FamilyMember $parent;
    /** @var array<string, Recipe> */
    private array $recipes = [];
    private FakeAiClient $ai;

    public function _before(FunctionalTester $I): void
    {
        $this->ai = new FakeAiClient();
        Yii::$container->setSingleton(AiClient::class, fn () => $this->ai);
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        FamilyFactory::child($this->parent->family, 'Luca');
        $this->parent->family->settings = ['meal_types' => ['lunch', 'dinner']];
        $this->parent->family->save(false);
        foreach ([
            'Pasta al pomodoro' => ['tags' => ['veloce', 'preferito_bimbi'], 'is_favorite' => true],
            'Risotto ai funghi' => ['tags' => [], 'is_favorite' => false],
            'Frittata di zucchine' => ['tags' => ['veloce', 'vegetariano'], 'is_favorite' => false],
        ] as $title => $attrs) {
            $recipe = new Recipe(['title' => $title] + $attrs);
            $recipe->family_id = $this->parent->family_id;
            $recipe->save(false);
            $this->recipes[$title] = $recipe;
        }
        $I->amAuthenticatedAs($this->parent->user);
    }

    public function _after(): void
    {
        Yii::$container->setSingleton(AiClient::class, fn () => GeminiClient::fromParams());
    }

    public function localAlgorithmFillsEmptySlotsWithoutSaving(FunctionalTester $I): void
    {
        $this->ai->enabled = false;
        $I->sendPut('meal-plans/2026-11-16/slots/0/lunch', ['entries' => [['free_text' => 'Pizza']]]);

        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['source' => 'local', 'fallback_reason' => 'not_configured']]);
        $suggestions = $I->grabDataFromResponseByJsonPath('$.data.suggestions[*]');
        $I->assertCount(13, $suggestions, '14 slots, one already planned');
        $I->assertNotContains('0:lunch', array_map(fn (array $s): string => "{$s['day_index']}:{$s['meal_type']}", $suggestions));
        $first = $suggestions[0];
        $I->assertSame('2026-11-16', $first['date']);
        $I->assertNotNull($first['recipe_id']);
        $I->assertNotNull($first['reason_code']);
        // three recipes, so no recipe twice in a row on the same day
        foreach (range(0, 6) as $day) {
            $ids = array_column(array_filter($suggestions, fn (array $s): bool => $s['day_index'] === $day), 'recipe_id');
            $I->assertSame(count($ids), count(array_unique($ids)));
        }
        $this->assertNothingSaved($I);
        $I->assertSame([], $this->ai->requests);
    }

    public function aiSuggestionsAreAnonymousAndValidated(FunctionalTester $I): void
    {
        $this->ai->answer = ['suggestions' => [
                ['slot' => 1, 'recipe' => 2, 'idea' => '', 'reason' => 'Non lo fate da tempo'],
                ['slot' => 2, 'recipe' => 0, 'idea' => 'Minestrone di verdure', 'reason' => 'Di stagione'],
                ['slot' => 2, 'recipe' => 1, 'idea' => '', 'reason' => 'duplicate slot: ignored'],
                ['slot' => 3, 'recipe' => 99, 'idea' => '', 'reason' => 'unknown recipe: ignored'],
                ['slot' => 999, 'recipe' => 1, 'idea' => '', 'reason' => 'unknown slot: ignored'],
            ]];
        $I->sendPost('meal-plans/2026-11-16/suggest', ['note' => 'Martedì poco tempo']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['source' => 'ai', 'fallback_reason' => null]]);
        $suggestions = $I->grabDataFromResponseByJsonPath('$.data.suggestions[*]');
        $I->assertCount(14, $suggestions, 'slots the model skipped are filled locally');
        $I->assertSame(['day_index' => 0, 'meal_type' => 'lunch', 'recipe_id' => $this->recipes['Risotto ai funghi']->id, 'title' => 'Risotto ai funghi', 'reason' => 'Non lo fate da tempo'],
            array_intersect_key($suggestions[0], array_flip(['day_index', 'meal_type', 'recipe_id', 'title', 'reason'])));
        $I->assertSame(['recipe_id' => null, 'free_text' => 'Minestrone di verdure', 'title' => 'Minestrone di verdure'],
            array_intersect_key($suggestions[1], array_flip(['recipe_id', 'free_text', 'title'])));
        $I->assertNull($suggestions[2]['reason'], 'invalid recipe number → local pick');
        $I->assertNotNull($suggestions[2]['reason_code']);

        // what left the server: no ids, no names, no family
        $I->assertCount(1, $this->ai->requests);
        $sent = $this->ai->requests[0]['input'];
        foreach (['Rossi', 'Luca', '"id"', 'family'] as $private) {
            $I->assertStringNotContainsString($private, $sent);
        }
        $input = json_decode($sent, true);
        $I->assertSame('Martedì poco tempo', $input['nota_del_genitore']);
        $I->assertSame(['slot' => 1, 'giorno' => 'lunedì', 'pasto' => 'pranzo'], $input['slot_da_riempire'][0]);
        $I->assertSame(['n' => 1, 'titolo' => 'Pasta al pomodoro'], array_slice($input['ricette'][0], 0, 2));
        $this->assertNothingSaved($I);
    }

    public function failuresAndSettingFallBackToLocal(FunctionalTester $I): void
    {
        $this->ai->answer = null;
        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeResponseContainsJson(['data' => ['source' => 'local', 'fallback_reason' => 'error']]);

        $this->ai->answer = ['suggestions' => [['slot' => 1, 'recipe' => 42, 'idea' => '', 'reason' => '']]];
        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeResponseContainsJson(['data' => ['source' => 'local', 'fallback_reason' => 'error']]);

        $I->sendPatch('family', ['settings' => ['ai_suggestions' => false]]);
        $I->seeResponseContainsJson(['data' => ['settings' => ['ai_suggestions' => false]]]);
        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeResponseContainsJson(['data' => ['source' => 'local', 'fallback_reason' => 'disabled']]);
        $I->assertCount(2, $this->ai->requests);
    }

    public function dailyLimit(FunctionalTester $I): void
    {
        $this->ai->answer = ['suggestions' => [['slot' => 1, 'recipe' => 1, 'idea' => '', 'reason' => 'ok']]];
        $limit = (int) Yii::$app->params['aiDailyLimit'];
        for ($i = 0; $i < $limit; $i++) {
            $I->sendPost('meal-plans/2026-11-16/suggest');
        }
        $I->seeResponseContainsJson(['data' => ['source' => 'ai']]);
        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeResponseContainsJson(['data' => ['source' => 'local', 'fallback_reason' => 'rate_limited']]);
        $I->assertCount($limit, $this->ai->requests);
    }

    public function validationRolesAndIsolation(FunctionalTester $I): void
    {
        $this->ai->enabled = false;
        $I->sendPost('meal-plans/2026-11-16/suggest', ['note' => str_repeat('a', 201)]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->sendPost('meal-plans/2026-11-17/suggest');
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->amChild(FamilyFactory::child($this->parent->family, 'Anna'));
        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeErrorEnvelope(403, 'forbidden');

        // another family never sees our recipes
        $I->amFamilyMember(FamilyFactory::family('Bianchi'));
        $I->sendPost('meal-plans/2026-11-16/suggest');
        $I->seeResponseCodeIs(200);
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data.suggestions[*]'));
    }

    public function historyAndVotesShapeTheLocalChoice(FunctionalTester $I): void
    {
        $this->ai->enabled = false;
        // last week: risotto liked, breakfast habit
        $this->parent->family->settings = ['meal_types' => ['breakfast', 'dinner']];
        $this->parent->family->save(false);
        $I->sendPut('meal-plans/2026-11-09/slots/0/breakfast', ['entries' => [['free_text' => 'Latte e biscotti']]]);
        $I->sendPut('meal-plans/2026-11-09/slots/1/breakfast', ['entries' => [['free_text' => 'Latte e biscotti']]]);
        $I->sendPut('meal-plans/2026-11-09/slots/0/dinner', ['entries' => [['recipe_id' => $this->recipes['Pasta al pomodoro']->id]]]);
        $entry = $I->grabDataFromResponseByJsonPath('$.data.entries[0].id')[0];
        $I->sendPost("meal-entries/$entry/vote", ['value' => -1]);

        $I->sendPost('meal-plans/2026-11-16/suggest');
        $suggestions = $I->grabDataFromResponseByJsonPath('$.data.suggestions[*]');
        $breakfasts = array_values(array_filter($suggestions, fn (array $s): bool => $s['meal_type'] === 'breakfast'));
        $I->assertCount(7, $breakfasts);
        $I->assertSame(['Latte e biscotti'], array_values(array_unique(array_column($breakfasts, 'free_text'))));
        $I->assertSame('habit', $breakfasts[0]['reason_code']);
        $dinners = array_filter($suggestions, fn (array $s): bool => $s['meal_type'] === 'dinner');
        $I->assertNotContains(null, array_column($dinners, 'recipe_id'), 'dinners come from the cookbook');
    }

    private function assertNothingSaved(FunctionalTester $I): void
    {
        $I->sendGet('meal-plans/2026-11-16');
        $filled = array_filter($I->grabDataFromResponseByJsonPath('$.data.slots[*]'), fn (array $s): bool => $s['entries'] !== []);
        $I->assertLessThanOrEqual(1, count($filled));
    }
}
