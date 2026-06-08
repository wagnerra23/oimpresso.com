<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use App\Services\GrowthBookAdminService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Tool MCP `flag-env-toggle` — mata-switch que liga/desliga feature inteira
 * em um environment. Quando OFF, ninguém recebe a feature independente das
 * rules. Use pra emergência geral / killswitch / pausar canary global.
 */
class FlagEnvToggleTool extends Tool
{
    protected string $name = 'flag-env-toggle';

    protected string $title = 'Mata-switch: liga/desliga feature inteira em um env';

    protected string $description = 'KILLSWITCH global. Liga/desliga uma feature inteira num environment (todas as rules ficam suspensas se OFF). Use pra emergência ampla. Pra rollback de 1 biz específico use `flag-set` (mais cirúrgico).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description('Feature key')
                ->required(),
            'enabled' => $schema->boolean()
                ->description('true=liga environment / false=desliga (mata-switch)')
                ->required(),
            'env' => $schema->string()
                ->description('Environment (default: production)'),
            'clear_cache' => $schema->boolean()
                ->description('Limpa cache local pós-mudança (default: true)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $service = app(GrowthBookAdminService::class);

        if (! $service->isConfigured()) {
            return Response::text('❌ GrowthBookAdminService não configurado.');
        }

        $key = trim((string) $request->get('key', ''));
        if ($key === '') return Response::text('❌ key é obrigatório.');

        if (! $request->has('enabled')) {
            return Response::text('❌ enabled é obrigatório (true/false).');
        }

        $enabled = (bool) $request->get('enabled');
        $env = trim((string) $request->get('env', 'production')) ?: 'production';
        $clearCacheRaw = $request->get('clear_cache', true);
        $clearCache = $clearCacheRaw === null ? true : (bool) $clearCacheRaw;

        try {
            $service->setEnvEnabled($key, $enabled, $env);
        } catch (Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        $action = $enabled ? '🟢 LIGADA' : '🔴 DESLIGADA';
        $msg = "{$action} feature `{$key}` no environment `{$env}`.";

        if ($clearCache) {
            $service->clearLocalCache();
            $msg .= "\n\n_Cache local Laravel limpo._";
        }

        return Response::text($msg);
    }
}
