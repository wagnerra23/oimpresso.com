<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Account;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
use Modules\Financeiro\Http\Requests\UpsertContaBancariaRequest;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Strategies\CnabDirectStrategy;

/**
 * Tela /financeiro/contas-bancarias.
 *
 * Lista accounts do business + complemento (fin_contas_bancarias) e permite
 * configurar dados de boleto + beneficiário por conta. Credenciais API de
 * gateway (Inter OAuth2/mTLS, Asaas token, C6 etc) vivem em
 * /settings/payment-gateways desde 2026-05-19 (PR #1153/#1154) com FK canon
 * payment_gateway_credentials.conta_bancaria_id.
 *
 * Pattern: ADR 0029 (Inertia + React + UPos).
 * Decisão schema: ADR TECH-0003, ADR ARQ-0008 (Asaas como conta virtual).
 */
class ContaBancariaController extends Controller
{
    use RendersMockCowork;

    // Bancos gateway-only (sem CNAB tradicional). Asaas continua selecionável
    // como conta destino aqui — a credencial é cadastrada em
    // /settings/payment-gateways e referencia esta conta via FK
    // payment_gateway_credentials.conta_bancaria_id.
    private const GATEWAY_ONLY_BANKS = ['274'];

    public function index(Request $request): Response|\Illuminate\Http\Response
    {
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

        $businessId = $request->session()->get('business.id');

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
                'cb.tipo_conta',
                'cb.metadata',
            ])
            ->orderBy('accounts.name')
            ->get()
            ->map(function ($a) {
                // toArray(): converte Eloquent\Model corretamente em array plano.
                // NÃO usar `(array) $a` aqui — PHP cast em Eloquent expõe propriedades protected
                // com prefixo null-byte (`\x00*\x00attributes`...), quebra serialização Inertia
                // (frontend recebe `Cc undefined` em vez do `name`).
                return $a->toArray();
            });

        // array_values: força reindexação 0..N. Sem isso, Inertia serializa como
        // objeto {0:..., 2:..., 5:...} (chaves não-sequenciais), e React quebra
        // com `bancos_suportados.join is not a function` (detectado 2026-05-10).
        $bancosSuportados = array_values(array_unique(array_merge(
            array_keys(CnabDirectStrategy::BANCO_MAP),
            self::GATEWAY_ONLY_BANKS,
        )));

        return Inertia::render('Financeiro/ContasBancarias/Index', [
            'accounts'         => $accounts,
            'bancos_suportados' => $bancosSuportados,
        ]);
    }

    public function upsert(UpsertContaBancariaRequest $request, int $accountId): RedirectResponse
    {
        $businessId = $request->session()->get('business.id');

        $account = Account::where('business_id', $businessId)->findOrFail($accountId);

        $complementoFields = $request->validated();

        // Asaas (274) — conta virtual PJ
        if (($request->input('banco_codigo')) === '274') {
            $complementoFields['tipo_conta'] = 'virtual_pj';
        }

        ContaBancaria::updateOrCreate(
            ['account_id' => $account->id],
            $complementoFields + ['business_id' => $businessId]
        );

        return back()->with('success', 'Boleto configurado com sucesso.');
    }
}
