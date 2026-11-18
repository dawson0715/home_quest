<?php

declare(strict_types=1);

namespace app\commands;

use app\components\AiClient;
use app\components\AiException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * AI provider (menu suggestions).
 */
class AiController extends Controller
{
    /**
     * Sends a tiny request without any family data to check key, model and API format.
     */
    public function actionCheck(): int
    {
        /** @var AiClient $ai */
        $ai = Yii::$container->get(AiClient::class);
        if (!$ai->isEnabled()) {
            $this->stderr("No API key: set GEMINI_API_KEY in backend/.env (or params-local.php).\n");
            return ExitCode::CONFIG;
        }
        $schema = [
            'type' => 'object',
            'properties' => ['dish' => ['type' => 'string'], 'season' => ['type' => 'string']],
            'required' => ['dish', 'season'],
        ];
        $started = microtime(true);
        try {
            $answer = $ai->completeJson('Rispondi in italiano.', 'Suggerisci un piatto tipico di novembre.', $schema);
        } catch (AiException $e) {
            $this->stderr('Failed: ' . $e->getMessage() . "\n");
            return ExitCode::UNAVAILABLE;
        }
        $this->stdout(sprintf("OK in %.1fs (model: %s)\n%s\n", microtime(true) - $started, Yii::$app->params['gemini']['model'], json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)));
        return ExitCode::OK;
    }
}
