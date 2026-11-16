<?php

declare(strict_types=1);

namespace tests\functional\security;

use app\models\ChildProfile;
use app\models\FamilyMember;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class SecurityCest
{
    /** Parent-only endpoints: [method, url] (ids filled per test). */
    private const PARENT_ONLY = [
        ['GET', 'family/members'], ['PATCH', 'family'], ['GET', 'family/export'], ['DELETE', 'family'],
        ['GET', 'children'], ['POST', 'children'], ['GET', 'children/{child}/transactions'], ['POST', 'children/{child}/adjust'],
        ['PUT', 'children/{child}/pin'], ['GET', 'devices'], ['POST', 'devices/pairing-code'],
        ['GET', 'tasks'], ['POST', 'tasks'], ['GET', 'task-templates'], ['GET', 'occurrences'],
        ['POST', 'occurrences/bulk-approve'], ['POST', 'rewards'], ['GET', 'stats'],
        ['POST', 'recipes'], ['GET', 'ingredients'], ['GET', 'shopping-categories'], ['GET', 'favorite-items'],
        ['PUT', 'meal-plans/2026-11-16/slots/0/dinner'], ['POST', 'meal-plans/2026-11-16/copy-from'],
        ['GET', 'meal-templates'], ['POST', 'shopping-lists'],
    ];

    /** Child-only endpoints. */
    private const CHILD_ONLY = [
        ['GET', 'me/today'], ['GET', 'me/transactions'], ['PUT', 'me/savings-goal'],
        ['POST', 'meal-plans/2026-11-16/proposals'], ['GET', 'devices/profiles'],
    ];

    private FamilyMember $parent;
    private ChildProfile $child;

    public function _before(FunctionalTester $I): void
    {
        Yii::$app->cache->flush();
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->child = FamilyFactory::child($this->parent->family, 'Luca');
    }

    private function call(FunctionalTester $I, string $method, string $url): void
    {
        $url = str_replace('{child}', (string) $this->child->id, $url);
        $I->{'send' . ucfirst(strtolower($method))}($url, []);
    }

    public function parentOnlyEndpointsRejectChildrenDevicesAndAnonymous(FunctionalTester $I): void
    {
        $childToken = FamilyFactory::childToken($this->child);
        foreach (self::PARENT_ONLY as [$method, $url]) {
            $I->deleteHeader('Authorization');
            $this->call($I, $method, $url);
            $I->seeErrorEnvelope(401, 'unauthorized');

            $I->haveHttpHeader('Authorization', "Bearer $childToken");
            $this->call($I, $method, $url);
            $I->seeErrorEnvelope(403, 'forbidden');
        }
    }

    public function childOnlyEndpointsRejectParents(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->parent->user);
        foreach (self::CHILD_ONLY as [$method, $url]) {
            $this->call($I, $method, $url);
            $I->seeErrorEnvelope(403, 'forbidden');
        }
    }

    public function securityHeadersOnEveryResponse(FunctionalTester $I): void
    {
        foreach (['health', 'does-not-exist', 'me'] as $url) {
            $I->sendGet($url);
            $I->seeHttpHeader('X-Content-Type-Options', 'nosniff');
            $I->seeHttpHeader('X-Frame-Options', 'DENY');
            $I->seeHttpHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        }
    }

    public function corsOnlyForConfiguredOrigins(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
        $I->haveHttpHeader('Origin', 'http://localhost:5173');
        $I->sendOptions('me');
        $I->seeHttpHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $I->haveHttpHeader('Origin', 'https://evil.example');
        $I->sendOptions('me');
        $I->dontSeeHttpHeader('Access-Control-Allow-Origin');
    }

    public function invalidTokensAreRateLimitedPerIp(FunctionalTester $I): void
    {
        for ($i = 0; $i < 20; $i++) {
            $I->haveHttpHeader('Authorization', 'Bearer guess-' . $i);
            $I->sendGet('me');
            $I->seeErrorEnvelope(401, 'unauthorized');
        }
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendGet('me');
        $I->seeErrorEnvelope(429, 'rate_limited');
    }
}
