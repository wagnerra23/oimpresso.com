<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Onda 2 — tira o `backup:run` da requisicao HTTP.
 *
 * O legado rodava `Artisan::call('backup:run')` dentro do controller: dump + zip da base
 * inteira no request-lifecycle, com 504 garantido em base maior.
 *
 * ── FILA `backups`, e por que ela e DEDICADA ────────────────────────────────
 * Medido em app/Console/Kernel.php (2026-08-19): quem drena `default` e o worker de
 * backlog, e ele esta atras de `config('queue.backlog_worker_enabled')` — default FALSE,
 * com uma sequencia propria de liberacao (purgar ~48k jobs represados ANTES de ligar).
 * Se o backup fosse pra `default`, ele ficaria parado na tabela `jobs` ate alguem ligar
 * aquele gate — o backup simplesmente nao aconteceria, em silencio.
 *
 * Por isso a fila e `backups` e tem worker proprio no Kernel, NAO gated: esta fila so
 * recebe job recem-despachado por acao humana na tela, entao nao existe o risco de
 * backlog stale que justifica o gate da `default`.
 *
 * ⚠️ Continua valendo: se `QUEUE_CONNECTION=sync` no ambiente, `dispatch()` roda INLINE
 * e o 504 volta. Nao e regressao (e o que o legado ja fazia), mas o ganho da onda so
 * existe com a connection `database`.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Dump + compactacao de base grande. */
    public int $timeout = 1800;

    /** Nao repetir: um backup pela metade e pior que nenhum. */
    public int $tries = 1;

    // PHP 8.4 + Laravel 13: o trait Queueable declara `public $queue;` (sem default).
    // Re-declarar a property aqui com QUALQUER default viola trait composition
    // ("definition differs and is considered incompatible") e e FATAL na carga da
    // classe — `php -l` nao pega, so estoura em runtime. Solucao canonica do repo:
    // setar via $this->onQueue() no constructor (ver Modules/NfeBrasil/Jobs/EmitirNfceJob).
    // $tries/$timeout sao do contrato ShouldQueue (nao do trait), entao sao seguros.

    /** auth()->id() devolve int|string|null — o tipo acompanha, sem cast mentiroso. */
    public function __construct(public int|string|null $userId = null)
    {
        $this->onQueue('backups');
    }

    public function handle(): void
    {
        Artisan::call('backup:run');

        Log::info('[backup] backup:run concluido via job', [
            'user_id' => $this->userId,
            'output' => Artisan::output(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // A falha tem que ser visivel: sem isto, `tries=1` a torna silenciosa.
        Log::error('[backup] falha ao gerar', [
            'user_id' => $this->userId,
            'erro' => $e->getMessage(),
        ]);
    }
}
