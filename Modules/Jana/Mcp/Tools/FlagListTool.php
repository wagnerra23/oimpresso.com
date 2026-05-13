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
 * Tool MCP `flag-list` — lista feature flags do GrowthBook admin.
 *
 * Resolve "quais flags estão configuradas e em que estado?" sem o usuário
 * precisar abrir o painel UI.
 */
class FlagListTool extends Tool
{
    protected string $name = 'flag-list';

    protected string $title = 'Listar feature flags (GrowthBook)';

    protected string $description = 'Lista TODAS as feature flags configuradas no GrowthBook admin. Mostra key, defaultValue, valueType, e resumo por environment (enabled + count rules). Use pra responder "quais flags estão ligadas? quais bizs estão em canary?".';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('Filtrar por projectId GrowthBook (omite pra todas).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $service = app(GrowthBookAdminService::class);

        if (! $service->isConfigured()) {
            return Response::text('❌ GrowthBookAdminService não configurado. Wagner precisa definir GROWTHBOOK_ADMIN_API_TOKEN no .env CT 100.');
        }

        $project = trim((string) $request->get('project', ''));

        try {
            $features = $service->listFeatures($project !== '' ? $project : null);
        } catch (Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        if ($features === []) {
            return Response::text('Nenhuma feature flag configurada.');
        }

        $lines = ['**Feature flags GrowthBook:**', ''];
        foreach ($features as $f) {
            $key = $f['id'] ?? '?';
            $default = (string) ($f['defaultValue'] ?? '?');
            $type = (string) ($f['valueType'] ?? 'boolean');
            $envs = $f['environments'] ?? [];
            $envSummary = collect($envs)->map(function ($e, $name) {
                $ruleCount = is_array($e['rules'] ?? null) ? count($e['rules']) : 0;
                $enabled = ($e['enabled'] ?? false) ? '🟢' : '🔴';
                return "{$enabled} {$name} ({$ruleCount} rule" . ($ruleCount === 1 ? '' : 's') . ')';
            })->implode(' · ');
            $lines[] = "- **`{$key}`** ({$type}, default=`{$default}`) — {$envSummary}";
        }

        $lines[] = '';
        $lines[] = '_Use `flag-get <key>` pra ver detalhe das rules._';

        return Response::text(implode("\n", $lines));
    }
}
