<?php

namespace Modules\Financeiro\Models\Concerns;

use App\Tenancy\ContextoEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Implementação concreta do scope global por business_id.
 * Separada do trait pra permitir withoutGlobalScope(BusinessScopeImpl::class).
 */
class BusinessScopeImpl implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Empresa vem do ContextoEmpresa (ADR 0453). No modo `aberto` (padrão) é só a sessão,
        // como sempre foi; em `log`/`fechado`, também job/API, e sem empresa registra ou lança.
        $businessId = app(ContextoEmpresa::class)->empresaDoEscopo($model::class);
        if ($businessId === null) {
            return;
        }

        // SEM excecao de superadmin — DECISAO [W] 2026-08-26: "so da empresa
        // selecionada". Ate hoje este scope fazia `return` seco quando o usuario tinha
        // a permissao `superadmin`, ou seja, NAO filtrava nada e a tela mostrava TODAS
        // as empresas.
        //
        // Exposicao medida em producao antes do conserto:
        //   fin_titulos           151.427 linhas de 3 empresas (1, 4, 164)
        //   fin_contas_bancarias       94 linhas de 69 empresas
        //   fin_planos_conta          433 linhas de 88 empresas
        //
        // Quem PRECISA de cross-tenant continua tendo o caminho explicito, que ja era o
        // usado por todo codigo legitimo (jobs, comandos, seeders):
        //   Model::withoutGlobalScope(BusinessScopeImpl::class)->...
        // Varredura antes do conserto: 8 call sites usam a escotilha; NENHUM dependia do
        // atalho implicito do superadmin, e os 3 controllers que citam `superadmin` usam
        // pra PERMISSAO (menu, autorizacao de acao), nunca pra escopo de dado.

        $builder->where($model->getTable() . '.business_id', $businessId);
    }
}
