<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Business;
use App\Services\Sells\ImportSalesService;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ImportarVendasJob — importação de vendas acima do limite de linhas, fora do request.
 *
 * Decisão D2 de [W] (2026-10-02, playbook de Vendas): planilha pequena importa na hora;
 * acima de `ImportSalesService::limiteSincrono()` linhas vira job em fila, com progresso
 * na tela. O legado rodava tudo no POST com `max_execution_time = 0` e `memory_limit = -1`
 * (achado A2 do charter): planilha grande derrubava o processo do shared hosting.
 *
 * ── FILA `sales-import`, e por que ela é DEDICADA ─────────────────────────────
 * Mesma razão das filas `backups` e `attendance-import` (ver o Kernel): quem drena
 * `default` é o worker de backlog, atrás de `config('queue.backlog_worker_enabled')`, e
 * `default` está na lista que o `jobs:purge-represados` apaga. Esta fila só recebe job
 * recém-despachado por ação humana na tela, então tem worker próprio e não gated.
 *
 * Medido em produção em 2026-10-02 (SSH Hostinger, leitura): `queue.default = database`,
 * `app.env = live`, os 5 workers `queue:work` do Kernel no `schedule:list`, tabela `jobs`
 * vazia e `failed_jobs` com falha de 2026-09-28 — ou seja, há quem processe a fila.
 *
 * ── Mesmo cálculo do request ──────────────────────────────────────────────────
 * O Job chama o MESMO `ImportSalesService::importar()` que o controller chama abaixo do
 * limite. Duas dependências do legado vinham da sessão HTTP e a fila não tem sessão:
 *  - usuário: vem no construtor e é posto no guard durante o handle (o activity log da
 *    venda grava o causador por `auth()`), e retirado no `finally`;
 *  - `session('business')`: o `TransactionUtil::mapPurchaseSell` consulta a regra de
 *    validade de lote ("parar de vender vencido") por ela. Sem a sessão, a fila custearia
 *    o lote vencido que o request recusaria. O handle põe o business do PRÓPRIO job e
 *    restaura o valor anterior no `finally` — o worker processa jobs de vários negócios
 *    no mesmo processo, e sessão vazada entre eles seria vazamento cross-tenant.
 *
 * ── Multi-tenant Tier 0 (ADR 0093) ────────────────────────────────────────────
 * `$businessId` no construtor; o progresso fica em cache com chave escopada por business.
 *
 * Uma tentativa só: o lote inteiro roda numa transação de banco (entra tudo ou nada),
 * mas a criação de cliente e a numeração de lote não são idempotentes entre tentativas.
 */
class ImportarVendasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const FILA = 'sales-import';

    /** Quanto tempo o estado da última importação fica disponível para a tela. */
    public const TTL_ESTADO = 86400;

    public int $timeout = 900;

    public int $tries = 1;

    /**
     * @param  array<int|string, string|null>  $importFields  coluna => campo (mapeamento da prévia)
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $userId,
        public readonly int $locationId,
        public readonly string $caminhoArquivo,
        public readonly array $importFields,
        public readonly int|string $groupBy,
        public readonly ?string $nomeOriginal = null,
    ) {
        // PHP 8.4 + Laravel 13: `public $queue` do trait não pode ser redeclarada (ver RunBackupJob).
        $this->onQueue(self::FILA);
    }

    /** Chave do estado da última importação do negócio — escopada por business (ADR 0093). */
    public static function chaveDeEstado(int $businessId): string
    {
        return sprintf('sells.import_vendas.%d.ultimo', $businessId);
    }

    /**
     * Registra o estado da importação para a tela ler.
     *
     * @param  array<string, mixed>  $estado
     */
    public static function publicarEstado(int $businessId, array $estado): void
    {
        Cache::put(self::chaveDeEstado($businessId), $estado + ['em' => now()->toDateTimeString()], self::TTL_ESTADO);
    }

    public function handle(ImportSalesService $service): void
    {
        $base = ['arquivo' => $this->nomeOriginal];

        if (! is_file($this->caminhoArquivo)) {
            self::publicarEstado($this->businessId, $base + [
                'estado' => 'erro',
                'mensagem' => 'O arquivo da importação não está mais no servidor. Envie a planilha de novo.',
            ]);

            return;
        }

        $sessaoAnterior = session()->get('business');
        $usuarioAnterior = Auth::user();

        try {
            $business = Business::findOrFail($this->businessId);
            $usuario = User::where('business_id', $this->businessId)->findOrFail($this->userId);

            Auth::setUser($usuario);
            session()->put('business', $business);

            $parsed = $service->lerPlanilha($this->caminhoArquivo);
            unset($parsed[0]);
            $grupos = $service->formatar($parsed, $this->importFields, $this->groupBy);

            self::publicarEstado($this->businessId, $base + [
                'estado' => 'processando',
                'feitas' => 0,
                'total' => count($grupos),
            ]);

            $resultado = DB::transaction(fn () => $service->importar(
                $grupos,
                $this->businessId,
                $this->userId,
                $this->locationId,
                function (int $feitas, int $total) use ($base) {
                    self::publicarEstado($this->businessId, $base + [
                        'estado' => 'processando',
                        'feitas' => $feitas,
                        'total' => $total,
                    ]);
                },
            ));

            self::publicarEstado($this->businessId, $base + [
                'estado' => 'concluido',
                'feitas' => $resultado['vendas'],
                'total' => $resultado['vendas'],
                'lote' => $resultado['lote'],
            ]);

            Log::info('sells.import_vendas.concluido', [
                'business_id' => $this->businessId,
                'user_id' => $this->userId,
                'lote' => $resultado['lote'],
                'vendas' => $resultado['vendas'],
            ]);
        } catch (\Throwable $e) {
            // A transação já foi desfeita pelo DB::transaction. A mensagem é a do legado
            // (ela cita a linha da planilha — R6 do charter).
            self::publicarEstado($this->businessId, $base + [
                'estado' => 'erro',
                'mensagem' => $e->getMessage(),
            ]);

            Log::error('sells.import_vendas.falhou', [
                'business_id' => $this->businessId,
                'user_id' => $this->userId,
                'erro' => $e->getMessage(),
            ]);
        } finally {
            if ($sessaoAnterior === null) {
                session()->forget('business');
            } else {
                session()->put('business', $sessaoAnterior);
            }
            if ($usuarioAnterior === null) {
                Auth::forgetUser();
            } else {
                Auth::setUser($usuarioAnterior);
            }

            @unlink($this->caminhoArquivo);
        }
    }
}
