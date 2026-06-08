---
slug: sells-edit-visual-comparison
title: "Sells/Edit — Visual Comparison F1.5 (MWART gate)"
type: visual-comparison
module: Sells
date: 2026-05-15
wave: W1-A
adr_pattern_reuse: 0149
---

# Visual Comparison — `/sells/{id}/edit` (Edit)

> **Pattern Reuse declarado:** ADR 0149. Edit é tela DERIVADA de `Sells/Create` (mesma entidade + mesmo form pattern).
> **Wagner aprovação SCREENSHOT:** referência `Create.charter.md` (status live PR #257-261).

## Blueprint Cowork base

Reuso completo do form pattern `Sells/Create.tsx`:
- Header sticky top com filter-pills (Dados / Produtos / Pagamento / Resumo / Mais opções)
- 4 KPIs gigantes
- 8 campos sempre visíveis + 10 `<details>` colapsáveis
- Footer sticky bottom com Cancelar + Salvar
- Atalho `⌘+Enter` submete

## Divergence from blueprint

| Item | Create (Cowork base) | Edit | Justificativa |
|---|---|---|---|
| Título | "Adicionar venda" | "Editar venda #{invoice_no}" | Contexto edit precisa identificar |
| useForm | defaults `status='final'` | pre-fill via `form` deferred | Edit carrega estado da venda |
| Submit | POST `/sells` | PUT `/sells/{id}` | RESTful |
| Auto-save draft | localStorage `oimpresso.sells.create.draft` | OFF | edit é mutação intencional |
| Bloqueios | nenhum | `canBeEdited`/`isReturnExist` retornam 422 | regras de negócio legacy preservadas |
| FSM | pode marcar stage final | NUNCA mexe `current_stage_id` | trait GuardsFsmTransitions enforces |

## Anti-padrões evitados

- ❌ Form vazio (precisa carregar dados antes de mostrar)
- ❌ Submit sem confirmação se já tem return → backend 422
- ❌ Mexer FSM direto

## Refs

- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [Create.charter.md](../../../../resources/js/Pages/Sells/Create.charter.md)
- Blueprint: `prototipo-ui/prototipos/vendas-cockpit/`
