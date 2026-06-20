<?php

namespace Modules\Jana\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\ExtrairFatosAgent;
use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Services\Memoria\SupersedeDetector;

/**
 * ExtrairFatosDaConversaJob — extrai fatos persistentes de uma Conversa
 * e persiste via MemoriaContrato (sprint 5 do ADR 0036).
 *
 * Trigger: ChatController@send dispatcha após resposta do LLM.
 * Queue: 'copiloto-memoria' (Horizon monitora).
 * Idempotência: limita janela às últimas N mensagens não-processadas.
 *
 * Falha tolerante: erros de extração logados mas não rethrown — perder
 * 1 extração não é crítico, conversa segue normal.
 */
class ExtrairFatosDaConversaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $conversaId,
        public int $businessId,
        public int $userId,
        public int $janelaMensagens = 10,
    ) {
        $this->onQueue('copiloto-memoria');
    }

    public function handle(MemoriaContrato $memoria, ?SupersedeDetector $detector = null): void
    {
        // $detector nullable + resolve interno: mantém o call direto
        // `$job->handle(app(MemoriaContrato::class))` do BackfillFatosCommand
        // funcionando, e o container injeta nos dispatches normais da fila.
        $detector ??= app(SupersedeDetector::class);

        $conversa = Conversa::find($this->conversaId);

        if ($conversa === null) {
            Log::channel('copiloto-ai')->warning('ExtrairFatosDaConversaJob: conversa não encontrada', [
                'conversa_id' => $this->conversaId,
            ]);
            return;
        }

        if (config('copiloto.dry_run')) {
            Log::channel('copiloto-ai')->info('ExtrairFatosDaConversaJob: dry_run, pulando');
            return;
        }

        $mensagens = $conversa
            ->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit($this->janelaMensagens)
            ->get()
            ->reverse()
            ->values();

        if ($mensagens->isEmpty()) {
            return;
        }

        $transcript = $mensagens
            ->map(fn (Mensagem $m) => strtoupper($m->role) . ': ' . $m->content)
            ->implode("\n");

        $businessName = $conversa->business?->name ?? "Business #{$this->businessId}";

        try {
            $agent = new ExtrairFatosAgent($businessName, $transcript);
            $response = $agent->prompt($agent->montarPrompt());

            $fatos = $response['fatos'] ?? [];

            if (! is_array($fatos)) {
                Log::channel('copiloto-ai')->warning('ExtrairFatosDaConversaJob: shape inválido', [
                    'conversa_id' => $this->conversaId,
                ]);
                return;
            }

            // MEM-EVAL-2 (2026-04-29): threshold relaxado de 5 → 3.
            // Baseline com threshold=5: 6% taxa de aceite (94% rejeitados),
            // levando a corpus de 6 fatos total e Recall@3 de 0.125.
            // Com 3, esperamos ~30-50% taxa de aceite + Recall@3 ~0.30.
            // Tag `relevancia` fica salva em metadata pra filtro DOWNSTREAM
            // no recall (a busca pode penalizar relevância baixa, sem ter
            // descartado o fato).
            $thresholdRelevancia = (int) config('copiloto.memoria.relevancia_min', 3);

            // ADR 0295 slice 3 — detecção de supersede event-time (FLAG OFF default).
            // Com a flag OFF NADA daqui roda (nem a query de candidatos) → o loop é
            // BYTE-IDÊNTICO ao legado. Os candidatos são carregados 1× e tenant-scoped
            // pelo businessId/userId do CONSTRUTOR — nunca inferidos da fila (ADR 0093).
            $deteccaoAtiva = $detector->habilitado();
            $candidatosModels = new Collection();
            $mapaCandidatos = [];
            if ($deteccaoAtiva) {
                $candidatosModels = $this->candidatosSupersede($detector->maxCandidatos());
                $mapaCandidatos = $candidatosModels->pluck('fato', 'id')->all();
            }

            $totalSalvos = 0;
            $totalRejeitados = 0;
            $totalSuperseded = 0;
            $rejeitadosPorRelevancia = [];

            foreach ($fatos as $f) {
                if (! isset($f['fato'], $f['categoria'], $f['relevancia'])) {
                    $totalRejeitados++;
                    continue;
                }

                $rel = (int) $f['relevancia'];

                if ($rel < $thresholdRelevancia) {
                    $totalRejeitados++;
                    $rejeitadosPorRelevancia[$rel] = ($rejeitadosPorRelevancia[$rel] ?? 0) + 1;
                    continue;
                }

                $metadata = [
                    'categoria' => $f['categoria'],
                    'relevancia' => $rel,
                    'origem' => 'ExtrairFatosDaConversaJob',
                    'conversa_id' => $this->conversaId,
                    'extraido_em' => now()->toIso8601String(),
                ];

                // Supersede event-time (só com flag ON e havendo candidatos): ao
                // detectar, consolida APPEND-ONLY (fecha o antigo + cria o novo já
                // linkado por supersedes_id) em vez do lembrar() simples.
                if ($deteccaoAtiva && $mapaCandidatos !== []) {
                    $decisao = $detector->detectar($this->businessId, $this->userId, $f['fato'], $mapaCandidatos);

                    if ($decisao !== null) {
                        $antigo = $candidatosModels->firstWhere('id', $decisao['supersedes_id']);

                        if ($antigo instanceof MemoriaFato) {
                            $detector->consolidar($antigo, $f['fato'], $metadata + [
                                'supersede' => [
                                    'supersedes_id' => $decisao['supersedes_id'],
                                    'confianca' => $decisao['confianca'],
                                ],
                            ]);

                            // Não deixa o mesmo antigo ser supersededo 2× no mesmo run.
                            unset($mapaCandidatos[$antigo->id]);
                            $totalSalvos++;
                            $totalSuperseded++;
                            continue;
                        }
                    }
                }

                $memoria->lembrar(
                    businessId: $this->businessId,
                    userId: $this->userId,
                    fato: $f['fato'],
                    metadata: $metadata
                );
                $totalSalvos++;
            }

            Log::channel('copiloto-ai')->info('ExtrairFatosDaConversaJob: concluído', [
                'conversa_id' => $this->conversaId,
                'business_id' => $this->businessId,
                'user_id' => $this->userId,
                'fatos_recebidos' => count($fatos),
                'fatos_salvos' => $totalSalvos,
                'fatos_rejeitados' => $totalRejeitados,
                'fatos_superseded' => $totalSuperseded,
                'threshold_min' => $thresholdRelevancia,
                'rejeitados_por_relevancia' => $rejeitadosPorRelevancia,
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('ExtrairFatosDaConversaJob: erro', [
                'conversa_id' => $this->conversaId,
                'error' => $e->getMessage(),
            ]);
            // Não rethrow — extração falhar não deve quebrar fluxo do chat
        }
    }

    /**
     * Fatos ATIVOS do (business,user) — candidatos a serem supersededos (ADR 0295).
     *
     * Tenant-scoped EXPLÍCITO (ADR 0093): doUser filtra business_id+user_id do
     * CONSTRUTOR do job; em contexto de fila o global scope ScopeByBusiness é no-op
     * (sem sessão), então o filtro explícito é o que garante o isolamento. Seleciona
     * só as colunas necessárias (id + tenant + fato) — o consolidar() opera sobre o
     * próprio model carregado aqui, sem re-find.
     *
     * @return Collection<int, MemoriaFato>
     */
    protected function candidatosSupersede(int $max): Collection
    {
        return MemoriaFato::doUser($this->businessId, $this->userId)
            ->ativos()
            ->orderByDesc('valid_from')
            ->limit($max)
            ->get(['id', 'business_id', 'user_id', 'fato']);
    }
}
