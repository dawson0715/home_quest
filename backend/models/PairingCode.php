<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;

/**
 * One-time 6-char code to pair a device (15 minutes).
 *
 * @property int $id
 * @property int $family_id
 * @property string $code
 * @property string $expires_at
 * @property string|null $used_at
 * @property int $created_by
 * @property int $created_at
 * @property int $updated_at
 */
class PairingCode extends FamilyScopedRecord
{
    /** No 0/O/1/I to avoid confusion when typed by kids. */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    public const LENGTH = 6;
    public const TTL_SECONDS = 900;

    public static function tableName(): string
    {
        return '{{%pairing_code}}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $code));
    }

    public static function randomCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }
}
