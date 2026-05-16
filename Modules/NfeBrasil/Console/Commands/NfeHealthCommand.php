<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Throwable;

/**
 * Wave 16 D9 — Health-check diário NfeBrasil.
 *
 * Roda 06:05 BRT (logo após jana:health-check 06:00). Valida 3 sinais críticos
 * SEFAZ por business com cert ativo + emissões nos últimos 30d:
 *
 *   1. **Certificado válido** — `valido_ate >= today + 7 dias` (alerta vencimento
 *      próximo); `valido_ate < today` = CRÍTICO (emissões já bloqueadas).
 *   2. **Última emissão** — última `nfe_emissoes.emitido_em` <= 7 dias atrás
 *      (silencio > 7d em business ativo = drift candidato — pipeline travada,
 *      cert sem rotacionar, scheduler caiu).
 *   3. **Ping SEFAZ status** — opcional via `--ping-sefaz` (consulta sefazStatus
 *      via NfeService::consultarStatusSefaz, exige cert válido + rede). Default
 *      desligado pra evitar tráfego diário desnecessário.
 *
 * Multi-tenant Tier 0 (ADR 0093): commands CLI rodam sem session(); itera
 * `DB::table('business')` direto + filtra por business_id quando query Eloquent.
 *
 * Exit codes:
 *   - 0 (SUCCESS) — tudo OK ou só warnings
 *   - 1 (FAILURE) — pelo menos 1 erro CRÍTICO (cert vencido, ou ping --ping-sefaz falhou)
 *
 * Output estruturado em log channel `single` pra dashboard Grafana/Loki
 * consumir + alert humano via `--notify` se algo falhou.
 *
 * Uso:
 *   php artisan nfe:health
 *   php artisan nfe:health --business-id=1
 *   php artisan nfe:health --ping-sefaz          # consulta SEFAZ real (custo+latência)
 *   php artisan nfe:health --notify              # ALERT log se issue crítica
 *   php artisan nfe:health --detail              # tabela detalhada por business
 *
 * Scheduled `daily 06:05 BRT` em app/Console/Kernel.php.
 *
 * @see ADR 0155 module-grade-v3 D9 (observability rubric)
 */
class NfeHealthCommand extends Command
{
    protected $signature = 'nfe:health
                            {--business-id= : Apenas 1 business (omite = todos com cert ativo)}
                            {--ping-sefaz : Faz ping real sefazStatus (custo+latência)}
                            {--notify : ALERT log se algo falhou (cron-friendly)}
                            {--detail : Tabela detalhada por business (não usar com --notify cron)}';

    protected $description = 'Health-check diário NfeBrasil (cert válido + última emissão + SEFAZ ping opcional)';

    /** Dias de margem antes do vencimento do cert vira WARNING. */
    private const CERT_WARN_DIAS = 7;

    /** Silêncio > N dias na última emissão dispara WARNING. */
    private const SILENCIO_MAX_DIAS = 7;

    /**
     * Lookback pra considerar business "ativo" em NFe (teve emissão recente).
     */
    private const ATIVO_LOOKBACK_DIAS = 30;

    public function handle(): int
    {
        $businessFilter = $this->option('business-id') !== null
            ? (int) $this->option('business-id')
            : null;

        $pingSefaz = (bool) $this->option('ping-sefaz');

        $businesses = $this->resolverBusinesses($businessFilter);

        if ($businesses->isEmpty()) {
            $this->warn('Nenhum business com cert ativo encontrado — nada pra checar.');
            Log::channel('single')->info('nfe:health', [
                'ok'     => true,
                'total'  => 0,
                'razao'  => 'sem_cert_ativo',
            ]);
            return self::SUCCESS;
        }

        $linhas = [];
        $crit = 0;
        $warn = 0;

        foreach ($businesses as $biz) {
            $bizId = (int) $biz->business_id;

            $cert = $this->checkCertificado($bizId);
            $ult  = $this->checkUltimaEmissao($bizId);
            $ping = $pingSefaz ? $this->checkPingSefaz($bizId) : ['status' => 'skipped'];

            $bizCrit = $cert['status'] === 'crit' || $ping['status'] === 'crit';
            $bizWarn = $cert['status'] === 'warn' || $ult['status'] === 'warn';

            if ($bizCrit) {
                $crit++;
            } elseif ($bizWarn) {
                $warn++;
            }

            $linhas[] = [
                'biz'              => $bizId,
                'cert_status'      => $cert['status'],
                'cert_dias'        => $cert['dias'],
                'cert_msg'         => $cert['msg'],
                'ultima_emissao'   => $ult['ultima_em'],
                'silencio_dias'    => $ult['silencio_dias'],
                'ult_status'       => $ult['status'],
                'ping_status'      => $ping['status'],
                'ping_cstat'       => $ping['cstat'] ?? null,
            ];
        }

        // Log estruturado pra cada business (Loki/Grafana consume)
        foreach ($linhas as $linha) {
            Log::channel('single')->info('nfe.health.business', $linha);
        }

        $resumo = [
            'ok'    => $crit === 0,
            'total' => count($linhas),
            'crit'  => $crit,
            'warn'  => $warn,
            'ping_sefaz_enabled' => $pingSefaz,
        ];

        Log::channel('single')->info('nfe:health', $resumo);

        if ($this->option('detail')) {
            $this->table(
                ['biz', 'cert', 'dias', 'ult.emissao', 'silêncio(d)', 'ult', 'ping'],
                array_map(fn ($l) => [
                    $l['biz'],
                    $l['cert_status'],
                    $l['cert_dias'] !== null ? (string) $l['cert_dias'] : '—',
                    $l['ultima_emissao'] ?? '—',
                    $l['silencio_dias'] !== null ? (string) $l['silencio_dias'] : '—',
                    $l['ult_status'],
                    $l['ping_status'] . (isset($l['ping_cstat']) ? " ({$l['ping_cstat']})" : ''),
                ], $linhas),
            );
        }

        $this->info(sprintf(
            'nfe:health total=%d crit=%d warn=%d ping_sefaz=%s',
            count($linhas), $crit, $warn, $pingSefaz ? 'sim' : 'nao',
        ));

        if ($this->option('notify') && $crit > 0) {
            Log::channel('single')->error("nfe:health ALERT — {$crit} business com cert vencido ou SEFAZ down");
        }

        return $crit === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolve businesses-alvo: filtro explícito ou todos com cert ativo +
     * com emissões nos últimos 30 dias (evita ruído de business inativos).
     *
     * Multi-tenant Tier 0: usa DB::table (sem global scope) pois é CLI sem
     * session — necessário pra ver todos os tenants. Toda query Eloquent
     * downstream respeita business_id explícito.
     */
    private function resolverBusinesses(?int $bizFilter): \Illuminate\Support\Collection
    {
        $query = DB::table('nfe_certificados as c')
            ->select('c.business_id')
            ->where('c.ativo', true)
            ->whereNull('c.deleted_at')
            ->groupBy('c.business_id');

        if ($bizFilter !== null) {
            $query->where('c.business_id', $bizFilter);
        }

        return collect($query->get());
    }

    /**
     * Cert ativo do business existe? Vencido? Próximo do vencimento?
     */
    private function checkCertificado(int $bizId): array
    {
        try {
            $cert = NfeCertificado::withoutGlobalScopes()
                ->where('business_id', $bizId)
                ->ativos()
                ->latest('id')
                ->first();
        } catch (Throwable $e) {
            return ['status' => 'crit', 'dias' => null, 'msg' => 'erro_query: ' . substr($e->getMessage(), 0, 80)];
        }

        if (! $cert) {
            return ['status' => 'crit', 'dias' => null, 'msg' => 'sem_cert_ativo'];
        }

        $dias = $cert->diasAteVencimento();

        if ($dias < 0) {
            return ['status' => 'crit', 'dias' => $dias, 'msg' => 'vencido'];
        }

        if ($dias <= self::CERT_WARN_DIAS) {
            return ['status' => 'warn', 'dias' => $dias, 'msg' => "vence_em_{$dias}d"];
        }

        return ['status' => 'ok', 'dias' => $dias, 'msg' => 'valido'];
    }

    /**
     * Última emissão do business: dias de silêncio desde a última autorizada.
     */
    private function checkUltimaEmissao(int $bizId): array
    {
        try {
            $ultima = NfeEmissao::withoutGlobalScopes()
                ->where('business_id', $bizId)
                ->whereNotNull('emitido_em')
                ->orderByDesc('emitido_em')
                ->first();
        } catch (Throwable $e) {
            return ['status' => 'crit', 'ultima_em' => null, 'silencio_dias' => null];
        }

        if (! $ultima) {
            // Sem emissão alguma — não é WARN por ora (business pode estar setup)
            return ['status' => 'ok', 'ultima_em' => null, 'silencio_dias' => null];
        }

        $silencio = (int) now()->diffInDays($ultima->emitido_em, true);
        $emitidoEm = $ultima->emitido_em?->toIso8601String();

        if ($silencio > self::SILENCIO_MAX_DIAS) {
            return ['status' => 'warn', 'ultima_em' => $emitidoEm, 'silencio_dias' => $silencio];
        }

        return ['status' => 'ok', 'ultima_em' => $emitidoEm, 'silencio_dias' => $silencio];
    }

    /**
     * Ping real SEFAZ via NfeService::consultarStatusSefaz. SEFAZ cstat=107
     * = "Servico em Operacao". Qualquer outra coisa = warn ou crit (depende
     * do código). Cert vencido (280/281/283) classifica como crit.
     */
    private function checkPingSefaz(int $bizId): array
    {
        try {
            $resp = app(NfeService::class)->consultarStatusSefaz($bizId);
        } catch (Throwable $e) {
            return ['status' => 'crit', 'cstat' => null, 'msg' => substr($e->getMessage(), 0, 80)];
        }

        $cstat = (string) ($resp['cstat'] ?? '999');

        if ($cstat === '107') {
            return ['status' => 'ok', 'cstat' => $cstat];
        }

        // Cert ou auth = crit
        if (in_array($cstat, ['280', '281', '283'], true)) {
            return ['status' => 'crit', 'cstat' => $cstat, 'msg' => $resp['xMotivo'] ?? ''];
        }

        // SEFAZ paralisada = warn (transiente)
        return ['status' => 'warn', 'cstat' => $cstat, 'msg' => $resp['xMotivo'] ?? ''];
    }
}
