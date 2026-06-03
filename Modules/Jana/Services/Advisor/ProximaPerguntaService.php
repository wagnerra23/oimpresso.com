<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Advisor;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\ProximaPerguntaAgent;
use Modules\Jana\Services\BriefDiarioService;
use Throwable;

/**
 * ProximaPerguntaService — orquestra a "próxima-melhor-pergunta proativa"
 * (Jana "Modo Consultor" / Advisor — Metade B, proposta §10.4 / ADR 0245).
 *
 * Pipeline: snapshot do BriefDiarioService (estado real) → resumo compacto → ProximaPerguntaAgent
 * (frontier) → bloco markdown "Perguntas que você deveria fazer agora", por persona, já respondidas.
 * ESTENDE o brief diário (o snapshot é o gancho), não cria do zero.
 *
 * Princípios (mesmos da Metade A):
 *   - DEFAULT-OFF (`copiloto.advisor_questions.enabled`) — Wagner liga depois de validar.
 *   - FAIL-OPEN: qualquer erro → retorna null (o brief sai normal, sem o bloco).
 *   - HONESTIDADE: persona sem sinal forte é OMITIDA; se nenhuma tem, retorna null.
 *   - TIER 0: businessId explícito (ADR 0093), nunca de session.
 *   - MEDIÇÃO: log `advisor_questions_event` (quantas perguntas, quantas personas).
 *
 * @see \Modules\Jana\Ai\Agents\ProximaPerguntaAgent
 */
class ProximaPerguntaService
{
    /**
     * Gera o bloco markdown das perguntas proativas, ou null (desligado / vazio / erro).
     */
    public function gerarBloco(int $businessId, ?string $businessName = null): ?string
    {
        if (! (bool) config('copiloto.advisor_questions.enabled', false)) {
            return null;
        }

        try {
            return OtelHelper::spanBiz('jana.advisor.proxima_pergunta', function () use ($businessId, $businessName) {
                return $this->gerarBlocoInternal($businessId, $businessName);
            }, ['business_id' => $businessId]);
        } catch (Throwable $e) {
            // FAIL-OPEN — o brief nunca quebra por causa do bloco de perguntas.
            Log::channel('copiloto-ai')->warning('advisor_questions fail-open (brief segue sem bloco)', [
                'business_id' => $businessId,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return null;
        }
    }

    protected function gerarBlocoInternal(int $businessId, ?string $businessName): ?string
    {
        $resumo = $this->resumoCompacto($this->snapshot($businessId));

        // Sem nenhum sinal no negócio → não há o que pautar (honestidade).
        if ($this->resumoVazio($resumo)) {
            return null;
        }

        /** @var array<int, array{key: string, label: string, foco: string}> $personas */
        $personas = config('copiloto.advisor_questions.personas', []);
        if (empty($personas)) {
            return null;
        }

        $maxPorPersona = (int) config('copiloto.advisor_questions.max_por_persona', 2);

        $agent = new ProximaPerguntaAgent(
            snapshotResumo: $resumo,
            personas: $personas,
            businessName: $businessName,
            maxPorPersona: $maxPorPersona,
        );

        $resp = $agent->prompt($agent->montarPrompt());
        $blocos = is_array($resp['blocos'] ?? null) ? $resp['blocos'] : [];

        $labelPorKey = collect($personas)->keyBy('key');
        $linhas = [];
        $totalPerguntas = 0;
        $personasComPergunta = 0;

        foreach ($blocos as $bloco) {
            if (! ($bloco['tem_pergunta'] ?? false)) {
                continue; // honestidade — omite persona sem sinal
            }
            $perguntas = is_array($bloco['perguntas'] ?? null) ? $bloco['perguntas'] : [];
            $perguntas = array_slice($perguntas, 0, $maxPorPersona);
            if (empty($perguntas)) {
                continue;
            }

            $key = (string) ($bloco['persona'] ?? '');
            $label = $labelPorKey->get($key)['label'] ?? $key;

            $linhas[] = "**{$label}**";
            foreach ($perguntas as $p) {
                $pergunta = trim((string) ($p['pergunta'] ?? ''));
                $resposta = trim((string) ($p['resposta_curta'] ?? ''));
                if ($pergunta === '') {
                    continue;
                }
                $linhas[] = "- 🔮 *{$pergunta}*";
                if ($resposta !== '') {
                    $linhas[] = "  → {$resposta}";
                }
                $totalPerguntas++;
            }
            $linhas[] = '';
            $personasComPergunta++;
        }

        $this->logEvento($businessId, $personasComPergunta, $totalPerguntas);

        if ($totalPerguntas === 0) {
            return null; // honestidade — nada de alto valor hoje
        }

        $bloco = "## 🔮 Perguntas que você deveria fazer agora\n\n" . implode("\n", $linhas);

        return rtrim($bloco) . "\n";
    }

    /**
     * Seam testável — o snapshot do estado real do negócio (BriefDiarioService).
     * Tests sobrescrevem com fixture (sem precisar montar o schema inteiro do ERP).
     *
     * @return array<string, mixed>
     */
    protected function snapshot(int $businessId): array
    {
        return (new BriefDiarioService($businessId))->snapshot();
    }

    /**
     * Extrai um resumo compacto e estável do snapshot (números + nomes-chave) pro grounding.
     * Mesmos dados que o brief já mostra — não amplia a superfície de exposição.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    protected function resumoCompacto(array $snapshot): array
    {
        $src = is_array($snapshot['sources'] ?? null) ? $snapshot['sources'] : [];

        $vendas = is_array($src['vendas'] ?? null) ? $src['vendas'] : [];
        $inad = is_array($src['inadimplencia'] ?? null) ? $src['inadimplencia'] : [];
        $tickets = is_array($src['tickets'] ?? null) ? $src['tickets'] : [];
        $nfe = is_array($src['nfe'] ?? null) ? $src['nfe'] : [];
        $oport = is_array($src['oportunidades'] ?? null) ? $src['oportunidades'] : [];

        return [
            'vendas' => [
                'mes_corrente' => $vendas['mes_corrente']['total'] ?? null,
                'projecao_fechamento_mes' => $vendas['projecao_fechamento_mes'] ?? null,
                'delta_projetado_pct' => $vendas['delta_projetado_pct'] ?? null,
                'ticket_medio' => $vendas['mes_corrente']['ticket_medio'] ?? null,
            ],
            'inadimplencia' => [
                'total_devido_atrasado' => $inad['total_devido_atrasado'] ?? null,
                'clientes_inadimplentes' => $inad['clientes_inadimplentes_count'] ?? null,
                'top_3_devedores' => array_slice($inad['top_5_devedores'] ?? [], 0, 3),
            ],
            'tickets' => [
                'total_unread' => $tickets['total_unread_business'] ?? null,
                'top_priorizados' => array_slice($tickets['top_5'] ?? [], 0, 3),
            ],
            'nfe' => [
                'rejeitadas_30d' => $nfe['rejeitadas_30d'] ?? null,
                'taxa_rejeicao_pct' => $nfe['taxa_rejeicao_pct'] ?? null,
            ],
            'oportunidades' => [
                'reativacao' => array_slice($oport['reativacao_candidatos'] ?? [], 0, 3),
                'combo' => array_slice($oport['combo_candidatos'] ?? [], 0, 3),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $resumo
     */
    protected function resumoVazio(array $resumo): bool
    {
        $vendasMes = (float) ($resumo['vendas']['mes_corrente'] ?? 0);
        $inad = (float) ($resumo['inadimplencia']['total_devido_atrasado'] ?? 0);
        $unread = (int) ($resumo['tickets']['total_unread'] ?? 0);
        $reativacao = count($resumo['oportunidades']['reativacao'] ?? []);
        $rejeitadas = (int) ($resumo['nfe']['rejeitadas_30d'] ?? 0);

        return $vendasMes <= 0 && $inad <= 0 && $unread <= 0 && $reativacao === 0 && $rejeitadas <= 0;
    }

    protected function logEvento(int $businessId, int $personas, int $perguntas): void
    {
        try {
            Log::channel('copiloto-ai')->info('advisor_questions_event', [
                'business_id' => $businessId,
                'personas_com_pergunta' => $personas,
                'total_perguntas' => $perguntas,
            ]);
        } catch (Throwable) {
            // medição nunca quebra a geração
        }
    }
}
