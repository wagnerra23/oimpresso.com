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
 * Tool MCP `flag-set` — liga/desliga feature pra um business_id específico.
 *
 * Convenção: rule id estável `biz-{N}` com condition `{"business_id": N}`.
 * Use `remove=true` pra apagar a rule (biz volta a seguir defaultValue).
 *
 * Audit: cada chamada grava em `feature_flag_audits` (independente do MCP audit
 * que cobre tool calls).
 */
class FlagSetTool extends Tool
{
    protected string $name = 'flag-set';

    protected string $title = 'Setar feature flag pra um business_id';

    protected string $description = 'Liga/desliga feature flag pra um business_id específico (rule biz-{N} com condition {"business_id": N}). Use `value=true` pra ativar, `value=false` pra forçar desligado, `remove=true` pra apagar a rule (biz volta pro defaultValue). Audit gravado em feature_flag_audits. Por default limpa cache local Laravel após.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description('Feature key (ex: useV2SellsCreate)')
                ->required(),
            'biz_id' => $schema->number()
                ->description('business_id alvo (inteiro positivo)')
                ->required(),
            'value' => $schema->boolean()
                ->description('Valor que será forçado pro biz alvo (true=feature ON). Ignorado se remove=true.'),
            'remove' => $schema->boolean()
                ->description('Se true, remove a rule biz-{N} (biz volta pro defaultValue). Default: false.'),
            'env' => $schema->string()
                ->description('Environment (default: production)'),
            'clear_cache' => $schema->boolean()
                ->description('Limpa cache local Laravel pós-mudança (default: true)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $service = app(GrowthBookAdminService::class);

        if (! $service->isConfigured()) {
            return Response::text('❌ GrowthBookAdminService não configurado. Wagner precisa setar GROWTHBOOK_ADMIN_API_TOKEN no .env.');
        }

        $key = trim((string) $request->get('key', ''));
        if ($key === '') return Response::text('❌ key é obrigatório.');

        $bizId = (int) $request->get('biz_id', 0);
        if ($bizId <= 0) return Response::text('❌ biz_id deve ser inteiro positivo.');

        $env = trim((string) $request->get('env', 'production')) ?: 'production';
        $remove = (bool) $request->get('remove', false);
        $clearCache = $request->get('clear_cache', true);
        $clearCache = $clearCache === null ? true : (bool) $clearCache;

        try {
            if ($remove) {
                $service->removeBizRule($key, $bizId, $env);
                $msg = "✅ Rule `biz-{$bizId}` removida de `{$key}` ({$env}). Biz volta pro defaultValue.";
            } else {
                if (! $request->has('value')) {
                    return Response::text('❌ value é obrigatório quando remove=false.');
                }
                $forceValue = (bool) $request->get('value');
                $service->setBizRule($key, $bizId, $forceValue, $env);
                $valueStr = $forceValue ? 'true' : 'false';
                $msg = "✅ Rule `biz-{$bizId}` em `{$key}` ({$env}) → value=`{$valueStr}`.";
            }
        } catch (Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        if ($clearCache) {
            $service->clearLocalCache();
            $msg .= "\n\n_Cache local Laravel limpo (refetch imediato)._";
        } else {
            $msg .= "\n\n_Cache local Laravel TTL 60s — pode demorar até 1min pra propagar._";
        }

        $msg .= "\n\nAuditoria: 1 linha em `feature_flag_audits`.";

        return Response::text($msg);
    }
}
