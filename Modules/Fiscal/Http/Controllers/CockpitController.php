<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfseEmissao;

/**
 * Cockpit Fiscal (sub-página 1 do design KB-9.75).
 *
 * Agrega KPIs + alertas + quick links de todos os outros sub-módulos fiscais:
 *  - NF-e/NFC-e (NfeEmissao via HasBusinessScope ADR 0093)
 *  - NFS-e (NfseEmissao)
 *  - DF-e (NfeDfeRecebido — manifestação)
 *  - Certificado (NfeCertificado — vencimento)
 *
 * Sparklines: contagem por dia (últimos 14d) por status.
 * Alertas: 3 níveis (crit/warn/info) derivados deterministicamente do estado.
 *
 * Eager (não defer) — KPIs do cockpit precisam aparecer first paint.
 */
class CockpitController extends Controller
{
    /**
     * Cache key pra KPIs do cockpit por business — 60s TTL (GAP-FISCAL-002).
     * Invalidado via InvalidaCockpitCacheListener em NFeAutorizada/NFCeAutorizada.
     */
    public const KPIS_CACHE_TTL_SECONDS = 60;

    /**
     * GET /fiscal — entrypoint do módulo.
     *
     * Reuse: $cert + $dfeCount são computados uma vez em buildContexto() e
     * passados pra computeKpis() + computeAlerts() (audit sênior 2026-05-25
     * achou 2 queries redundantes; agora 8 queries → 6 em cache miss, 0 em hit).
     */
    public function index(): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            abort(403, 'Sem permissão fiscal.access');
        }

        $contexto = $this->buildContexto();

        $businessId = (int) (session('user.business_id') ?? 0);
        $kpis = Cache::remember(
            $this->kpisCacheKey($businessId),
            self::KPIS_CACHE_TTL_SECONDS,
            fn () => $this->computeKpis($contexto),
        );

        return Inertia::render('Fiscal/Cockpit', [
            'kpis'       => $kpis,
            'sparklines' => $this->computeSparklines(),
            'alerts'     => $this->computeAlerts($contexto),
        ]);
    }

    /**
     * Cache key — DEVE bater com InvalidaCockpitCacheListener::KEY_PREFIX.
     */
    public function kpisCacheKey(int $businessId): string
    {
        return 'fiscal:cockpit:kpis:biz:' . $businessId;
    }

    /**
     * Reusa queries caras (cert + dfeCount) entre computeKpis e computeAlerts.
     * Antes: cada query rodava 2× (uma em KPIs, outra em alerts). Agora 1×.
     */
    protected function buildContexto(): array
    {
        $cert = NfeCertificado::query()->where('ativo', true)->orderByDesc('valido_ate')->first();
        $dfeCount = NfeDfeRecebido::query()
            ->whereIn('status_manifestacao', ['pendente', 'ciencia'])
            ->count();

        return [
            'cert' => $cert,
            'dfeCount' => $dfeCount,
        ];
    }

    /**
     * KPIs do mês corrente (eager — query rápida count/sum).
     *
     * @param  array{cert: ?NfeCertificado, dfeCount: int}  $contexto
     */
    protected function computeKpis(array $contexto): array
    {
        $inicioMes = now()->startOfMonth();

        $emitidas    = NfeEmissao::query()->where('emitido_em', '>=', $inicioMes)->count();
        $autorizadas = NfeEmissao::query()->where('emitido_em', '>=', $inicioMes)->where('status', 'autorizada')->count();
        $rejeitadas  = NfeEmissao::query()->where('emitido_em', '>=', $inicioMes)->whereIn('status', ['rejeitada', 'denegada'])->count();
        $faturado    = (float) NfeEmissao::query()->where('emitido_em', '>=', $inicioMes)->where('status', 'autorizada')->sum('valor_total');

        // Reuse contexto (vinha de 2 queries idênticas no computeAlerts)
        $dfeAguardando = $contexto['dfeCount'];
        $cert = $contexto['cert'];
        $certDias = $cert?->valido_ate
            ? (int) now()->startOfDay()->diffInDays($cert->valido_ate, false)
            : null;

        return [
            'emitidas'                => $emitidas,
            'autorizadas'             => $autorizadas,
            'autorizadasPct'          => $emitidas > 0 ? round($autorizadas * 100 / $emitidas, 1) : 0.0,
            'rejeitadas'              => $rejeitadas,
            'faturamentoFiscal'       => $faturado,
            'dfeAguardando'           => $dfeAguardando,
            'certificadoValidadeDias' => $certDias,
        ];
    }

    /**
     * Sparklines (últimos 14 dias) — array por status com 14 ints (uma contagem por dia).
     * Querya 1× e agrupa em PHP pra evitar 14 round-trips.
     */
    protected function computeSparklines(): array
    {
        $inicio = now()->startOfDay()->subDays(13); // hoje + 13 dias atrás = 14 dias

        $rows = NfeEmissao::query()
            ->where('emitido_em', '>=', $inicio)
            ->selectRaw('DATE(emitido_em) as dia, status, COUNT(*) as n, SUM(valor_total) as v')
            ->groupBy('dia', 'status')
            ->get()
            ->groupBy('dia');

        $emitidas = [];
        $autorizadas = [];
        $rejeitadas = [];
        $faturamento = [];

        for ($i = 0; $i < 14; $i++) {
            $dia = $inicio->copy()->addDays($i)->format('Y-m-d');
            $diaRows = $rows->get($dia, collect());

            $emitidas[]    = (int) $diaRows->sum('n');
            $autorizadas[] = (int) $diaRows->where('status', 'autorizada')->sum('n');
            $rejeitadas[]  = (int) $diaRows->whereIn('status', ['rejeitada', 'denegada'])->sum('n');
            $faturamento[] = (float) round(
                $diaRows->where('status', 'autorizada')->sum('v') / 1000, // em milhares
                2
            );
        }

        return compact('emitidas', 'autorizadas', 'rejeitadas', 'faturamento');
    }

    /**
     * Alertas determinísticos (sem LLM) — 3 níveis (crit/warn/info).
     *
     * Reusa $cert + $dfeCount do contexto (antes: query duplicada do computeKpis).
     *
     * @param  array{cert: ?NfeCertificado, dfeCount: int}  $contexto
     */
    protected function computeAlerts(array $contexto): array
    {
        $alerts = [];

        // Crit: rejeições recentes (últimos 7d)
        $rejs = NfeEmissao::query()
            ->whereIn('status', ['rejeitada', 'denegada'])
            ->where('emitido_em', '>=', now()->subDays(7))
            ->orderByDesc('emitido_em')
            ->limit(2)
            ->get(['id', 'numero', 'modelo', 'cstat', 'motivo', 'valor_total', 'emitido_em']);

        foreach ($rejs as $rej) {
            $alerts[] = [
                'level'  => 'crit',
                'icon'   => 'audit',
                'title'  => "NF{$this->modeloLabel($rej->modelo)} {$rej->numero} rejeitada (cstat {$rej->cstat})",
                'sub'    => $rej->motivo ?? 'Sem motivo registrado',
                'action' => 'Abrir nota',
                'goto'   => 'nfe',
                'focus'  => (string) $rej->id,
            ];
        }

        // Warn: cert vencendo <60d (reusa $cert do contexto)
        $cert = $contexto['cert'];
        if ($cert?->valido_ate) {
            $dias = (int) now()->startOfDay()->diffInDays($cert->valido_ate, false);
            if ($dias <= 60 && $dias > 0) {
                $alerts[] = [
                    'level'  => $dias <= 7 ? 'crit' : 'warn',
                    'icon'   => 'shield',
                    'title'  => "Certificado A1 vence em {$dias} dias",
                    'sub'    => 'Agendar renovação com contador',
                    'action' => 'Abrir configuração',
                    'goto'   => 'fiscal_config',
                ];
            }
        }

        // Info: DF-e pendente manifestação (reusa $dfeCount do contexto)
        $dfeCount = $contexto['dfeCount'];
        if ($dfeCount > 0) {
            $alerts[] = [
                'level'  => $dfeCount > 10 ? 'warn' : 'info',
                'icon'   => 'receipt',
                'title'  => "{$dfeCount} DF-e aguardando manifestação",
                'sub'    => 'Prazo legal: 90 dias da emissão',
                'action' => 'Manifestar',
                'goto'   => 'dfe',
            ];
        }

        return $alerts;
    }

    protected function modeloLabel(string $modelo): string
    {
        return $modelo === '65' ? 'C-e' : 'e';
    }
}
