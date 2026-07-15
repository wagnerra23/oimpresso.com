<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
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
            // Onda 2 G — tab Histórico de manifestações já processadas.
            // TODO[CL]: substituir por query real de NfeDfeRecebido WHERE
            // status_manifestacao IN ('confirmada','desconhecida','nao_realizada')
            // ordenado por manifestado_em DESC.
            'historicoMock' => $this->mockHistorico(),
        ]);
    }

    /**
     * Mock pra tab Histórico do DF-e (Onda 2). PII-safe.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mockHistorico(): array
    {
        $now = now();

        return [
            ['id' => 901, 'chave' => '[REDACTED-CHAVE-NFE-44]',
             'nomeEmitente' => 'TechSupply Ltda', 'cnpjEmitente' => '[REDACTED-CNPJ]',
             'when' => $now->copy()->subDays(2)->format('d/m H:i'),
             'ack' => 'confirmada', 'actor' => 'Wagner',
             'obs' => 'Material recebido OK · NFe 12345', 'valor' => 8420.00],
            ['id' => 902, 'chave' => '[REDACTED-CHAVE-NFE-44]',
             'nomeEmitente' => 'Gráfica Parceira ME', 'cnpjEmitente' => '[REDACTED-CNPJ]',
             'when' => $now->copy()->subDays(4)->format('d/m H:i'),
             'ack' => 'confirmada', 'actor' => 'Eliana',
             'obs' => 'Insumos pra produção', 'valor' => 3240.00],
            ['id' => 903, 'chave' => '[REDACTED-CHAVE-NFE-44]',
             'nomeEmitente' => 'Logística Express', 'cnpjEmitente' => '[REDACTED-CNPJ]',
             'when' => $now->copy()->subDays(7)->format('d/m H:i'),
             'ack' => 'ciencia', 'actor' => 'Auto (job diário)',
             'obs' => null, 'valor' => 142.00],
            ['id' => 904, 'chave' => '[REDACTED-CHAVE-NFE-44]',
             'nomeEmitente' => 'Empresa Desconhecida XYZ', 'cnpjEmitente' => '[REDACTED-CNPJ]',
             'when' => $now->copy()->subDays(10)->format('d/m H:i'),
             'ack' => 'desconhecida', 'actor' => 'Wagner',
             'obs' => 'Operação não foi solicitada por nós — fornecedor errou destinatário', 'valor' => 18900.00],
            ['id' => 905, 'chave' => '[REDACTED-CHAVE-NFE-44]',
             'nomeEmitente' => 'Material Especial', 'cnpjEmitente' => '[REDACTED-CNPJ]',
             'when' => $now->copy()->subDays(14)->format('d/m H:i'),
             'ack' => 'nao_realizada', 'actor' => 'Eliana',
             'obs' => 'Pedido cancelado em comum acordo · mercadoria nunca chegou', 'valor' => 5200.00],
        ];
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
                $q->where('chave_44', 'like', "%{$s}%")
                  ->orWhere('cnpj_emitente', 'like', "%{$s}%")
                  ->orWhere('nome_emitente', 'like', "%{$raw}%");
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
