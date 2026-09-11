<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fiscal\Services\NotasUnifiedService;
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
    public function index(NotasUnifiedService $notasService): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            abort(403, 'Sem permissão fiscal.access');
        }

        $contexto = $this->buildContexto();

        // CU-FISC-16 — uma consulta, duas superfícies: a lista e os contadores das
        // visões salvas saem da MESMA fonte, pra não voltarem a divergir.
        $notas = $notasService->listar();

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
            // CU-FISC-16 (2026-09-02) — DADO REAL. O TODO[CL] daqui pedia exatamente
            // isto: NotasUnifiedService unificando NfeEmissao + NfseEmissao. Enquanto
            // era mock, a tela se contradizia — header "0 notas" (KPI real) × 10 linhas
            // "Autorizada" (mock) × chip "Todas 18" (outro mock). Os contadores agora
            // derivam da MESMA lista, então chip e tabela concordam por construção.
            // Sem emissão real, a lista vem vazia e o empty state da Page assume.
            'notas'       => $notas,
            // HOTFIX 2026-09-02 — produção ficou com TELA PRETA por ~1h depois do #6541.
            // Causa medida no ar: o backend subiu servindo `notas`, mas os assets do Vite
            // NÃO foram rebuildados — `Cockpit-CCw9ixg9.js` em prod ainda continha
            // `notasMock`. O bundle antigo lia `props.notasMock`, recebia undefined, e o
            // `useMemo` com `.find()` derrubava a Page inteira.
            //
            // Renomear prop que atravessa backend↔frontend só é seguro quando os dois
            // lados sobem juntos. Enquanto não houver garantia disso, servir os DOIS
            // nomes é o que torna o rename atômico do ponto de vista do cliente.
            //
            // Remover quando: (a) o build em prod não contiver mais `notasMock` — confira
            // com `curl -s <asset-url> | grep -c notasMock` — e (b) o deploy passar a
            // rebuildar assets no mesmo passo do PHP.
            'notasMock'   => $notas,
            'savedViewCounts' => $notasService->contadores($notas),
            'sefazStatus'    => $this->mockSefazStatus(),
            // Onda 2 — drawers do header (Eventos + Enviar p/ contabilidade)
            'eventosMock'    => $this->mockEventos(),
            'contabilData'   => $this->mockContabilData(),
            // Onda 3 L — auditoria mensal (write-off candidatos determinístico, sem IA)
            'writeOffSummary' => $this->mockWriteOffSummary(),
            'procedencia'     => $this->procedencia(),
        ]);
    }

    /**
     * CU-FISC-16 — a procedência de cada superfície desta tela.
     *
     * DECLARADA AQUI, e não adivinhada na Page, por um motivo medido: quando o #6541
     * trocou a lista mockada pelo `NotasUnifiedService`, o protótipo e o SDD §5.4.1
     * continuaram dizendo "demonstração" para `notas` e `savedViewCounts` — dois
     * documentos afirmando algo que o código já tinha desmentido. Aqui a declaração
     * fica a poucas linhas do `Inertia::render`, então quem troca um mock por serviço
     * real troca a linha correspondente no MESMO diff.
     *
     * Medido em 2026-09-04 sobre o tip d23bc3df34: das 8 superfícies desta tela,
     * 4 servem dado inventado, e são exatamente os 4 métodos `mock*` deste arquivo.
     *
     * `sparklines` ficou de fora ATÉ 2026-09-04, e o motivo era DATADO, não permanente:
     * medido no tip `d23bc3df34`, a prop era servida e real (`computeSparklines` agrupa
     * por dia em `nfe_emissoes`) mas a Page a recebia sem consumir — selo sem superfície
     * visível não teria onde pousar.
     *
     * ✅ GATILHO CUMPRIDO no #6732, que desenhou as três séries: a chave `spark` está no
     * array abaixo E o selo correspondente está na Page (`chave="spark"`, no `<small>` de
     * "Emitidas", ao lado do `kpis`). Os dois JUNTOS de propósito — o `SeloProcedencia`
     * faz `return null` sem invocação, e o assert 3 do `ProcedenciaCockpitTest` é one-way
     * (tela → declaração), então chave sem selo ficaria muda e nenhum teste acusaria.
     *
     * O gatilho fica como fato datado, não como imperativo: mantê-lo em "acrescente aqui"
     * depois de acrescentado seria instrução obsoleta com cara de pendência (LC-15).
     *
     * @return array<string, array{origem: string, explica: string}>
     */
    protected function procedencia(): array
    {
        return [
            'kpis' => [
                'origem'  => 'real',
                'explica' => 'Contagem e soma em nfe_emissoes do mês corrente, com escopo do business e cache de 60s.',
            ],
            'spark' => [
                'origem'  => 'real',
                'explica' => 'Série de 14 dias agrupada por dia em nfe_emissoes numa consulta só, com escopo do business.',
            ],
            'alerts' => [
                'origem'  => 'real',
                'explica' => 'Receita determinística sobre o estado atual: rejeições, validade do certificado e DF-e pendente. Sem IA.',
            ],
            'notas' => [
                'origem'  => 'real',
                'explica' => 'Lista unificada NF-e/NFC-e/NFS-e servida pelo NotasUnifiedService desde 2026-09-02. Sem emissão no período, vem vazia.',
            ],
            'viewCounts' => [
                'origem'  => 'real',
                'explica' => 'Contadores derivados da mesma lista exibida — por construção, chip e tabela concordam.',
            ],
            'sefaz' => [
                'origem'  => 'demonstracao',
                'explica' => 'Situação fixa no código. Ser real depende de consumir o webservice de status da SEFAZ por UF.',
            ],
            'eventos' => [
                'origem'  => 'demonstracao',
                'explica' => 'Os 5 eventos do cabeçalho são inventados, autores inclusive. Ser real depende de consultar nfe_eventos.',
            ],
            'contabil' => [
                'origem'  => 'demonstracao',
                'explica' => 'Números do pacote e histórico de envios são fixos no código, e o envio por e-mail/SFTP ainda não existe.',
            ],
            'writeoff' => [
                'origem'  => 'demonstracao',
                'explica' => 'Candidatos e valores são fixos no código. Ser real depende de consultar fin_titulos vencidos há mais de 365 dias sem pagamento.',
            ],
        ];
    }

    /**
     * Mock summary write-off candidatos. Onda 3 L. Determinístico (sem IA).
     * TODO[CL]: substituir por WriteOffAuditService::analyzeMonth() —
     * query fin_titulos WHERE due_at < now()-365d AND payment_count=0.
     *
     * @return array<string, mixed>|null
     */
    protected function mockWriteOffSummary(): ?array
    {
        return [
            'totalCandidates' => 2470,
            'totalValor'      => 770_000.00,
            'oldestAge'       => 1847, // ~5 anos
            'category'        => 'incobravel',
            'scopeLabel'      => 'Inadimplência >365d',
        ];
    }

    /**
     * Mock eventos fiscais (CC-e, cancelamento, inutilização, EPEC,
     * manifestação) pra alimentar EventosDrawer do header. Onda 2.
     * TODO[CL]: substituir por query real em nfe_eventos (NfeBrasil).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mockEventos(): array
    {
        $now = now();

        return [
            ['id' => 'evt-1', 'tipo' => 'Carta de Correção', 'kind' => 'cce', 'nota' => 'NFe 8424',
             'sequencia' => 1, 'descricao' => 'Corrigir info adicional natureza operação',
             'emit' => $now->copy()->subHours(3)->format('d/m H:i'), 'autor' => 'Eliana', 'sefaz' => 100],
            ['id' => 'evt-2', 'tipo' => 'Cancelamento', 'kind' => 'cancel', 'nota' => 'NFe 8420',
             'descricao' => 'Cliente desistiu da compra antes de envelopamento',
             'emit' => $now->copy()->subHours(8)->format('d/m H:i'), 'autor' => 'Eliana', 'sefaz' => 101],
            ['id' => 'evt-3', 'tipo' => 'Inutilização', 'kind' => 'inutilizacao', 'nota' => 'Faixa 8418-8419',
             'descricao' => 'Inutilização de faixa numérica saltada (erro digitação)',
             'emit' => $now->copy()->subDay()->format('d/m H:i'), 'autor' => 'Wagner', 'sefaz' => 102],
            ['id' => 'evt-4', 'tipo' => 'Manifestação destinatário', 'kind' => 'manifest', 'nota' => 'NFe entrada 982',
             'descricao' => 'Confirmação operação fornecedor TechSupply Ltda',
             'emit' => $now->copy()->subDays(2)->format('d/m H:i'), 'autor' => 'Wagner', 'sefaz' => 135],
            ['id' => 'evt-5', 'tipo' => 'Cancelamento', 'kind' => 'cancel', 'nota' => 'NFCe 9005',
             'descricao' => 'Cliente devolveu mercadoria mesma data',
             'emit' => $now->copy()->subDays(3)->format('d/m H:i'), 'autor' => 'Larissa', 'sefaz' => 101],
        ];
    }

    /**
     * Mock dados pro SendToContabilDrawer. Onda 2.
     * TODO[CL]: substituir por ContabilSendService real (validações reais
     * + agrega XMLs + dispara job de envio email/SFTP).
     *
     * @return array<string, mixed>
     */
    protected function mockContabilData(): array
    {
        $now = now();

        return [
            'periodoCorrente'    => $now->locale('pt_BR')->isoFormat('MMMM/YYYY'),
            'contadorNome'       => 'A configurar em /fiscal/config',
            'destinatarioPadrao' => 'contador@example.com.br',
            'validacoes' => [
                ['ok' => true,   'label' => '184 NF-e autorizadas no período'],
                ['ok' => 'warn', 'label' => '3 NF-e rejeitadas — não entram no pacote', 'action' => 'Ver rejeitadas', 'goto' => '/fiscal/nfe?status=rejeitadas'],
                ['ok' => true,   'label' => '5 DF-e manifestadas (4 confirmadas + 1 desconhecida)'],
                ['ok' => 'warn', 'label' => 'Certificado A1 vence em 47d — renovar antes do próximo fechamento', 'action' => 'Renovar', 'goto' => '/fiscal/config'],
                ['ok' => true,   'label' => 'SPED EFD ICMS/IPI pronto pra gerar (último: abr/2026)'],
            ],
            'totalsByPeriodo' => [
                'autorizadas' => 184,
                'nfse'        => 12,
                'eventos'     => 5,
            ],
            'history' => [
                ['id' => 'hist-1', 'periodo' => 'abril/2026', 'enviadoEm' => '03/05 09:23',
                 'metodo' => 'email', 'destino' => 'contador@example.com.br',
                 'pacote' => 4_320_000, 'status' => 'enviado'],
                ['id' => 'hist-2', 'periodo' => 'março/2026', 'enviadoEm' => '02/04 10:15',
                 'metodo' => 'email', 'destino' => 'contador@example.com.br',
                 'pacote' => 3_980_000, 'status' => 'enviado'],
                ['id' => 'hist-3', 'periodo' => 'fevereiro/2026', 'enviadoEm' => '04/03 11:48',
                 'metodo' => 'download', 'destino' => 'eliana@local',
                 'pacote' => 2_140_000, 'status' => 'enviado'],
            ],
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

        // Cert VENCIDO, vencendo hoje, ou vencendo em <=60d (reusa $cert do contexto).
        //
        // `$dias` é NEGATIVO quando o certificado já venceu. Até 2026-09-04 a guarda
        // era `$dias <= 60 && $dias > 0`, que descartava justamente esse caso: no pior
        // estado possível a fila do cockpit ficava MUDA, enquanto o badge da sidebar
        // (GUARD US-NFE-001) já acusava "vencido há N dias" na MESMA tela.
        //
        // Medido em 2026-09-04: os 5 consumidores de `diasAteVencimento()` classificam
        // por `$dias < 0` (vencido) e põem o `0` na banda de aviso, nunca num vão
        // — CertHealthCheckCommand:187, ConfigController:61, NfeHealthCommand:213,
        // HandleInertiaRequests:384 e PaymentGatewaysController:97 (`$dias >= 0`).
        // O cockpit era o único fora do padrão, e era outlier nas DUAS pontas.
        $cert = $contexto['cert'];
        if ($cert?->valido_ate) {
            $dias = (int) now()->startOfDay()->diffInDays($cert->valido_ate, false);

            if ($dias < 0) {
                $ha = abs($dias);
                $alerts[] = [
                    'level'  => 'crit',
                    'icon'   => 'shield',
                    'title'  => "Certificado A1 vencido há {$ha} " . ($ha === 1 ? 'dia' : 'dias'),
                    'sub'    => 'Renovar com o contador imediatamente',
                    'action' => 'Abrir configuração',
                    'goto'   => 'fiscal_config',
                ];
            } elseif ($dias === 0) {
                $alerts[] = [
                    'level'  => 'crit',
                    'icon'   => 'shield',
                    'title'  => 'Certificado A1 vence hoje',
                    'sub'    => 'Renovar com o contador imediatamente',
                    'action' => 'Abrir configuração',
                    'goto'   => 'fiscal_config',
                ];
            } elseif ($dias <= 60) {
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
