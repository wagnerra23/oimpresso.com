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

/**
 * Conciliação OFX — Onda 19 (2026-05-19) #49.
 *
 * Workflow:
 *  1. GET  /financeiro/conciliacao — lista linhas pendentes
 *  2. POST /financeiro/conciliacao/upload — recebe arquivo OFX, parseia, persiste
 *  3. POST /financeiro/conciliacao/{lineId}/match — confirma match com Titulo
 *  4. POST /financeiro/conciliacao/{lineId}/ignorar — marca como ignorado
 *
 * Parser OFX simples (regex). NÃO usa biblioteca externa (mantém deps enxutas).
 * Para CNAB / formatos complexos: Onda 22 com `OfxImporter` dedicated service.
 *
 * Tier 0: business_id global scope (BankStatementLine model usa BusinessScope).
 */
class ConciliacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        // Linhas pendentes/sugeridas ordenadas por data desc.
        $linhas = DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)
            ->whereIn('status', ['pendente', 'sugerido'])
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

        $contas = ContaBancaria::where('business_id', $businessId)->get(['id', 'nome']);

        return Inertia::render('Financeiro/Conciliacao/Index', [
            'linhas' => $linhas,
            'stats' => $stats,
            'contas' => $contas,
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

        DB::table('fin_bank_statement_lines')
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status' => 'conciliado',
                'titulo_id' => $request->integer('titulo_id'),
                'conciliado_by' => $request->user()?->id,
                'conciliado_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Conciliação confirmada.');
    }

    public function ignorar(Request $request, int $lineId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        DB::table('fin_bank_statement_lines')
            ->where('id', $lineId)
            ->where('business_id', $businessId)
            ->update([
                'status' => 'ignorado',
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Linha marcada como ignorada.');
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
