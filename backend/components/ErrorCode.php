<?php

declare(strict_types=1);

namespace app\components;

/**
 * Stable machine-readable error codes returned in meta.errors[].code.
 * The frontend translates these, never the backend message.
 */
final class ErrorCode
{
    public const BAD_REQUEST = 'bad_request';
    public const UNAUTHORIZED = 'unauthorized';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const CONFLICT = 'conflict';
    public const UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';
    public const RATE_LIMITED = 'rate_limited';
    public const SERVER_ERROR = 'server_error';

    public const VALIDATION_FAILED = 'validation_failed';
    public const NO_FAMILY = 'no_family';
    public const INSUFFICIENT_POINTS = 'insufficient_points';
    public const INVALID_TRANSITION = 'invalid_transition';
    public const CHECKLIST_INCOMPLETE = 'checklist_incomplete';
    public const TASK_TAKEN = 'task_taken';
    public const REWARD_LIMIT_REACHED = 'reward_limit_reached';
    public const REWARD_UNAVAILABLE = 'reward_unavailable';
    public const PENALTIES_DISABLED = 'penalties_disabled';
    public const PAIRING_CODE_INVALID = 'pairing_code_invalid';
    public const PIN_INVALID = 'pin_invalid';
    public const IMAGE_INVALID = 'image_invalid';

    private const BY_STATUS = [
        400 => self::BAD_REQUEST,
        401 => self::UNAUTHORIZED,
        403 => self::FORBIDDEN,
        404 => self::NOT_FOUND,
        405 => self::METHOD_NOT_ALLOWED,
        409 => self::CONFLICT,
        415 => self::UNSUPPORTED_MEDIA_TYPE,
        422 => self::VALIDATION_FAILED,
        429 => self::RATE_LIMITED,
    ];

    public static function fromStatus(int $status): string
    {
        return self::BY_STATUS[$status] ?? ($status >= 500 ? self::SERVER_ERROR : self::BAD_REQUEST);
    }
}
