<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Account;
use App\AccountType;
use App\Services\Contas\ContasDoNucleo;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
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
    /**
     * Gate de permissão (Camada 3 Spatie). Até 2026-09-23 este controller não
     * verificava permissão nenhuma — rota e FormRequest só exigiam login.
     * O escopo por business_id segue no corpo dos métodos; isto fecha o acesso
     * DENTRO da empresa. Admin#{biz} passa pelo Gate::before (AuthServiceProvider).
     */
    public function __construct()
    {
        $this->middleware('can:financeiro.contas_bancarias.manage');
    }


    // Bancos gateway-only (sem CNAB tradicional). Asaas continua selecionável
    // como conta destino aqui — a credencial é cadastrada em
    // /settings/payment-gateways e referencia esta conta via FK
    // payment_gateway_credentials.conta_bancaria_id.
    private const GATEWAY_ONLY_BANKS = ['274'];

    private const CAMPOS_COMPLEMENTO = ['banco_codigo', 'agencia', 'agencia_dv', 'conta_dv', 'carteira', 'convenio',
        'codigo_cedente', 'beneficiario_documento', 'beneficiario_razao_social', 'beneficiario_logradouro',
        'beneficiario_bairro', 'beneficiario_cidade', 'beneficiario_uf', 'beneficiario_cep', 'ativo_para_boleto',
        'tipo_conta', 'metadata'];

    public function index(Request $request, ContasDoNucleo $contas): Response|\Illuminate\Http\Response
    {
        $businessId = (int) $request->session()->get('business.id');
        $encerradas = $request->query('situacao') === 'encerrada';

        // Saldo e filtro por local: o mesmo SQL das contas do núcleo (ContasDoNucleo::consulta) — não há 2ª conta dele.
        $linhas = $contas->consulta($businessId, $request->user())
            ->where('is_closed', $encerradas ? 1 : 0)
            ->groupBy('accounts.id')
            ->orderBy('accounts.name')
            ->get();

        $complementos = ContaBancaria::where('business_id', $businessId)
            ->whereIn('account_id', $linhas->pluck('id'))
            ->get()
            ->keyBy('account_id');

        $util = app(\App\Utils\Util::class);
        // As colunas vêm de aliases do SELECT (saldo, tipo, quem criou), não de atributos do model: lidas por getAttribute.
        $accounts = $linhas->map(function (\Illuminate\Database\Eloquent\Model $row) use ($complementos, $util) {
            $v = fn (string $campo) => $row->getAttribute($campo);
            $id = (int) $v('id');
            $cb = $complementos->get($id);
            $tipoPai = $v('parent_account_type_name');
            $saldo = (float) $v('balance');

            // Chaves do complemento como o ConfigurarBoletoSheet já lê; null quando a conta não tem complemento.
            return array_merge(
                array_fill_keys(self::CAMPOS_COMPLEMENTO, null),
                $cb ? array_intersect_key($cb->toArray(), array_flip(self::CAMPOS_COMPLEMENTO)) : [],
                [
                    'id' => $id,
                    'name' => $v('name'),
                    'account_number' => $v('account_number'),
                    'account_type_id' => $v('account_type_id') ? (int) $v('account_type_id') : null,
                    'note' => $v('note'),
                    'account_details' => array_values((array) ($v('account_details') ?? [])),
                    'complemento_id' => $cb?->id,
                    'tipo' => empty($tipoPai) ? $v('account_type_name') : $tipoPai,
                    'subtipo' => empty($tipoPai) ? null : $v('account_type_name'),
                    // Saldo formatado pelo mesmo num_f da Blade; a tela não soma nem formata valor.
                    'saldo' => $saldo,
                    'saldo_texto' => $util->num_f($saldo, true),
                    'adicionado_por' => trim(preg_replace('/\s+/', ' ', (string) $v('added_by'))),
                    'encerrada' => (int) $v('is_closed') === 1,
                ]
            );
        })->values();

        // array_values: força reindexação 0..N. Sem isso, Inertia serializa como
        // objeto {0:..., 2:..., 5:...} (chaves não-sequenciais), e React quebra
        // com `bancos_suportados.join is not a function` (detectado 2026-05-10).
        $bancosSuportados = array_values(array_unique(array_merge(
            array_keys(CnabDirectStrategy::BANCO_MAP),
            self::GATEWAY_ONLY_BANKS,
        )));

        return Inertia::render('Financeiro/ContasBancarias/Index', [
            'accounts'          => $accounts,
            'bancos_suportados' => $bancosSuportados,
            'situacao'          => $encerradas ? 'encerrada' : 'ativa',
            'tipos_conta'       => AccountType::where('business_id', $businessId)
                ->whereNull('parent_account_type_id')
                ->with('sub_types')
                ->orderBy('name')
                ->get()
                ->map(fn (AccountType $t) => [
                    'id' => (int) $t->id,
                    'nome' => (string) $t->name,
                    'subtipos' => $t->sub_types
                        ->map(fn (AccountType $s) => ['id' => (int) $s->id, 'nome' => (string) $s->name])
                        ->values()
                        ->all(),
                ])->values(),
        ]);
    }

    /** Nova conta (core `accounts`) + saldo de abertura opcional, pelo mesmo caminho do cadastro do núcleo. */
    public function store(Request $request, ContasDoNucleo $contas): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');
        $dados = $this->validarCadastro($request, $businessId, comSaldo: true);

        $contas->criar($businessId, (int) $request->user()->id, $dados, $dados['opening_balance'] ?? null);

        return back()->with('success', 'Conta criada.');
    }

    public function atualizar(Request $request, ContasDoNucleo $contas, int $accountId): RedirectResponse
    {
        $businessId = (int) $request->session()->get('business.id');
        $contas->atualizar($businessId, $accountId, $this->validarCadastro($request, $businessId, comSaldo: false));

        return back()->with('success', 'Conta atualizada.');
    }

    public function encerrar(Request $request, ContasDoNucleo $contas, int $accountId): RedirectResponse
    {
        $contas->definirEncerrada((int) $request->session()->get('business.id'), $accountId, true);

        return back()->with('success', 'Conta encerrada.');
    }

    public function reativar(Request $request, ContasDoNucleo $contas, int $accountId): RedirectResponse
    {
        $contas->definirEncerrada((int) $request->session()->get('business.id'), $accountId, false);

        return back()->with('success', 'Conta reativada.');
    }

    private function validarCadastro(Request $request, int $businessId, bool $comSaldo): array
    {
        $regras = [
            'name' => ['required', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:1000'],
            // Tier 0: o tipo tem de ser da mesma empresa.
            'account_type_id' => ['nullable', 'integer', Rule::exists('account_types', 'id')->where('business_id', $businessId)],
            'account_details' => ['nullable', 'array', 'max:20'],
            'account_details.*.label' => ['nullable', 'string', 'max:191'],
            'account_details.*.value' => ['nullable', 'string', 'max:191'],
        ];
        if ($comSaldo) {
            // Texto no formato da empresa (ex.: "1.234,56"); o num_uf do núcleo converte, como no cadastro Blade.
            $regras['opening_balance'] = ['nullable', 'string', 'max:30'];
        }

        $dados = $request->validate($regras);
        $dados['account_details'] = array_values(array_filter(
            $dados['account_details'] ?? [],
            fn ($d) => ! empty($d['label']) || ! empty($d['value'])
        ));

        return $dados;
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
