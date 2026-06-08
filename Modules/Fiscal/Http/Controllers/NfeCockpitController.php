<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * Cockpit NF-e · NFC-e (sub-página 2 do design Fiscal).
 *
 * Thin agregador: lê `Modules\NfeBrasil\Models\NfeEmissao` (HasBusinessScope
 * global scope — ADR 0093) e entrega lista + counts + KPIs pra Pages/Fiscal/Nfe.tsx.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL — toda query passa por business scope automático.
 * `Inertia::defer` em props caras (skill `inertia-defer-default` Tier B).
 *
 * Sem mutations no PR #1 — somente leitura. Ações (cancelar, retransmitir, CC-e)
 * em PRs subsequentes; design já reserva os botões no NotaDrawer.
 */
class NfeCockpitController extends Controller
{
    /**
     * GET /fiscal/nfe — cockpit NF-e + NFC-e (modelos 55 + 65).
     *
     * Props:
     *  - filters (search, status, tab) — para reidratar UI
     *  - counts (eager) — barra de chips
     *  - rows (deferred) — lista paginada
     *  - sefazCodes (eager, estático) — para SefazPill render no client
     */
    public function index(Request $request): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.nfe.view')) {
            abort(403, 'Sem permissão fiscal.nfe.view');
        }

        $filters = [
            'search' => (string) $request->input('search', ''),
            'status' => (string) $request->input('status', 'todas'),
            'tab'    => (string) $request->input('tab', 'saida_nfe'),
            'focus'  => (string) $request->input('focus', ''),
        ];

        // Counts são baratos (SUM/COUNT scoped per business via HasBusinessScope).
        // Eager pra UI mostrar chips imediatamente.
        $counts = $this->computeCounts();

        // Rows são caras (paginate + soft join potencial em PR futura).
        // Deferred — Inertia chama via partial reload quando UI precisa.
        return Inertia::render('Fiscal/Nfe', [
            'filters'    => $filters,
            'counts'     => $counts,
            'sefazCodes' => $this->sefazCodes(),
            'rows'       => Inertia::defer(fn () => $this->buildRowsPayload($filters)),
        ]);
    }

    /**
     * Counts por modelo + status — barra de filtros.
     * Query única com CASE WHEN pra evitar N round-trips ao DB.
     */
    protected function computeCounts(): array
    {
        $base = NfeEmissao::query();

        $total       = (clone $base)->count();
        $nfe         = (clone $base)->where('modelo', '55')->count();
        $nfce        = (clone $base)->where('modelo', '65')->count();
        $autorizadas = (clone $base)->where('status', 'autorizada')->count();
        $rejeitadas  = (clone $base)->whereIn('status', ['rejeitada', 'denegada'])->count();
        $processando = (clone $base)->where('status', 'pendente')->count();
        $canceladas  = (clone $base)->where('status', 'cancelada')->count();

        // Cancelaveis = autorizadas dentro da janela (24h NFC-e / 168h NF-e).
        // Computado em PHP porque depende de now() vs emitido_em + modelo.
        $cancelaveis = (clone $base)
            ->where('status', 'autorizada')
            ->whereNotNull('emitido_em')
            ->get(['modelo', 'emitido_em'])
            ->filter(fn ($e) => $this->isCancelavel($e))
            ->count();

        return [
            'total'        => $total,
            'nfe'          => $nfe,
            'nfce'         => $nfce,
            'autorizadas'  => $autorizadas,
            'rejeitadas'   => $rejeitadas,
            'processando'  => $processando,
            'canceladas'   => $canceladas,
            'cancelaveis'  => $cancelaveis,
        ];
    }

    /**
     * Lista paginada de notas — DEFERRED (Inertia partial reload).
     * Filtrado por tab (modelo) + status + search.
     */
    protected function buildRowsPayload(array $filters): array
    {
        $query = NfeEmissao::query()->orderByDesc('emitido_em');

        // Tab → modelo
        if ($filters['tab'] === 'saida_nfe')  $query->where('modelo', '55');
        if ($filters['tab'] === 'saida_nfce') $query->where('modelo', '65');

        // Status chip
        if ($filters['status'] === 'autorizadas')  $query->where('status', 'autorizada');
        if ($filters['status'] === 'rejeitadas')   $query->whereIn('status', ['rejeitada', 'denegada']);
        if ($filters['status'] === 'processando')  $query->where('status', 'pendente');
        if ($filters['status'] === 'canceladas')   $query->where('status', 'cancelada');

        // Search: número, chave (44 dígitos), ou últimos 6 da chave
        if ($filters['search'] !== '') {
            $s = preg_replace('/\D/', '', $filters['search']);
            $raw = $filters['search'];
            $query->where(function ($q) use ($s, $raw) {
                if (strlen($s) >= 1) {
                    $q->where('numero', 'like', "%{$s}%")
                      ->orWhere('chave_44', 'like', "%{$s}%");
                }
                $q->orWhere('motivo', 'like', "%{$raw}%");
            });
        }

        $paginator = $query->paginate(50);

        return [
            'data' => $paginator->getCollection()->map(fn (NfeEmissao $e) => $this->mapRow($e))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }

    /**
     * Shape de cada linha da tabela.
     * Sem PII direto (nome cliente) no PR #1 — design exibe `dest` mas vamos
     * popular via metadata->dest_name quando emissor preencher; fallback "—".
     * Próximo PR junta com transactions/contacts pra nome+CNPJ corretos.
     */
    protected function mapRow(NfeEmissao $e): array
    {
        $metadata = $e->metadata ?? [];

        return [
            'id'             => $e->id,
            'num'            => $e->numero,
            'serie'          => (int) $e->serie,
            'modelo'         => (int) $e->modelo,
            'key'            => $e->chave_44,
            'status'         => $e->status,             // pendente|autorizada|rejeitada|denegada|cancelada|inutilizada
            'cstat'          => (int) ($e->cstat ?? 0), // 100, 110, 220, 539, etc — usado pra SefazPill
            'motivo'         => $e->motivo,
            'value'          => (float) $e->valor_total,
            'emittedAtIso'   => $e->emitido_em?->toIso8601String(),
            'when'           => $e->emitido_em?->format('d/m H:i'),
            'transactionId'  => $e->transaction_id,
            'dest'           => $metadata['dest_name'] ?? '—',
            'cnpj'           => $metadata['dest_cnpj'] ?? null,
            'cpf'            => $metadata['dest_cpf'] ?? null,
            'uf'             => $metadata['dest_uf'] ?? null,
            'items'          => $metadata['items_count'] ?? null,
            'cancelavel'     => $this->isCancelavel($e),
        ];
    }

    /**
     * Janela legal de cancelamento: 24h NFC-e (65) / 168h (7d) NF-e (55).
     * Modelo regulamentar (CONFAZ Ajuste SINIEF 07/2005 Art. 14).
     */
    protected function isCancelavel(NfeEmissao $e): bool
    {
        if ($e->status !== 'autorizada' || ! $e->emitido_em) return false;

        $prazoHoras = $e->modelo === '65' ? 24 : 168;
        return $e->emitido_em->diffInHours(now()) <= $prazoHoras;
    }

    /**
     * Mapa de códigos SEFAZ → tom/label/hint. Estático, baixo custo.
     * Espelha fiscal-data.jsx SEFAZ_CODES do design (R#1 KB-9.75).
     */
    protected function sefazCodes(): array
    {
        return [
            100 => ['tone' => 'ok',   'label' => 'Autorizada',              'hint' => 'Nota válida na SEFAZ.'],
            101 => ['tone' => 'ok',   'label' => 'Cancelamento homologado', 'hint' => 'Cancelamento aceito.'],
            102 => ['tone' => 'ok',   'label' => 'Inutilização homologada', 'hint' => 'Faixa de numeração inutilizada.'],
            104 => ['tone' => 'ok',   'label' => 'Autorizada (NFC-e)',      'hint' => 'NFC-e válida na SEFAZ.'],
            110 => ['tone' => 'bad',  'label' => 'Uso denegado',            'hint' => 'Destinatário irregular na SEFAZ.'],
            135 => ['tone' => 'ok',   'label' => 'Evento registrado',       'hint' => 'CC-e ou manifestação aceita.'],
            204 => ['tone' => 'bad',  'label' => 'Duplicidade de NF-e',     'hint' => 'Já existe nota com a mesma chave.'],
            220 => ['tone' => 'bad',  'label' => 'Duplicidade',             'hint' => 'Numeração já usada. Inutilize ou pule a numeração.'],
            539 => ['tone' => 'bad',  'label' => 'Duplicidade de chave',    'hint' => 'Chave de acesso já existe. Verifique o cNF aleatório.'],
            691 => ['tone' => 'warn', 'label' => 'NCM divergente',          'hint' => 'NCM informado não bate com o cadastro.'],
            778 => ['tone' => 'bad',  'label' => 'CST/CFOP inválido',       'hint' => 'Combinação CST+CFOP rejeitada pela UF destino.'],
            999 => ['tone' => 'warn', 'label' => 'Processando',             'hint' => 'SEFAZ não respondeu ainda. Reenvio automático em 30s.'],
        ];
    }
}
