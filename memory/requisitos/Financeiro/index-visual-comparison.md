---
id: requisitos-financeiro-index-visual-comparison
tela: /financeiro/conciliacao
component: resources/js/Pages/Financeiro/Conciliacao/Index.tsx
charter: resources/js/Pages/Financeiro/Conciliacao/Index.charter.md
status: approved
related_adrs: [0093, 0104, 0107, 0236]
data: 2026-05-31
---

# Comparativo visual — Financeiro · Conciliação (Fase 1 ADR 0236)

> ADR 0107 §F1.5 — gate visual obrigatório. Esta tela **não é** uma migração
> Blade→Inertia (já nasceu Inertia na Onda 19). O comparativo aqui documenta a
> evolução **dentro do Inertia**: antes a Conciliação só via o upload OFX; a
> Fase 1 da [ADR 0236](../../decisions/0236-extrato-conciliacao-modelo-unificado.md)
> passou a unir as duas origens de extrato (OFX + API do banco) na mesma tela.

## Resumo executivo

A tela ganhou a coluna **Origem** (chip Banco / OFX) e passou a listar/conciliar
também as linhas do extrato sincronizado via API (`fin_extrato_lancamentos`),
que antes ficavam invisíveis aqui. Zero migração de dado — leitura unificada +
colunas de workflow aditivas na tabela do extrato.

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Antes (Onda 19) | Fase 1 (ADR 0236) | Decisão |
|---|---|---|---|
| Header | `<PageHeader>` "Conciliação · OFX bancário" | **Mantido** | sem mudança |
| KPI strip | 4 KPIs (pendentes/sugeridos/conciliados/ignorados) | **Mantido** — agora somam as 2 origens | evolução |
| Tabela | 6 colunas (Data/Descrição/Valor/Tipo/Status/Ações) | **7 colunas** — adiciona **Origem** entre Data e Descrição | Fase 1 adiciona |

### 2. Conteúdo informacional

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Linhas listadas | só `fin_bank_statement_lines` (OFX upload) | OFX **+** `fin_extrato_lancamentos` (API), normalizadas | Fase 1 une |
| Coluna Origem | ❌ ausente | ✅ chip `Banco` (API) / `OFX` (upload) com tooltip | Fase 1 adiciona |
| KPIs | contam só OFX | contam as 2 origens (API status NULL = pendente) | Fase 1 evolui |

### 3. Ações disponíveis (CRUD)

| Ação | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Upload OFX | Sim → `insert` linha a linha | Sim → `insertOrIgnore` idempotente (anti-race) | hardening |
| Confirmar match | Sim (só OFX) | Sim — resolve tabela por `origem` (OFX/API) | Fase 1 estende |
| Ignorar | Sim (só OFX) | Sim — idem por `origem` | Fase 1 estende |
| Migrar linha entre origens | ❌ | ❌ (Fase 2, atrás de flag) | fora de escopo |

### 4. Multi-tenant Tier 0

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Filter `business_id` | Sim em todas queries OFX | Sim — OFX **e** API, inclusive nos UPDATE de match/ignorar | ✅ ADR 0093 IRREVOGÁVEL |
| Pest cross-tenant | parcial | ✅ `match api respeita business id tier0` (2 businesses reais) | Fase 1 adiciona GUARD |

### 5. Permissões

| Permissão | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Gate | `financeiro.conciliacao.manage` | **Mesma** | sem mudança |

### 6. Cores / tokens (R1 ui:lint)

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Chip origem | n/a | tokens semânticos (`bg-accent` / `bg-transparent`) — NÃO cor crua | respeita R1 |

### 7. Performance

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Queries | 1 tabela (limit 200) | 2 tabelas (limit 200 cada) + normalização PHP | custo desprezível (volume baixo) |

### 8. Acessibilidade

| Aspecto | Antes | Fase 1 | Decisão |
|---|---|---|---|
| Chip origem | n/a | `title` (tooltip) + contraste via token + texto "Banco"/"OFX" (não só cor) | OK — não depende só de cor |

## Evidência

Validado em `staging.oimpresso.com/financeiro/conciliacao` (2026-05-31): tela
renderiza as 2 origens lado a lado (chip Banco/OFX), sem erro 500, com a
migration Fase 1 aplicada. Screenshot na sessão de origem.
