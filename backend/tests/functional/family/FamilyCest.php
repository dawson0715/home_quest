<?php

declare(strict_types=1);

namespace tests\functional\family;

use app\models\Family;
use app\models\FamilyMember;
use FunctionalTester;
use tests\_support\FamilyFactory;

final class FamilyCest
{
    public function meReturnsUserFamilyAndRole(FunctionalTester $I): void
    {
        $family = FamilyFactory::family('Rossi');
        $member = $I->amFamilyMember($family, FamilyMember::ROLE_COPARENT);

        $I->sendGet('me');

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => [
            'user' => ['id' => $member->user_id, 'username' => $member->user->username],
            'family' => ['id' => $family->id, 'name' => 'Rossi', 'settings' => Family::DEFAULT_SETTINGS],
            'role' => 'coparent',
        ]]);
        $I->dontSeeResponseJsonMatchesJsonPath('$.data.user.access_token');
    }

    public function meWithoutFamilyHasNullFamily(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs();
        $I->sendGet('me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['family' => null, 'role' => null]]);
    }

    public function patchMergesSettings(FunctionalTester $I): void
    {
        $family = FamilyFactory::family('Rossi');
        $family->settings = ['week_starts_on' => 7];
        $family->save(false);
        $I->amFamilyMember($family, FamilyMember::ROLE_COPARENT);

        $I->sendPatch('family', ['name' => '  Rossi Bianchi ', 'settings' => ['allow_penalties' => true]]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => [
            'name' => 'Rossi Bianchi',
            'settings' => ['allow_penalties' => true, 'week_starts_on' => 7, 'shopping_poll_seconds' => 5],
        ]]);
        $I->seeRecord(Family::class, ['id' => $family->id, 'name' => 'Rossi Bianchi']);
    }

    public function patchRejectsUnknownOrInvalidSettings(FunctionalTester $I): void
    {
        $I->amFamilyMember();

        $I->sendPatch('family', ['settings' => ['meal_types' => ['brunch']]]);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPatch('family', ['settings' => ['foo' => 1]]);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPatch('family', ['settings' => 'nope']);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function membersListsOnlyOwnFamily(FunctionalTester $I): void
    {
        $rossi = FamilyFactory::family('Rossi');
        $bianchi = FamilyFactory::family('Bianchi');
        $other = FamilyFactory::member($bianchi);
        $coparent = FamilyFactory::member($rossi, FamilyFactory::user('marco', 'Marco'), FamilyMember::ROLE_COPARENT);
        $me = $I->amFamilyMember($rossi);

        $I->sendGet('family/members');

        $I->seeResponseCodeIs(200);
        $ids = $I->grabDataFromResponseByJsonPath('$.data[*].id');
        $I->assertSame([$coparent->id, $me->id], $ids);
        $I->assertNotContains($other->id, $ids);
        $I->seeResponseContainsJson(['data' => [['username' => 'marco', 'name' => 'Marco', 'role' => 'coparent']]]);
    }

    public function familyIsIsolated(FunctionalTester $I): void
    {
        $bianchi = FamilyFactory::family('Bianchi');
        FamilyFactory::member($bianchi);
        $I->amFamilyMember(FamilyFactory::family('Rossi'));

        $I->sendGet('family');
        $I->seeResponseContainsJson(['data' => ['name' => 'Rossi']]);

        $I->sendPatch('family', ['name' => 'Hacked']);
        $I->seeRecord(Family::class, ['id' => $bianchi->id, 'name' => 'Bianchi']);
    }
}
