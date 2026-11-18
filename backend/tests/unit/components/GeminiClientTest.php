<?php

declare(strict_types=1);

namespace tests\unit\components;

use app\components\AiException;
use app\components\GeminiClient;
use Codeception\Test\Unit;

final class GeminiClientTest extends Unit
{
    public function testDecodesTheModelOutputText(): void
    {
        $response = [
            'id' => 'x',
            'status' => 'completed',
            'steps' => [
                ['type' => 'thought', 'content' => [['type' => 'text', 'text' => 'ignored']]],
                ['type' => 'model_output', 'content' => [
                    ['type' => 'text', 'text' => '{"suggestions": [{"slot": 1,'],
                    ['type' => 'text', 'text' => ' "recipe": 2, "idea": "", "reason": "ok"}]}'],
                ]],
            ],
        ];
        $this->assertSame(
            ['suggestions' => [['slot' => 1, 'recipe' => 2, 'idea' => '', 'reason' => 'ok']]],
            GeminiClient::decodeAnswer($response)
        );
    }

    public function testRejectsIncompleteOrInvalidAnswers(): void
    {
        foreach ([
            ['status' => 'failed', 'steps' => []],
            ['steps' => []],
            ['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'not json']]]]],
            ['status' => 'completed', 'steps' => []],
        ] as $response) {
            try {
                GeminiClient::decodeAnswer($response);
                $this->fail('expected AiException');
            } catch (AiException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testDisabledWithoutKey(): void
    {
        $this->assertFalse((new GeminiClient(null, 'm'))->isEnabled());
        $this->assertFalse((new GeminiClient('', 'm'))->isEnabled());
        $this->assertTrue((new GeminiClient('k', 'm'))->isEnabled());
        $this->expectException(AiException::class);
        (new GeminiClient(null, 'm'))->completeJson('s', 'i', []);
    }
}
