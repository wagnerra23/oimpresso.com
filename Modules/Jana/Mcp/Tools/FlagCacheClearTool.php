<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use App\Services\GrowthBookAdminService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Tool MCP `flag-cache-clear` — invalida cache local Laravel do
 * FeatureFlagService (TTL 60s). Útil quando a mudança foi feita pelo painel
 * UI GrowthBook (não passou pelas tools) e queremos propagação imediata.
 */
class FlagCacheClearTool extends Tool
{
    protected string $name = 'flag-cache-clear';

    protected string $title = 'Invalidar cache local de feature flags';

    protected string $description = 'Limpa cache local Laravel (TTL 60s) do FeatureFlagService — força refetch imediato do GrowthBook. Use quando a mudança veio do painel UI ou de outro processo. As tools `flag-set` e `flag-env-toggle` já limpam o cache por default; esta tool é pra invalidação manual.';

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        app(GrowthBookAdminService::class)->clearLocalCache();
        return Response::text('✅ Cache local Laravel (growthbook.features) limpo. Próxima isOn() vai refetch do GrowthBook.');
    }
}
