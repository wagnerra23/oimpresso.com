<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Account;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Http\Controllers\Concerns\RendersMockCowork;
use Modules\Financeiro\Http\Requests\UpsertContaBancariaRequest;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Strategies\CnabDirectStrategy;
use Modules\RecurringBilling\Models\BoletoCredential;

/**
 * Tela /financeiro/contas-bancarias.
 *
 * Lista accounts do business + complemento (fin_contas_bancarias) +
 * credenciais de gateway (rb_boleto_credentials). Permite configurar
 * dados de boleto e credenciais Inter/Asaas via Sheet.
 *
 * Pattern: ADR 0029 (Inertia + React + UPos).
 * Decisão schema: ADR TECH-0003, ADR ARQ-0008 (Asaas como conta virtual).
 */
class ContaBancariaController extends Controller
{
    use RendersMockCowork;

    // Bancos que usam API de gateway (precisam de credenciais)
    private const GATEWAY_BANKS = [
        '077' => 'inter',
        '274' => 'asaas',
    ];

    public function index(Request $request): Response|\Illuminate\Http\Response
    {
        if ($mock = $this->tryRenderMockCowork()) {
            return $mock;
        }

        $businessId = $request->session()->get('business.id');

        $accounts = Account::where('accounts.business_id', $businessId)
            ->where('is_closed', 0)
            ->leftJoin('fin_contas_bancarias as cb', 'cb.account_id', '=', 'accounts.id')
            ->leftJoin('rb_boleto_credentials as bc', 'bc.id', '=', 'cb.rb_gateway_credential_id')
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
                'cb.rb_gateway_credential_id',
                'cb.metadata',
                // Dados públicos da credencial (sem segredos)
                'bc.banco as gateway_banco',
                'bc.ambiente as gateway_ambiente',
                'bc.ativo as gateway_ativo',
                'bc.config_json as gateway_config_json',
            ])
            ->orderBy('accounts.name')
            ->get()
            ->map(function ($a) {
                // Extrai client_id do config_json (não-secreto) para pré-preencher form
                $gatewayClientId = null;
                if ($a->gateway_config_json) {
                    $cfg = is_array($a->gateway_config_json)
                        ? $a->gateway_config_json
                        : json_decode($a->gateway_config_json, true);
                    $gatewayClientId = $cfg['client_id'] ?? null;
                }

                // toArray(): converte Eloquent\Model corretamente em array plano.
                // NÃO usar `(array) $a` aqui — PHP cast em Eloquent expõe propriedades protected
                // com prefixo null-byte (`\x00*\x00attributes`...), quebra serialização Inertia
                // (frontend recebe `Cc undefined` em vez do `name`).
                $result = $a->toArray();
                unset($result['gateway_config_json']); // nunca expõe segredos
                $result['gateway_client_id'] = $gatewayClientId;

                return $result;
            });

        // array_values: força reindexação 0..N. Sem isso, Inertia serializa como
        // objeto {0:..., 2:..., 5:...} (chaves não-sequenciais), e React quebra
        // com `bancos_suportados.join is not a function` (detectado 2026-05-10).
        $bancosSuportados = array_values(array_unique(array_merge(
            array_keys(CnabDirectStrategy::BANCO_MAP),
            array_keys(self::GATEWAY_BANKS),
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

        // Campos do complemento fin_contas_bancarias
        $complementoFields = $request->safe()->except([
            'gateway_ambiente',
            'gateway_client_id',
            'gateway_client_secret',
            'gateway_certificado_crt',
            'gateway_certificado_key',
            'gateway_api_key',
        ]);

        // Para Asaas (274) — conta virtual PJ
        $bancoCodigo = $request->input('banco_codigo');
        if ($bancoCodigo === '274') {
            $complementoFields['tipo_conta'] = 'virtual_pj';
        }

        $conta = ContaBancaria::updateOrCreate(
            ['account_id' => $account->id],
            $complementoFields->toArray() + ['business_id' => $businessId]
        );

        // Salva credenciais de gateway quando banco usa API
        if (isset(self::GATEWAY_BANKS[$bancoCodigo])) {
            $this->saveGatewayCredential($request, $businessId, $conta, self::GATEWAY_BANKS[$bancoCodigo]);
        }

        return back()->with('success', 'Boleto configurado com sucesso.');
    }

    private function saveGatewayCredential(
        UpsertContaBancariaRequest $request,
        int $businessId,
        ContaBancaria $conta,
        string $banco,
    ): void {
        $credencial = BoletoCredential::firstOrNew([
            'business_id' => $businessId,
            'banco'       => $banco,
        ]);

        $credencial->conta_bancaria_id = $conta->id;
        $credencial->ambiente = $request->input('gateway_ambiente', $credencial->ambiente ?? 'production');
        $credencial->ativo    = true;

        $config = $credencial->config_json ?? [];

        if ($banco === 'inter') {
            if ($v = $request->input('gateway_client_id')) {
                $config['client_id'] = $v;
            }
            if ($v = $request->input('gateway_client_secret')) {
                $config['client_secret'] = Crypt::encryptString($v);
            }
            if ($v = $request->input('gateway_certificado_crt')) {
                // Cert público — base64 puro (não-sensível)
                $config['certificado_crt_b64'] = base64_encode($v);
            }
            if ($v = $request->input('gateway_certificado_key')) {
                // Chave privada — criptografa o base64 (BoletoService::decryptConfig
                // descriptografa antes de o driver fazer base64_decode)
                $config['certificado_key_b64'] = Crypt::encryptString(base64_encode($v));
            }
        }

        if ($banco === 'asaas') {
            if ($v = $request->input('gateway_api_key')) {
                $config['api_key'] = Crypt::encryptString($v);
            }
        }

        $credencial->config_json = $config;
        $credencial->save();

        // Garante o FK inverso em fin_contas_bancarias
        $conta->rb_gateway_credential_id = $credencial->id;
        $conta->saveQuietly();
    }
}
