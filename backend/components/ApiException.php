<?php

declare(strict_types=1);

namespace app\components;

use yii\web\HttpException;

/**
 * Domain/API error with a stable code, rendered as
 * {"data": null, "meta": {"errors": [{"code", "message", "field"?}]}}.
 */
class ApiException extends HttpException
{
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        int $status = 422,
        public readonly ?string $field = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($status, $message, 0, $previous);
    }

    /**
     * @return array{code: string, message: string, field?: string}
     */
    public function toError(): array
    {
        $error = ['code' => $this->errorCode, 'message' => $this->getMessage()];
        if ($this->field !== null) {
            $error['field'] = $this->field;
        }
        return $error;
    }
}
