---
slug: caixa-visual-comparison
title: "Financeiro — Comparativo visual da tela Caixa do turno (F6 Soft wrapper)"
type: visual-comparison
module: Financeiro
status: approved
date: 2026-05-21
canon_reference: n/a (F6 Soft wrapper — sem protótipo Cowork; reusa cash_registers core UltimatePOS)
blade_source: resources/views/cash_register/index.blade.php (legacy UltimatePOS — só listagem básica)
inertia_target: resources/js/Pages/Financeiro/Caixa/Index.tsx
service_new: n/a (sem service novo; CaixaController query direto via DB facade)
controller_new: Modules/Financeiro/Http/Controllers/CaixaController::index()
stories: US-FIN-CAIXA-F6-SOFT
related_adrs: [0093, 0094, 0104]
---

# Comparativo visual — Financeiro · Caixa do turno (F6 Soft)

> **Tipo de tela:** lista read-only com filtros + KPIs (histórico de fechamentos de turno)
> **Persona alvo:** Larissa [L] (dona PME vestuário ROTA LIVRE biz=4) + Eliana [E] (financeiro escritório). Desktop ≥1024px.
> **Refs:**
> - Blade legacy: `resources/views/cash_register/index.blade.php` — tela core UltimatePOS, listagem simples sem KPIs nem banner explicativo
> - Canon Cockpit: ❌ **n/a** — F6 Soft é wrapper sem protótipo Cowork. Decisão Wagner 2026-05-21: "Soft (wrapper Inertia)" → não vale investir tempo desenhando Cowork pra uma tela que pode ser deprecada se F6 Hard for aprovada
> - Charter: [resources/js/Pages/Financeiro/Caixa/Index.charter.md](../../../resources/js/Pages/Financeiro/Caixa/Index.charter.md)
> - ADRs: [0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md), [0094 Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [0104 MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)

## Resumo executivo

Wagner 2026-05-21 perguntou "onde ficou o caixa?". Resposta: Cash Register UltimatePOS **nunca esteve no sidebar lateral** — acesso era só via header POS. Após F1+F2 esconder/redirecionar legacy, Caixa ficou ainda menos descobrível.

**F6 Soft (wrapper Inertia):** cria `/financeiro/caixa` Inertia bonito com **dados da tabela `cash_registers` core inalterada**. Sidebar entry visível em FINANCEIRO · OPERAÇÃO (order 85.45). Lifecycle abrir/fechar **continua na header POS** — não há mutação aqui.

**Por que Soft e não Hard:** sem demanda de cliente reportada, refator pesado (migrar `cash_registers` → `fin_caixa_turnos` + Controller + Views + POS integrations) é over-engineering. Soft é reversível com 5 arquivos.

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Hoje (Blade legacy `/cash-register`) | F6 Soft (`/financeiro/caixa`) | Decisão |
|---|---|---|---|
| Header | `<h1>Caixa registradora</h1>` simples | `<PageHeader>` Cockpit V2 com título "Caixa do turno · Histórico e fechamentos" + sub explicativa + link pra POS | F6 Soft — pattern Cockpit V2 consistente com Fluxo F3 |
| Body grid | Tabela DataTables + botão "Abrir caixa" | Banner explicativo + KPI grid 4 cols + Pill filter + Tabela read-only | F6 Soft — KPIs novos + banner anti-confusão |
| Banner explicativo | ❌ ausente | ✅ "Fluxo de Caixa (mensal) ≠ Caixa do turno (POS). Lifecycle continua na tela POS." | F6 Soft adiciona — anti-alucinação Larissa |

### 2. Conteúdo informacional

| Aspecto | Hoje | F6 Soft | Decisão |
|---|---|---|---|
| Listagem caixas | Sim, datatables com `#, abertura, fechamento, fechou em` | Mesmas colunas + `Operador, Loja, Status badge, Entradas, Saídas` | F6 Soft expande — adiciona operador e loja (gerencial PME) |
| KPI cards | ❌ ausente | 4 KPIs: total caixas registrados, abertos agora, soma de fechamentos, saldo recente | F6 Soft adiciona — visão macro |
| Filtros | ❌ ausente | Pill `Todos|Abertos|Fechados` + `?limit` clamped [10,200] | F6 Soft adiciona |
| Drilldown | `?id=N` mostra `cash_register/register_details` Blade | ❌ não implementado F6 Soft — backlog US-FIN-CAIXA-DETAIL | Backlog |

### 3. Ações disponíveis (CRUD)

| Ação | Hoje | F6 Soft | Decisão |
|---|---|---|---|
| Abrir caixa | Sim, botão "Abrir caixa" → `CashRegisterController::store` | ❌ delegado pra POS (`/pos/create`) | F6 Soft — lifecycle POS-only |
| Fechar caixa | Sim, botão "Fechar caixa" → modal `close_register_modal.blade` | ❌ delegado pra POS header | F6 Soft — lifecycle POS-only |
| Editar valores | Sim, edit `closing_amount` `closing_note` | ❌ ausente | F6 Soft — read-only |
| Sangria/Reforço | Sim via POS modal | ❌ ausente | Backlog US-FIN-CAIXA-SANGRIA-UI |

### 4. Multi-tenant Tier 0

| Aspecto | Hoje | F6 Soft | Decisão |
|---|---|---|---|
| Filter `business_id` | Sim, hardcode `WHERE business_id = session('user.business_id')` | Sim — `DB::table('cash_registers')->where('cr.business_id', $businessId)` em todas queries | ✅ ADR 0093 IRREVOGÁVEL respeitado |
| Pest test cross-business | ❌ ausente | ✅ case "Tier 0 multi-tenant — não vaza caixa de outro business" | F6 Soft adiciona GUARD |

### 5. Permissões

| Permissão | Hoje | F6 Soft | Decisão |
|---|---|---|---|
| Gate view | `view_cash_register` core | **Mesma** `view_cash_register` — reusa permission core | ✅ não cria duplicação |
| Gate sidebar | n/a (sem entrada sidebar) | `DataController::modifyAdminMenu` esconde entry se `! can('view_cash_register')` | F6 Soft adiciona — defesa em camadas |

### 6. Banner explicativo (anti-confusão)

| Aspecto | Hoje | F6 Soft | Decisão |
|---|---|---|---|
| Diferença Fluxo vs Caixa | ❌ ausente — user confunde | ✅ banner azul no topo explicando os 2 conceitos + links | F6 Soft — anti-alucinação UI |

### 7. Acessibilidade & Mobile

| Aspecto | Hoje | F6 Soft | Decisão |
|---|---|---|---|
| Layout responsivo | Parcial (Bootstrap default) | `max-w-7xl mx-auto`, grid responsivo `md:grid-cols-4` | F6 Soft melhora em desktop ≥1024px |
| Mobile | Tabela datatables com scroll | Tabela com overflow horizontal | Equivalente |

### 8. Reversibilidade

| Aspecto | F6 Soft | F6 Hard (backlog) |
|---|---|---|
| Migração de dados | ❌ não há | ✅ `cash_registers` → `fin_caixa_turnos` |
| Rollback | Deletar 5 arquivos | Migration reversa + restaurar Controller core |
| Tempo de implementação | ~2h | 3-4 PRs, múltiplas sessões |
| Quando vale fazer Hard | Backlog — aguarda sinal cliente | — |

---

## Decisões aprovadas (Wagner 2026-05-21)

1. **Q1 — Soft vs Hard:** Soft (wrapper Inertia) ✅ **aprovado**. Hard fica backlog até sinal de cliente real.
2. **Q2 — Read-only ou mutação?** Read-only ✅ — lifecycle continua POS-only
3. **Q3 — Sidebar grupo:** FINANCEIRO · OPERAÇÃO (order 85.45) ✅ — depois de Cobrança, antes de ANÁLISE
4. **Q4 — Permission gate:** `view_cash_register` (mesma do core) ✅ — não duplica permission
5. **Q5 — Banner explicativo:** Sim ✅ — anti-confusão entre Fluxo de Caixa (mensal) vs Caixa do turno (POS)

---

## Refs

- `Modules/Financeiro/Http/Controllers/CaixaController.php` — Controller wrapper
- `resources/js/Pages/Financeiro/Caixa/Index.tsx` — Page Inertia
- `resources/js/Pages/Financeiro/Caixa/Index.charter.md` — Charter F6
- `app/Http/Controllers/CashRegisterController.php` — Controller core inalterado
- `database/migrations/2018_01_30_181442_create_cash_registers_table.php` — schema fonte
