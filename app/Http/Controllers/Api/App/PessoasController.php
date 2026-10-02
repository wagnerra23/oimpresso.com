<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\Pessoas\PessoaEscopo;
use App\Services\Pessoas\PessoaVendas;
use App\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'documento' => $c->cpf_cnpj ?: $c->tax_number,
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

    // ------------------------------------------------------------------

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
