<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\base\UserException;
use yii\web\ErrorHandler;
use yii\web\HttpException;

/**
 * Renders every uncaught exception in the API envelope with an error code.
 */
class ApiErrorHandler extends ErrorHandler
{
    /**
     * @param \Throwable $exception
     * @return array<string, mixed>
     */
    protected function convertExceptionToArray($exception): array
    {
        if ($exception instanceof ApiException) {
            $error = $exception->toError();
        } else {
            if (!YII_DEBUG && !$exception instanceof UserException && !$exception instanceof HttpException) {
                $exception = new HttpException(500, Yii::t('yii', 'An internal server error occurred.'));
            }
            $status = $exception instanceof HttpException ? $exception->statusCode : 500;
            $error = ['code' => ErrorCode::fromStatus($status), 'message' => $exception->getMessage()];
        }

        $meta = ['errors' => [$error]];
        if (YII_DEBUG && !$exception instanceof UserException) {
            $meta['debug'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return ['data' => null, 'meta' => $meta];
    }
}
