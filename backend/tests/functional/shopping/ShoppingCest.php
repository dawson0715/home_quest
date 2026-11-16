<?php

declare(strict_types=1);

namespace tests\functional\shopping;

use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\Ingredient;
use app\models\ShoppingItem;
use FunctionalTester;
use tests\_support\FamilyFactory;

final class ShoppingCest
{
    private FamilyMember $parent;
    private ChildProfile $luca;

    public function _before(FunctionalTester $I): void
    {
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->luca = FamilyFactory::child($this->parent->family, 'Luca');
        $I->amAuthenticatedAs($this->parent->user);
    }

    private function defaultListId(FunctionalTester $I): int
    {
        $I->sendGet('shopping-lists');
        return (int) $I->grabDataFromResponseByJsonPath('$.data[0].id')[0];
    }

    private function itemId(FunctionalTester $I, string $label): int
    {
        foreach ($I->grabDataFromResponseByJsonPath('$.data.items[*]') as $item) {
            if ($item['label'] === $label) {
                return $item['id'];
            }
        }
        $I->fail("item $label not found");
    }

    public function defaultListItemsAndEtag(FunctionalTester $I): void
    {
        $I->sendGet('shopping-lists');
        $I->seeResponseContainsJson(['data' => [['name' => 'Spesa', 'is_default' => true, 'items_count' => 0]]]);
        $id = $this->defaultListId($I);

        $milk = (int) Ingredient::find()->where(['family_id' => null, 'name' => 'Latte'])->select('id')->scalar();
        $I->sendPost("shopping-lists/$id/items", ['ingredient_id' => $milk, 'quantity' => 2, 'unit' => 'l']);
        $I->seeResponseCodeIs(201);
        $I->sendPost("shopping-lists/$id/items", ['label' => 'Detersivo', 'client_uuid' => '7b0d9c1e-1a2b-4c3d-8e9f-001122334455']);
        $I->seeResponseContainsJson(['data' => ['items' => [
            ['label' => 'Latte', 'quantity' => 2, 'unit' => 'l', 'source' => 'manual', 'checked' => false],
            ['label' => 'Detersivo', 'quantity' => null],
        ]]]);
        $etag = $I->grabHttpHeader('ETag');
        $milkItem = $this->itemId($I, 'Latte');
        $soapItem = $this->itemId($I, 'Detersivo');
        $I->assertMatchesRegularExpression('/^"v\d+"$/', $etag);

        $I->haveHttpHeader('If-None-Match', $etag);
        $I->sendGet("shopping-lists/$id");
        $I->seeResponseCodeIs(304);
        $I->deleteHeader('If-None-Match');

        $I->sendPost("shopping-items/$milkItem/check");
        $I->seeResponseContainsJson(['data' => ['items' => [['label' => 'Detersivo', 'checked' => false], ['label' => 'Latte', 'checked' => true]]]]);
        $I->assertNotSame($etag, $I->grabHttpHeader('ETag'));

        $I->sendPatch("shopping-items/$soapItem", ['label' => 'Detersivo piatti', 'quantity' => 1, 'unit' => 'pz']);
        $I->seeResponseContainsJson(['data' => ['items' => [['label' => 'Detersivo piatti', 'quantity' => 1]]]]);
        $I->sendPost("shopping-lists/$id/clear-checked");
        $I->seeResponseContainsJson(['meta' => ['removed' => 1]]);
        $I->assertSame(['Detersivo piatti'], $I->grabDataFromResponseByJsonPath('$.data.items[*].label'));

        $I->sendGet('favorite-items');
        $I->seeResponseContainsJson(['data' => [['label' => 'Latte', 'use_count' => 1]]]);
    }

    public function syncIsIdempotent(FunctionalTester $I): void
    {
        $id = $this->defaultListId($I);
        $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $ops = ['ops' => [
            ['op' => 'add', 'client_uuid' => $uuid, 'data' => ['label' => 'Pane']],
            ['op' => 'check', 'client_uuid' => $uuid],
            ['op' => 'delete', 'item_id' => 999999],
            ['op' => 'explode'],
        ]];
        $I->sendPost("shopping-lists/$id/sync", $ops);
        $I->seeResponseContainsJson(['meta' => ['applied' => 2, 'skipped' => 2], 'data' => ['items' => [['label' => 'Pane', 'checked' => true, 'client_uuid' => $uuid]]]]);

        $I->sendPost("shopping-lists/$id/sync", $ops); // retry after a lost response
        $I->assertCount(1, $I->grabDataFromResponseByJsonPath('$.data.items[*]'));

        $I->sendPost("shopping-lists/$id/sync", ['ops' => 'nope']);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function childrenNeedThePermission(FunctionalTester $I): void
    {
        $id = $this->defaultListId($I);
        $I->sendPost("shopping-lists/$id/items", ['label' => 'Mele']);
        $itemId = $this->itemId($I, 'Mele');

        $I->amChild($this->luca);
        $I->sendGet("shopping-lists/$id");
        $I->seeErrorEnvelope(403, 'forbidden');

        $this->luca->updateAttributes(['can_check_shopping' => true]);
        $I->sendGet("shopping-lists/$id");
        $I->seeResponseCodeIs(200);
        $I->sendPost("shopping-items/$itemId/check");
        $I->seeRecord(ShoppingItem::class, ['id' => $itemId, 'checked_by_type' => 'child', 'checked_by_id' => $this->luca->id]);
        $I->sendPost("shopping-lists/$id/items", ['label' => 'Caramelle']);
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendPost("shopping-lists/$id/sync", ['ops' => [['op' => 'add', 'client_uuid' => 'aaaaaaaa-bbbb-4ccc-8ddd-000000000001', 'data' => ['label' => 'Caramelle']], ['op' => 'uncheck', 'item_id' => $itemId]]]);
        $I->seeResponseContainsJson(['meta' => ['applied' => 1, 'skipped' => 1]]);
        $I->dontSeeRecord(ShoppingItem::class, ['label' => 'Caramelle']);
    }

    public function listsPantryAndCategoryOrder(FunctionalTester $I): void
    {
        $default = $this->defaultListId($I);
        $I->sendPost('shopping-lists', ['name' => 'Farmacia']);
        $I->seeResponseCodeIs(201);
        $pharmacy = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->sendPatch("shopping-lists/$pharmacy", ['name' => 'Farmacia di turno']);
        $I->seeResponseContainsJson(['data' => ['name' => 'Farmacia di turno']]);
        $I->sendDelete("shopping-lists/$default");
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->sendDelete("shopping-lists/$pharmacy");
        $I->seeResponseCodeIs(204);

        $oil = (int) Ingredient::find()->where(['family_id' => null, 'name' => 'Olio extravergine di oliva'])->select('id')->scalar();
        $I->sendPut("shopping-lists/$default/pantry/$oil");
        $I->seeResponseContainsJson(['data' => ['pantry' => [$oil]]]);
        $I->sendDelete("shopping-lists/$default/pantry/$oil");
        $I->seeResponseContainsJson(['data' => ['pantry' => []]]);

        $I->sendGet('shopping-categories');
        $ids = $I->grabDataFromResponseByJsonPath('$.data[*].id');
        $reversed = array_reverse($ids);
        $I->sendPut('shopping-categories/order', ['ids' => $reversed]);
        $I->seeResponseContainsJson(['data' => ['ids' => $reversed]]);
        $I->sendGet('shopping-categories');
        $I->assertSame($reversed, $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->sendPut('shopping-categories/order', ['ids' => [999999]]);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function generateFromMenuEndpoint(FunctionalTester $I): void
    {
        $I->sendPost('recipes', ['title' => 'Frittata', 'servings' => 2, 'ingredients' => [['name' => 'Uova', 'quantity' => 4, 'unit' => 'pz']]]);
        $recipeId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->sendPut('meal-plans/2026-11-16/slots/4/dinner', ['servings' => 3, 'entries' => [['recipe_id' => $recipeId]]]);
        $id = $this->defaultListId($I);

        $I->sendPost("shopping-lists/$id/generate", ['week_start' => '2026-11-16']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['meta' => ['added' => 1, 'updated' => 0, 'removed' => 0], 'data' => ['items' => [['label' => 'Uova', 'quantity' => 6, 'unit' => 'pz', 'source' => 'generated']]]]);
        $I->sendPost("shopping-lists/$id/generate", ['week_start' => '2026-11-17']);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function familiesAreIsolated(FunctionalTester $I): void
    {
        $id = $this->defaultListId($I);
        $I->sendPost("shopping-lists/$id/items", ['label' => 'Segreto']);
        $itemId = $this->itemId($I, 'Segreto');

        $I->amFamilyMember(FamilyFactory::family('Bianchi'));
        $I->sendGet("shopping-lists/$id");
        $I->seeErrorEnvelope(404, 'not_found');
        foreach (['check', 'uncheck'] as $state) {
            $I->sendPost("shopping-items/$itemId/$state");
            $I->seeErrorEnvelope(404, 'not_found');
        }
        $I->sendDelete("shopping-items/$itemId");
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendPost("shopping-lists/$id/sync", ['ops' => [['op' => 'delete', 'item_id' => $itemId]]]);
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendGet('shopping-lists');
        $I->assertNotContains($id, $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->seeRecord(ShoppingItem::class, ['id' => $itemId, 'label' => 'Segreto']);
    }
}
