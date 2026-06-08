# RUNBOOK — Pages Financeiro Index

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN) — índice das Pages Inertia do módulo Financeiro
> **Status:** vivo (consolidado 2026-05-21 após Fases 1-6 deprecação legacy)
> **Refs:** ADR 0080 module charter Financeiro, ADR 0093 multi-tenant Tier 0, ADR 0104 MWART canônico, ADR 0107 visual-comparison gate F1.5

## Pages cobertas por este RUNBOOK

Hook `block-mwart-violation.ps1` matcha pelo nome do arquivo. Pages atuais no módulo:

### Sub-grupo OPERAÇÃO (sidebar `fin-op`, uso diário)

1. `resources/js/Pages/Financeiro/Unificado/Index.tsx` — **Visão Unificada** AR+AP (cockpit principal)
2. `resources/js/Pages/Financeiro/ContasReceber/Index.tsx` — **Contas a Receber** (recorte dedicado, mental model BR)
3. `resources/js/Pages/Financeiro/ContasPagar/Index.tsx` — **Contas a Pagar** (recorte dedicado)
4. `resources/js/Pages/Financeiro/Fluxo/Index.tsx` — **Fluxo de Caixa** tabs Projetado+Realizado (F3 absorve Cash Flow legacy)
5. `resources/js/Pages/Financeiro/Cobranca/Index.tsx` — **Cobrança** (F3 PaymentGateway UI Tela 1)
6. `resources/js/Pages/Financeiro/Caixa/Index.tsx` — **Caixa do turno** (F6 Soft wrapper read-only sobre `cash_registers` core) ← novo 2026-05-21

### Sub-grupo ANÁLISE (sidebar `fin-analise`, uso mensal)

7. `resources/js/Pages/Financeiro/Conciliacao/Index.tsx` — **Conciliação OFX** (Onda 19)
8. `resources/js/Pages/Financeiro/Dre/Index.tsx` — **DRE Gerencial** com tabs Demonstrativo+Balanço+Balancete (F4 absorve Balance Sheet + Trial Balance legacy)
9. `resources/js/Pages/Financeiro/Relatorios/Index.tsx` — **Relatórios** legacy (cleanup PR D)

### Sub-grupo AJUSTES (sidebar `fin-config`, setup)

10. `resources/js/Pages/Financeiro/ContasBancarias/Index.tsx` — **Contas Bancárias** (CRUD)
11. `resources/js/Pages/Financeiro/PlanoContas/Index.tsx` — **Plano de Contas** BR (Onda 18)
12. `resources/js/Pages/Financeiro/Categorias/Index.tsx` — **Categorias** livres (CRUD)
13. `resources/js/Pages/Financeiro/Configuracoes/Contador.tsx` — **Contador Parceiro** (Onda 31 US-FIN-037)

### Outras (não-sidebar)

- `resources/js/Pages/Financeiro/Unificado/Novo.tsx` — form de criar título (acessada via botão dentro da Unificada)
- `resources/js/Pages/Financeiro/Extrato/Index.tsx` — extrato bancário com `?contaBancariaId=` (deeplink)
- `resources/js/Pages/Financeiro/AssinaturaAtualizar.tsx` — fluxo HITL pontual
- `resources/js/Pages/Financeiro/Advisor/Dashboard.tsx` — portal contador parceiro (guard `web-advisor` isolado)
- `resources/js/Pages/Financeiro/Advisor/Login.tsx` — login advisor

## Contratos canônicos por tela

> Cada tela tem seu próprio `.charter.md` ao lado do `.tsx` com Mission/Goals/Non-Goals/UX targets/Anti-hooks completos. Este RUNBOOK lista apenas o índice + sub-grupo sidebar.

### Caixa do turno (F6 Soft, 2026-05-21)

- **Props:** `caixas: CaixaRow[]`, `stats: Stats`, `filters: { status, limit }`, `links: { pos_create, cash_register_legacy }`
- **Componentes:** AppShellV2 + 4 KPI cards + Pill segmented filter + tabela read-only
- **Ações:** ❌ nenhuma (lifecycle abrir/fechar continua na header POS via `CashRegisterController` core)
- **Filtros:** `?status=open|close`, `?limit` clamped [10, 200]
- **Multi-tenant:** `business_id` explícito em todas queries (`cash_registers` core não tem global scope Eloquent — usa DB facade direto)
- **Permission:** `view_cash_register` (mesma do core) em DUAS camadas (sidebar + Controller middleware)
- **Charter:** [resources/js/Pages/Financeiro/Caixa/Index.charter.md](../../../resources/js/Pages/Financeiro/Caixa/Index.charter.md)
- **Visual comparison:** [caixa-visual-comparison.md](caixa-visual-comparison.md) — F6 Soft sem protótipo Cowork (decisão Wagner 2026-05-21)
- **Pest GUARD:** `Modules/Financeiro/Tests/Feature/CaixaControllerTest.php` — 6 cases

## Padrões transversais (todas Pages)

- **AppShellV2** layout com breadcrumbs
- **Multi-tenant Tier 0** ADR 0093 IRREVOGÁVEL — `business_id` propagado em todas queries
- **Permissão** controllers via middleware `can:financeiro.*` exceto Caixa que reusa `view_cash_register` core
- **Inertia render** sem global state — cada tela recebe seus props
- **Read-only por padrão** — mutações sempre via POST/PUT/DELETE explícitos (não inline edit a não ser quando charter declara)

## Refs

- [SCOPE.md](../../../Modules/Financeiro/SCOPE.md) — declaração canônica do módulo + Controllers
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — comparativo de mercado + score
- [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) — buckets ✅/🟡/❌ por feature
- [SPEC.md](SPEC.md) — User Stories US-FIN-*
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART canônico
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — visual-comparison gate F1.5
