# ADR ARQ-0001 (Financeiro) · Módulo isolado via nwidart, sem monkey-patch no core

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: `memory/requisitos/MemCofre/adr/arq/0007-docvault-como-framework-de-program-comprehension.md`

## Contexto

UltimatePOS 6.7 é fork comprado do Codecanyon. Próximo upgrade (Laravel 13 → 14) vai aplicar Laravel Shift novamente sobre código original do fornecedor. Toda alteração no `app/` core vira merge conflict crônico — já doeu uma vez no M5+M7+M10 (`reference_diff_3_7_vs_6_7_officeimpresso.md`).

Financeiro precisa: (a) CRUD de títulos, (b) observers em `Transaction` core, (c) menu admin, (d) permissões Spatie, (e) license check Superadmin. Tem 3 caminhos:

1. Adicionar tudo em `app/` (rápido, vira inferno no próximo upgrade)
2. Módulo `nwidart/laravel-modules` isolado em `Modules/Financeiro/` (padrão do projeto pra Connector/PontoWr2/Repair/etc.)
3. Pacote Composer separado (overkill — não vai rodar fora do oimpresso)

## Decisão

Caminho **2**. Módulo nwidart com:

- Namespace `Modules\Financeiro\`
- Tabelas com prefix `fin_` (exceção: `boleto_*` e `pg_*` quando compartilhado com RecurringBilling)
- Hooks injetados via `\App\Utils\ModuleUtil::moduleData('financeiro', [...])` no `boot()` do ServiceProvider
- ZERO edição em arquivos `app/` core
- Observers anexados em runtime (`Transaction::observe(...)` no boot do módulo)
- Listeners em queue própria `financeiro` (failures não derrubam venda)

## Consequências

**Positivas:**
- Próximo upgrade Laravel 14 não toca em `Modules/Financeiro/`
- Tenant pode comprar/desativar Financeiro sem afetar core
- Testes do módulo isolados (`Modules/Financeiro/Tests/`)
- Uninstall = `php artisan module:disable Financeiro` + drop `fin_*` (idempotente)

**Negativas:**
- Performance: observer Eloquent ao invés de chamada direta (overhead ~2ms por transação — aceitável)
- Visibilidade: developer novo precisa entender que "menu Financeiro vem do `ModuleUtil`" (resolvido via `reference_ultimatepos_integracao.md`)
- Cross-módulo só por evento → mais código boilerplate, mais robusto

## Alternativas consideradas

- **Adicionar em `app/`** — rejeitado: dor de cabeça no upgrade
- **Composer separado** — rejeitado: overhead pra zero benefício; nunca vai rodar standalone
- **Plugin via service provider sem nwidart** — rejeitado: perde scaffold (`module:make`), perde scope unificado de testes

## Referências

- `auto-memória: reference_ultimatepos_integracao.md` — pattern hooks DataController
- `Modules/PontoWr2/Providers/PontoWr2ServiceProvider.php` — exemplo do mesmo pattern já em produção
