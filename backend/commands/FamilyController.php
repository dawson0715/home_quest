<?php

declare(strict_types=1);

namespace app\commands;

use app\models\Family;
use app\models\FamilyMember;
use app\models\User;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Manages families while there is no signup flow (docs/02-architettura.md §5).
 */
class FamilyController extends Controller
{
    /**
     * Creates a family with an existing user as admin.
     *
     * @param string $name family name, e.g. "Rossi"
     * @param string $username existing user (see user/create), must not belong to a family yet
     */
    public function actionCreate(string $name, string $username): int
    {
        $user = $this->findUser($username);
        if ($user === null) {
            return ExitCode::DATAERR;
        }

        $tx = Family::getDb()->beginTransaction();
        $family = new Family(['name' => $name]);
        if (!$family->save()) {
            $tx->rollBack();
            return $this->fail('Could not create family', $family->getErrors());
        }
        $member = new FamilyMember(['family_id' => $family->id, 'user_id' => $user->id, 'role' => FamilyMember::ROLE_ADMIN]);
        if (!$member->save()) {
            $tx->rollBack();
            return $this->fail('Could not add admin', $member->getErrors());
        }
        $tx->commit();

        $this->stdout("Created family #{$family->id} \"{$family->name}\" with admin \"{$user->username}\"\n");
        return ExitCode::OK;
    }

    /**
     * Adds an existing user to a family.
     *
     * @param int $familyId
     * @param string $username existing user, must not belong to a family yet
     * @param string $role admin|coparent
     */
    public function actionAddMember(int $familyId, string $username, string $role = FamilyMember::ROLE_COPARENT): int
    {
        $user = $this->findUser($username);
        if ($user === null) {
            return ExitCode::DATAERR;
        }

        $member = new FamilyMember(['family_id' => $familyId, 'user_id' => $user->id, 'role' => $role]);
        if (!$member->save()) {
            return $this->fail('Could not add member', $member->getErrors());
        }

        $this->stdout("Added \"{$user->username}\" to family #$familyId as $role\n");
        return ExitCode::OK;
    }

    /**
     * Lists families and their members.
     */
    public function actionList(): int
    {
        foreach (Family::find()->with('members.user')->orderBy('id')->all() as $family) {
            $this->stdout("#{$family->id} {$family->name}\n", Console::BOLD);
            foreach ($family->members as $member) {
                $this->stdout("  - {$member->user->username} ({$member->role})\n");
            }
        }
        return ExitCode::OK;
    }

    private function findUser(string $username): ?User
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("No user named \"$username\" (create it with user/create).\n");
        }
        return $user;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function fail(string $message, array $errors): int
    {
        $this->stderr("$message: " . json_encode($errors) . "\n");
        return ExitCode::DATAERR;
    }
}
