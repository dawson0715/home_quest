<?php

declare(strict_types=1);

namespace tests\functional\children;

use app\models\ChildProfile;
use app\models\FamilyMember;
use FunctionalTester;
use tests\_support\FamilyFactory;

final class ChildCest
{
    public function createAndList(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember(role: FamilyMember::ROLE_COPARENT);

        $I->sendPost('children', [
            'name' => ' Luca ', 'avatar' => 'dragon', 'color' => '#ff8a00', 'birth_year_month' => '2018-05',
            // not writable through the API:
            'points_balance' => 9999, 'family_id' => 999, 'pin_hash' => 'x',
        ]);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => [
            'name' => 'Luca', 'avatar' => 'dragon', 'color' => '#FF8A00', 'birth_year_month' => '2018-05',
            'has_pin' => false, 'points_balance' => 0, 'level' => 1, 'archived' => false,
        ]]);
        $I->dontSeeResponseJsonMatchesJsonPath('$.data.pin_hash');
        $I->seeRecord(ChildProfile::class, ['name' => 'Luca', 'family_id' => $member->family_id, 'points_balance' => 0]);

        $I->sendPost('children', ['name' => 'Anna', 'avatar' => 'owl', 'color' => '#336699', 'sort_order' => 0]);
        $I->sendGet('children');
        $I->seeResponseCodeIs(200);
        $I->assertSame(['Luca', 'Anna'], $I->grabDataFromResponseByJsonPath('$.data[*].name'));
    }

    public function validation(FunctionalTester $I): void
    {
        $I->amFamilyMember();
        $I->sendPost('children', ['name' => '', 'avatar' => 'tiger', 'color' => 'red', 'birth_year_month' => '2018-13']);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $fields = $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field');
        $I->assertEqualsCanonicalizing(['name', 'avatar', 'color', 'birth_year_month'], $fields);
    }

    public function updateAndArchive(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember();
        $child = FamilyFactory::child($member->family, 'Luca');

        $I->sendPatch("children/{$child->id}", ['name' => 'Luchino', 'can_check_shopping' => true]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['name' => 'Luchino', 'can_check_shopping' => true]]);

        $I->sendDelete("children/{$child->id}");
        $I->seeResponseCodeIs(204);
        $I->seeRecord(ChildProfile::class, ['id' => $child->id]);

        $I->sendGet('children');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->sendGet('children?archived=1');
        $I->assertSame([$child->id], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
    }

    public function pin(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember();
        $child = FamilyFactory::child($member->family);

        $I->sendPut("children/{$child->id}/pin", ['pin' => '12a4']);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPut("children/{$child->id}/pin", ['pin' => '1234']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['has_pin' => true]]);
        $child->refresh();
        $I->assertTrue($child->validatePin('1234'));
        $I->assertFalse($child->validatePin('0000'));

        $I->sendPut("children/{$child->id}/pin", ['pin' => null]);
        $I->seeResponseContainsJson(['data' => ['has_pin' => false]]);
    }

    public function otherFamiliesChildrenAreInvisible(FunctionalTester $I): void
    {
        $other = FamilyFactory::child(FamilyFactory::family('Bianchi'), 'Mario');
        $I->amFamilyMember(FamilyFactory::family('Rossi'));

        $I->sendGet('children');
        $I->assertNotContains($other->id, $I->grabDataFromResponseByJsonPath('$.data[*].id'));

        foreach (['sendGet', 'sendPatch', 'sendDelete'] as $method) {
            $I->$method("children/{$other->id}", ['name' => 'X']);
            $I->seeErrorEnvelope(404, 'not_found');
        }
        $I->sendPut("children/{$other->id}/pin", ['pin' => '1234']);
        $I->seeErrorEnvelope(404, 'not_found');
        $I->seeRecord(ChildProfile::class, ['id' => $other->id, 'name' => 'Mario', 'archived_at' => null, 'pin_hash' => null]);
    }

    public function userWithoutFamilyIsRejected(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs();
        $I->sendGet('children');
        $I->seeErrorEnvelope(403, 'no_family');
    }

    public function unsupportedVerbIsMethodNotAllowed(FunctionalTester $I): void
    {
        $I->amFamilyMember();
        $I->sendDelete('children');
        $I->seeErrorEnvelope(405, 'method_not_allowed');
    }
}
