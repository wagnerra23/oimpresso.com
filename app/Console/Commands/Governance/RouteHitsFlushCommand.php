<?php

declare(strict_types=1);

namespace App\Console\Commands\Governance;

use App\Http\Middleware\ContadorHitsRota;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * route-hits:flush — move os contadores de cache (ContadorHitsRota) pra tabela
 * agregada `route_hits`, em batch (o único caminho de escrita da tabela).
 *
 * Design race-free SEM índice de chaves em cache: o universo de chaves é
 * derivável — (dia ∈ janela) × (identidade de cada rota REGISTRADA no router).
 * Itera Route::getRoutes() (bounded, ~centenas) e dá Cache::pull() por par;
 * pull é atômico o bastante pro caso (perder 1-2 increments entre get/forget
 * do driver file é aceitável — telemetria advisory, não contabilidade).
 *
 * Janela default: ontem + hoje (cobre flush diário com folga pra virada de dia).
 * --prune: apaga linhas mais antigas que config route_hits.retencao_dias.
 *
 * Agendado daily 00:15 BRT no app/Console/Kernel.php. Rodar manual:
 *   php artisan route-hits:flush [--dias=2] [--prune]
 */
class RouteHitsFlushCommand extends Command
{
    protected $signature = 'route-hits:flush
        {--dias=2 : Janela de dias (contando hoje) cujas chaves de cache serão coletadas}
        {--prune : Apaga linhas de route_hits mais antigas que route_hits.retencao_dias}';

    protected $description = 'Move contadores de hits por rota do cache pra tabela agregada route_hits (batch diário).';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $hoje = CarbonImmutable::now();

        $identidades = $this->identidadesRegistradas();
        $movidos = 0;

        for ($i = 0; $i < $dias; $i++) {
            $data = $hoje->subDays($i)->format('Y-m-d');
            foreach ($identidades as $rota) {
                $hits = (int) (Cache::pull(ContadorHitsRota::chaveCache($rota, $data)) ?? 0);
                if ($hits <= 0) {
                    continue;
                }
                $this->acumular($data, $rota, $hits);
                $movidos++;
            }
        }

        $this->info("route-hits:flush — {$movidos} par(es) rota×dia movidos do cache pra route_hits.");

        if ($this->option('prune')) {
            $retencao = (int) config('route_hits.retencao_dias', 90);
            $apagadas = DB::table('route_hits')
                ->where('data', '<', $hoje->subDays($retencao)->format('Y-m-d'))
                ->delete();
            $this->info("route-hits:flush --prune — {$apagadas} linha(s) além de {$retencao}d apagadas.");
        }

        return self::SUCCESS;
    }

    /**
     * Universo de identidades contáveis — espelha ContadorHitsRota::identidadeRota
     * (nome canônico; fallback URI-pattern). Deduplicado.
     *
     * @return list<string>
     */
    private function identidadesRegistradas(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $route) {
            $nome = $route->getName();
            $out[($nome !== null && $nome !== '') ? $nome : $route->uri()] = true;
        }

        return array_keys($out);
    }

    /** Upsert-increment portátil (MySQL prod / sqlite lane CI) — volume baixo, 1 rota×dia por vez. */
    private function acumular(string $data, string $rota, int $hits): void
    {
        $atual = DB::table('route_hits')->where('data', $data)->where('rota', $rota)->first();
        if ($atual === null) {
            DB::table('route_hits')->insert([
                'data' => $data, 'rota' => $rota, 'hits' => $hits,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return;
        }
        DB::table('route_hits')->where('id', $atual->id)
            ->update(['hits' => (int) $atual->hits + $hits, 'updated_at' => now()]);
    }
}
