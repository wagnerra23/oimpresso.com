<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

/**
 * MEM-S8-3 (ADR 0037 Sprint 8) — Profile Distiller.
 *
 * Job diário que destila um "perfil compacto" de cada business com base em:
 *   - Faturamento últimos 90d (3 ângulos: bruto/líquido/caixa)
 *   - Top 3 clientes do trimestre
 *   - Metas ativas
 *   - Padrão sazonal (qual mês forte, qual fraco)
 *   - Categoria business (gráfica? plotter? brindes?)
 *
 * Resultado: ~250 tokens texto compactado em copiloto_business_profile.
 * Substitui o `formatarContextoNegocio` do ChatCopilotoAgent (~150-250 tokens
 * dinâmicos hoje) por um snapshot estático refrescado 1×/dia.
 *
 * Diferença vs ContextoNegocio (MEM-HOT-2):
 *   - ContextoNegocio: dados crus (números brutos sem narrativa)
 *   - ProfileDistiller: narrativa compacta com insights (LLM-distilled)
 *
 * Custo: 1× LLM call/dia/business (~500 tokens out @ gpt-4o-mini = R$ 0,002/dia/biz)
 * Ganho: ~30% redução no system prompt (snapshots prontos vs cálculo dinâmico)
 */
class ProfileDistiller
{
    public function __construct(
        protected \Modules\Copiloto\Services\ContextSnapshotService $context,
    ) {
    }

    /**
     * Destila profile pra 1 business. Idempotente — re-executar overrides.
     *
     * @return array{
     *   profile_text: string,
     *   tokens_estimated: int,
     *   raw_context_tokens: int,
     *   compression_ratio: float,
     * }
     */
    public function destilar(int $businessId): array
    {
        // Pega snapshot crus via ContextSnapshotService (já existe)
        $ctx = $this->context->paraBusiness($businessId);
        if ($ctx === null) {
            return [
                'profile_text' => '',
                'tokens_estimated' => 0,
                'raw_context_tokens' => 0,
                'compression_ratio' => 0,
            ];
        }

        // Estimativa raw (chars/4)
        $rawTokens = (int) (mb_strlen(json_encode($ctx)) / 4);

        // LLM destila narrativa compacta
        try {
            $agent = new AnonymousAgent(
                instructions: $this->systemPrompt(),
                messages: [],
                tools: [],
            );
            $response = $agent->prompt(
                "Dados do business pra destilar:\n\n" . json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $profileText = (string) $response;

            // Persiste em cache + DB
            Cache::tags(['copiloto:profile'])->put(
                "profile:{$businessId}",
                $profileText,
                now()->addDay()
            );

            DB::table('copiloto_business_profile')->updateOrInsert(
                ['business_id' => $businessId],
                [
                    'profile_text' => $profileText,
                    'tokens_estimated' => (int) (mb_strlen($profileText) / 4),
                    'raw_context_tokens' => $rawTokens,
                    'gerado_em' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $estTokens = (int) (mb_strlen($profileText) / 4);
            $ratio = $rawTokens > 0 ? round($rawTokens / max(1, $estTokens), 2) : 0;

            Log::channel('copiloto-ai')->info('ProfileDistiller: gerado', [
                'business_id' => $businessId,
                'tokens_estimated' => $estTokens,
                'raw_context_tokens' => $rawTokens,
                'compression_ratio' => $ratio,
            ]);

            return [
                'profile_text' => $profileText,
                'tokens_estimated' => $estTokens,
                'raw_context_tokens' => $rawTokens,
                'compression_ratio' => $ratio,
            ];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('ProfileDistiller: erro', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
            return [
                'profile_text' => '',
                'tokens_estimated' => 0,
                'raw_context_tokens' => $rawTokens,
                'compression_ratio' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lê profile cacheado de um business (cheap — Redis cache).
     */
    public function obter(int $businessId): ?string
    {
        return Cache::tags(['copiloto:profile'])->get("profile:{$businessId}")
            ?: DB::table('copiloto_business_profile')
                ->where('business_id', $businessId)
                ->value('profile_text');
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
        Você é um analista que destila o perfil de uma empresa em narrativa COMPACTA.

        OBJETIVO: ~200 tokens (≈ 800 caracteres) que substituam todos os dados crus.

        FORMATO obrigatório (3 parágrafos curtos, máximo):
        1. Identidade: "Empresa X, do setor Y, com N clientes. Movimentou R$ Z em 90d."
        2. Performance: melhor mês, pior mês, tendência (subindo/caindo/estável), top 2-3 clientes
        3. Metas/sinais: meta ativa atual, % atingido, gargalos

        REGRAS:
        - Português brasileiro
        - Números absolutos (não "muitos", "poucos")
        - Preserva nomes próprios (clientes, produtos)
        - Sem markdown, sem bullets, parágrafos contínuos
        - Sem inventar — se dado falta, omite

        EXEMPLO:
        "Gráfica X (id 4), comunicação visual em SP, com 5993 clientes ativos. Faturou
        R$ 31.513 bruto / R$ 27.272 caixa nos últimos 90 dias.
        Março foi o melhor mês (R$ 310k); abril vem caindo 50%. Top clientes: Y, Z, W.
        Meta atual: R$ 80k/mês — 39% atingido em abril, projeção de não bater."
        PROMPT;
    }
}
