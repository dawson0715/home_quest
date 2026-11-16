<?php

declare(strict_types=1);

namespace app\commands;

use app\models\User;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Provisions users and their bearer tokens (no signup flow yet, see docs/02-architettura.md §5).
 */
class UserController extends Controller
{
    /**
     * Creates a user and prints its bearer token.
     *
     * @param string $username login name
     * @param string|null $name display name, e.g. "Anna"
     */
    public function actionCreate(string $username, ?string $name = null): int
    {
        if (User::find()->where(['username' => $username])->exists()) {
            $this->stderr("A user named \"$username\" already exists.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $user = new User(['username' => $username, 'name' => $name, 'status' => User::STATUS_ACTIVE]);
        $user->auth_key = User::generateAuthKey();
        $user->access_token = User::generateAccessToken();

        if (!$user->save()) {
            $this->stderr("Could not create user: " . json_encode($user->getErrors()) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Created user #{$user->id} \"{$user->username}\"\n");
        $this->stdout("access_token: {$user->access_token}\n");

        return ExitCode::OK;
    }

    /**
     * Sets (or clears with "-") the email used for the weekly summary.
     */
    public function actionSetEmail(string $username, string $email): int
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("No user named \"$username\" found.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $user->email = $email === '-' ? null : $email;
        if (!$user->validate(['email'])) {
            $this->stderr('Invalid email: ' . $user->getFirstError('email') . "\n");
            return ExitCode::DATAERR;
        }
        $user->save(false, ['email', 'updated_at']);
        $this->stdout("Email of \"$username\" set.\n");
        return ExitCode::OK;
    }

    public function actionRegenerateToken(string $username): int
    {
        $user = User::findOne(['username' => $username]);
        if ($user === null) {
            $this->stderr("No user named \"$username\" found.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $user->access_token = User::generateAccessToken();
        if (!$user->save(false, ['access_token'])) {
            $this->stderr("Could not update token.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("New access_token for \"{$user->username}\": {$user->access_token}\n");

        return ExitCode::OK;
    }
}
