<?php

declare(strict_types=1);

namespace tests\functional\devices;

use app\models\ChildSession;
use app\models\Device;
use app\models\FamilyMember;
use app\models\PairingCode;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class DeviceCest
{
    public function _before(FunctionalTester $I): void
    {
        Yii::$app->cache->flush();
    }

    /** Parent generates a code, the device pairs: returns the device token. */
    private function pairDevice(FunctionalTester $I, FamilyMember $parent, string $name = 'Tablet'): string
    {
        $I->amAuthenticatedAs($parent->user);
        $I->sendPost('devices/pairing-code');
        $code = $I->grabDataFromResponseByJsonPath('$.data.code')[0];

        $I->deleteHeader('Authorization');
        $I->sendPost('devices/pair', ['code' => strtolower(substr($code, 0, 3)) . '-' . substr($code, 3), 'device_name' => $name]);
        $I->seeResponseCodeIs(201);
        return $I->grabDataFromResponseByJsonPath('$.data.device_token')[0];
    }

    private function useToken(FunctionalTester $I, string $token): void
    {
        $I->haveHttpHeader('Authorization', "Bearer $token");
        $I->haveHttpHeader('Content-Type', 'application/json');
    }

    public function fullPairingAndChildLoginFlow(FunctionalTester $I): void
    {
        $parent = $I->amFamilyMember(FamilyFactory::family('Rossi'));
        $luca = FamilyFactory::child($parent->family, 'Luca', ['sort_order' => 2]);
        $anna = FamilyFactory::child($parent->family, 'Anna', ['avatar' => 'owl', 'sort_order' => 1]);
        FamilyFactory::child($parent->family, 'Old', ['archived_at' => '2026-01-01 00:00:00']);

        $I->sendPost('devices/pairing-code');
        $I->seeResponseCodeIs(201);
        $code = $I->grabDataFromResponseByJsonPath('$.data.code')[0];
        $I->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $code);

        $deviceToken = $this->pairDevice($I, $parent);
        $I->assertStringStartsWith('d_', $deviceToken);
        $I->seeRecord(Device::class, ['family_id' => $parent->family_id, 'name' => 'Tablet']);
        $I->dontSeeRecord(Device::class, ['token_hash' => $deviceToken]);

        $this->useToken($I, $deviceToken);
        $I->sendGet('me');
        $I->seeResponseContainsJson(['data' => ['role' => 'device', 'device' => ['name' => 'Tablet'], 'family' => ['name' => 'Rossi']]]);

        $I->sendGet('devices/profiles');
        $I->seeResponseCodeIs(200);
        $I->assertSame(['Anna', 'Luca'], $I->grabDataFromResponseByJsonPath('$.data[*].name'));
        $I->dontSeeResponseJsonMatchesJsonPath('$.data[0].points_balance');

        $I->sendPost('devices/select-child', ['child_id' => $luca->id]);
        $I->seeResponseCodeIs(201);
        $childToken = $I->grabDataFromResponseByJsonPath('$.data.child_token')[0];
        $I->assertStringStartsWith('c_', $childToken);

        $this->useToken($I, $childToken);
        $I->sendGet('me');
        $I->seeResponseContainsJson(['data' => ['role' => 'child', 'child' => ['id' => $luca->id, 'name' => 'Luca'], 'family' => ['name' => 'Rossi']]]);
        $I->sendGet('family');
        $I->seeResponseCodeIs(200);

        $I->assertNotNull($anna->id);
    }

    public function codesAreSingleUseAndExpire(FunctionalTester $I): void
    {
        $parent = $I->amFamilyMember();
        $I->sendPost('devices/pairing-code');
        $code = $I->grabDataFromResponseByJsonPath('$.data.code')[0];

        $I->deleteHeader('Authorization');
        $I->sendPost('devices/pair', ['code' => $code, 'device_name' => 'A']);
        $I->seeResponseCodeIs(201);
        $I->sendPost('devices/pair', ['code' => $code, 'device_name' => 'B']);
        $I->seeErrorEnvelope(422, 'pairing_code_invalid');

        $expired = new PairingCode();
        $expired->family_id = $parent->family_id;
        $expired->code = 'ZZZZZZ';
        $expired->created_by = $parent->user_id;
        $expired->expires_at = '2020-01-01 00:00:00';
        $expired->save(false);
        $I->sendPost('devices/pair', ['code' => 'ZZZZZZ', 'device_name' => 'C']);
        $I->seeErrorEnvelope(422, 'pairing_code_invalid');
    }

    public function pairingIsRateLimited(FunctionalTester $I): void
    {
        for ($i = 0; $i < 10; $i++) {
            $I->sendPost('devices/pair', ['code' => 'AAAAAA', 'device_name' => 'X']);
            $I->seeErrorEnvelope(422, 'pairing_code_invalid');
        }
        $I->sendPost('devices/pair', ['code' => 'AAAAAA', 'device_name' => 'X']);
        $I->seeErrorEnvelope(429, 'rate_limited');
    }

    public function pinIsCheckedAndRateLimited(FunctionalTester $I): void
    {
        $parent = $I->amFamilyMember();
        $child = FamilyFactory::child($parent->family);
        $child->setPin('2468');
        $child->save(false);
        $this->useToken($I, $this->pairDevice($I, $parent));

        $I->sendGet('devices/profiles');
        $I->seeResponseContainsJson(['data' => [['id' => $child->id, 'has_pin' => true]]]);

        $I->sendPost('devices/select-child', ['child_id' => $child->id]);
        $I->seeErrorEnvelope(422, 'pin_invalid');
        $I->sendPost('devices/select-child', ['child_id' => $child->id, 'pin' => '2468']);
        $I->seeResponseCodeIs(201);

        for ($i = 0; $i < 5; $i++) {
            $I->sendPost('devices/select-child', ['child_id' => $child->id, 'pin' => '0000']);
            $I->seeErrorEnvelope(422, 'pin_invalid');
        }
        $I->sendPost('devices/select-child', ['child_id' => $child->id, 'pin' => '2468']);
        $I->seeErrorEnvelope(429, 'rate_limited');
    }

    public function devicesAndChildrenCannotDoParentThings(FunctionalTester $I): void
    {
        $parent = $I->amFamilyMember();
        $child = FamilyFactory::child($parent->family);
        $deviceToken = $this->pairDevice($I, $parent);

        $this->useToken($I, $deviceToken);
        foreach (['children', 'devices', 'family/members'] as $url) {
            $I->sendGet($url);
            $I->seeErrorEnvelope(403, 'forbidden');
        }
        $I->sendPost('devices/pairing-code');
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendPatch('family', ['name' => 'X']);
        $I->seeErrorEnvelope(403, 'forbidden');

        $I->sendPost('devices/select-child', ['child_id' => $child->id]);
        $this->useToken($I, $I->grabDataFromResponseByJsonPath('$.data.child_token')[0]);
        $I->sendGet('children');
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendGet('devices/profiles');
        $I->seeErrorEnvelope(403, 'forbidden');

        $I->amAuthenticatedAs($parent->user);
        $I->sendGet('devices/profiles');
        $I->seeErrorEnvelope(403, 'forbidden');
    }

    public function revokingADeviceKillsItsTokens(FunctionalTester $I): void
    {
        $parent = $I->amFamilyMember();
        $child = FamilyFactory::child($parent->family);
        $deviceToken = $this->pairDevice($I, $parent);
        $this->useToken($I, $deviceToken);
        $I->sendPost('devices/select-child', ['child_id' => $child->id]);
        $childToken = $I->grabDataFromResponseByJsonPath('$.data.child_token')[0];

        $I->amAuthenticatedAs($parent->user);
        $I->sendGet('devices');
        $I->seeResponseContainsJson(['data' => [['name' => 'Tablet', 'revoked' => false]]]);
        $deviceId = $I->grabDataFromResponseByJsonPath('$.data[0].id')[0];

        $I->sendDelete("devices/$deviceId");
        $I->seeResponseCodeIs(204);
        $I->dontSeeRecord(ChildSession::class, ['device_id' => $deviceId]);

        foreach ([$deviceToken, $childToken] as $token) {
            $this->useToken($I, $token);
            $I->sendGet('me');
            $I->seeErrorEnvelope(401, 'unauthorized');
        }

        $I->amAuthenticatedAs($parent->user);
        $I->sendGet('devices');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
    }

    public function archivingAChildEndsItsSessions(FunctionalTester $I): void
    {
        $parent = $I->amFamilyMember();
        $child = FamilyFactory::child($parent->family);
        $this->useToken($I, $this->pairDevice($I, $parent));
        $I->sendPost('devices/select-child', ['child_id' => $child->id]);
        $childToken = $I->grabDataFromResponseByJsonPath('$.data.child_token')[0];

        $I->amAuthenticatedAs($parent->user);
        $I->sendDelete("children/{$child->id}");

        $this->useToken($I, $childToken);
        $I->sendGet('me');
        $I->seeErrorEnvelope(401, 'unauthorized');
    }

    public function familiesAreIsolated(FunctionalTester $I): void
    {
        $bianchi = FamilyFactory::member(FamilyFactory::family('Bianchi'));
        $otherChild = FamilyFactory::child($bianchi->family, 'Mario');
        $otherDeviceToken = $this->pairDevice($I, $bianchi, 'Altro');
        $otherDeviceId = Device::find()->select('id')->where(['family_id' => $bianchi->family_id])->scalar();

        $rossi = $I->amFamilyMember(FamilyFactory::family('Rossi'));
        $I->sendDelete("devices/$otherDeviceId");
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendGet('devices');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));

        $this->useToken($I, $this->pairDevice($I, $rossi));
        $I->sendGet('devices/profiles');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->sendPost('devices/select-child', ['child_id' => $otherChild->id]);
        $I->seeErrorEnvelope(404, 'not_found');

        $this->useToken($I, $otherDeviceToken);
        $I->sendGet('me');
        $I->seeResponseContainsJson(['data' => ['family' => ['name' => 'Bianchi']]]);
    }
}
