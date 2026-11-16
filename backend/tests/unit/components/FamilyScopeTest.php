<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\ApiException;
use app\components\FamilyContext;
use app\models\FamilyMember;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;
use Yii;

final class FamilyScopeTest extends Unit
{
    private FamilyContext $context;

    protected function _before(): void
    {
        $this->context = Yii::$app->get('familyContext');
        $this->context->reset();
    }

    public function testForFamilyIsolatesFamilies(): void
    {
        $rossi = FamilyFactory::family('Rossi');
        $bianchi = FamilyFactory::family('Bianchi');
        $a = FamilyFactory::member($rossi);
        FamilyFactory::member($rossi, role: FamilyMember::ROLE_COPARENT);
        FamilyFactory::member($bianchi);

        $ids = FamilyMember::find()->forFamily($rossi->id)->select('family_id')->column();

        $this->assertCount(2, $ids);
        $this->assertSame([$rossi->id], array_values(array_unique(array_map('intval', $ids))));

        Yii::$app->user->setIdentity($a->user);
        $this->assertCount(2, FamilyMember::find()->forFamily()->all());
    }

    public function testForFamilyWorksWithAlias(): void
    {
        $rossi = FamilyFactory::family();
        FamilyFactory::member($rossi);

        $count = FamilyMember::find()->alias('m')->forFamily($rossi->id)->count();

        $this->assertSame(1, (int) $count);
    }

    public function testContextExposesFamilyAndRole(): void
    {
        $family = FamilyFactory::family('Verdi');
        $member = FamilyFactory::member($family, role: FamilyMember::ROLE_COPARENT);
        Yii::$app->user->setIdentity($member->user);

        $this->assertSame($family->id, $this->context->getId());
        $this->assertSame('Verdi', $this->context->getFamily()->name);
        $this->assertTrue($this->context->hasRole(FamilyMember::ROLE_ADMIN, FamilyMember::ROLE_COPARENT));
        $this->assertFalse($this->context->hasRole(FamilyMember::ROLE_ADMIN));
    }

    public function testRequireRoleThrowsForbidden(): void
    {
        $member = FamilyFactory::member(FamilyFactory::family(), role: FamilyMember::ROLE_COPARENT);
        Yii::$app->user->setIdentity($member->user);

        try {
            $this->context->requireRole(FamilyMember::ROLE_ADMIN);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->statusCode);
            $this->assertSame('forbidden', $e->errorCode);
        }
    }

    public function testUserWithoutFamilyGetsNoFamilyError(): void
    {
        Yii::$app->user->setIdentity(FamilyFactory::user());

        $this->assertFalse($this->context->isAvailable());
        $this->expectExceptionObject(new ApiException('no_family', 'The user does not belong to any family.', 403));
        $this->context->getId();
    }

    public function testNewRecordInheritsCurrentFamilyAndCannotMove(): void
    {
        $rossi = FamilyFactory::family('Rossi');
        $admin = FamilyFactory::member($rossi);
        Yii::$app->user->setIdentity($admin->user);

        $member = new FamilyMember(['user_id' => FamilyFactory::user()->id, 'role' => FamilyMember::ROLE_COPARENT]);
        $this->assertTrue($member->save());
        $this->assertSame($rossi->id, $member->family_id);

        $member->family_id = FamilyFactory::family('Bianchi')->id;
        $this->expectException(\yii\base\InvalidCallException::class);
        $member->save(false);
    }
}
