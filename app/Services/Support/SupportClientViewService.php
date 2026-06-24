<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modo Suporte (ADR 0305) — montagem READ-ONLY da visão de uma empresa-cliente.
 *
 * Caminho SEGURO (auditoria de scoping 2026-06-23): NÃO troca o contexto de sessão
 * (isso causaria split-brain — `CashRegisterUtil`/`payContact`/criação-de-usuário leem
 * `auth()->user()->business_id`, não a sessão → vazariam o operador / gravariam no tenant
 * errado). Em vez disso, passa o `business_id` **EXPLÍCITO** em toda leitura — imune à
 * ambiguidade sessão/auth-user. É o mesmo padrão do Superadmin (`BusinessAuditService`).
 *
 * Defesa em profundidade: re-afirma `canAccessBusiness` (nunca a operadora) mesmo que o
 * middleware `EnsureSupportAccess` já tenha autorizado + auditado a entrada.
 *
 * @see App\Services\Support\SupportAccessService
 * @see App\Http\Middleware\EnsureSupportAccess
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class SupportClientViewService
{
    public function __construct(private SupportAccessService $access)
    {
    }

    /**
     * Resumo read-only da empresa-cliente. Lança se o agente não puder acessá-la
     * (não é agente · é a operadora · empresa inexistente).
     *
     * @return array{empresa: array{id:int, name:string}, contagens: array<string,int>}
     */
    public function clientSummary(User|int $agent, int $businessId): array
    {
        if (! $this->access->canAccessBusiness($agent, $businessId)) {
            throw new \RuntimeException('Empresa fora do alcance do Modo Suporte (ADR 0305).');
        }

        // SUPORTE: leitura cross-tenant EXPLÍCITA por business_id (ADR 0305) — nunca sessão/auth-user.
        $empresa = Business::query()->whereKey($businessId)->firstOrFail(['id', 'name']);

        return [
            'empresa'   => ['id' => (int) $empresa->id, 'name' => (string) $empresa->name],
            'contagens' => [
                'usuarios' => $this->countFor('users', $businessId),
                'contatos' => $this->countFor('contacts', $businessId),
                'produtos' => $this->countFor('products', $businessId),
                'vendas'   => $this->countFor('transactions', $businessId, ['type' => 'sell']),
                'compras'  => $this->countFor('transactions', $businessId, ['type' => 'purchase']),
            ],
        ];
    }

    /**
     * Fase A (ADR 0308) — usuários da empresa-cliente, read-only (business_id EXPLÍCITO).
     *
     * Cada item traz `pode_acessar_como`: se o agente pode "Acessar como" aquele usuário
     * (false p/ superadmin/operador/inativo). Quem decide é o SupportAccessService — a tela
     * só reflete; a porta de entrada re-checa no servidor (defesa em profundidade).
     *
     * @return list<array{id:int, username:string, nome:string, papel:string, email:string, pode_acessar_como:bool}>
     */
    public function clientUsers(User|int $agent, int $businessId): array
    {
        if (! $this->access->canAccessBusiness($agent, $businessId)) {
            throw new \RuntimeException('Empresa fora do alcance do Modo Suporte (ADR 0305).');
        }

        // SUPORTE: leitura cross-tenant EXPLÍCITA por business_id (ADR 0305) — nunca sessão/auth-user.
        return User::query()
            ->where('business_id', $businessId)
            ->where('is_cmmsn_agnt', 0)
            ->with('roles')
            ->orderBy('username')
            ->get()
            ->map(function (User $u) use ($agent): array {
                $roles = $u->getRoleNames();
                $papel = ! empty($roles[0]) ? (explode('#', (string) $roles[0], 2)[0] ?? '') : '';
                $nome = trim(implode(' ', array_filter([$u->surname, $u->first_name, $u->last_name])));

                return [
                    'id'                => (int) $u->id,
                    'username'          => (string) $u->username,
                    'nome'              => $nome,
                    'papel'             => $papel,
                    'email'             => (string) $u->email,
                    'pode_acessar_como' => $this->access->canImpersonate($agent, $u),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Contagem cross-tenant EXPLÍCITA (business_id na mão) — defensiva quanto a schema.
     *
     * @param  array<string,mixed>  $extra
     */
    private function countFor(string $table, int $businessId, array $extra = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        // SUPORTE: cross-tenant explícito (ADR 0305) — filtra SEMPRE por $businessId, nunca sessão.
        $query = DB::table($table)->where('business_id', $businessId);

        foreach ($extra as $col => $val) {
            $query->where($col, $val);
        }

        return (int) $query->count();
    }
}
