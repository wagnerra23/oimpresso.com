<?php

namespace Modules\Copiloto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Ai\Agents\ExtrairFatosAgent;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\Mensagem;

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

    public function handle(MemoriaContrato $memoria): void
    {
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

            $totalSalvos = 0;
            foreach ($fatos as $f) {
                if (! isset($f['fato'], $f['categoria'], $f['relevancia'])) {
                    continue;
                }

                if ($f['relevancia'] < 5) {
                    // Filtro de qualidade — relevancia <5 é ruído
                    continue;
                }

                $memoria->lembrar(
                    businessId: $this->businessId,
                    userId: $this->userId,
                    fato: $f['fato'],
                    metadata: [
                        'categoria' => $f['categoria'],
                        'relevancia' => $f['relevancia'],
                        'origem' => 'ExtrairFatosDaConversaJob',
                        'conversa_id' => $this->conversaId,
                        'extraido_em' => now()->toIso8601String(),
                    ]
                );
                $totalSalvos++;
            }

            Log::channel('copiloto-ai')->info('ExtrairFatosDaConversaJob: concluído', [
                'conversa_id' => $this->conversaId,
                'business_id' => $this->businessId,
                'user_id' => $this->userId,
                'fatos_recebidos' => count($fatos),
                'fatos_salvos' => $totalSalvos,
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('ExtrairFatosDaConversaJob: erro', [
                'conversa_id' => $this->conversaId,
                'error' => $e->getMessage(),
            ]);
            // Não rethrow — extração falhar não deve quebrar fluxo do chat
        }
    }
}
