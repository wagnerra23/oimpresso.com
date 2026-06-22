---
page: /financeiro/caixa
component: resources/js/Pages/Financeiro/Caixa/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-21"
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-FICHA.md
related_adrs: [93, 94, 104]
related_us: [US-FIN-CAIXA-F6-SOFT]
related_prototype: n/a (F6 Soft wrapper — sem protótipo Cowork; reusa tabela cash_registers core UltimatePOS)
related_decisions: memory/requisitos/Financeiro/caixa-visual-comparison.md (F6 Soft 2026-05-21)
tier: B
charter_version: 1
---

# Page Charter — /financeiro/caixa

> **Status:** Fase 6 deprecação legacy entregue 2026-05-21 (Wagner pergunta "onde ficou o caixa?" → Soft wrapper aprovado).
> Persona: **Larissa [L]** (dona PME vestuário ROTA LIVRE biz=4) + **Eliana [E]** (financeiro escritório). Desktop ≥1024px.
>
> **Read-only:** lifecycle abrir/fechar caixa **continua na header POS** (`/sells/pos/create` → `CashRegisterController` core). Esta tela só lista histórico de turnos pra descoberta no Financeiro.

---

## Mission (1 frase)

Permitir que Larissa @ ROTA LIVRE encontre o **histórico de fechamentos de caixa** (turnos do vendedor) navegando pelo menu Financeiro, sem precisar abrir a tela POS — diferenciando claramente **Fluxo de Caixa (mensal, business)** vs **Caixa do turno (por usuário+POS)**.

---

## Goals — Features (faz)

- 4 KPI cards: **Caixas registrados** (count all), **Caixas abertos agora** (count status=open), **Soma de fechamentos** (SUM closing_amount), **Saldo nos últimos N** (SUM credit - debit dos rows exibidos)
- Tabela read-only com colunas: `#`, Status badge (Aberto/Fechado), Operador, Loja (location_name), Abertura, Fechamento, Entradas (verde), Saídas (vermelho), Fechou em (closing_amount)
- Pill segmented control no topo: **Todos | Abertos | Fechados** (preserva via `?status=open|close` query param)
- Filtro `?limit=N` clamped em **[10, 200]** (default 50)
- Banner explicativo no topo: "Fluxo de Caixa (mensal) ≠ Caixa do turno (POS)" — anti-confusão pro usuário
- Links pro POS preservados (botão "abrir caixa via POS" no empty state + footer)
- Permission gate `view_cash_register` (mesma do CashRegisterController core) — enforce em DUAS camadas:
  1. `DataController::modifyAdminMenu` esconde sidebar entry pra user sem permission
  2. `CaixaController` middleware `can:view_cash_register` retorna 403 pra deeplink direto
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL: `business_id` explícito em todas queries SQL
- Stats agregados via 1 query separada (SUM em `cash_registers` direto, sem JOIN N+1)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ **NÃO permite abrir caixa** — botão "Abrir caixa" leva pro POS (`/pos/create`), não cria registro aqui
- ❌ **NÃO permite fechar caixa** — botão "Fechar caixa registradora" continua só na header POS (`CashRegisterController::getCloseRegister` core)
- ❌ **NÃO permite editar valores** — sem update de `closing_amount`, `closing_note`, sangria/reforço (continua via POS)
- ❌ **NÃO migra dados** — tabela `cash_registers` e `cash_register_transactions` permanecem intocadas. Backlog F6 Hard se houver demanda
- ❌ **NÃO substitui Fluxo de Caixa** — Fluxo é mensal/macro, Caixa do turno é por turno/POS. Banner UI deixa explícito
- ❌ **NÃO exporta PDF/CSV** — backlog **US-FIN-CAIXA-EXPORT**
- ❌ **NÃO mostra detalhes individuais de cada cash_register_transaction** — só agregação (SUM credit/debit) por turno. Drilldown vai pro `/cash-register/{id}` legacy se necessário (deeplink)

---

## UX targets (mensuráveis)

- **Larissa descobre Caixa pelo menu em ≤ 5s** (entrada visível em FINANCEIRO · OPERAÇÃO, order 85.45, ícone `fa-cash-register`)
- **Diferenciação Fluxo vs Caixa entendida em ≤ 30s** (banner explicativo no topo da tela)
- **Encontrar último fechamento em ≤ 10s** (tabela ordenada DESC por `created_at`, status badge visível)
- **Carregamento da tela ≤ 200ms** com 50 caixas (query indexed por `business_id` em `cash_registers`)

---

## Anti-hooks (sinais de drift)

> Quando esta tela "ganhar" funcionalidade, suspeite — fica fácil escorregar pra F6 Hard sem ADR.

- ⚠️ Aparecer **botão de mutação** (Abrir/Fechar/Editar caixa) — drift pra F6 Hard
- ⚠️ Aparecer **edit inline em `closing_amount`** — mesma coisa
- ⚠️ Aparecer **link "criar fin_titulo a partir do caixa"** — drift pra integração contábil que precisa ADR
- ⚠️ Aparecer **sangria/reforço UI** — vira US-FIN-CAIXA-SANGRIA-UI separada
- ⚠️ Quebrar contrato "POS continua sendo source of truth" — qualquer dependência onde Modules/Financeiro tem que ser consultado pra ABRIR caixa via POS é red flag

---

## Test plan (Pest GUARD)

Cobertos em `Modules/Financeiro/Tests/Feature/CaixaControllerTest.php` (6 cases):

1. ✅ `renderiza Inertia component Financeiro/Caixa/Index com shape esperado` (caixas[], stats, filters, links)
2. ✅ `bloqueia user sem permission view_cash_register (403)` — permission gate
3. ✅ `aplica filtro ?status=open na query`
4. ✅ `clamp ?limit acima de 200 vira 200`
5. ✅ `clamp ?limit abaixo de 10 vira 10`
6. ✅ `Tier 0 multi-tenant — não vaza caixa de outro business` — invariante ADR 0093

---

## Backlog (não no escopo F6 Soft)

- **F6 Hard** — full migrate: tabela `fin_caixa_turnos`, migration de dados, novo Controller, deprecar core. Aguarda sinal de cliente.
- **US-FIN-CAIXA-SANGRIA-UI** — botões "Sangria" / "Reforço" no Modules/Financeiro (hoje só via POS modal)
- **US-FIN-CAIXA-EXPORT** — PDF/CSV do histórico de turnos
- **US-FIN-CAIXA-DETAIL** — `/financeiro/caixa/{id}` page com timeline de transações do turno (drilldown próprio em vez de redirecionar pro legacy)

---

## Refs

- [memory/requisitos/Financeiro/caixa-visual-comparison.md](../../../../../memory/requisitos/Financeiro/caixa-visual-comparison.md) — visual-comparison F6 Soft
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- `app/Http/Controllers/CashRegisterController.php` — Controller core (lifecycle abrir/fechar — não tocado)
- `database/migrations/2018_01_30_181442_create_cash_registers_table.php` — schema `cash_registers`
