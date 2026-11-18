<?php

declare(strict_types=1);

namespace app\forms;

use app\models\ChildProfile;
use app\models\User;
use Throwable;
use Yii;
use yii\base\Model;

/**
 * PATCH /me (parent): partial update of the parent's personal data and game profile.
 * `name` is written to both the user and the game profile; avatar/color only to the profile.
 */
class ProfileUpdateForm extends Model
{
    public mixed $name = null;
    public mixed $email = null;
    public mixed $avatar = null;
    public mixed $color = null;

    /** @var list<string> fields present in the body (absent = unchanged; email null/"" = cleared) */
    private array $sent = [];

    public function __construct(private readonly User $user, private readonly ?ChildProfile $player, array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            // type checks only: value rules live in User/ChildProfile
            [['name', 'email', 'avatar', 'color'], 'string'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function load($data, $formName = null): bool
    {
        $this->sent = array_keys(array_intersect_key($data, array_flip(['name', 'email', 'avatar', 'color'])));
        return parent::load($data, $formName);
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $sent = array_flip($this->sent);
        if (isset($sent['name'])) {
            $this->user->name = $this->name;
            if ($this->player !== null) {
                $this->player->name = (string) $this->name;
            }
        }
        if (isset($sent['email'])) {
            $this->user->email = $this->email === '' ? null : $this->email;
        }
        if ($this->player !== null) {
            foreach (['avatar', 'color'] as $attribute) {
                if (isset($sent[$attribute])) {
                    $this->player->$attribute = $this->$attribute;
                }
            }
        }

        $valid = $this->user->validate(['name', 'email']);
        $this->addErrors($this->user->getErrors());
        if ($this->player !== null) {
            $valid = $this->player->validate(['name', 'avatar', 'color']) && $valid;
            $this->addErrors($this->player->getErrors());
        }
        if (!$valid) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->user->save(false, ['name', 'email', 'updated_at']);
            $this->player?->save(false, ['name', 'avatar', 'color', 'updated_at']);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        return true;
    }
}
