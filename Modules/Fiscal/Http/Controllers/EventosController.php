<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeEvento;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Eventos fiscais (sub-página 5 do design KB-9.75).
 *
 * Timeline append-only de eventos SEFAZ aplicados a NfeEmissao:
 *  - 110110 CC-e (Carta de Correção Eletrônica)
 *  - 110111 Cancelamento (cStat 135 = homologado)
 *  - 110140 EPEC (Contingência)
 *  - 210200/210210/210220/210240 Manifestação destinatário
 *
 * Sem mutação no PR — eventos são append-only por natureza (LGPD Art. 37
 * + CONFAZ SINIEF 07/2005 Art. 14). Mutações em sub-página NF-e (botão
 * "Cancelar" / "CC-e" em PR de ações).
 *
 * FRONTEIRA DECLARADA — inutilização de faixa NÃO é evento desta tela, e por
 * isso `$inutTipos` está vazio em `computeCounts()`. O protótipo Cowork oferece
 * um chip "Inutilização (102)" no lugar do EPEC; ele está errado para esta tela.
 * A prova estrutural (FK `emissao_id` NOT NULL · tabela `nfe_inutilizacoes`
 * própria · 102 é cStat, não tpEvento) está na tabela de
 * `Eventos.casos.md` §"Divergência protótipo × produção" — dono do tema, não
 * repetida aqui. Já era Non-Goal no charter e fronteira do UC-FEVT-03.
 */
class EventosController extends Controller
{
    /** Mapa tipo (tpEvento NFe) → label PT-BR + classe CSS. */
    public const TIPOS = [
        '110110' => ['kind' => 'cce',      'label' => 'Carta de correção'],
        '110111' => ['kind' => 'cancel',   'label' => 'Cancelamento'],
        '110140' => ['kind' => 'epec',     'label' => 'EPEC (contingência)'],
        '210200' => ['kind' => 'manifest', 'label' => 'Manifesto · Confirmação'],
        '210210' => ['kind' => 'manifest', 'label' => 'Manifesto · Ciência'],
        '210220' => ['kind' => 'manifest', 'label' => 'Manifesto · Desconhecimento'],
        '210240' => ['kind' => 'manifest', 'label' => 'Manifesto · Não realizada'],
    ];

    /** Janelas que a UI oferece. O export clampa nelas — ver `parseDias()`. */
    public const DIAS_PERMITIDOS = [7, 30, 90];

    /**
     * Teto de linhas do CSV. A janela já limita a 90 dias, mas manifestação de
     * destinatário cresce com NF-e RECEBIDA, que não tem teto conhecido — sem
     * cap, um tenant movimentado transformaria o download numa varredura de
     * tabela. Atingido o teto, o arquivo sai truncado com uma linha avisando.
     */
    public const EXPORT_MAX_LINHAS = 10000;

    /** Tamanho do lote do streaming — 10k/500 = 20 queries no pior caso. */
    protected const EXPORT_CHUNK = 500;

    public function index(Request $request): Response
    {
        $this->autorizar();

        $filters = [
            'kind' => (string) $request->input('kind', 'todos'),
            'dias' => (int) $request->input('dias', 30),
        ];

        return Inertia::render('Fiscal/Eventos', [
            'filters' => $filters,
            'tipos'   => self::TIPOS,
            'counts'  => $this->computeCounts($filters),
            'rows'    => Inertia::defer(fn () => $this->buildRowsPayload($filters)),
        ]);
    }

    /**
     * GET /fiscal/eventos/export — CSV da timeline, respeitando os filtros ATIVOS.
     *
     * Exporta o CONJUNTO FILTRADO (tipo + período), não a página de 50 que a tela
     * mostra: a contadora que filtrou 90 dias espera levar os 90 dias. Não é a
     * tabela inteira — a janela é clampada em `DIAS_PERMITIDOS` e o volume em
     * `EXPORT_MAX_LINHAS`.
     *
     * Multi-tenant: `NfeEvento` usa `HasBusinessScope` (ADR 0093), então o filtro
     * por business é aplicado pelo global scope — nunca por `where` manual aqui.
     *
     * Formato: BOM UTF-8 + separador `;` — é o que o Excel pt-BR abre sem wizard.
     * Mesmo padrão de `Modules/Financeiro/.../UnificadoController` (dono do tema).
     */
    public function exportarCsv(Request $request): StreamedResponse
    {
        $this->autorizar();

        $filters = [
            'kind' => (string) $request->input('kind', 'todos'),
            'dias' => $this->parseDias($request->input('dias')),
        ];

        $nome = 'eventos-fiscais-' . $filters['dias'] . 'd-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM — Excel pt-BR abre UTF-8 correto

            fputcsv($out, [
                'Quando', 'Tipo', 'Sequência', 'Documento', 'Justificativa', 'Autor', 'cstat',
            ], ';', '"', '\\');

            $escritas = 0;
            $truncou  = false;

            $this->eventosQuery($filters)
                ->with(['emissao:id,numero,modelo'])
                ->chunk(self::EXPORT_CHUNK, function ($lote) use ($out, &$escritas, &$truncou) {
                    foreach ($lote as $evento) {
                        if ($escritas >= self::EXPORT_MAX_LINHAS) {
                            $truncou = true;

                            return false; // interrompe o streaming
                        }
                        fputcsv($out, $this->mapLinhaCsv($evento), ';', '"', '\\');
                        $escritas++;
                    }

                    return true;
                });

            if ($truncou) {
                fputcsv($out, [
                    'Exportação truncada em ' . self::EXPORT_MAX_LINHAS
                        . ' linhas — reduza o período ou filtre por tipo.',
                    '', '', '', '', '', '',
                ], ';', '"', '\\');
            }

            fclose($out);
        }, $nome, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Gate de acesso — mesmo contrato de `index` e do export (R-FISCAL-003). */
    protected function autorizar(): void
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            abort(403, 'Sem permissão fiscal.access');
        }
    }

    /**
     * Clampa a janela nas opções que a UI oferece.
     *
     * `index()` faz `max(1, dias)` sem teto, e ali o `paginate(50)` segura o
     * estrago. No export não há paginação, então `?dias=99999` viraria varredura
     * de tabela inteira. Fora da lista → 30 (o default da tela).
     */
    protected function parseDias(mixed $bruto): int
    {
        $dias = (int) $bruto;

        return in_array($dias, self::DIAS_PERMITIDOS, true) ? $dias : 30;
    }

    /**
     * Query base da timeline — cutoff + filtro por kind.
     *
     * Dono único do mapeamento `kind → tipos`: `buildRowsPayload` e o export
     * consomem daqui. Sem isso, o CSV e a tela poderiam divergir no filtro sem
     * ninguém perceber (é o defeito de manter N cópias da mesma regra).
     */
    protected function eventosQuery(array $filters): Builder
    {
        $cutoff = now()->subDays(max(1, $filters['dias']));

        $query = NfeEvento::query()
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->orderByDesc('id'); // desempate determinístico — `chunk` exige ordem total

        if (($filters['kind'] ?? 'todos') !== 'todos') {
            $tiposPraKind = collect(self::TIPOS)
                ->filter(fn ($meta) => $meta['kind'] === $filters['kind'])
                ->keys()
                ->all();
            if (! empty($tiposPraKind)) {
                $query->whereIn('tipo', $tiposPraKind);
            }
        }

        return $query;
    }

    protected function computeCounts(array $filters): array
    {
        $cutoff = now()->subDays(max(1, $filters['dias']));

        $base = NfeEvento::query()->where('created_at', '>=', $cutoff);

        $cceTipos      = ['110110'];
        $cancelTipos   = ['110111'];
        $inutTipos     = []; // inutilização não vive em NfeEvento — vive em NfeInutilizacao (sub-página separada)
        $epecTipos     = ['110140'];
        $manifestTipos = ['210200', '210210', '210220', '210240'];

        return [
            'total'     => (clone $base)->count(),
            'cce'       => (clone $base)->whereIn('tipo', $cceTipos)->count(),
            'cancel'    => (clone $base)->whereIn('tipo', $cancelTipos)->count(),
            'epec'      => (clone $base)->whereIn('tipo', $epecTipos)->count(),
            'manifest'  => (clone $base)->whereIn('tipo', $manifestTipos)->count(),
            'autorizados' => (clone $base)->where('status', 'autorizado')->count(),
        ];
    }

    protected function buildRowsPayload(array $filters): array
    {
        $paginator = $this->eventosQuery($filters)
            ->with(['emissao:id,numero,modelo,chave_44'])
            ->paginate(50);

        return [
            'data' => $paginator->getCollection()->map(fn (NfeEvento $e) => $this->mapRow($e))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ];
    }

    /**
     * Uma linha do CSV — as 7 colunas do alvo (`fiscal-subpages.jsx:37-40`).
     *
     * Duas saem `—` por AUSÊNCIA DE FONTE, não por esquecimento:
     *  - **Sequência**: não há coluna. O `n_seq_evento` só existe dentro de
     *    `payload_json`, e só a CC-e o grava (`NfeCartaCorrecaoService`). Lemos
     *    essa chave única — escalar, não PII — o que não fere o anti-hook do
     *    charter, que proíbe expor o `payload_json` COMPLETO. Senão, `—`.
     *  - **Autor**: não existe `user_id` em `nfe_eventos` e nenhum produtor grava
     *    causer. Preencher via `activity_log` mentiria a autoria numa trilha de
     *    auditoria — evento de job/webhook SEFAZ não tem usuário. Sempre `—`.
     *
     * Justificativa truncada em 200 chars, igual à tela — o anti-hook do charter
     * vale para o CSV (o motivo da SEFAZ pode conter PII do XML).
     */
    protected function mapLinhaCsv(NfeEvento $e): array
    {
        $tipoMeta = self::TIPOS[$e->tipo] ?? ['kind' => 'manifest', 'label' => "Tipo {$e->tipo}"];

        $sequencia = data_get($e->payload_json, 'n_seq_evento');

        $documento = $e->emissao
            ? ((int) $e->emissao->modelo === 65 ? 'NFC-e ' : 'NF-e ') . $e->emissao->numero
            : '—';

        return [
            $e->created_at?->format('d/m/Y H:i') ?? '—',
            $tipoMeta['label'],
            $sequencia !== null ? (string) $sequencia : '—',
            $documento,
            mb_substr((string) ($e->justificativa ?? ''), 0, 200),
            '—',
            (string) ($e->cstat_evento ?? '—'),
        ];
    }

    protected function mapRow(NfeEvento $e): array
    {
        $tipoMeta = self::TIPOS[$e->tipo] ?? ['kind' => 'manifest', 'label' => "Tipo {$e->tipo}"];

        return [
            'id'             => $e->id,
            'tipo'           => $e->tipo,
            'kind'           => $tipoMeta['kind'],
            'label'          => $tipoMeta['label'],
            'status'         => $e->status,
            'cstatEvento'    => (int) ($e->cstat_evento ?? 0),
            'justificativa'  => mb_substr((string) ($e->justificativa ?? ''), 0, 200),
            'createdAtIso'   => $e->created_at?->toIso8601String(),
            'when'           => $e->created_at?->format('d/m H:i'),
            'emissao'        => $e->emissao ? [
                'id'     => $e->emissao->id,
                'numero' => $e->emissao->numero,
                'modelo' => (int) $e->emissao->modelo,
                'chave'  => $e->emissao->chave_44,
            ] : null,
        ];
    }
}
