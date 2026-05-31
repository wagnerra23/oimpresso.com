<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // fluxo original: só pendente/sugerido.
        $incluirResolvidos = $request->boolean('incluir_resolvidos');
        $statusFiltro = $incluirResolvidos
            ? ['pendente', 'sugerido', 'conciliado', 'ignorado']
            : ['pendente', 'sugerido'];

        // Linhas ordenadas por data desc.
        $linhas = DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)
            ->whereIn('status', $statusFiltro)
            ->whereNull('deleted_at')
            ->orderBy('data_movimento', 'desc')
            ->limit(200)
            ->get();

        // Stats.
        $stats = [
            'pendentes'   => DB::table('fin_bank_statement_lines')->where('business_id', $businessId)->where('status', 'pendente')->whereNull('deleted_at')->count(),
            'sugeridos'   => DB::table('fin_bank_statement_lines')->where('business_id', $businessId)->where('status', 'sugerido')->whereNull('deleted_at')->count(),
            'conciliados' => DB::table('fin_bank_statement_lines')->where('business_id', $businessId)->where('status', 'conciliado')->whereNull('deleted_at')->count(),
            'ignorados'   => DB::table('fin_bank_statement_lines')->where('business_id', $businessId)->where('status', 'ignorado')->whereNull('deleted_at')->count(),
        ];

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

        // Insert ignorando duplicados (unique fitid + business_id).
        $count = 0;
        foreach ($transacoes as $t) {
            $exists = DB::table('fin_bank_statement_lines')
                ->where('business_id', $businessId)
                ->where('fitid', $t['fitid'])
                ->exists();
            if (! $exists) {
                DB::table('fin_bank_statement_lines')->insert($t);
                $count++;
            }
        }

        // Sugestão automática de match (fuzzy: valor + data ±3 dias).
        $this->sugerirMatches($businessId);

        return back()->with('success', "OFX processado: {$count} novas transações importadas.");
    }

    /**
     * Confirma match com Titulo.
     * POST /financeiro/conciliacao/{lineId}/match com {titulo_id}.
     */
    public function match(Request $request, int $lineId): RedirectResponse
    {
        $request->validate(['titulo_id' => 'required|integer']);
        $businessId = (int) session('user.business_id');
        $tituloId = $request->integer('titulo_id');
        $userId = $request->user()?->id;

        $afetadas = DB::table('fin_bank_statement_lines')
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status' => 'conciliado',
                'titulo_id' => $tituloId,
                'conciliado_by' => $userId,
                'conciliado_at' => now(),
                'updated_at' => now(),
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
        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;

        $afetadas = DB::table('fin_bank_statement_lines')
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status' => 'ignorado',
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
     * Reabre (undo) uma conciliação/ignore: volta a linha pra `pendente`,
     * limpa titulo_id + match_score, e registra a reabertura na auditoria.
     * POST /financeiro/conciliacao/{lineId}/reabrir.
     *
     * Tier 0: scoped por business_id. Linha inexistente neste tenant → 404
     * (não-silencioso). Idempotente: reabrir linha já pendente é inócuo (o
     * UPDATE só zera campos já nulos), mas SEMPRE muta de fato (sem no-op).
     */
    public function reabrir(Request $request, int $lineId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = $request->user()?->id;

        // Carrega estado anterior pra auditoria + valida existência no tenant.
        $linha = DB::table('fin_bank_statement_lines')
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->first();

        if (! $linha) {
            // Não-silencioso: linha não existe pra este business (Tier 0).
            abort(404);
        }

        $statusAnterior = $linha->status;

        DB::table('fin_bank_statement_lines')
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status' => 'pendente',
                'titulo_id' => null,
                'match_score' => null,
                'conciliado_by' => null,
                'conciliado_at' => null,
                'updated_at' => now(),
            ]);

        // Auditoria append-only (ADR 0093 + Wave 14 D7.a) — registra a reabertura
        // com o status de onde veio (conciliado/ignorado/sugerido).
        app(FinanceiroAuditLogger::class)->info(
            'conciliacao.reabrir: linha reaberta (volta a pendente)',
            [
                'business_id' => $businessId,
                'line_id' => $lineId,
                'status' => 'pendente',
                'status_anterior' => $statusAnterior,
                'titulo_id' => $linha->titulo_id,
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
     */
    private function sugerirMatches(int $businessId): void
    {
        $pendentes = DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)
            ->where('status', 'pendente')
            ->whereNull('deleted_at')
            ->get();

        foreach ($pendentes as $linha) {
            $valorAbs = abs((float) $linha->valor);
            $candidato = Titulo::where('business_id', $businessId)
                ->whereIn('status', ['aberto', 'parcial'])
                ->whereRaw('ABS(valor_total - ?) < 0.01', [$valorAbs])
                ->whereBetween('vencimento', [
                    CarbonImmutable::parse($linha->data_movimento)->subDays(3)->toDateString(),
                    CarbonImmutable::parse($linha->data_movimento)->addDays(3)->toDateString(),
                ])
                ->orderByRaw('ABS(DATEDIFF(vencimento, ?))', [$linha->data_movimento])
                ->first();

            if ($candidato) {
                DB::table('fin_bank_statement_lines')
                    ->where('id', $linha->id)
                    ->update([
                        'status' => 'sugerido',
                        'titulo_id' => $candidato->id,
                        'match_score' => 0.85,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
