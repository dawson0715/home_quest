<?php

declare(strict_types=1);

namespace tests\functional\privacy;

use app\models\ChildProfile;
use app\models\Family;
use app\models\FamilyMember;
use app\models\Recipe;
use app\models\User;
use FunctionalTester;
use tests\_support\FamilyFactory;

final class PrivacyCest
{
    public function exportContainsDataButNoSecrets(FunctionalTester $I): void
    {
        $admin = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $child = FamilyFactory::child($admin->family, 'Luca');
        $child->setPin('1234');
        $child->save(false);
        FamilyFactory::task($admin->family, [$child], ['title' => 'Letto']);
        $I->amAuthenticatedAs($admin->user);
        $I->sendPost('recipes', ['title' => 'Pizza', 'ingredients' => [['name' => 'Farina 00', 'quantity' => 500, 'unit' => 'g']]]);

        $I->sendGet('family/export');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Disposition', 'attachment; filename="homequest-rossi.json"');
        $I->seeResponseContainsJson(['data' => [
            'family' => ['name' => 'Rossi'],
            'children' => [['name' => 'Luca']],
            'tasks' => [['title' => 'Letto']],
            'recipes' => [['title' => 'Pizza']],
        ]]);
        $body = $I->grabResponse();
        foreach (['pin_hash', 'access_token', 'auth_key', 'token_hash', $admin->user->access_token] as $secret) {
            $I->assertStringNotContainsString($secret, $body);
        }

        $coparent = FamilyFactory::member($admin->family, role: FamilyMember::ROLE_COPARENT);
        $I->amAuthenticatedAs($coparent->user);
        $I->sendGet('family/export');
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendDelete('family', ['confirm_name' => 'Rossi']);
        $I->seeErrorEnvelope(403, 'forbidden');
    }

    public function deleteRemovesEverythingOfTheFamilyOnly(FunctionalTester $I): void
    {
        $other = FamilyFactory::member(FamilyFactory::family('Bianchi'));
        $otherChild = FamilyFactory::child($other->family, 'Mario');
        $admin = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $coparent = FamilyFactory::member($admin->family, role: FamilyMember::ROLE_COPARENT);
        $child = FamilyFactory::child($admin->family, 'Luca');
        $I->amAuthenticatedAs($admin->user);
        $I->sendPost('recipes', ['title' => 'Pizza']);
        $recipeId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->sendPut('meal-plans/2026-11-16/slots/0/dinner', ['entries' => [['recipe_id' => $recipeId]]]);
        $I->sendGet('shopping-lists');

        $I->sendDelete('family', ['confirm_name' => 'Rosso']);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->sendDelete('family', ['confirm_name' => 'Rossi']);
        $I->seeResponseCodeIs(204);

        $I->dontSeeRecord(Family::class, ['id' => $admin->family_id]);
        $I->dontSeeRecord(ChildProfile::class, ['id' => $child->id]);
        $I->dontSeeRecord(Recipe::class, ['id' => $recipeId]);
        $I->dontSeeRecord(User::class, ['id' => $admin->user_id]);
        $I->dontSeeRecord(User::class, ['id' => $coparent->user_id]);
        $I->seeRecord(ChildProfile::class, ['id' => $otherChild->id]);
        $I->seeRecord(User::class, ['id' => $other->user_id]);

        $I->sendGet('me');
        $I->seeErrorEnvelope(401, 'unauthorized');
    }
}
