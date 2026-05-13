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
 * Tool MCP `flag-get` — detalha 1 feature flag (rules, condition, value, enabled).
 */
class FlagGetTool extends Tool
{
    protected string $name = 'flag-get';

    protected string $title = 'Detalhar 1 feature flag';

    protected string $description = 'Mostra detalhe completo de 1 feature flag: defaultValue, valueType, e tabela das rules de targeting (id, type, value, condition JSON, enabled) no environment escolhido. Use pra "qual o estado da flag X pra biz=N hoje?".';

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description('Feature key (ex: useV2SellsCreate)')
                ->required(),
            'env' => $schema->string()
                ->description('Environment (production|dev|staging). Default: production.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $service = app(GrowthBookAdminService::class);

        if (! $service->isConfigured()) {
            return Response::text('❌ GrowthBookAdminService não configurado.');
        }

        $key = trim((string) $request->get('key', ''));
        if ($key === '') {
            return Response::text('❌ key é obrigatório.');
        }
        $env = trim((string) $request->get('env', 'production')) ?: 'production';

        try {
            $feature = $service->getFeature($key);
        } catch (Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        if ($feature === null) {
            return Response::text("❌ Feature `{$key}` não encontrada no GrowthBook.");
        }

        $out = ["**Feature `{$key}`**"];
        $out[] = '- defaultValue: `' . ((string) ($feature['defaultValue'] ?? '?')) . '`';
        $out[] = '- valueType: `' . ((string) ($feature['valueType'] ?? 'boolean')) . '`';
        $out[] = '';

        $envs = $feature['environments'] ?? [];
        if (! isset($envs[$env])) {
            $out[] = "⚠️ Environment `{$env}` não definido. Disponíveis: " . implode(', ', array_keys($envs));
            return Response::text(implode("\n", $out));
        }

        $envData = $envs[$env];
        $envEnabled = ($envData['enabled'] ?? false) ? '🟢 ON' : '🔴 OFF';
        $out[] = "**Environment `{$env}` [{$envEnabled}]**";
        $out[] = '';

        $rules = $envData['rules'] ?? [];
        if ($rules === []) {
            $out[] = '_(sem rules — usa defaultValue)_';
            return Response::text(implode("\n", $out));
        }

        $out[] = '| ID | Type | Value | Condition | Enabled |';
        $out[] = '|---|---|---|---|---|';
        foreach ($rules as $r) {
            $out[] = sprintf(
                '| `%s` | %s | `%s` | `%s` | %s |',
                (string) ($r['id'] ?? '?'),
                (string) ($r['type'] ?? '?'),
                (string) ($r['value'] ?? '?'),
                str_replace('|', '\\|', (string) ($r['condition'] ?? '(none)')),
                ($r['enabled'] ?? false) ? '🟢' : '🔴',
            );
        }

        return Response::text(implode("\n", $out));
    }
}
