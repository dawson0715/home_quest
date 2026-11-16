<?php

declare(strict_types=1);

namespace app\rest;

use app\components\ErrorCode;
use Yii;
use yii\base\Model;
use yii\data\DataProviderInterface;

/**
 * Wraps every REST response (CRUD and custom actions alike) in a consistent
 * {"data": ..., "meta": ...} envelope, since yii\rest\Serializer only wraps
 * collections when collectionEnvelope is set and leaves single models/plain
 * arrays/errors in incompatible shapes otherwise.
 */
class Serializer extends \yii\rest\Serializer
{
    /**
     * Guards against double-wrapping: parent::serialize() recurses into
     * $this->serialize($value) for each element of a plain array, so without
     * this guard every nested value would get its own {"data":...,"meta":...}.
     */
    private bool $entered = false;

    public function serialize($data)
    {
        if ($this->entered) {
            return parent::serialize($data);
        }

        $this->entered = true;
        try {
            return $this->envelope($data);
        } finally {
            $this->entered = false;
        }
    }

    /**
     * @return array{data: mixed, meta: array<string, mixed>|\stdClass}
     */
    private function envelope(mixed $data): array
    {
        $meta = [];

        if ($data instanceof Model && $data->hasErrors()) {
            $meta['errors'] = array_map(
                static fn (array $e): array => ['code' => ErrorCode::VALIDATION_FAILED] + $e,
                $this->serializeModelErrors($data),
            );
            $payload = null;
        } elseif ($data instanceof DataProviderInterface) {
            $payload = $this->serializeDataProvider($data);
            $pagination = $data->getPagination();
            if ($pagination !== false) {
                $meta['pagination'] = [
                    'totalCount' => $pagination->totalCount,
                    'pageCount' => $pagination->getPageCount(),
                    'currentPage' => $pagination->getPage() + 1,
                    'perPage' => $pagination->getPageSize(),
                ];
            }
        } else {
            $payload = parent::serialize($data);
        }

        // e.g. yii\rest\OptionsAction sets 405 and returns null without throwing
        if (empty($meta['errors']) && $payload === null && $this->response->statusCode >= 400) {
            $meta['errors'] = [[
                'code' => ErrorCode::fromStatus($this->response->statusCode),
                'message' => $this->response->statusText,
            ]];
        }

        $controller = Yii::$app->controller;
        if ($controller !== null && property_exists($controller, 'responseMeta') && !empty($controller->responseMeta)) {
            $meta = array_merge($meta, $controller->responseMeta);
        }

        return [
            'data' => $payload,
            'meta' => empty($meta) ? new \stdClass() : $meta,
        ];
    }
}
