<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\FinanceiroAuditLogger;

/**
 * Conciliação OFX — Onda 19 (2026-05-19) #49.
 *
 * Workflow:
 *  1. GET  /financeiro/conciliacao — lista linhas pendentes (?incluir_resolvidos=1 inclui resolvidas)
 *  2. POST /financeiro/conciliacao/upload — recebe arquivo OFX, parseia, persiste
 *  3. POST /financeiro/conciliacao/{lineId}/match — confirma match com Titulo
 *  4. POST /financeiro/conciliacao/{lineId}/ignorar — marca como ignorado
 *  5. POST /financeiro/conciliacao/{lineId}/reabrir — desfaz conciliação/ignore (volta a pendente)
 *
 * Parser OFX simples (regex). NÃO usa biblioteca externa (mantém deps enxutas).
 * Para CNAB / formatos complexos: Onda 22 com `OfxImporter` dedicated service.
 *
 * Tier 0: business_id global scope (BankStatementLine model usa BusinessScope).
 *
 * Fase 1 da ADR 0236 (2026-05-31): a conciliação passou a enxergar TAMBÉM o
 * extrato sincronizado via API do banco (`fin_extrato_lancamentos`, origem
 * `api`), além do upload OFX (`fin_bank_statement_lines`, origem `ofx`). A
 * leitura é normalizada pra um shape único e cada linha carrega `origem` +
 * `uid` ("ofx:123" / "api:456"); match/ignorar resolvem a tabela certa pela
 * `origem`. NÃO há migração de dado — só leitura unificada + status na linha API.
 * @see memory/decisions/0236-extrato-conciliacao-modelo-unificado.md
 *
 * Auditoria (append-only): match/ignorar/reabrir registram quem+o quê+quando via
 * FinanceiroAuditLogger (PII redacted, business_id preservado — ADR 0093 + Wave 14 D7.a).
 */
class ConciliacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        // Toggle "ver conciliados/ignorados" — quando ligado, inclui linhas
        // resolvidas pra permitir reabrir (undo). Default (não setado) mantém o
        // fluxo original: só pendente/sugerido. Vale pras 2 origens (OFX + API).
        $incluirResolvidos = $request->boolean('incluir_resolvidos');
        $statusFiltro = $incluirResolvidos
            ? ['pendente', 'sugerido', 'conciliado', 'ignorado']
            : ['pendente', 'sugerido'];

        // OFX (upload manual): pendentes/sugeridas (+ resolvidas se o toggle ligado).
        $linhas = DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)
            ->whereIn('status', $statusFiltro)
            ->whereNull('deleted_at')
            ->orderBy('data_movimento', 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($l) => $this->normalizeOfx($l));

        // API (sync banco): linhas nunca avaliadas (status NULL) + sugeridas.
        // Guard hasColumn: degrada pra OFX-only enquanto a migration Fase 1 não rodou.
        if ($this->apiConciliavel()) {
            $api = DB::table('fin_extrato_lancamentos')
                ->where('business_id', $businessId)
                ->where(function ($q) use ($incluirResolvidos) {
                    $q->whereNull('status')->orWhere('status', 'sugerido');
                    if ($incluirResolvidos) {
                        $q->orWhereIn('status', ['conciliado', 'ignorado']);
                    }
                })
                ->orderBy('data', 'desc')
                ->limit(200)
                ->get()
                ->map(fn ($l) => $this->normalizeApi($l));

            $linhas = $linhas->concat($api)->sortByDesc('data_movimento')->values();
        }

        $stats = $this->statsConsolidados($businessId);

        // ContaBancaria.nome é accessor que lê de Account.name (eager load needed)
        $contas = ContaBancaria::where('business_id', $businessId)
            ->with('account:id,name')
            ->get(['id', 'account_id'])
            ->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome]);

        return Inertia::render('Financeiro/Conciliacao/Index', [
            'linhas' => $linhas,
            'stats' => $stats,
            'contas' => $contas,
            'filters' => [
                'incluir_resolvidos' => $incluirResolvidos,
            ],
        ]);
    }

    /** Fase 1 ADR 0236: extrato API só é conciliável após a migration que adiciona `status`. */
    private function apiConciliavel(): bool
    {
        return Schema::hasTable('fin_extrato_lancamentos')
            && Schema::hasColumn('fin_extrato_lancamentos', 'status');
    }

    /** Normaliza linha OFX (`fin_bank_statement_lines`) pro shape único da tela. */
    private function normalizeOfx(object $l): array
    {
        return [
            'uid'            => 'ofx:' . $l->id,
            'origem'         => 'ofx',
            'id'             => (int) $l->id,
            'data_movimento' => $l->data_movimento,
            'descricao'      => $l->descricao,
            'valor'          => (float) $l->valor, // OFX já guarda valor com sinal.
            'tipo'           => $l->tipo,
            'status'         => $l->status,
            'titulo_id'      => $l->titulo_id !== null ? (int) $l->titulo_id : null,
            'match_score'    => $l->match_score !== null ? (float) $l->match_score : null,
            'source_file'    => $l->source_file,
        ];
    }

    /** Normaliza linha API (`fin_extrato_lancamentos`) pro mesmo shape. */
    private function normalizeApi(object $l): array
    {
        // API guarda valor positivo + tipo C/D separado → converte pra valor com sinal.
        $signed = $l->tipo === 'D' ? -abs((float) $l->valor) : abs((float) $l->valor);

        return [
            'uid'            => 'api:' . $l->id,
            'origem'         => 'api',
            'id'             => (int) $l->id,
            'data_movimento' => $l->data,
            'descricao'      => $l->descricao,
            'valor'          => $signed,
            'tipo'           => $l->tipo === 'C' ? 'credit' : 'debit',
            'status'         => $l->status ?? 'pendente', // NULL = nunca avaliada → exibe "pendente".
            'titulo_id'      => isset($l->titulo_id) ? (int) $l->titulo_id : null,
            'match_score'    => isset($l->match_score) ? (float) $l->match_score : null,
            'source_file'    => null, // API não tem arquivo; front mostra chip "Banco".
        ];
    }

    /** Stats consolidados das duas origens (API status NULL conta como pendente). */
    private function statsConsolidados(int $businessId): array
    {
        $ofxBase = fn () => DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)->whereNull('deleted_at');

        $stats = [
            'pendentes'   => (clone $ofxBase())->where('status', 'pendente')->count(),
            'sugeridos'   => (clone $ofxBase())->where('status', 'sugerido')->count(),
            'conciliados' => (clone $ofxBase())->where('status', 'conciliado')->count(),
            'ignorados'   => (clone $ofxBase())->where('status', 'ignorado')->count(),
        ];

        if ($this->apiConciliavel()) {
            $apiBase = fn () => DB::table('fin_extrato_lancamentos')->where('business_id', $businessId);
            $stats['pendentes']   += (clone $apiBase())->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'pendente'))->count();
            $stats['sugeridos']   += (clone $apiBase())->where('status', 'sugerido')->count();
            $stats['conciliados'] += (clone $apiBase())->where('status', 'conciliado')->count();
            $stats['ignorados']   += (clone $apiBase())->where('status', 'ignorado')->count();
        }

        return $stats;
    }

    /**
     * Upload arquivo OFX + parser básico.
     * Aceita multipart com campo `arquivo` (.ofx text-based).
     * Detecta transações via OFX tags <STMTTRN>...</STMTTRN>.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'arquivo' => 'required|file|max:10240', // 10 MB max
            'conta_bancaria_id' => 'nullable|integer',
        ]);

        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;
        $file = $request->file('arquivo');
        $content = (string) file_get_contents($file->getPathname());
        $sourceFile = $file->getClientOriginalName();

        // Parser OFX simples — regex pra STMTTRN blocks.
        // Formato OFX: <STMTTRN><TRNTYPE>...</TRNTYPE><DTPOSTED>20260520</DTPOSTED>
        //              <TRNAMT>-30000.00</TRNAMT><FITID>...</FITID><MEMO>...</MEMO></STMTTRN>
        $transacoes = [];
        if (preg_match_all('#<STMTTRN>(.+?)</STMTTRN>#s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                $get = fn (string $tag): ?string => (preg_match('#<' . $tag . '>([^<\r\n]+)#', $block, $m) ? trim($m[1]) : null);
                $valor = $get('TRNAMT');
                $data = $get('DTPOSTED');
                if (! $valor || ! $data) {
                    continue;
                }
                $transacoes[] = [
                    'business_id' => $businessId,
                    'conta_bancaria_id' => $request->integer('conta_bancaria_id') ?: null,
                    'fitid' => $get('FITID') ?: 'auto-' . md5($block),
                    'data_movimento' => CarbonImmutable::createFromFormat('Ymd', substr($data, 0, 8))->toDateString(),
                    'descricao' => $get('MEMO') ?: $get('NAME') ?: 'Transação importada',
                    'valor' => (float) $valor,
                    'tipo' => $this->detectarTipo($get('TRNTYPE'), (float) $valor),
                    'memo' => $get('MEMO'),
                    'status' => 'pendente',
                    'source_file' => $sourceFile,
                    'uploaded_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($transacoes)) {
            return back()->with('error', 'Nenhuma transação encontrada no arquivo OFX. Verifique formato.');
        }

        // Insert idempotente — insertOrIgnore é atômico no banco: pula linhas que
        // violam o unique (business_id, fitid). Dois uploads concorrentes do mesmo
        // arquivo (double-click / retry) NÃO causam mais QueryException/500 — antes,
        // o check-then-insert (exists() + insert()) tinha uma janela de corrida onde
        // ambos passavam no exists() e o segundo insert estourava o constraint.
        // É insertOrIgnore (e não upsert) de propósito: a semântica é PULAR
        // duplicados, nunca sobrescrever — uma linha já conciliada/sugerida não pode
        // ser revertida por um reimport do mesmo extrato.
        $fitids = array_column($transacoes, 'fitid');

        // fitids que já existiam ANTES do insert. Sem filtro de deleted_at de
        // propósito: o unique constraint não distingue soft-deletes, então o
        // comportamento bate com o exists() original (que também não filtrava).
        $jaExistentes = [];
        foreach (array_chunk(array_unique($fitids), 1000) as $chunk) {
            $jaExistentes = array_merge($jaExistentes, DB::table('fin_bank_statement_lines')
                ->where('business_id', $businessId)
                ->whereIn('fitid', $chunk)
                ->pluck('fitid')
                ->all());
        }

        // insertOrIgnore em lotes (evita estourar bind vars / max_allowed_packet
        // em arquivos OFX grandes — o loop anterior inseria 1 linha por vez).
        foreach (array_chunk($transacoes, 500) as $lote) {
            DB::table('fin_bank_statement_lines')->insertOrIgnore($lote);
        }

        // Conta só os fitids realmente novos (distintos, menos os já existentes).
        $count = count(array_diff(array_unique($fitids), $jaExistentes));

        // Sugestão automática de match (fuzzy: valor + data ±3 dias).
        $this->sugerirMatches($businessId);

        return back()->with('success', "OFX processado: {$count} novas transações importadas.");
    }

    /**
     * Confirma match com Titulo.
     * POST /financeiro/conciliacao/{lineId}/match com {titulo_id, origem?}.
     *
     * Fase 1 ADR 0236: `origem` (ofx|api) decide a tabela. Default `ofx` —
     * retrocompat com chamadas antigas que só mandavam {titulo_id}.
     */
    public function match(Request $request, int $lineId): RedirectResponse
    {
        $request->validate([
            'titulo_id' => 'required|integer',
            'origem'    => 'nullable|in:ofx,api',
        ]);
        $businessId = (int) session('user.business_id');
        $tituloId = $request->integer('titulo_id');
        $userId = $request->user()?->id;

        // Fase 1 ADR 0236: conciliacaoTable() resolve a tabela certa pela origem
        // (OFX/API). $afetadas alimenta a auditoria (quantas linhas o UPDATE tocou).
        $afetadas = $this->conciliacaoTable($request->input('origem', 'ofx'))
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status'        => 'conciliado',
                'titulo_id'     => $tituloId,
                'conciliado_by' => $userId,
                'conciliado_at' => now(),
                'updated_at'    => now(),
            ]);

        // Auditoria append-only (ADR 0093 + Wave 14 D7.a) — quem/o quê/quando.
        // business_id/titulo_id são chaves operacionais (não redacionadas).
        app(FinanceiroAuditLogger::class)->info(
            'conciliacao.match: linha conciliada com título',
            [
                'business_id' => $businessId,
                'line_id' => $lineId,
                'titulo_id' => $tituloId,
                'status' => 'conciliado',
                'user_id' => $userId,
                'afetadas' => $afetadas,
            ]
        );

        return back()->with('success', 'Conciliação confirmada.');
    }

    public function ignorar(Request $request, int $lineId): RedirectResponse
    {
        $request->validate(['origem' => 'nullable|in:ofx,api']);
        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;

        $afetadas = $this->conciliacaoTable($request->input('origem', 'ofx'))
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status'     => 'ignorado',
                'updated_at' => now(),
            ]);

        // Auditoria append-only (ADR 0093 + Wave 14 D7.a) — quem/o quê/quando.
        app(FinanceiroAuditLogger::class)->info(
            'conciliacao.ignorar: linha marcada como ignorada',
            [
                'business_id' => $businessId,
                'line_id' => $lineId,
                'status' => 'ignorado',
                'user_id' => $userId,
                'afetadas' => $afetadas,
            ]
        );

        return back()->with('success', 'Linha marcada como ignorada.');
    }

    /**
     * Query builder da tabela certa por origem (Tier 0: o filtro business_id
     * é aplicado por quem chama). `api` exige a migration Fase 1; cai pra OFX
     * se ainda não rodou (degradação segura).
     */
    private function conciliacaoTable(string $origem): \Illuminate\Database\Query\Builder
    {
        if ($origem === 'api' && $this->apiConciliavel()) {
            return DB::table('fin_extrato_lancamentos');
        }

        return DB::table('fin_bank_statement_lines');
    }

    /**
     * Reabre (undo) uma conciliação/ignore: volta a linha pra "pendente",
     * limpa titulo_id + match_score, e registra a reabertura na auditoria.
     * POST /financeiro/conciliacao/{lineId}/reabrir com {origem?}.
     *
     * Tier 0: scoped por business_id. Linha inexistente neste tenant → 404
     * (não-silencioso). Fase 1 ADR 0236: `origem` (ofx|api) decide a tabela.
     * Na origem API o estado "pendente" é representado por status NULL
     * (nunca avaliada), pra reentrar no fluxo de sugestão.
     */
    public function reabrir(Request $request, int $lineId): RedirectResponse
    {
        $request->validate(['origem' => 'nullable|in:ofx,api']);
        $origem = (string) $request->input('origem', 'ofx');
        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;

        // Carrega estado anterior pra auditoria + valida existência no tenant.
        $linha = $this->conciliacaoTable($origem)
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->first();

        if (! $linha) {
            // Não-silencioso: linha não existe pra este business (Tier 0).
            abort(404);
        }

        $statusAnterior = $linha->status;

        // Campos comuns às 2 origens; conciliado_by/at só existem no OFX.
        $update = [
            'status'      => $origem === 'api' ? null : 'pendente',
            'titulo_id'   => null,
            'match_score' => null,
            'updated_at'  => now(),
        ];
        if ($origem === 'ofx') {
            $update['conciliado_by'] = null;
            $update['conciliado_at'] = null;
        }

        $this->conciliacaoTable($origem)
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update($update);

        // Auditoria append-only (ADR 0093 + Wave 14 D7.a) — status de onde veio.
        app(FinanceiroAuditLogger::class)->info(
            'conciliacao.reabrir: linha reaberta (volta a pendente)',
            [
                'business_id' => $businessId,
                'line_id' => $lineId,
                'origem' => $origem,
                'status' => 'pendente',
                'status_anterior' => $statusAnterior,
                'titulo_id' => $linha->titulo_id ?? null,
                'user_id' => $userId,
            ]
        );

        return back()->with('success', 'Linha reaberta — voltou para pendente.');
    }

    /** OFX TRNTYPE → enum interno */
    private function detectarTipo(?string $trnType, float $valor): string
    {
        if ($trnType === 'CREDIT') return 'credit';
        if ($trnType === 'DEBIT') return 'debit';
        if ($trnType === 'FEE') return 'fee';
        if ($trnType === 'XFER') return 'transfer';
        return $valor >= 0 ? 'credit' : 'debit';
    }

    /**
     * Fuzzy match: pra cada linha pendente, busca Titulo aberto com valor ≈ e data ±3d.
     * Score = (valor_match * 0.7) + (data_proximity * 0.3).
     *
     * Fase 1 ADR 0236: roda nas DUAS origens — OFX (`fin_bank_statement_lines`,
     * status='pendente') e API (`fin_extrato_lancamentos`, status NULL = nunca
     * avaliada). Mesmo algoritmo MVP; grava `status=sugerido` na tabela de origem.
     */
    private function sugerirMatches(int $businessId): void
    {
        // OFX — data em `data_movimento`, valor já com sinal.
        $ofx = DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)
            ->where('status', 'pendente')
            ->whereNull('deleted_at')
            ->get();
        foreach ($ofx as $linha) {
            $this->sugerirParaLinha($businessId, 'fin_bank_statement_lines', $linha->id, $linha->valor, $linha->data_movimento);
        }

        // API — data em `data`, valor positivo (abs já é aplicado no matcher).
        if ($this->apiConciliavel()) {
            $api = DB::table('fin_extrato_lancamentos')
                ->where('business_id', $businessId)
                ->whereNull('status')
                ->get();
            foreach ($api as $linha) {
                $this->sugerirParaLinha($businessId, 'fin_extrato_lancamentos', $linha->id, $linha->valor, $linha->data);
            }
        }
    }

    /** Casa UMA linha de extrato (qualquer origem) com um Titulo aberto e marca sugerido. */
    private function sugerirParaLinha(int $businessId, string $table, int $id, $valor, string $data): void
    {
        $valorAbs = abs((float) $valor);
        $candidato = Titulo::where('business_id', $businessId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->whereRaw('ABS(valor_total - ?) < 0.01', [$valorAbs])
            ->whereBetween('vencimento', [
                CarbonImmutable::parse($data)->subDays(3)->toDateString(),
                CarbonImmutable::parse($data)->addDays(3)->toDateString(),
            ])
            ->orderByRaw('ABS(DATEDIFF(vencimento, ?))', [$data])
            ->first();

        if ($candidato) {
            DB::table($table)
                ->where('id', $id)
                ->where('business_id', $businessId) // Tier 0 reforçado no UPDATE.
                ->update([
                    'status'      => 'sugerido',
                    'titulo_id'   => $candidato->id,
                    'match_score' => $this->calcularMatchScore(
                        $valorAbs,
                        (float) $candidato->getAttribute('valor_total'),
                        $data,
                        CarbonImmutable::parse((string) $candidato->getAttribute('vencimento'))->toDateString(),
                    ),
                    'updated_at'  => now(),
                ]);
        }
    }

    /**
     * Score real do match — substitui o constante 0.85 fake (bug B1, ADR 0236).
     * Peso 0.7 valor + 0.3 proximidade-de-data; arredondado a 2 casas, clamp [0,1].
     *  - valor_score: 1.0 quando os valores batem; decai com a diferença relativa
     *    ao valor da linha (a janela de candidatos já garante diff ≤ R$0,01 ≈ 1.0).
     *  - data_score: 1.0 no mesmo dia; decai linear até ~0 na borda da janela ±3 dias.
     */
    private function calcularMatchScore(
        float $valorLinha,
        float $valorTitulo,
        string $dataLinha,
        string $vencimentoTitulo,
    ): float {
        // valor_score — decaimento proporcional à diferença relativa.
        $escalaValor = max(abs($valorLinha), 0.01);
        $valorScore = 1.0 - (abs($valorLinha - abs($valorTitulo)) / $escalaValor);
        $valorScore = max(0.0, min(1.0, $valorScore));

        // data_score — decaimento linear na janela ±3 dias.
        $diasDiff = abs(
            CarbonImmutable::parse($dataLinha)
                ->diffInDays(CarbonImmutable::parse($vencimentoTitulo))
        );
        $dataScore = 1.0 - ($diasDiff / 4.0);
        $dataScore = max(0.0, min(1.0, $dataScore));

        $score = (0.7 * $valorScore) + (0.3 * $dataScore);

        return round(max(0.0, min(1.0, $score)), 2);
    }
}
