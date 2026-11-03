<?php

namespace Modules\Financeiro\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Services\ExportacaoContabilService;

/**
 * Exportação contábil (US-FIN-077).
 *
 * - GET  /financeiro/exportacao-contabil            tela: competência, sistema e de-para
 * - PUT  /financeiro/exportacao-contabil/de-para    grava os códigos do contador (vazio apaga)
 * - GET  /financeiro/exportacao-contabil/{layout}?competencia=AAAA-MM   arquivo; com pendência de
 *   de-para responde 422 com a lista: arquivo incompleto não sai.
 *
 * Tier 0: toda origem do de-para é conferida contra a empresa da sessão antes de gravar.
 */
class ExportacaoContabilController extends Controller
{
    public function __construct(private ExportacaoContabilService $service)
    {
        $this->middleware('can:financeiro.relatorios.view');
    }

    /** Origens fixas do de-para: uma linha por empresa, origem_id = 0. */
    private const ORIGENS_FIXAS = ['juros' => 'Juros e multa', 'desconto' => 'Descontos'];

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        return Inertia::render('Financeiro/Contabilidade', [
            // Padrão: o mês anterior, que é o que o contador fecha.
            'competencia' => (string) $request->query('competencia', date('Y-m', strtotime('first day of last month'))),
            'layouts' => ExportacaoContabilService::LAYOUTS,
            'depara' => Inertia::defer(fn () => $this->deParaDaEmpresa($businessId)),
        ]);
    }

    public function salvarDePara(Request $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $dados = $request->validate([
            'itens' => ['required', 'array', 'max:2000'],
            'itens.*.origem_tipo' => ['required', 'in:conta_bancaria,plano_conta,juros,desconto'],
            'itens.*.origem_id' => ['required', 'integer', 'min:0'],
            'itens.*.codigo' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-]+$/'],
        ]);

        // Tier 0: a origem tem de ser da empresa da sessão; fixas usam origem_id 0.
        $permitidos = [
            'conta_bancaria' => DB::table('fin_contas_bancarias')->where('business_id', $businessId)->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'plano_conta' => DB::table('fin_planos_conta')->where('business_id', $businessId)->whereNull('deleted_at')->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'juros' => [0],
            'desconto' => [0],
        ];
        foreach ($dados['itens'] as $i => $item) {
            if (! in_array((int) $item['origem_id'], $permitidos[$item['origem_tipo']], true)) {
                throw ValidationException::withMessages(["itens.$i.origem_id" => 'Origem não encontrada nesta empresa.']);
            }
        }

        DB::transaction(function () use ($dados, $businessId) {
            foreach ($dados['itens'] as $item) {
                $chave = ['business_id' => $businessId, 'origem_tipo' => $item['origem_tipo'], 'origem_id' => (int) $item['origem_id']];
                $codigo = trim((string) ($item['codigo'] ?? ''));
                if ($codigo === '') {
                    DB::table('fin_contabil_contas')->where($chave)->delete();
                } else {
                    DB::table('fin_contabil_contas')->updateOrInsert($chave, ['codigo' => $codigo, 'updated_at' => now(), 'created_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Códigos do contador salvos.');
    }

    /** @return list<array{origem_tipo: string, origem_id: int, grupo: string, nome: string, codigo: string}> */
    private function deParaDaEmpresa(int $businessId): array
    {
        $codigos = DB::table('fin_contabil_contas')->where('business_id', $businessId)
            ->get(['origem_tipo', 'origem_id', 'codigo'])
            ->mapWithKeys(fn ($r) => [$r->origem_tipo.':'.$r->origem_id => (string) $r->codigo])->all();
        $linha = fn (string $tipo, int $id, string $grupo, string $nome) => [
            'origem_tipo' => $tipo, 'origem_id' => $id, 'grupo' => $grupo, 'nome' => $nome,
            'codigo' => $codigos[$tipo.':'.$id] ?? '',
        ];

        $bancos = DB::table('fin_contas_bancarias as cb')
            ->leftJoin('accounts as a', function ($j) use ($businessId) {
                $j->on('a.id', '=', 'cb.account_id')->where('a.business_id', '=', $businessId);
            })
            ->where('cb.business_id', $businessId)
            ->orderBy('a.name')
            ->get(['cb.id', 'a.name', 'cb.banco_codigo'])
            ->map(fn ($r) => $linha('conta_bancaria', (int) $r->id, 'Conta bancária', (string) ($r->name ?: 'Conta '.$r->banco_codigo)));

        $planos = DB::table('fin_planos_conta')
            ->where('business_id', $businessId)->whereNull('deleted_at')->where('aceita_lancamento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->map(fn ($r) => $linha('plano_conta', (int) $r->id, 'Plano de contas', $r->codigo.' '.$r->nome));

        $fixas = collect(self::ORIGENS_FIXAS)->map(fn ($nome, $tipo) => $linha($tipo, 0, 'Acréscimos', $nome))->values();

        return $bancos->concat($planos)->concat($fixas)->values()->all();
    }

    public function download(Request $request, string $layout)
    {
        abort_unless(in_array($layout, ExportacaoContabilService::LAYOUTS, true), 404);
        $competencia = (string) $request->query('competencia', '');
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competencia) === 1, 422, 'Competência inválida (AAAA-MM).');

        $businessId = (int) session('user.business_id');
        $r = $this->service->lancamentos($businessId, $competencia);
        if ($r['pendencias'] !== []) {
            return response()->json(['message' => 'De-para contábil incompleto.', 'pendencias' => $r['pendencias']], 422);
        }

        $cnpj = (string) DB::table('business')->where('id', $businessId)->value('tax_number_1');
        $conteudo = $this->service->gerar($layout, $cnpj, $r['lancamentos']);
        $nome = 'lancamentos_'.$layout.'_'.str_replace('-', '', $competencia).'.txt';

        return response($conteudo, 200, [
            'Content-Type' => 'text/plain; charset='.($layout === 'dominio' ? 'ISO-8859-1' : 'UTF-8'),
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }
}
