<?php

declare(strict_types=1);

namespace app\commands;

use Minishlink\WebPush\VAPID;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Web push setup.
 */
class PushController extends Controller
{
    /**
     * Prints a new VAPID key pair to put in config/params-local.php (never commit it).
     */
    public function actionGenerateKeys(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->stdout("<?php\n// config/params-local.php\nreturn [\n    'vapid' => [\n");
        $this->stdout("        'subject' => 'mailto:admin@example.com',\n");
        $this->stdout("        'publicKey' => '{$keys['publicKey']}',\n");
        $this->stdout("        'privateKey' => '{$keys['privateKey']}',\n    ],\n];\n");
        return ExitCode::OK;
    }
}
