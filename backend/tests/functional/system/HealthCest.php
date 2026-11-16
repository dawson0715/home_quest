<?php

declare(strict_types=1);

namespace tests\functional\system;

use FunctionalTester;

final class HealthCest
{
    public function healthIsPublicAndReportsDb(FunctionalTester $I): void
    {
        $I->sendGet('health');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['status' => 'ok', 'db' => true], 'meta' => []]);
    }
}
