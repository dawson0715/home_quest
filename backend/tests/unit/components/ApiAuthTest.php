<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\ApiAuth;
use app\models\User;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;

final class ApiAuthTest extends Unit
{
    public function testUserTokensThatLookPrefixedStillResolveToTheUser(): void
    {
        foreach (['c_', 'd_'] as $prefix) {
            $user = FamilyFactory::user();
            $user->access_token = $prefix . str_repeat('x', 46);
            $user->save(false);

            $identity = ApiAuth::resolve($user->access_token);

            $this->assertInstanceOf(User::class, $identity);
            $this->assertSame($user->id, $identity->id);
        }
    }

    public function testGeneratedUserTokensAvoidReservedPrefixes(): void
    {
        for ($i = 0; $i < 3000; $i++) {
            $token = User::generateAccessToken();
            $this->assertDoesNotMatchRegularExpression('/^[cd]_/', $token);
        }
    }

    public function testUnknownTokensResolveToNull(): void
    {
        $this->assertNull(ApiAuth::resolve(''));
        $this->assertNull(ApiAuth::resolve('d_nope'));
        $this->assertNull(ApiAuth::resolve('c_nope'));
        $this->assertNull(ApiAuth::resolve('nope'));
    }
}
