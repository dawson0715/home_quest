<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\commands\SeedController;
use app\models\ChildProfile;
use app\models\Family;
use app\models\ShoppingItem;
use app\models\Task;
use Codeception\Test\Unit;
use Yii;
use yii\console\ExitCode;

final class SeedTest extends Unit
{
    public function testDemoSeedCreatesAUsableFamilyOnce(): void
    {
        $controller = new SeedController('seed', Yii::$app);
        $first = $controller->actionDemo();
        $second = $controller->actionDemo();

        $this->assertSame(ExitCode::OK, $first);
        $this->assertSame(ExitCode::DATAERR, $second);
        $this->assertTrue(\app\models\User::find()->where(['username' => 'demo-anna'])->exists());
        $family = Family::findOne(['name' => 'Rossi']);
        $this->assertNotNull($family);
        $this->assertSame(2, (int) ChildProfile::find()->where(['family_id' => $family->id, 'user_id' => null])->count());
        $this->assertSame(5, (int) Task::find()->where(['family_id' => $family->id])->count());
        $this->assertGreaterThan(5, (int) ShoppingItem::find()->innerJoinWith('list l', false)->count());
    }
}
