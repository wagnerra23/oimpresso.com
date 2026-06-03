<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Ai\Clarify;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\ClarificadorAgent;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Jana\Support\ClarifyResult;
use Modules\Jana\Support\ContextoNegocio;
use Throwable;

/**
 * ClarifyCascadeService — orquestra a cascata Decidir → Clarificar → Responder do
 * "Modo Consultor" (Advisor — Metade A, proposta §10.4).
 *
 * Cascata por LATÊNCIA (senão mata o tempo de resposta):
 *   Estágio 1a — HEURÍSTICA LOCAL (zero LLM): resolve ~80% direto. Default = "responder";
 *                só cai pro cinza se a mensagem tem cara de ambígua (curta + dêitica +
 *                imperativo solto, sem objeto concreto). Latência ~µs.
 *   Estágio 1b — DISAMBIGUADOR (frontier, só no cinza ~20%): ClarificadorAgent decide
 *                ambiguo vs falta_dado vs claro e, se ambíguo, dá a pergunta de maior ganho.
 *
 * Princípios duros desta peça:
 *   - FAIL-OPEN: QUALQUER erro/exceção → 'responder' (a cascata NUNCA quebra o chat).
 *   - HONESTIDADE: não inventa pergunta; só clarifica quando o disambiguador devolve uma.
 *   - ANTI-LOOP: se o turno anterior já foi um clarify, não pergunta de novo (TTL cache).
 *   - MEDIÇÃO: loga `clarify_event` (gray-hit, false-clarify proxy) — engenharia, não fé.
 *   - TIER 0: histórico/contexto vão PII-redigidos pro LLM (defense-in-depth).
 *
 * @see \Modules\Jana\Ai\Agents\ClarificadorAgent
 * @see \Modules\Jana\Support\ClarifyResult
 */
class ClarifyCascadeService
{
    public function __construct(
        protected PiiRedactor $pii,
    ) {
    }

    /**
     * Decide se a Jana deve clarificar antes de responder.
     *
     * @param string $original  Mensagem crua do user (heurística local usa esta — sinal melhor).
     * @param string $redigida  Mensagem já PII-redigida (é a que vai pro LLM).
     */
    public function decidir(Conversa $conv, string $original, string $redigida, ?ContextoNegocio $ctx = null): ClarifyResult
    {
        if (! (bool) config('copiloto.clarify.enabled', false)) {
            return ClarifyResult::responder('claro', 'flag_off');
        }

        try {
            return OtelHelper::span('jana.clarify.decidir', [
                'business_id' => $conv->business_id,
                'conversa_id' => $conv->id,
            ], fn () => $this->decidirInternal($conv, $original, $redigida, $ctx));
        } catch (Throwable $e) {
            // FAIL-OPEN absoluto — clarify nunca derruba o chat.
            Log::channel('copiloto-ai')->warning('clarify cascade falhou (fail-open → responder)', [
                'conversa_id' => $conv->id,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return ClarifyResult::responder('claro', 'erro_fail_open');
        }
    }

    protected function decidirInternal(Conversa $conv, string $original, string $redigida, ?ContextoNegocio $ctx): ClarifyResult
    {
        // ANTI-LOOP — se o turno anterior já foi um clarify, o user provavelmente está
        // respondendo. Consome o marcador e responde (não pergunta de novo).
        if ($this->turnoAnteriorFoiClarify($conv)) {
            $this->limparMarcadorClarify($conv);
            $resultado = ClarifyResult::responder('claro', 'anti_loop_resposta_a_clarify');
            $this->logEvento($conv, $original, $resultado);

            return $resultado;
        }

        // ESTÁGIO 1a — heurística local (zero LLM). Default = responder.
        if (! $this->pareceCinza($original)) {
            $resultado = ClarifyResult::responder('claro', 'heuristica_acionavel');
            $this->logEvento($conv, $original, $resultado);

            return $resultado;
        }

        // ESTÁGIO 1b — cinza: paga o disambiguador frontier.
        $resultado = $this->disambiguar($conv, $redigida, $ctx);

        if ($resultado->deveClarificar()) {
            $this->marcarClarify($conv);
        }

        $this->logEvento($conv, $original, $resultado);

        return $resultado;
    }

    /**
     * Estágio 1b — chama o ClarificadorAgent (structured output, frontier) e marshaliza
     * o veredito em ClarifyResult. Honestidade: ambiguo SEM pergunta de alto valor → responde.
     */
    protected function disambiguar(Conversa $conv, string $redigida, ?ContextoNegocio $ctx): ClarifyResult
    {
        $historico = $this->historicoRedigido($conv);

        $agent = new ClarificadorAgent(
            mensagem: $redigida,
            historicoRecente: $historico,
            ctx: $ctx,
        );

        $resp = $agent->prompt($agent->montarPrompt());

        $tipo = (string) ($resp['tipo'] ?? 'claro');
        $confianca = isset($resp['confianca']) ? (float) $resp['confianca'] : null;
        $pergunta = trim((string) ($resp['pergunta'] ?? ''));
        $intencoes = is_array($resp['intencoes'] ?? null) ? array_values(array_filter(array_map('strval', $resp['intencoes']))) : [];

        // Gate de confiança — abaixo do mínimo, responde (não arrisca falso-clarify).
        $minConfianca = (float) config('copiloto.clarify.min_confianca', 0.6);

        if ($tipo === 'ambiguo' && $pergunta !== '' && ($confianca === null || $confianca >= $minConfianca)) {
            return ClarifyResult::clarificar($pergunta, 'llm_ambiguo', $confianca, $intencoes);
        }

        // Honestidade: ambiguo sem pergunta / baixa confiança / falta_dado / claro → responde.
        $tipoNormalizado = in_array($tipo, ['claro', 'falta_dado', 'ambiguo'], true) ? $tipo : 'claro';

        return ClarifyResult::responder(
            $tipoNormalizado,
            $tipo === 'ambiguo' ? 'llm_ambiguo_sem_pergunta_ou_baixa_confianca' : "llm_{$tipoNormalizado}",
            custoLlm: true,
            confianca: $confianca,
        );
    }

    /**
     * Estágio 1a — heurística "parece cinza?" (zero-custo). Conservadora: default = NÃO
     * (responde). Só vira cinza quando há SINAL forte de ambiguidade. Tunável via config.
     *
     * Sinais de cinza:
     *   - Mensagem curta E (dêixis sem antecedente concreto OU imperativo solto sem objeto).
     * Sinais de NÃO-cinza (curto-circuito p/ "responder"):
     *   - Vazia/saudação/agradecimento, longa (já específica), ou termina com '?' tendo objeto.
     */
    public function pareceCinza(string $msg): bool
    {
        $t = trim(mb_strtolower($msg));

        if ($t === '') {
            return false;
        }

        // Longa o bastante = já tem especificidade suficiente → responde.
        $maxChars = (int) config('copiloto.clarify.gray_max_chars', 140);
        if (mb_strlen($t) > $maxChars) {
            return false;
        }

        $palavras = preg_split('/\s+/u', $t) ?: [];
        $nPalavras = count(array_filter($palavras, fn ($p) => $p !== ''));

        $maxPalavras = (int) config('copiloto.clarify.gray_max_words', 8);
        if ($nPalavras > $maxPalavras) {
            return false;
        }

        // Saudações / social puro → responde (não clarifica).
        if (preg_match('/^(oi|ol[áa]|e a[íi]|bom dia|boa tarde|boa noite|obrigad[oa]|valeu|tchau|blz|beleza)\b/iu', $t)) {
            return false;
        }

        // Dêixis/anáfora sem antecedente concreto (precisa de contexto → cinza).
        $deiticos = config('copiloto.clarify.deiticos', [
            'isso', 'isto', 'aquilo', 'aquele', 'aquela', 'aqueles', 'aquelas',
            'esse', 'essa', 'esses', 'essas', 'ele', 'ela', 'eles', 'elas',
            'l[áa]', 'ali', 'disso', 'nisso', 'desse', 'dessa', 'assim',
        ]);
        $reDeitico = '/\b(' . implode('|', $deiticos) . ')\b/iu';
        $temDeitico = (bool) preg_match($reDeitico, $t);

        // Imperativo solto (verbo de ação sem objeto definido).
        $imperativos = config('copiloto.clarify.imperativos', [
            'manda', 'mande', 'envia', 'envie', 'cria', 'crie', 'faz', 'faça', 'fazer',
            'resolve', 'resolva', 'analisa', 'analise', 'melhora', 'melhore', 'arruma',
            'arrume', 'gera', 'gere', 'dispara', 'dispare', 'mostra', 'mostre', 'ajusta', 'ajuste',
        ]);
        $reImper = '/^\s*(' . implode('|', $imperativos) . ')\b/iu';
        $imperativoSolto = preg_match($reImper, $t) && $nPalavras <= 4;

        // Pergunta curta E vaga: só é cinza se tiver dêixis OU casar um "stem vago"
        // ("e agora?", "como tá?", "e aí?"). Uma pergunta curta MAS específica
        // ("quanto vendi ontem?") NÃO é cinza — é falta_dado, responde direto.
        $stemsVagos = config('copiloto.clarify.stems_vagos', [
            'e agora', 'e a[íi]', 'e da[íi]', 'e ent[ãa]o', 'como assim',
            'como t[áa]', 'como est[áa]', 'que mais', 'pode ser', 'tipo o que',
        ]);
        $reStem = '/^\s*(' . implode('|', $stemsVagos) . ')\b/iu';
        $perguntaVaga = str_ends_with($t, '?')
            && $nPalavras <= $maxPalavras
            && ($temDeitico || (bool) preg_match($reStem, $t));

        return ($temDeitico && $nPalavras <= $maxPalavras) || $imperativoSolto || $perguntaVaga;
    }

    /**
     * Últimos turnos da conversa, PII-redigidos, p/ alimentar o disambiguador
     * (resolução de dêixis + detecção de "respondendo pergunta anterior").
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function historicoRedigido(Conversa $conv): array
    {
        $limite = (int) config('copiloto.clarify.historico_turnos', 4);

        try {
            return $conv->mensagens()
                ->whereIn('role', ['user', 'assistant'])
                ->orderByDesc('created_at')
                ->limit($limite)
                ->get(['role', 'content'])
                ->reverse()
                ->map(fn ($m) => [
                    'role' => (string) $m->role,
                    'content' => $this->pii->redact((string) $m->content),
                ])
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function turnoAnteriorFoiClarify(Conversa $conv): bool
    {
        return (bool) Cache::get($this->cacheKeyClarify($conv));
    }

    protected function marcarClarify(Conversa $conv): void
    {
        $ttl = (int) config('copiloto.clarify.anti_loop_ttl_segundos', 600);
        Cache::put($this->cacheKeyClarify($conv), true, $ttl);
    }

    protected function limparMarcadorClarify(Conversa $conv): void
    {
        Cache::forget($this->cacheKeyClarify($conv));
    }

    protected function cacheKeyClarify(Conversa $conv): string
    {
        return "jana:clarify:last:{$conv->id}";
    }

    /**
     * MEDIÇÃO — `clarify_event` no channel copiloto-ai. Permite calcular:
     *   - gray-hit rate   = eventos com custo_llm=true / total
     *   - false-clarify    = clarify em mensagem que depois o user ignorou (cruzar com ação)
     *   - taxa de clarify  = acao=clarificar / total
     */
    protected function logEvento(Conversa $conv, string $original, ClarifyResult $r): void
    {
        try {
            Log::channel('copiloto-ai')->info('clarify_event', [
                'business_id' => $conv->business_id !== null ? (int) $conv->business_id : null,
                'conversa_id' => (int) $conv->id,
                'acao' => $r->acao,
                'tipo' => $r->tipo,
                'motivo' => $r->motivo,
                'custo_llm' => $r->custoLlm,
                'confianca' => $r->confianca,
                'msg_chars' => mb_strlen($original),
            ]);
        } catch (Throwable) {
            // Medição nunca quebra a cascata.
        }
    }
}
