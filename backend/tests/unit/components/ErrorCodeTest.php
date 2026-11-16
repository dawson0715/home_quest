<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\ApiException;
use app\components\ErrorCode;
use Codeception\Test\Unit;

final class ErrorCodeTest extends Unit
{
    public function testStatusMapping(): void
    {
        $this->assertSame(ErrorCode::NOT_FOUND, ErrorCode::fromStatus(404));
        $this->assertSame(ErrorCode::RATE_LIMITED, ErrorCode::fromStatus(429));
        $this->assertSame(ErrorCode::SERVER_ERROR, ErrorCode::fromStatus(503));
        $this->assertSame(ErrorCode::BAD_REQUEST, ErrorCode::fromStatus(418));
    }

    public function testApiExceptionDefaultsTo422AndExposesField(): void
    {
        $e = new ApiException(ErrorCode::INSUFFICIENT_POINTS, 'Not enough points', field: 'points');
        $this->assertSame(422, $e->statusCode);
        $this->assertSame(
            ['code' => 'insufficient_points', 'message' => 'Not enough points', 'field' => 'points'],
            $e->toError(),
        );
    }
}
