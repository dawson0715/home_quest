<?php

declare(strict_types=1);

namespace app\models;

use app\components\TokenHasher;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $username
 * @property string|null $name
 * @property string|null $email
 * @property string $auth_key
 * @property string $access_token
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read FamilyMember|null $familyMember
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 10;

    public static function tableName()
    {
        return '{{%user}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['username'], 'required'],
            ['username', 'string', 'max' => 64],
            ['username', 'unique'],
            ['name', 'trim'],
            ['name', 'string', 'max' => 80],
            ['name', 'default', 'value' => null],
            ['email', 'trim'],
            ['email', 'email'],
            ['email', 'unique'],
            ['email', 'default', 'value' => null],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE]],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
        ];
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    public function fields()
    {
        return ['id', 'username', 'name'];
    }

    public function getFamilyMember(): ActiveQuery
    {
        return $this->hasOne(FamilyMember::class, ['user_id' => 'id']);
    }

    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['access_token' => $token, 'status' => self::STATUS_ACTIVE]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey)
    {
        return $this->auth_key === $authKey;
    }

    /** Never starts with a device/child-session prefix (see ApiAuth). */
    public static function generateAccessToken(): string
    {
        do {
            $token = Yii::$app->security->generateRandomString(48);
        } while (str_starts_with($token, TokenHasher::DEVICE_PREFIX) || str_starts_with($token, TokenHasher::CHILD_PREFIX));
        return $token;
    }

    public static function generateAuthKey(): string
    {
        return Yii::$app->security->generateRandomString(32);
    }
}
