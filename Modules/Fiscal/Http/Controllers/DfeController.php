<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeRecebido;

/**
 * Manifesto DF-e (sub-página 4 do design KB-9.75).
 *
 * Lista NF-e emitidas CONTRA nosso CNPJ (recebidas via NSU SEFAZ). Prazo
 * legal 90 dias pra manifestação (confirmar/desconhecer/não-realizada/ciência).
 *
 * HasBusinessScope (ADR 0093). Mutações (ações manifestar) em PR futuro
 * — esta PR é leitura + filtros only.
 */
class DfeController extends Controller
{
    public function index(Request $request): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.dfe.manage')) {
            abort(403, 'Sem permissão fiscal.dfe.manage');
        }

        // Aba ativa via rota (?tab=) — barra canônica PageHeaderTabs navega por href
        // (padronização DS Onda 3). Whitelist server-side; default 'pendente'.
        $activeTab = (string) $request->query('tab', 'pendente');
        if (! in_array($activeTab, ['pendente', 'historico'], true)) {
            $activeTab = 'pendente';
        }

        $filters = [
            'status' => (string) $request->input('status', 'pendentes'),
            'search' => (string) $request->input('search', ''),
        ];

        return Inertia::render('Fiscal/Dfe', [
            'activeTab' => $activeTab,
            'filters' => $filters,
            'counts'  => $this->computeCounts(),
            'rows'    => Inertia::defer(fn () => $this->buildRowsPayload($filters)),
            // Aba Histórico — manifestações REAIS (decisão [W] 2026-11-19, lote 2 dos
            // débitos do Fiscal): nota recebida que já teve evento na SEFAZ, mais recente
            // primeiro. Deferida porque é consulta.
            'historico' => Inertia::defer(fn () => $this->buildHistoricoPayload()),
            // Relatório do último lote (US-FISCAL-008). Vem por prop, não por `flash`: o
            // `HandleInertiaRequests` só compartilha as chaves fixas success/error/info/slash,
            // e este payload é estruturado (nota a nota) porque falha parcial em manifestação
            // precisa ser NOMEADA — "3 de 10 falharam" não diz quais 3 refazer.
            // Eager de propósito: vem da sessão, não toca banco (a rule de `Inertia::defer`
            // mira prop cara; esta custa uma leitura de array).
            'loteResultado' => session('fiscal.dfe.lote'),
        ]);
    }

    /**
     * Manifestações já feitas: notas recebidas com `manifestado_em` gravado pelo
     * ManifestacaoService (ciência, confirmação, desconhecimento, não realizada).
     * A observação é a justificativa do último evento autorizado daquela nota.
     * O evento não guarda quem manifestou, então a tela não mostra "quem".
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildHistoricoPayload(): array
    {
        $notas = NfeDfeRecebido::query()
            ->whereNotNull('manifestado_em')
            ->whereIn('status_manifestacao', [
                NfeDfeRecebido::STATUS_CIENCIA,
                NfeDfeRecebido::STATUS_CONFIRMADA,
                NfeDfeRecebido::STATUS_DESCONHECIDA,
                NfeDfeRecebido::STATUS_NAO_REALIZADA,
            ])
            ->orderByDesc('manifestado_em')
            ->limit(100)
            ->get();

        $justificativas = $notas->isEmpty() ? collect() : NfeDfeEvento::query()
            ->whereIn('dfe_recebido_id', $notas->pluck('id'))
            ->where('status', 'autorizado')
            ->orderByDesc('id')
            ->get(['dfe_recebido_id', 'justificativa'])
            ->unique('dfe_recebido_id')
            ->pluck('justificativa', 'dfe_recebido_id');

        return $notas->map(fn (NfeDfeRecebido $d) => [
            'id'           => $d->id,
            'chave'        => $d->chave_44,
            'nomeEmitente' => $d->nome_emitente,
            'cnpjEmitente' => $d->cnpj_emitente,
            'when'         => $d->manifestado_em?->format('d/m/Y H:i'),
            'ack'          => $d->status_manifestacao,
            'obs'          => $justificativas[$d->id] ?? null,
            'valor'        => (float) $d->valor_total,
        ])->values()->all();
    }

    protected function computeCounts(): array
    {
        $base = NfeDfeRecebido::query();

        return [
            'total'        => (clone $base)->count(),
            'pendentes'    => (clone $base)->whereIn('status_manifestacao', [
                NfeDfeRecebido::STATUS_PENDENTE,
                NfeDfeRecebido::STATUS_CIENCIA,
            ])->count(),
            'confirmadas'  => (clone $base)->where('status_manifestacao', NfeDfeRecebido::STATUS_CONFIRMADA)->count(),
            'desconhecidas'=> (clone $base)->where('status_manifestacao', NfeDfeRecebido::STATUS_DESCONHECIDA)->count(),
            'naoRealizadas'=> (clone $base)->where('status_manifestacao', NfeDfeRecebido::STATUS_NAO_REALIZADA)->count(),
            'valorPendente'=> (float) (clone $base)
                ->whereIn('status_manifestacao', [NfeDfeRecebido::STATUS_PENDENTE, NfeDfeRecebido::STATUS_CIENCIA])
                ->sum('valor_total'),
        ];
    }

    protected function buildRowsPayload(array $filters): array
    {
        $query = NfeDfeRecebido::query()->orderByDesc('data_emissao');

        match ($filters['status']) {
            'pendentes'      => $query->whereIn('status_manifestacao', [
                NfeDfeRecebido::STATUS_PENDENTE,
                NfeDfeRecebido::STATUS_CIENCIA,
            ]),
            'confirmadas'    => $query->where('status_manifestacao', NfeDfeRecebido::STATUS_CONFIRMADA),
            'desconhecidas'  => $query->where('status_manifestacao', NfeDfeRecebido::STATUS_DESCONHECIDA),
            'nao_realizadas' => $query->where('status_manifestacao', NfeDfeRecebido::STATUS_NAO_REALIZADA),
            'todas'          => $query,
            default          => $query,
        };

        if ($filters['search'] !== '') {
            $s = preg_replace('/\D/', '', $filters['search']);
            $raw = $filters['search'];
            $query->where(function ($q) use ($s, $raw) {
                // Busca por nome não tem dígito: sem este `if`, `$s` vira '' e
                // `chave_44 like '%%'` casa todas as notas (UC-FDFE-16).
                if ($s !== '') {
                    $q->where('chave_44', 'like', "%{$s}%")
                      ->orWhere('cnpj_emitente', 'like', "%{$s}%");
                }
                $q->orWhere('nome_emitente', 'like', "%{$raw}%");
            });
        }

        $paginator = $query->paginate(50);
        $hoje = now()->startOfDay();

        return [
            'data' => $paginator->getCollection()->map(function (NfeDfeRecebido $d) use ($hoje) {
                $prazoDias = $d->prazo_confirmacao_em
                    ? (int) $hoje->diffInDays($d->prazo_confirmacao_em, false)
                    : null;

                return [
                    'id'                  => $d->id,
                    'chave'               => $d->chave_44,
                    'nsu'                 => $d->nsu,
                    'cnpjEmitente'        => $d->cnpj_emitente,
                    'nomeEmitente'        => $d->nome_emitente,
                    'valor'               => (float) $d->valor_total,
                    'numProtocolo'        => $d->num_protocolo,
                    'dataEmissaoIso'      => $d->data_emissao?->toIso8601String(),
                    'when'                => $d->data_emissao?->format('d/m H:i'),
                    'statusManifestacao'  => $d->status_manifestacao,
                    'manifestadoEmIso'    => $d->manifestado_em?->toIso8601String(),
                    'prazoDias'           => $prazoDias,
                ];
            })->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }
}
