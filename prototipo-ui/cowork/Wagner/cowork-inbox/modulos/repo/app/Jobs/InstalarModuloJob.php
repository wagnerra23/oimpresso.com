<?php

namespace App\Jobs;

use App\Services\ModuleManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * P6 (decisão D4) — instala módulo FORA do request web.
 *
 * Motivo: ModuleManagementController@install roda `module:migrate --force` dentro do
 * request. Módulo com 24 migrations (Essentials) pode estourar max_execution_time e
 * deixar a UI sem saber o que aconteceu. Aqui o operador recebe "instalando…" na hora
 * e o resultado chega pelo cache de estado (a tela faz reload parcial de ['modules']).
 *
 * Lock por nome: dois cliques em Instalar não podem rodar migrate concorrente.
 *
 * ⚠️ Pré-requisito não verificado por [CC]: fila configurada em produção
 * (QUEUE_CONNECTION != sync e worker rodando). Confirmar antes de trocar o caminho
 * síncrono — sem worker, o job nunca sai da tabela e a tela fica "instalando" pra sempre.
 */
class InstalarModuloJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;   // migration não é idempotente: nunca reexecutar sozinho

    public function __construct(
        public string $moduleName,
        public ?int $businessId = null,
        public ?int $userId = null,
    ) {}

    public static function estadoCacheKey(string $moduleName): string
    {
        return 'modulo:install:estado:' . $moduleName;
    }

    public function handle(ModuleManagerService $manager): void
    {
        $lock = Cache::lock('modulo:install:lock:' . $this->moduleName, 600);

        if (! $lock->get()) {
            Cache::put(self::estadoCacheKey($this->moduleName), [
                'estado' => 'erro',
                'msg'    => 'Já existe uma instalação em andamento para este módulo.',
            ], now()->addMinutes(10));

            return;
        }

        try {
            Cache::put(self::estadoCacheKey($this->moduleName), ['estado' => 'instalando'], now()->addMinutes(15));

            $resultado = $manager->install($this->moduleName, $this->businessId);

            Cache::put(self::estadoCacheKey($this->moduleName), [
                'estado' => $resultado['success'] ? 'ok' : 'erro',
                'msg'    => $resultado['success']
                    ? 'Módulo instalado (migrations OK).' . (! empty($resultado['install_output'])
                        ? ' Setup completo: permissões + plano de contas pré-populados.'
                        : '')
                    : 'Falha ao instalar: ' . $resultado['output'],
            ], now()->addMinutes(30));

            Log::withContext(['module' => $this->moduleName, 'user_id' => $this->userId])
                ->info($resultado['success'] ? 'modulo.instalado' : 'modulo.instalacao_falhou');
        } finally {
            $lock->release();
        }
    }
}
