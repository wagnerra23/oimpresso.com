<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Events\ContactCreatedOrModified;
use App\Http\Controllers\Controller;
use App\Rules\BR\CpfCnpj;
use App\Services\Pessoas\PessoaEscopo;
use App\Services\Pessoas\PessoaVendas;
use App\User;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Pessoas do app das lojas (oimpresso-app). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §4.
 * D11 ([W]): sem papel Transportadora. Só leitura na v1.
 *
 * Visibilidade = a da tela web: permissões customer.view(_own) / supplier.view(_own) e a regra de
 * "só os próprios" via App\Services\Pessoas\PessoaEscopo (a MESMA do ContactController, #8469).
 * Tier 0 (ADR 0093): business_id do usuário do token, explícito.
 */
class PessoasController extends Controller
{
    private const POR_PAGINA = 30;

    /** filtro do app → papel do ERP. */
    private const PAPEIS = [
        'todos' => 'all',
        'clientes' => 'customer',
        'fornecedores' => 'supplier',
        'funcionarios' => 'employee',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        $papel = (string) $request->query('papel', 'todos');
        if ($papel !== 'em_debito' && ! isset(self::PAPEIS[$papel])) {
            $papel = 'todos';
        }
        $busca = trim((string) $request->query('q', ''));
        $pagina = max((int) $request->query('pagina', 1), 1);

        $linhas = $this->filtrar($this->base($user, $busca), $papel, $user)
            ->orderBy('contacts.name')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get(['contacts.id', 'contacts.name', 'contacts.supplier_business_name', 'contacts.tipo',
                'contacts.is_customer', 'contacts.is_supplier', 'contacts.is_employee', 'contacts.contact_status']);

        $temMais = $linhas->count() > self::POR_PAGINA;
        $linhas = $linhas->take(self::POR_PAGINA);
        $saldos = PessoaVendas::saldosAbertos((int) $user->business_id, $linhas->pluck('id')->all());

        $contadores = [];
        foreach (array_merge(array_keys(self::PAPEIS), ['em_debito']) as $p) {
            $contadores[$p] = $this->filtrar($this->base($user, $busca), $p, $user)->count();
        }

        return response()->json([
            'itens' => $linhas->map(fn ($c) => [
                'id' => (int) $c->id,
                'nome' => $this->nome($c),
                'tipo' => $c->tipo ?: null,
                'papeis' => $this->papeis($c),
                'saldo_aberto' => round($saldos[(int) $c->id] ?? 0.0, 2),
                'ativo' => $c->contact_status === 'active',
            ])->values(),
            'contadores' => $contadores,
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    /**
     * GET /api/app/pessoas/{id}/cadastro — Ficha cadastral (tela 34, só leitura, contrato §4.1).
     * Mesmas permissões e o mesmo escopo da ficha. Campo que o cadastro não tem sai null.
     */
    public function cadastro(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        $c = $this->base($user, '')->where('contacts.id', $id)->first([
            'contacts.id', 'contacts.name', 'contacts.supplier_business_name', 'contacts.tipo',
            'contacts.tax_number', 'contacts.cpf_cnpj', 'contacts.indicador_ie', 'contacts.ind_ie_dest',
            'contacts.is_customer', 'contacts.is_supplier', 'contacts.is_employee',
            'contacts.city', 'contacts.state', 'contacts.cep', 'contacts.zip_code', 'contacts.city_code',
            'contacts.email_nfe', 'contacts.credit_limit',
            'contacts.pay_term_number', 'contacts.pay_term_type',
            'contacts.whatsapp_consent', 'contacts.email_consent', 'contacts.consent_updated_at',
        ]);
        if (! $c) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Pessoa não encontrada.'], 404);
        }

        $prazo = $c->pay_term_number !== null
            ? (int) $c->pay_term_number * ($c->pay_term_type === 'months' ? 30 : 1)
            : null;

        return response()->json([
            'id' => (int) $c->id,
            'nome' => $this->nome($c),
            'tipo' => $c->tipo ?: null,
            'identificacao' => [
                'razao_social' => $c->supplier_business_name ?: ($c->name ?: null),
                'documento' => $this->documento($c->cpf_cnpj ?: $c->tax_number),
                'indicador_ie' => $c->indicador_ie ?: ($c->ind_ie_dest ?: null),
                'papeis' => $this->papeis($c),
            ],
            'endereco_fiscal' => [
                'cidade' => $c->city ?: null,
                'uf' => $c->state ?: null,
                'cep' => $c->cep ?: ($c->zip_code ?: null),
                'codigo_ibge' => $c->city_code ?: null,
                'email_nfe' => $c->email_nfe ?: null,
            ],
            'comercial' => [
                // O ERP não tem classificação ABC do cliente (`segmento` é ramo: varejo, atacado…).
                'classificacao' => null,
                'limite_credito' => $c->credit_limit !== null ? round((float) $c->credit_limit, 2) : null,
                'prazo_padrao_dias' => $prazo,
            ],
            'consentimento' => [
                'whatsapp' => $c->whatsapp_consent === null ? null : (bool) $c->whatsapp_consent,
                'email_nfe' => $c->email_consent === null ? null : (bool) $c->email_consent,
                'sms' => null,
                'registrado_em' => $c->consent_updated_at ? \Carbon\Carbon::parse($c->consent_updated_at)->toIso8601String() : null,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->podeVer($user)) {
            return $this->semPermissao();
        }

        $c = $this->base($user, '')->where('contacts.id', $id)->first([
            'contacts.id', 'contacts.name', 'contacts.supplier_business_name', 'contacts.tipo',
            'contacts.tax_number', 'contacts.cpf_cnpj', 'contacts.mobile', 'contacts.email',
            'contacts.city', 'contacts.state', 'contacts.is_customer', 'contacts.is_supplier',
            'contacts.is_employee', 'contacts.contact_status',
        ]);
        if (! $c) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Pessoa não encontrada.'], 404);
        }

        $bizId = (int) $user->business_id;
        $vendas = DB::table('transactions')
            ->where('business_id', $bizId)
            ->where('contact_id', $c->id)
            ->where('type', 'sell')
            ->where('status', 'final');
        $resumo = PessoaVendas::resumo($bizId, (int) $c->id);
        $qtd = $resumo['qtd'];
        $soma = $resumo['soma'];

        return response()->json([
            'id' => (int) $c->id,
            'nome' => $this->nome($c),
            'tipo' => $c->tipo ?: null,
            'documento' => $this->documento($c->cpf_cnpj ?: $c->tax_number),
            'papeis' => $this->papeis($c),
            'ativo' => $c->contact_status === 'active',
            'contato' => ['telefone' => $c->mobile ?: null, 'email' => $c->email ?: null],
            'endereco' => ['cidade' => $c->city ?: null, 'uf' => $c->state ?: null],
            'kpis' => [
                'pedidos' => $qtd,
                'ticket_medio' => $qtd > 0 ? round($soma / $qtd, 2) : 0.0,
                'saldo_aberto' => round(PessoaVendas::saldosAbertos($bizId, [(int) $c->id])[(int) $c->id] ?? 0.0, 2),
            ],
            'pedidos_recentes' => (clone $vendas)
                ->orderByDesc('transaction_date')
                ->limit(5)
                ->get(['id', 'invoice_no', 'transaction_date', 'final_total'])
                ->map(fn ($t) => [
                    'id' => (int) $t->id,
                    'numero' => (string) $t->invoice_no,
                    'data' => substr((string) $t->transaction_date, 0, 10),
                    'valor' => round((float) $t->final_total, 2),
                ])->values(),
        ]);
    }

    /**
     * POST /api/app/pessoas — Nova pessoa (tela 09), contrato §4.2. Grava pelo mesmo caminho do
     * ContactController::store da web (ContactUtil::createNewContact + evento + log de atividade).
     * Sem saldo inicial nem limite de crédito: mexem em valor e ficam no ERP web.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $bizId = (int) $user->business_id;

        $v = Validator::make($request->all(), [
            'tipo' => ['required', 'in:PF,PJ'],
            'nome' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:150'],
            'documento' => ['nullable', new CpfCnpj],
            'indicador_ie' => ['nullable', 'integer', 'in:1,2,9'],
            'papeis' => ['required', 'array', 'min:1'],
            'papeis.*' => ['in:cliente,fornecedor'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'email_nfe' => ['nullable', 'email', 'max:120'],
            'cep' => ['nullable', 'string', 'max:20'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'max:255'],
            'codigo_ibge' => ['nullable', 'string', 'max:10'],
            'prazo_padrao_dias' => ['nullable', 'integer', 'min:0', 'max:365'],
            'consentimento' => ['nullable', 'array'],
            'consentimento.whatsapp' => ['nullable', 'boolean'],
            'consentimento.email_nfe' => ['nullable', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome.',
            'papeis.required' => 'Escolha cliente, fornecedor ou os dois.',
            'papeis.min' => 'Escolha cliente, fornecedor ou os dois.',
            'email.email' => 'Informe um e-mail válido.',
            'email_nfe.email' => 'Informe um e-mail válido para a NF-e.',
            'indicador_ie.in' => 'Indicador IE deve ser 1 (contribuinte), 2 (isento) ou 9 (não contribuinte).',
        ]);
        if ($v->fails()) {
            return response()->json([
                'erro' => 'validacao',
                'campos' => collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all(),
            ], 422);
        }
        $d = $v->validated();
        $cliente = in_array('cliente', $d['papeis'], true);
        $fornecedor = in_array('fornecedor', $d['papeis'], true);

        if (($cliente && ! $user->can('customer.create')) || ($fornecedor && ! $user->can('supplier.create'))) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não pode cadastrar essa pessoa.'], 403);
        }
        if (! app(ModuleUtil::class)->isSubscribed($bizId)) {
            return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'A assinatura da empresa não está ativa.'], 403);
        }

        $documento = preg_replace('/\D/', '', (string) ($d['documento'] ?? ''));
        $input = [
            'business_id' => $bizId,
            'created_by' => $user->id,
            'type' => $cliente && $fornecedor ? 'both' : ($cliente ? 'customer' : 'supplier'),
            'is_customer' => $cliente,
            'is_supplier' => $fornecedor,
            'tipo' => $d['tipo'],
            'contact_type' => $d['tipo'] === 'PJ' ? 'business' : 'person',
            'name' => trim($d['nome']),
            'supplier_business_name' => $d['tipo'] === 'PJ' ? trim($d['nome']) : null,
            'nome_fantasia' => $d['nome_fantasia'] ?? null,
            'cpf_cnpj' => $documento !== '' ? $documento : null,
            // `contacts.mobile` é NOT NULL no schema UltimatePOS: sem telefone grava vazio, não null
            // (null derrubava o INSERT com 500 — medido na lane Acessos, run 37033539499).
            'mobile' => $d['telefone'] ?? '',
            'email' => $d['email'] ?? null,
            'email_nfe' => $d['email_nfe'] ?? null,
            'indicador_ie' => $d['indicador_ie'] ?? null,
            'zip_code' => $d['cep'] ?? null,
            'address_line_1' => $d['logradouro'] ?? null,
            'numero' => $d['numero'] ?? null,
            'complemento' => $d['complemento'] ?? null,
            'neighborhood' => $d['bairro'] ?? null,
            'city' => $d['cidade'] ?? null,
            'state' => $d['uf'] ?? null,
            'city_code' => $d['codigo_ibge'] ?? null,
            'pay_term_number' => $d['prazo_padrao_dias'] ?? null,
            'pay_term_type' => isset($d['prazo_padrao_dias']) ? 'days' : null,
        ];
        // Consentimento LGPD (Art. 7º, I): só grava o que veio; a data registra quando foi dado.
        $consent = $d['consentimento'] ?? [];
        if (array_key_exists('whatsapp', $consent) && $consent['whatsapp'] !== null) {
            $input['whatsapp_consent'] = (bool) $consent['whatsapp'];
            // Coluna do módulo Whatsapp: só existe com ele migrado.
            if (\Illuminate\Support\Facades\Schema::hasColumn('contacts', 'whatsapp_opt_in_at')) {
                $input['whatsapp_opt_in_at'] = $consent['whatsapp'] ? now() : null;
            }
        }
        if (array_key_exists('email_nfe', $consent) && $consent['email_nfe'] !== null) {
            $input['email_consent'] = (bool) $consent['email_nfe'];
        }
        if (isset($input['whatsapp_consent']) || isset($input['email_consent'])) {
            $input['consent_updated_at'] = now();
        }

        DB::beginTransaction();
        try {
            $output = app(ContactUtil::class)->createNewContact($input);
            if (empty($output['success'])) {
                DB::rollBack();

                return response()->json(['erro' => 'validacao', 'campos' => [], 'mensagem' => (string) ($output['msg'] ?? '')], 422);
            }
            event(new ContactCreatedOrModified($input, 'added'));
            app(ContactUtil::class)->activityLog($output['data'], 'added');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('API app pessoas.store: ' . $e->getMessage());

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível cadastrar a pessoa.'], 500);
        }

        return response()->json(['id' => (int) $output['data']->id], 201);
    }

    // ------------------------------------------------------------------

    /** Quem vê a aba Pessoas. */
    public function podeVerPessoas(User $u): bool
    {
        return $this->podeVer($u);
    }

    private function podeVer(?User $u): bool
    {
        return $u !== null && (
            $u->can('customer.view') || $u->can('customer.view_own')
            || $u->can('supplier.view') || $u->can('supplier.view_own')
        );
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json(['erro' => 'sem_permissao', 'mensagem' => 'Seu usuário não tem acesso às pessoas.'], 403);
    }

    private function base(User $user, string $busca): Builder
    {
        $q = DB::table('contacts')->where('contacts.business_id', (int) $user->business_id);
        PessoaEscopo::viewOwn($q, 'all', $user);

        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $q->where(fn ($w) => $w->where('contacts.name', 'like', $like)
                ->orWhere('contacts.supplier_business_name', 'like', $like)
                ->orWhere('contacts.mobile', 'like', $like));
        }

        return $q;
    }

    private function filtrar(Builder $q, string $papel, User $user): Builder
    {
        if ($papel === 'em_debito') {
            return $q->whereExists(function ($e) use ($user) {
                $e->select(DB::raw(1))
                    ->from('transactions as t')
                    ->whereColumn('t.contact_id', 'contacts.id')
                    ->where('t.business_id', (int) $user->business_id)
                    ->where('t.type', 'sell')
                    ->where('t.status', 'final')
                    ->whereIn('t.payment_status', ['due', 'partial']);
            });
        }

        $tipo = self::PAPEIS[$papel] ?? 'all';
        PessoaEscopo::papel($q, $tipo);
        PessoaEscopo::viewOwn($q, $tipo, $user);

        return $q;
    }

    /**
     * CPF sai mascarado no celular (só os 5 últimos dígitos); CNPJ sai inteiro, formatado, porque
     * é dado público da empresa. Decisão [W] 2026-10-02 ("faça todas"), contrato §4. A ficha web
     * segue mostrando o CPF inteiro; esconder lá é outra decisão.
     */
    private function documento(?string $doc): ?string
    {
        $d = preg_replace('/\D/', '', (string) $doc);
        if (strlen($d) === 11) {
            return '***.***.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
        }
        if (strlen($d) === 14) {
            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
        }

        return $doc !== null && $doc !== '' ? $doc : null;
    }

    private function nome(object $c): string
    {
        return trim((string) ($c->name ?: $c->supplier_business_name)) ?: '—';
    }

    /** @return list<string> */
    private function papeis(object $c): array
    {
        return array_values(array_filter([
            $c->is_customer ? 'cliente' : null,
            $c->is_supplier ? 'fornecedor' : null,
            $c->is_employee ? 'funcionario' : null,
        ]));
    }
}
