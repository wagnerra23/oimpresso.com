<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

/**
 * MEM-S10-1 (ADR 0037 Sprint 10) — HyDE Query Expansion.
 *
 * Hypothetical Document Embeddings: gera "documento hipotético" que
 * RESPONDERIA a pergunta do user, e usa esse documento (não a query
 * original) pra busca semântica. Bridge "phrasing gap".
 *
 * Exemplo:
 *   User: "como tá o caixa?" (curto, ambíguo)
 *   HyDE doc: "O caixa atual da empresa é R$ X. Considera entradas
 *              efetivas (transaction_payments) descontando inadimplência.
 *              Compara com mês anterior pra identificar tendência."
 *   → embedding desse doc tem MUITO mais sinal pra match com facts
 *     tipo "faturamento líquido R$ 27.272 abril 2026"
 *
 * Ganho esperado (literatura 2026): +15% Recall@10.
 * Custo: 1× LLM cheap call (~80 tokens out @ gpt-4o-mini = ~R$ 0,000264)
 * Cache: 1h TTL (perguntas iguais reusam expansão).
 *
 * Trigger: chamado por MeilisearchDriver::buscar quando feature ativa.
 */
class HydeQueryExpander
{
    /**
     * Expande query original em documento hipotético.
     * Retorna [query_original, doc_hipotetico] pra usar com RRF entre os 2.
     */
    public function expandir(string $queryOriginal, ?array $contextoMinimal = null): array
    {
        if (! config('copiloto.hyde.enabled', false)) {
            return [$queryOriginal];
        }

        $cacheKey = 'hyde:' . hash('sha256', $queryOriginal . json_encode($contextoMinimal));
        $cached = Cache::tags(['copiloto:hyde'])->get($cacheKey);
        if ($cached !== null) {
            return [$queryOriginal, $cached];
        }

        try {
            $agent = new AnonymousAgent(
                instructions: $this->systemPrompt(),
                messages: [],
                tools: [],
            );

            $userPrompt = "Pergunta do user:\n{$queryOriginal}";
            if ($contextoMinimal !== null) {
                $userPrompt .= "\n\nContexto mínimo:\n" . json_encode($contextoMinimal, JSON_UNESCAPED_UNICODE);
            }
            $userPrompt .= "\n\nGere o documento hipotético em ~80 tokens.";

            $response = $agent->prompt($userPrompt);
            $docHipotetico = (string) $response;

            Cache::tags(['copiloto:hyde'])->put($cacheKey, $docHipotetico, now()->addHour());

            Log::channel('copiloto-ai')->info('HyDE: expandido', [
                'query' => mb_substr($queryOriginal, 0, 100),
                'doc_hipotetico_len' => mb_strlen($docHipotetico),
            ]);

            return [$queryOriginal, $docHipotetico];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('HyDE: falha (degradação): ' . $e->getMessage());
            return [$queryOriginal];
        }
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
        Você é um query-expander para sistema RAG.

        Receba uma pergunta CURTA do user e gere um DOCUMENTO HIPOTÉTICO que
        responderia a ela COMO SE você tivesse acesso aos dados reais.

        REGRAS:
        - Português brasileiro (mesmo estilo da pergunta original)
        - 50-80 tokens (curto e denso)
        - Use jargão de negócio brasileiro: "faturamento líquido", "ticket médio",
          "ROI", "DRE", "fluxo de caixa", "ciclo financeiro"
        - Inclua palavras-chave que provavelmente aparecem em facts armazenados
          (R$, datas, percentuais, nomes de produtos/clientes hipotéticos)
        - NÃO invente números específicos — use placeholders genéricos quando
          não tiver dados ("R$ X", "[mês mais forte]", "[top cliente]")
        - Foque em SINAIS LEXICAIS que ajudam o vector match — não em ser correto

        EXEMPLO:
        Pergunta: "como tá o caixa?"
        Doc hipotético: "Caixa atual considera transactions com pagamento confirmado
        nos últimos 30 dias. Faturamento líquido descontando devoluções fica em R$ X.
        Comparativo com mês anterior indica tendência de crescimento ou queda."

        EVITAR:
        - Saudações ("Olá!", "Aqui está")
        - Promessas ("vou te dizer")
        - Markdown
        PROMPT;
    }
}
