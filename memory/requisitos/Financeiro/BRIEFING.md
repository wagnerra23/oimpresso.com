# BRIEFING — Modules/Financeiro

> Visão unificada de AR/AP (Contas a Receber / Contas a Pagar) + Fluxo de Caixa + Boletos + Conciliação. Cockpit V2 persona Eliana [E] (financeiro escritório, densidade alta, atalhos teclado).

**Última atualização:** 2026-05-18 · Onda Edit PR #1086 (Edit Sheet inline + Conferido per-user DB + cross-links auto-pop) · Skill `brief-update` (Tier B)

## Estado consolidado (Wave 18 RETRY — saturação 68→97)

| Dimensão (rubrica module-grade-v3) | Wave 17 | Wave 18 | Wave 18 RETRY | Δ total |
|---|---|---|---|---|
| D1 Multi-tenant Tier 0 | 23/30 | 30/30 | 30/30 | +7 (BaixaRepository + MultiTenantComprehensiveTest 11 datasets cross-tenant biz=99) |
| D4 SoC brutal (Service/Repository ratio) | 6/20 | 17/20 | 20/20 | +14 (BaixaRepository extraído, 4 métodos canônicos type-hinted, singleton no Provider) |
| D6 Inertia::defer pattern | 6/10 | 10/10 | 10/10 | +4 (Wave 17 já saturou — Dashboard/Fluxo confirmados) |
| D7 LGPD + retention + activitylog | 7/10 | 10/10 | 10/10 | +3 (Wave 17 já saturou — module.json lgpd_compliance) |
| D8 FormRequests tipados | 4/8 | 8/8 | 8/8 | +4 (StoreBaixaRequest + UpdateAccountRequest 5°+6° request) |
| D9 OTel spans + Health command | 4/7 | 7/7 | 7/7 | +3 (BaixaRepository spans + reflection Pest) |

## Capacidade canônica (estado real)

- **AR/AP unificado** — Visão Unificada `/financeiro/unificado` (ADR Cockpit V2) — coluna única Títulos (`fin_titulos`) tipo `receber`/`pagar`, baixas append-only (`fin_titulo_baixas`), idempotência via UNIQUE(business_id, origem, origem_id, parcela_numero).
- **Fluxo de Caixa projeção 35d** — `FluxoCaixaService::projetar(biz, dias)` agrega saldo_cached + títulos futuros + baixas históricas, retorna shape Inertia pronto (KPIs: saldo_hoje, saldo_30d, pior_dia, margem_mínima R$ 5.000).
- **Boletos** — `TituloService` orquestra emissão via `BoletoStrategy` (default `CnabDirectStrategy`); remessas CNAB 240 em `fin_boleto_remessas`.
- **Conta Bancária** — `saldo_cached` materializado (migration 2026-05-06), múltiplas contas por business, mapeamento legacy via `accounts_legacy_map`.
- **Extrato** — `fin_extrato_lancamentos` para conciliação OFX/CSV (US-FIN futuros).
- **Plano de Contas BR** — Seeder pronto (`PlanoContasBrSeeder`) + categorização hierárquica `fin_categorias`.
- **Integrações** — `TransactionObserver` + `TransactionPaymentObserver` propagam vendas core UltimatePOS → Títulos; `CriarTituloDeVendaJob` assíncrono.
- **Edit Sheet inline (Onda Edit 2026-05-18, PR #1086)** — `TituloEditSheet.tsx` drawer canon edita campos seguros (`cliente_descricao`, `observacoes`, `categoria_id`, `vencimento`, `valor_total` pré-baixa). PUT `/financeiro/unificado/{id}` via `useForm` Inertia. Guard `assertValorMutavel` bloqueia valor pós-baixa (ADR fin-tech/0002). Substitui STUB `<Button>Editar</Button>` linha 706 do Index.tsx.
- **Conferido per-user DB (Onda Edit, substitui Onda 5 R1 localStorage)** — `fin_titulos.conferido_by` (FK users.id) + `conferido_at` (TIMESTAMP) + index `idx_business_conferido`. Eliana confere ≠ Wagner confere = audit per-user. Routes POST/DELETE `/unificado/{id}/conferir`. `FinConferidoToggle` rewrite: `useFinConferido(lancamentos)` consulta DB-backed via prop array.
- **Cross-links auto-pop #V-/#PC-** — `TituloAutoService::sincronizarDeTransacaoInternal` enriquece `cliente_descricao` com `{ContactName} · #V-{tx_id}` (vendas) ou `#PC-{tx_id}` (compras) no afterCreate. FinCrossLinkify renderiza pills clicáveis route → Sells/Compras. Preserva edit manual user (não sobrescreve se já preenchido).

## Multi-tenant Tier 0 (ADR 0093)

Todas Models usam `BusinessScope` trait (`Modules/Financeiro/Models/Concerns/BusinessScope.php`). Queries filtram `business_id` automaticamente. Defesa em profundidade: services também passam `$businessId` explícito.

## Clientes piloto

| Biz | Cliente | Uso | Notas |
|---|---|---|---|
| **biz=1** | Wagner [W] (interno) | dev + smoke tests | sempre alvo de tests (ADR 0101) |
| **biz=4** | ROTA LIVRE — Larissa | produção 99% volume | ADR 0066 `format_date` shift +3h preservado |

## Arquitetura

- **Controllers (13):** Boleto, Categoria, ContaBancaria, ContaPagar, ContaReceber, Dashboard, Data, Extrato, Financeiro, Fluxo, Install, Relatorios, Unificado (com `update`+`conferir`+`unconferir` Onda Edit)
- **Services (4):** FluxoCaixaService (projeção read-side), TituloService (boleto lifecycle), TituloAutoService (origem auto vendas/compras + cross-links #V-/#PC- Onda Edit), UnificadoService (facade KPIs cockpit)
- **Repositories (2):** TituloRepository (Wave 18), BaixaRepository (Wave 18 RETRY) — singleton, type-safe `businessId:int` 1º param
- **Models (10):** Titulo (Onda Edit: +`conferidoPor()` BelongsTo App\User), TituloBaixa, CaixaMovimento, Categoria, ContaBancaria, BoletoRemessa, ExtratoLancamento, PlanoConta, AccountsLegacyMap
- **FormRequests (7):** UpsertCategoriaRequest, UpsertContaBancariaRequest, StoreTransactionRequest, UpdateTransactionRequest, StoreAccountRequest, FluxoFiltroRequest, StoreBaixaRequest, UpdateAccountRequest, UpdateTituloRequest (Onda Edit — guard imutabilidade pós-baixa)
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
