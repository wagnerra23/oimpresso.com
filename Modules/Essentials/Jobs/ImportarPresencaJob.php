<?php

declare(strict_types=1);

namespace Modules\Essentials\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Essentials\Services\AttendanceImportService;

/**
 * ImportarPresencaJob — tira o import de presença do request-lifecycle (HRM-O6 / PR-6, achado A7).
 *
 * O legado rodava tudo dentro do POST com `ini_set('max_execution_time', 0)`: parse +
 * 2 SELECT por linha + INSERT, sem teto de tempo, num shared hosting. Aqui o parse e a
 * validação rodam no worker, e o `ini_set` deixou de existir.
 *
 * ── FILA `attendance-import`, e por que ela é DEDICADA ────────────────────────
 * Medido em `app/Console/Kernel.php` (2026-09-04): quem drena `default` é o worker de
 * backlog, atrás de `config('queue.backlog_worker_enabled')` — default FALSE, com
 * sequência própria de liberação. Job de presença despachado pra `default` ficaria parado
 * na tabela `jobs` até alguém ligar aquele gate, e `default` ainda está na lista de filas
 * que o `jobs:purge-represados` apaga: a importação simplesmente não aconteceria, em
 * silêncio — que é o pior desfecho possível pra dado de jornada.
 *
 * Por isso a fila é própria e tem worker NÃO gated no Kernel, exatamente como a fila
 * `backups` (mesmo formato: só recebe job recém-despachado por ação humana na tela, então
 * não existe o risco de backlog stale que justifica o gate da `default`).
 *
 * ⚠️ Com `QUEUE_CONNECTION=sync` (CI e dev), `dispatch()` roda INLINE — o relatório fica
 * pronto antes do redirect e o controller já o exibe. Com `database` (Hostinger), o
 * relatório é lido do cache na volta à tela.
 *
 * ── Multi-tenant Tier 0 (ADR 0093) ────────────────────────────────────────────
 * `$businessId` vem no construtor porque `session()` NÃO funciona em fila. Nada aqui
 * (nem no service) lê sessão.
 *
 * @see Modules\Essentials\Services\AttendanceImportService
 * @see app/Jobs/RunBackupJob (precedente da fila dedicada não-gated)
 */
class ImportarPresencaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Nome da fila — worker dedicado no Kernel, sem gate. */
    public const FILA = 'attendance-import';

    /** Quanto tempo o relatório fica disponível pra tela (segundos). */
    public const TTL_RELATORIO = 86400;

    /**
     * Token da chave ESTÁVEL do último relatório do negócio.
     *
     * Com `QUEUE_CONNECTION=database` o Job termina depois do redirect, então o relatório
     * não cabe no flash daquele request. Sem esta chave, a mensagem "o relatório aparece
     * nesta tela ao terminar" seria uma promessa que o código não cumpre.
     */
    public const TOKEN_ULTIMO = 'ultimo';

    /** Parse + validação de planilha grande; folgado, mas com teto (o `ini_set(0)` não tinha). */
    public int $timeout = 900;

    /**
     * Uma tentativa só: o insert não é idempotente (não há chave natural em
     * `essentials_attendances`), então retry duplicaria marcação de jornada.
     */
    public int $tries = 1;

    /**
     * PHP 8.4 + Laravel 13: o trait `Queueable` declara `public $queue;` sem default.
     * Redeclarar a property aqui com qualquer default é FATAL na carga da classe e o
     * `php -l` não pega. A forma canônica do repo é `onQueue()` no construtor
     * (ver `app/Jobs/RunBackupJob`).
     */
    public function __construct(
        public readonly int $businessId,
        public readonly string $caminhoArquivo,
        public readonly string $chaveRelatorio,
        public readonly int|string|null $userId = null,
        public readonly ?string $ipPadrao = null,
        public readonly ?string $nomeOriginal = null,
    ) {
        $this->onQueue(self::FILA);
    }

    /**
     * Chave de cache do relatório — escopada por business pra que a tela de um negócio
     * nunca leia o relatório de outro (ADR 0093 vale também pro cache).
     */
    public static function chaveDeCache(int $businessId, string $token): string
    {
        return sprintf('essentials.import_presenca.%d.%s', $businessId, $token);
    }

    public function handle(AttendanceImportService $service): void
    {
        $disco = Storage::disk('local');

        Log::info('essentials.import_presenca.iniciado', [
            'business_id' => $this->businessId,
            'user_id' => $this->userId,
            'arquivo' => $this->nomeOriginal,
        ]);

        if (! $disco->exists($this->caminhoArquivo)) {
            $this->publicar([
                'estado' => 'erro',
                'mensagem' => __('essentials::lang.import_erro_arquivo_sumiu'),
            ]);

            Log::error('essentials.import_presenca.arquivo_ausente', [
                'business_id' => $this->businessId,
                'caminho' => $this->caminhoArquivo,
            ]);

            return;
        }

        try {
            $planilha = Excel::toArray([], $disco->path($this->caminhoArquivo));
            // Remove o cabeçalho — a numeração das recusas no service já compensa isso.
            $linhas = array_slice($planilha[0] ?? [], 1);

            $relatorio = $service->importar($this->businessId, $linhas, $this->ipPadrao);

            $this->publicar($relatorio + ['estado' => 'concluido']);

            Log::info('essentials.import_presenca.concluido', [
                'business_id' => $this->businessId,
                'user_id' => $this->userId,
                'total' => $relatorio['total'],
                'inseridas' => $relatorio['inseridas'],
                'recusadas' => count($relatorio['recusadas']),
            ]);
        } finally {
            // O upload é temporário: sai do disco tenha dado certo ou não.
            $disco->delete($this->caminhoArquivo);
        }
    }

    public function failed(\Throwable $e): void
    {
        // Com `tries = 1` a falha seria muda sem isto.
        $this->publicar([
            'estado' => 'erro',
            'mensagem' => $e->getMessage(),
        ]);

        Storage::disk('local')->delete($this->caminhoArquivo);

        Log::error('essentials.import_presenca.falhou', [
            'business_id' => $this->businessId,
            'user_id' => $this->userId,
            'erro' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function publicar(array $relatorio): void
    {
        $conteudo = $relatorio + [
            'arquivo' => $this->nomeOriginal,
            'em' => now()->toDateTimeString(),
        ];

        // Duas chaves, ambas escopadas por business_id (ADR 0093 vale pro cache também):
        // a do token serve ao request que enviou o arquivo (fila `sync`), e a estável
        // serve à tela quando o Job terminou depois do redirect (fila `database`).
        foreach ([$this->chaveRelatorio, self::TOKEN_ULTIMO] as $token) {
            Cache::put(self::chaveDeCache($this->businessId, $token), $conteudo, self::TTL_RELATORIO);
        }
    }
}
