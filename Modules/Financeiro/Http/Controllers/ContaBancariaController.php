<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Account;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Requests\UpsertContaBancariaRequest;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Strategies\CnabDirectStrategy;

/**
 * Tela /financeiro/contas-bancarias.
 *
 * Lista todas accounts do business + complemento (fin_contas_bancarias) se
 * tiver. Permite configurar dados de boleto via Sheet.
 *
 * Pattern: ADR 0029 (Inertia + React + UPos).
 * Decisao schema: ADR TECH-0003 (complemento 1-1 com accounts core).
 */
class ContaBancariaController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('business.id');

        // accounts.account_type foi REMOVIDA pela migration core
        // 2019_10_18_155633_create_account_types_table.php (DROP COLUMN). O FK
        // novo é accounts.account_type_id → account_types.id. Selecionamos o id
        // e deixamos pro frontend só exibir.
        $accounts = Account::where('accounts.business_id', $businessId)
            ->where('is_closed', 0)
            ->leftJoin('fin_contas_bancarias as cb', 'cb.account_id', '=', 'accounts.id')
            ->select([
                'accounts.id',
                'accounts.name',
                'accounts.account_number',
                'accounts.account_type_id',
                'cb.id as complemento_id',
                'cb.banco_codigo',
                'cb.agencia',
                'cb.agencia_dv',
                'cb.conta_dv',
                'cb.carteira',
                'cb.convenio',
                'cb.codigo_cedente',
                'cb.beneficiario_documento',
                'cb.beneficiario_razao_social',
                'cb.beneficiario_logradouro',
                'cb.beneficiario_bairro',
                'cb.beneficiario_cidade',
                'cb.beneficiario_uf',
                'cb.beneficiario_cep',
                'cb.ativo_para_boleto',
                'cb.metadata',
            ])
            ->orderBy('accounts.name')
            ->get();

        return Inertia::render('Financeiro/ContasBancarias/Index', [
            'accounts' => $accounts,
            'bancos_suportados' => array_keys(CnabDirectStrategy::BANCO_MAP),
        ]);
    }

    public function upsert(UpsertContaBancariaRequest $request, int $accountId): RedirectResponse
    {
        $businessId = $request->session()->get('business.id');

        // Garante que account é do business (autorização)
        $account = Account::where('business_id', $businessId)->findOrFail($accountId);

        ContaBancaria::updateOrCreate(
            ['account_id' => $account->id],
            $request->validated() + ['business_id' => $businessId]
        );

        return back()->with('success', 'Boleto configurado com sucesso.');
    }
}
