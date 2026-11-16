<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\ApiBehaviors;
use app\rest\Serializer;
use yii\rest\Controller;

/**
 * Base for custom (non-CRUD) REST endpoints, sharing auth/CORS/envelope with ApiActiveController.
 */
class ApiController extends Controller
{
    use EnvelopeMetaTrait;

    /** @var string|array<string, mixed> */
    public $serializer = Serializer::class;

    /**
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        return ApiBehaviors::apply(parent::behaviors());
    }
}
