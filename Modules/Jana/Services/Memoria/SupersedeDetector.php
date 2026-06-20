<?php

namespace Modules\Jana\Services\Memoria;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\DetectarSupersedeAgent;
use Modules\Jana\Entities\MemoriaFato;

/**
 * SupersedeDetector — detecção automática de update temporal event-time (ADR 0295
 * slice 3). Um fato novo pode SUBSTITUIR um anterior; ao detectar, a consolidação
 * marca o event-time do antigo e linka o novo por `supersedes_id`.
 *
 * Camadas (pra testabilidade + Tier-0):
 *  - habilitado()  : lê a flag OFF-by-default (config jana.memoria.supersede_detection).
 *  - detectar()    : entry de produção — resolve o threshold da config e delega.
 *  - decidir()     : DECISÃO PURA (sem config/DB) — testável no CI mockando o LLM.
 *  - consolidar()  : escrita APPEND-ONLY no banco (MySQL) — NUNCA edita o antigo.
 *  - perguntarAoLlm: seam do LLM (Haiku → fallback gpt-4o-mini), mockável no teste.
 *
 * Multi-tenant (ADR 0093): os candidatos chegam JÁ business_id+user_id scoped (o
 * job carrega via MemoriaFato::doUser). decidir() ainda revalida que o id devolvido
 * pelo LLM pertence ao conjunto — barreira anti-alucinação e anti-cross-tenant.
 *
 * FAILSAFE: qualquer erro de LLM/parse vira "sem supersede" (null) — o fato novo é
 * só apendado pelo fluxo legado. Detecção falhar NUNCA quebra a extração.
 */
class SupersedeDetector
{
    /** Flag mestra — OFF por default (job byte-idêntico ao legado quando false). */
    public function habilitado(): bool
    {
        return (bool) config('jana.memoria.supersede_detection.enabled', false);
    }

    /** Quantos fatos ativos entram como candidatos (cap de custo/tokens). */
    public function maxCandidatos(): int
    {
        return max(1, (int) config('jana.memoria.supersede_detection.max_candidatos', 20));
    }

    /**
     * Entry de produção: resolve o threshold da config e delega à decisão pura.
     *
     * @param  array<int, string>  $candidatos  map id => fato (ativos do business+user)
     * @return array{supersedes_id:int, confianca:int, motivo:string}|null
     */
    public function detectar(int $businessId, int $userId, string $novoFato, array $candidatos): ?array
    {
        $confiancaMin = (int) config('jana.memoria.supersede_detection.confianca_min', 70);

        return $this->decidir($businessId, $userId, $novoFato, $candidatos, $confiancaMin);
    }

    /**
     * DECISÃO PURA — sem config, sem DB. Recebe os candidatos já tenant-scoped e o
     * threshold explícito; chama o seam do LLM e valida o resultado. Testável no CI.
     *
     * @param  array<int, string>  $candidatos  map id => fato
     * @return array{supersedes_id:int, confianca:int, motivo:string}|null
     */
    public function decidir(int $businessId, int $userId, string $novoFato, array $candidatos, int $confiancaMin): ?array
    {
        if (trim($novoFato) === '' || $candidatos === []) {
            return null;
        }

        $bruto = $this->perguntarAoLlm($novoFato, $candidatos);
        if (! is_array($bruto)) {
            return null; // FAILSAFE — LLM indisponível / shape inválido
        }

        if (! (bool) ($bruto['supersede'] ?? false)) {
            return null; // o LLM disse que não há supersede
        }

        $supersedesId = (int) ($bruto['supersedes_id'] ?? 0);
        $confianca = (int) ($bruto['confianca'] ?? 0);

        // GUARD Tier-0 (ADR 0093) + anti-alucinação: o id DEVE pertencer ao conjunto
        // de candidatos — que já é business_id+user_id scoped. Bloqueia o LLM
        // "inventar" um id fora da janela ou de outro tenant.
        if (! array_key_exists($supersedesId, $candidatos)) {
            return null;
        }

        if ($confianca < $confiancaMin) {
            return null; // abaixo do threshold: apenas apenda (lembrar legado), sem link
        }

        return [
            'supersedes_id' => $supersedesId,
            'confianca' => $confianca,
            'motivo' => (string) ($bruto['motivo'] ?? ''),
        ];
    }

    /**
     * Consolidação APPEND-ONLY (banco — MySQL). NUNCA edita o conteúdo do antigo:
     * só FECHA as janelas (valid_until system-time + event_valid_until event-time) e
     * cria o fato novo já linkado por `supersedes_id` (mesmo padrão do
     * MeilisearchDriver::atualizar, + o link event-time da ADR 0295).
     *
     * Multi-tenant: opera sobre o model ANTIGO já carregado tenant-scoped pelo job;
     * o novo herda business_id+user_id do antigo (não infere nada da fila).
     */
    public function consolidar(MemoriaFato $antigo, string $novoFato, array $metadata = []): MemoriaFato
    {
        $agora = now();

        // Fecha o antigo. update() toca SÓ colunas de janela — o `fato` fica intacto
        // (append-only preservado; o histórico continua recuperável por buscarHistorico).
        $antigo->update([
            'valid_until' => $agora,
            'event_valid_until' => $agora,
        ]);

        return MemoriaFato::create([
            'business_id' => $antigo->business_id,
            'user_id' => $antigo->user_id,
            'fato' => $novoFato,
            'metadata' => $metadata,
            'valid_from' => $agora,
            'event_valid_from' => $agora,
            'supersedes_id' => $antigo->id,
        ]);
    }

    /**
     * Seam do LLM (protected = mockável no teste de lógica pura). Tenta Haiku via
     * provider `anthropic` (só se ANTHROPIC_API_KEY existir) e cai pro fallback
     * gpt-4o-mini via `openai`. Qualquer falha → null (FAILSAFE).
     *
     * @param  array<int, string>  $candidatos
     * @return array<string, mixed>|null
     */
    protected function perguntarAoLlm(string $novoFato, array $candidatos): ?array
    {
        $modelo = (string) config('jana.memoria.supersede_detection.model', 'claude-haiku-4-5-20251001');
        $fallback = (string) config('jana.memoria.supersede_detection.fallback_model', 'gpt-4o-mini');
        $temAnthropic = (string) config('ai.providers.anthropic.key', '') !== '';

        // Ordem de tentativa: Haiku primeiro (se houver chave), depois o fallback.
        $tentativas = [];
        if ($temAnthropic) {
            $tentativas[] = ['anthropic', $modelo];
        }
        $tentativas[] = ['openai', $fallback];

        $agent = new DetectarSupersedeAgent($novoFato, $candidatos);
        $prompt = $agent->montarPrompt();

        foreach ($tentativas as [$provider, $model]) {
            try {
                $resp = $agent->prompt($prompt, provider: $provider, model: $model);
                if (is_array($resp)) {
                    return $resp;
                }
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->warning('SupersedeDetector: tentativa LLM falhou', [
                    'provider' => $provider,
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
