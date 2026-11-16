<?php

declare(strict_types=1);

namespace tests\functional\system;

use FunctionalTester;

final class ErrorEnvelopeCest
{
    public function unknownRouteIsNotFound(FunctionalTester $I): void
    {
        $I->sendGet('does-not-exist');
        $I->seeErrorEnvelope(404, 'not_found');
    }

    public function missingTokenIsUnauthorized(FunctionalTester $I): void
    {
        $I->sendGet('me');
        $I->seeErrorEnvelope(401, 'unauthorized');
    }

    public function invalidTokenIsUnauthorized(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer not-a-real-token');
        $I->sendGet('family');
        $I->seeErrorEnvelope(401, 'unauthorized');
    }

    public function validationErrorsCarryCodeAndField(FunctionalTester $I): void
    {
        $I->amFamilyMember();
        $I->sendPatch('family', ['name' => str_repeat('x', 81), 'settings' => ['week_starts_on' => 3]]);
        $codes = $I->seeErrorEnvelope(422, 'validation_failed');
        $I->assertSame(['validation_failed', 'validation_failed'], $codes);
        $fields = $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field');
        $I->assertEqualsCanonicalizing(['name', 'settings'], $fields);
    }

    public function userWithoutFamilyGetsNoFamily(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs();
        $I->sendGet('family');
        $I->seeErrorEnvelope(403, 'no_family');
    }

    public function optionsOnCustomRoutesNeedNoToken(FunctionalTester $I): void
    {
        $I->sendOptions('family/members');
        $I->seeResponseCodeIs(204);
    }
}
