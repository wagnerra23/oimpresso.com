<?php

namespace Modules\Brief\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gera o Daily Brief via Brain B (claude-sonnet-4-6).
 *
 * Pipeline (ver ADR 0091):
 * 1. CALL refresh_brief_inputs_cache()
 * 2. Lê linha singleton de mcp_brief_inputs_cache
 * 3. Manda pro Anthropic API com prompt fixo
 * 4. Captura tokens consumidos pra cálculo de custo
 *
 * Não valida output — quem valida é BriefValidator (separação de
 * concerns: este service só GERA, validador VALIDA).
 *
 * Anthropic API direta via HTTP (laravel/ai está em composer mas não
 * instalado — ver composer.json). Sem SDK específico.
 */
final class BriefGeneratorService
{
    private const MODEL = 'claude-sonnet-4-6';

    private const TEMPERATURE = 0.2;

    private const MAX_TOKENS = 4096;

    private const STOP_SEQUENCE = "\n---END---";

    /** Custo em USD por 1k tokens — sonnet-4.6 oct/2025 pricing. */
    private const PRICE_INPUT_PER_1K = 0.003;

    private const PRICE_OUTPUT_PER_1K = 0.015;

    private float $lastCallCost = 0.0;

    /**
     * Executa pipeline completo: refresh cache → fetch → Brain B.
     */
    public function generateNow(): string
    {
        DB::statement('CALL refresh_brief_inputs_cache()');

        $aggregated = DB::selectOne(
            'SELECT * FROM mcp_brief_inputs_cache WHERE singleton_id = 1'
        );

        if ($aggregated === null) {
            throw new RuntimeException(
                'mcp_brief_inputs_cache vazio — refresh_brief_inputs_cache() falhou'
            );
        }

        return $this->generateFromAggregated($aggregated);
    }

    /**
     * Gera o brief a partir de um payload já agregado.
     * Útil pra dry-run e golden tests com fixtures.
     *
     * @param object|array $aggregated Linha de mcp_brief_inputs_cache
     */
    public function generateFromAggregated($aggregated): string
    {
        $payload = is_object($aggregated) ? (array) $aggregated : $aggregated;

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($payload);

        $apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY'));
        if (! $apiKey) {
            throw new RuntimeException(
                'ANTHROPIC_API_KEY ausente — configure em .env ou config/services.php'
            );
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'temperature' => self::TEMPERATURE,
                'stop_sequences' => [self::STOP_SEQUENCE],
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Anthropic API erro: HTTP '.$response->status().' '.$response->body()
            );
        }

        $body = $response->json();
        $content = $body['content'][0]['text'] ?? '';

        if (! str_ends_with(trim($content), '---END---')) {
            // Stop sequence cortou — re-anexa pra validador aceitar
            $content = rtrim($content)."\n---END---";
        }

        $this->lastCallCost = $this->computeCost(
            (int) ($body['usage']['input_tokens'] ?? 0),
            (int) ($body['usage']['output_tokens'] ?? 0),
        );

        return $content;
    }

    public function lastCallCost(): float
    {
        return $this->lastCallCost;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Você é o gerador do Daily Brief do projeto Oimpresso ERP.

REGRAS DURAS (não negocie):

1. Output é markdown puro, sem fences, sem preâmbulo.
2. EXATAMENTE 7 seções, NESTA ORDEM, com headers EXATOS:
   ## ESTADO MACRO
   ## EM VOO AGORA
   ## DECISÕES RECENTES (24h)
   ## SKILLS USO 7d
   ## CHARTERS APODRECENDO
   ## FLAGS
   ## METADATA
3. Total ≤3.500 tokens. Conte mentalmente. Se passar, corte da seção
   menos crítica (geralmente SKILLS ou CHARTERS).
4. Termine com a linha exata: \n---END---
5. PT-BR sempre. Tom: telegráfico, denso, factual. Sem floreio.
6. Use emojis SOMENTE em FLAGS (🔴 🟡 🟢) e setas (↑ ↓ →) onde fizer sentido.
7. Datas: "há 3d", "há 2h", "hoje 14h", "ontem". Nunca ISO completo no corpo.
8. Números: pt-BR (R$ 1.234,56 / 47% / 14d).
9. Nunca invente dados. Se um campo veio null/vazio do JSON, escreva "—" ou
   "nada hoje". Nunca preencha com placeholder genérico.
10. NUNCA inclua PII de clientes finais. Você só vê dados internos do time.

ESTRUTURA OBRIGATÓRIA DE CADA SEÇÃO:

## ESTADO MACRO
- Cycle: <codename> (<sprint_label>) · <X>d restantes
- Mission focus: <mission_focus>
- HITL pending Wagner: <N> (top 2 inline se houver)
- Brain B hoje: <pct>% (<spent>/<cap>) <emoji se >70%>

## EM VOO AGORA
Lista numerada. Cada linha:
N. <actor_id> @ <target_path> — <intent_label>, <aging_human>

Limite 8 linhas. Resto vira "+N outros".

## DECISÕES RECENTES (24h)
- ADRs: <lista compacta com IDs e títulos truncados em 50 chars>
- Commits: <N>
- ADS escalações: <N>
- Incidentes: <N>

## SKILLS USO 7d
Lista top 5:
- <skill>: <trigger_count> disparos<, autofix N> <(TIER)>

Se houver candidatas a poda, adicione linha:
- ⚠ Candidatas a poda: <names_csv>

## CHARTERS APODRECENDO
Lista até 5 charters com last_verified >60d:
- <charter_id> (<days_stale>d) — owner: <owner>

Se vazio: "—"

## FLAGS
3 linhas obrigatórias, sempre nesta ordem:
- <emoji> Migration aging: <texto curto>
- <emoji> PRs aguardando review: <texto curto>
- <emoji> Visual regression CI: <texto curto>

Critério emoji:
🔴 = >2 itens críticos | 🟡 = 1 item | 🟢 = nada

## METADATA
- Gerado: <human relative>
- Versão gerador: v1
- Tokens estimados: <N>

VALIDADOR INTERNO antes de devolver:
- 7 headers ##? ✓
- Termina em ---END---? ✓
- Tem PII de cliente final? ✗ (refazer se sim)
- Total tokens ≤3500? ✓
PROMPT;
    }

    private function buildUserPrompt(array $payload): string
    {
        $nowHuman = now()->locale('pt_BR')->isoFormat('DD MMM YYYY · HH:mm');
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        return <<<PROMPT
Gere o Daily Brief com os dados abaixo. Hora atual: {$nowHuman}.

DADOS AGREGADOS (JSON da tabela cache mcp_brief_inputs_cache):

{$payloadJson}

CONTEXTO ADICIONAL (opcional, pode estar vazio):
- Última ADR aprovada e relevante: —
- Mensagem do Wagner pra time hoje: —

Gere o brief seguindo as 10 regras duras do system prompt.
PROMPT;
    }

    private function computeCost(int $inputTokens, int $outputTokens): float
    {
        return round(
            ($inputTokens / 1000) * self::PRICE_INPUT_PER_1K
            + ($outputTokens / 1000) * self::PRICE_OUTPUT_PER_1K,
            4
        );
    }
}
