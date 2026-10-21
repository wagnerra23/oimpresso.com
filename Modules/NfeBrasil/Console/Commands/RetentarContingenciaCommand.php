<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Jobs\RetentarContingenciaJob;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\SefazStatusRecorder;
use Throwable;

/**
 * US-NFE-070 · UC-FLX-32 — quem dispara o RetentarContingenciaJob.
 *
 * Agendado a cada 5 min (NfeBrasilServiceProvider). Para cada business com nota em
 * `contingencia`, pergunta à SEFAZ pelo dono do ping (`NfeService::consultarStatusSefaz`,
 * com o cert DAQUELE business) e só despacha o job se ela respondeu cStat 107. O resultado
 * verde é gravado em `nfe_sefaz_status` (SefazStatusRecorder), o mesmo sinal do nfe:health.
 *
 * Por que não despachar às cegas: o job conta uma tentativa na 1ª nota a cada falha, e no
 * teto ela vira `rejeitada`. Disparar a cada 5 min com a SEFAZ fora queimaria essa nota.
 * Falha do ping (rede, cert, cStat != 107) = não despacha e não grava: "não consegui medir"
 * não vira estado da SEFAZ (proibicoes.md §5 2026-07-29); o nfe:health classifica o resto.
 *
 * Multi-tenant Tier 0 (ADR 0093): sem session(); o job recebe o $businessId no constructor
 * e só lê notas daquele business.
 */
class RetentarContingenciaCommand extends Command
{
    protected $signature = 'nfe:retentar-contingencia
                            {--business-id= : Apenas 1 business}';

    protected $description = 'Despacha a retransmissão das notas em contingência dos businesses cuja SEFAZ voltou';

    public function handle(NfeService $nfe, SefazStatusRecorder $recorder): int
    {
        $query = NfeEmissao::withoutGlobalScopes() // SUPERADMIN: command sem sessão; agrupa por business explícito.
            ->where('status', 'contingencia');

        if ($this->option('business-id') !== null) {
            $query->where('business_id', (int) $this->option('business-id'));
        }

        $businesses = $query->distinct()->orderBy('business_id')->pluck('business_id')
            ->map(fn ($id) => (int) $id);

        $despachados = 0;

        foreach ($businesses as $businessId) {
            if (! $this->sefazOperando($nfe, $recorder, $businessId)) {
                $this->line("business {$businessId}: SEFAZ ainda indisponível — aguardando");

                continue;
            }

            RetentarContingenciaJob::dispatch($businessId);
            $despachados++;
            $this->line("business {$businessId}: retransmissão despachada");
        }

        $this->info("Contingência: {$businesses->count()} business(es) com nota pendente, {$despachados} despachado(s).");

        return self::SUCCESS;
    }

    private function sefazOperando(NfeService $nfe, SefazStatusRecorder $recorder, int $businessId): bool
    {
        try {
            $resp = $nfe->consultarStatusSefaz($businessId);
        } catch (Throwable $e) {
            Log::warning('nfe:retentar-contingencia: ping SEFAZ falhou', [
                'business_id' => $businessId,
                'erro' => substr($e->getMessage(), 0, 200),
            ]);

            return false;
        }

        if ((string) ($resp['cstat'] ?? '') !== '107') {
            return false;
        }

        $uf = (string) ($resp['uf'] ?? '');
        if ($uf !== '') {
            $recorder->registrarSucesso($uf, (float) ($resp['tempoResposta'] ?? 0.0));
        }

        return true;
    }
}
