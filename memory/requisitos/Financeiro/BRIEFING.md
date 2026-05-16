# BRIEFING — Modules/Financeiro

> Visão unificada de AR/AP (Contas a Receber / Contas a Pagar) + Fluxo de Caixa + Boletos + Conciliação. Cockpit V2 persona Eliana [E] (financeiro escritório, densidade alta, atalhos teclado).

**Última atualização:** 2026-05-16 · Wave J boost · Skill `brief-update` (Tier B)

## Capacidade canônica (estado real)

- **AR/AP unificado** — Visão Unificada `/financeiro/unificado` (ADR Cockpit V2) — coluna única Títulos (`fin_titulos`) tipo `receber`/`pagar`, baixas append-only (`fin_titulo_baixas`), idempotência via UNIQUE(business_id, origem, origem_id, parcela_numero).
- **Fluxo de Caixa projeção 35d** — `FluxoCaixaService::projetar(biz, dias)` agrega saldo_cached + títulos futuros + baixas históricas, retorna shape Inertia pronto (KPIs: saldo_hoje, saldo_30d, pior_dia, margem_mínima R$ [redacted Tier 0]).
- **Boletos** — `TituloService` orquestra emissão via `BoletoStrategy` (default `CnabDirectStrategy`); remessas CNAB 240 em `fin_boleto_remessas`.
- **Conta Bancária** — `saldo_cached` materializado (migration 2026-05-06), múltiplas contas por business, mapeamento legacy via `accounts_legacy_map`.
- **Extrato** — `fin_extrato_lancamentos` para conciliação OFX/CSV (US-FIN futuros).
- **Plano de Contas BR** — Seeder pronto (`PlanoContasBrSeeder`) + categorização hierárquica `fin_categorias`.
- **Integrações** — `TransactionObserver` + `TransactionPaymentObserver` propagam vendas core UltimatePOS → Títulos; `CriarTituloDeVendaJob` assíncrono.

## Multi-tenant Tier 0 (ADR 0093)

Todas Models usam `BusinessScope` trait (`Modules/Financeiro/Models/Concerns/BusinessScope.php`). Queries filtram `business_id` automaticamente. Defesa em profundidade: services também passam `$businessId` explícito.

## Clientes piloto

| Biz | Cliente | Uso | Notas |
|---|---|---|---|
| **biz=1** | Wagner [W] (interno) | dev + smoke tests | sempre alvo de tests (ADR 0101) |
| **biz=4** | ROTA LIVRE — Larissa | produção 99% volume | ADR 0066 `format_date` shift +3h preservado |

## Arquitetura

- **Controllers (13):** Boleto, Categoria, ContaBancaria, ContaPagar, ContaReceber, Dashboard, Data, Extrato, Financeiro, Fluxo, Install, Relatorios, Unificado
- **Services (3):** FluxoCaixaService (projeção read-side), TituloService (boleto lifecycle), TituloAutoService (origem auto vendas/compras), UnificadoService (facade KPIs cockpit)
- **Models (10):** Titulo, TituloBaixa, CaixaMovimento, Categoria, ContaBancaria, BoletoRemessa, ExtratoLancamento, PlanoConta, AccountsLegacyMap
- **Strategies:** `CnabDirectStrategy` (default boleto via CNAB 240)
- **Observers:** TransactionObserver, TransactionPaymentObserver (sync vendas core → Títulos)
- **Pages Inertia (13 charters):** Cockpit V2 padrão; Fluxo, Unificado, ContasReceber, ContasPagar, Boletos validados visualmente

## Diferenciais (vs Bling/Tiny/Conta Azul/Omie)

1. **Cockpit V2 unificado AR/AP** — concorrentes separam telas; aqui visão única com tabs/filtros densos
2. **Fluxo projetado 35d com pior_dia + margem mínima** — KPI proativo, não só passivo
3. **Idempotência forte** — UNIQUE(biz, origem, origem_id, parcela) previne duplicação na sincronização vendas/compras
4. **Pest cross-tenant** — `MultiTenantIsolationTest` + `AccountsLegacyMapMultiTenantTest` blindam biz=1 vs biz=99 (Tier 0)
5. **Append-only audit** — TituloBaixa nunca DELETE; estorno via `estorno_de_id` (Activitylog Spatie em Titulo)

## Gaps conhecidos

- D3.b BRIEFING — **resolvido** neste documento (2026-05-16)
- D4.a Service ratio sub-detectado — Services existem (`Services/*.php`) mas auditor não cruzava com Controllers; UnificadoService criado como facade explícito de KPIs do cockpit
- Conciliação OFX UI — schema existe (`fin_extrato_lancamentos`) mas falta tela Cockpit
- Relatórios DRE — `RelatoriosController` esqueleto, falta US-FIN-XXX detalhada

## ADRs relacionadas

- [ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) — `format_date` shift +3h preservado pra ROTA LIVRE
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1, nunca cliente

## Charters Inertia

`Boletos.charter.md`, `Fluxo/Index.charter.md`, `Unificado/Index.charter.md`, `ContasReceber.charter.md`, `ContasPagar.charter.md` (5 ativos)

## SPECs e canon

- [SPEC.md](SPEC.md) US-FIN-001..020+
- [ARCHITECTURE.md](ARCHITECTURE.md)
- [COMPARATIVO_CONCORRENCIA.md](COMPARATIVO_CONCORRENCIA.md)
- [DOC_TELAS_E_SCORE.md](DOC_TELAS_E_SCORE.md)
- [PLANO_DETALHADO.md](PLANO_DETALHADO.md)
