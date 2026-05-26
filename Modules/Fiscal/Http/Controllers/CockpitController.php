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
            // Stub Wave Cowork — visual "Notas Fiscais" do prototipo-ui.
            // TODO[CL]: substituir por NotasUnifiedService::query() unificando
            // NfeEmissao + NfseEmissao com filtros server-side e cursor pagination.
            'notasMock'      => $this->mockNotasUnificadas(),
            'savedViewCounts' => $this->mockSavedViewCounts(),
            'sefazStatus'    => $this->mockSefazStatus(),
        ]);
    }

    /**
     * STUB mock — 10 notas unificadas (NF-e + NFC-e + NFS-e) pra hidratar
     * o visual "Notas Fiscais" do prototipo-ui. Datasets reais virão de
     * NotasUnifiedService no PR seguinte (TODO[CL]).
     */
    protected function mockNotasUnificadas(): array
    {
        $now = now();

        // PII-safe: CNPJ/CPF mascarados ([REDACTED]) — pii-scan compliant (LGPD Art. 7º).
        // Backend real (NotasUnifiedService no PR seguinte) terá os docs reais do DB.
        return [
            ['id' => 'nfse-2104', 'tipo' => 'NFS-e', 'kind' => 'nfse', 'num' => '2104', 'serie' => null, 'when' => '05/2026',
             'cliente' => 'TechPro Equipamentos', 'doc' => '[REDACTED-CNPJ]', 'cnpj' => '[REDACTED-CNPJ]', 'uf' => 'São Paulo/SP',
             'venda' => 'OS #4807', 'ref' => 'OS #4807', 'keyOrCode' => '14.05', 'iss' => 5,
             'codServ' => '14.05', 'competencia' => '05/2026',
             'status' => 'autorizada', 'statusKind' => 'nfse', 'rejMsg' => null,
             'modelo' => null, 'value' => 2840.00, 'prazoCancel' => null, 'prazoCce' => null],
            ['id' => 'nfe-8428', 'tipo' => 'NF-e', 'kind' => 'nfe', 'num' => '8428', 'serie' => '1', 'when' => $now->copy()->subHours(4)->format('d/m H:i'),
             'cliente' => 'Imobiliária Horizonte', 'doc' => '[REDACTED-CNPJ]', 'uf' => 'SP',
             'venda' => 'V-4821', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFE-44]',
             'status' => 100, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 55, 'value' => 540.00,
             'prazoCancel' => ['label' => '19h20', 'urgency' => 'ok'], 'prazoCce' => null],
            ['id' => 'nfce-9012', 'tipo' => 'NFC-e', 'kind' => 'nfe', 'num' => '9012', 'serie' => '9', 'when' => $now->copy()->subHours(5)->subMinutes(10)->format('d/m H:i'),
             'cliente' => 'Consumidor', 'doc' => '—', 'uf' => 'SP',
             'venda' => 'V-4825', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFCE-44]',
             'status' => 100, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 65, 'value' => 84.00,
             'prazoCancel' => ['label' => '18h50', 'urgency' => 'ok'], 'prazoCce' => null],
            ['id' => 'nfce-9011', 'tipo' => 'NFC-e', 'kind' => 'nfe', 'num' => '9011', 'serie' => '9', 'when' => $now->copy()->subHours(6)->format('d/m H:i'),
             'cliente' => 'Consumidor (CPF nota)', 'doc' => '[REDACTED-CPF]', 'uf' => 'SP',
             'venda' => 'V-4824', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFCE-44]',
             'status' => 100, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 65, 'value' => 142.00,
             'prazoCancel' => ['label' => '17h18', 'urgency' => 'ok'], 'prazoCce' => null],
            ['id' => 'nfe-8427', 'tipo' => 'NF-e', 'kind' => 'nfe', 'num' => '8427', 'serie' => '1', 'when' => $now->copy()->subHours(7)->format('d/m H:i'),
             'cliente' => 'Imobiliária Horizonte', 'doc' => '[REDACTED-CNPJ]', 'uf' => 'SP',
             'venda' => 'V-4820', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFE-44]',
             'status' => 100, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 55, 'value' => 560.00,
             'prazoCancel' => ['label' => '16h05', 'urgency' => 'ok'], 'prazoCce' => null],
            ['id' => 'nfe-8425', 'tipo' => 'NF-e', 'kind' => 'nfe', 'num' => '8425', 'serie' => '1',
             'when' => $now->copy()->subHours(9)->format('d/m H:i'),
             'emittedAtIso' => $now->copy()->subHours(9)->toIso8601String(),
             'cliente' => 'Gráfica Ribeirão Ltda', 'doc' => '[REDACTED-CNPJ]', 'cnpj' => '[REDACTED-CNPJ]', 'uf' => 'SP',
             'venda' => 'V-4815', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFE-44]',
             'status' => 110, 'statusKind' => 'sefaz',
             'rejMsg' => 'IE destinatário inválida no cadastro SP',
             'modelo' => 55, 'value' => 1840.00, 'prazoCancel' => null, 'prazoCce' => null,
             // Mock enriquecido pra demonstrar drawer com receita SEFAZ (cstat 110)
             'itens' => [
                ['nome' => 'Banner 3x1m lona impressa', 'codigo' => 'BNL-3X1', 'qtd' => 2, 'vl' => 720.00],
                ['nome' => 'Adesivo recortado 50x30cm', 'codigo' => 'ADV-RC50', 'qtd' => 10, 'vl' => 40.00],
             ],
             'arquivos' => [
                ['tipo' => 'XML', 'nome' => '8425-rejeitada.xml', 'tamanho' => '12.4 KB', 'status' => 'gerado'],
             ],
             'emails' => [],
             'auditoria' => [
                ['quando' => '14/05 09:23', 'autor' => 'Eliana', 'acao' => 'tentou transmitir → SEFAZ retornou 110'],
                ['quando' => '14/05 09:12', 'autor' => 'Wagner', 'acao' => 'criou venda V-4815'],
             ],
             'eventos' => [],
            ],
            ['id' => 'nfe-8424', 'tipo' => 'NF-e', 'kind' => 'nfe', 'num' => '8424', 'serie' => '1',
             'when' => $now->copy()->subDay()->format('d/m H:i'),
             'emittedAtIso' => $now->copy()->subDay()->toIso8601String(),
             'cliente' => 'AutoCenter Premium', 'doc' => '[REDACTED-CNPJ]', 'cnpj' => '[REDACTED-CNPJ]', 'uf' => 'SP',
             'venda' => 'V-4810', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFE-44]',
             'status' => 100, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 55, 'value' => 3200.00, 'prazoCancel' => null,
             'prazoCce' => ['label' => '29d', 'urgency' => 'ok'],
             // Mock enriquecido pra demonstrar drawer autorizada completa (itens + boleto + eventos + arquivos + emails + auditoria)
             'itens' => [
                ['nome' => 'Envelopamento veicular Hilux completo', 'codigo' => 'ENV-HLX-FULL', 'qtd' => 1, 'vl' => 2800.00],
                ['nome' => 'Película insulfilm G20 vidros laterais', 'codigo' => 'PEL-G20-LAT', 'qtd' => 4, 'vl' => 100.00],
             ],
             'boleto' => ['id' => 'BOL-4810', 'venc' => $now->copy()->addDays(28)->format('d/m/Y'), 'valor' => 3200.00, 'status' => 'pendente'],
             'arquivos' => [
                ['tipo' => 'XML', 'nome' => '8424-procNFe.xml', 'tamanho' => '14.8 KB', 'status' => 'gerado'],
                ['tipo' => 'PDF', 'nome' => '8424-DANFE.pdf', 'tamanho' => '128 KB', 'status' => 'gerado'],
             ],
             'emails' => [
                ['tipo' => 'XML + DANFE pro cliente', 'para' => 'compras@autocenterpremium.example.br', 'quando' => $now->copy()->subHours(22)->format('d/m H:i'), 'status' => 'entregue'],
             ],
             'auditoria' => [
                ['quando' => $now->copy()->subDay()->format('d/m H:i'), 'autor' => 'Eliana', 'acao' => 'autorizou e enviou pro cliente'],
                ['quando' => $now->copy()->subDays(2)->format('d/m H:i'), 'autor' => 'Wagner', 'acao' => 'criou venda V-4810 com 2 itens'],
             ],
             'eventos' => [],
            ],
            ['id' => 'nfse-2103', 'tipo' => 'NFS-e', 'kind' => 'nfse', 'num' => '2103', 'serie' => null, 'when' => '05/2026',
             'cliente' => 'Construtora Vale', 'doc' => '[REDACTED-CNPJ]', 'uf' => 'SP',
             'venda' => null, 'ref' => 'OS #4805', 'keyOrCode' => '14.05', 'iss' => 5,
             'status' => 'rejeitada', 'statusKind' => 'nfse',
             'rejMsg' => 'Tomador sem IE municipal — Guarulhos',
             'modelo' => null, 'value' => 1200.00, 'prazoCancel' => null, 'prazoCce' => null],
            ['id' => 'nfe-8423', 'tipo' => 'NF-e', 'kind' => 'nfe', 'num' => '8423', 'serie' => '1', 'when' => $now->copy()->subDays(2)->format('d/m H:i'),
             'cliente' => 'Vargas Distribuidor', 'doc' => '[REDACTED-CNPJ]', 'uf' => 'RJ',
             'venda' => 'V-4805', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFE-44]',
             'status' => 999, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 55, 'value' => 4250.00, 'prazoCancel' => null, 'prazoCce' => null],
            ['id' => 'nfce-9008', 'tipo' => 'NFC-e', 'kind' => 'nfe', 'num' => '9008', 'serie' => '9', 'when' => $now->copy()->subDays(2)->subHours(3)->format('d/m H:i'),
             'cliente' => 'Consumidor', 'doc' => '—', 'uf' => 'SP',
             'venda' => 'V-4802', 'ref' => null,
             'keyOrCode' => '[REDACTED-CHAVE-NFCE-44]',
             'status' => 100, 'statusKind' => 'sefaz', 'rejMsg' => null,
             'modelo' => 65, 'value' => 67.00, 'prazoCancel' => null, 'prazoCce' => null],
        ];
    }

    /**
     * Counts pra preset chips ("Pra resolver hoje", "Janela 24h", etc).
     * TODO[CL]: derivar dos rows reais via NotasUnifiedService.
     */
    protected function mockSavedViewCounts(): array
    {
        return [
            'todas'       => 18,
            'resolver'    => 3,
            'janela24'    => 5,
            'processando' => 1,
            'nfse'        => 2,
            'nfce'        => 4,
        ];
    }

    /**
     * Status SEFAZ-SP atual (mock — TODO[CL] consumir webservice status).
     */
    protected function mockSefazStatus(): array
    {
        return ['uf' => 'SP', 'operacional' => true, 'label' => 'SEFAZ-SP operacional'];
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
