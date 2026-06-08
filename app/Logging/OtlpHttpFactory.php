<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Level;
use Monolog\Logger;

/**
 * US-INFRA-016 PR-1 (ADR 0132) — Factory pro custom log driver `otel-gen-ai-langfuse`.
 *
 * Laravel custom logger factory: `__invoke($config)` retorna Logger instance.
 * Config vem do array em `config/logging.php`:
 *
 *     'otel-gen-ai-langfuse' => [
 *         'driver' => 'custom',
 *         'via' => App\Logging\OtlpHttpFactory::class,
 *         'endpoint' => env('LANGFUSE_HOST') . '/api/public/otel/v1/traces',
 *         'public_key' => env('LANGFUSE_PUBLIC_KEY'),
 *         'secret_key' => env('LANGFUSE_SECRET_KEY'),
 *         'service_name' => env('LANGFUSE_SERVICE_NAME', 'jana'),
 *         'level' => 'info',
 *         'timeout' => 5.0,
 *     ],
 */
class OtlpHttpFactory
{
    public function __invoke(array $config): Logger
    {
        $handler = new OtlpHttpHandler(
            endpoint: (string) ($config['endpoint'] ?? ''),
            publicKey: (string) ($config['public_key'] ?? ''),
            secretKey: (string) ($config['secret_key'] ?? ''),
            serviceName: (string) ($config['service_name'] ?? 'jana'),
            timeoutSeconds: (float) ($config['timeout'] ?? 5.0),
            level: $this->parseLevel($config['level'] ?? 'info'),
        );

        return new Logger('otel-gen-ai-langfuse', [$handler]);
    }

    private function parseLevel(string|int|Level $level): Level
    {
        if ($level instanceof Level) {
            return $level;
        }

        if (is_int($level)) {
            return Level::fromValue($level);
        }

        return Level::fromName(ucfirst(strtolower($level)));
    }
}
