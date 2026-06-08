---
slug: 0184-errata-0183-nao-deprecar-cash-register-rotas
number: 184
title: "Errata ADR 0183 — NÃO deprecar rotas `/cash-register/*` UPOS core (descoberta pós-investigação)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: financeiro
quarter: 2026-Q2
tags: [errata, financeiro, cash-register, ponte-caixa, ADR-0183-amends, descoberta-investigacao]
supersedes: []
supersedes_partially: []
amends:
  - "0183-caixa-fisico-bridge-financeiro-canon"
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0183-caixa-fisico-bridge-financeiro-canon"
pii: false
review_triggers:
  - "UPOS atualizar header POS removendo modal `register-details` → reabrir e considerar redirect 301 pra `/financeiro/caixa`"
  - "Algum dev tentar deprecar `/cash-register/*` (achando que é legacy órfã) sem ler esta errata → bloquear PR"
  - "ADR 0183 PR D ficar pendente >30d → consolidar nesta errata e fechar tracking"
---

# ADR 0184 — Errata 0183: NÃO deprecar `/cash-register/*` UPOS core

## Contexto

Após completar ADR 0183 (3 PRs A/B/C — ponte cash_registers → fin_titulos),
Wagner 2026-05-21 perguntou: *"faltou deprecar o outro?"*.

Investigação subsequente das 3 rotas `/cash-register/*` (em `routes/web.php`
linhas 490-492) revelou que **NENHUMA é tela legacy órfã** — todas são
ativamente integradas ao fluxo POS pelo Larissa:

| Rota | Uso real | Decisão |
|---|---|---|
| `GET /cash-register/register-details` | **Modal AJAX** carregado no `header-pos.blade.php` quando Larissa clica "Detalhes do Caixa" | **MANTER** — redirect 301 quebraria o modal (carregaria página inteira dentro do overlay) |
| `GET /cash-register/close-register/{id?}` | View do form de fechar caixa (renderizado em modal POS) | **MANTER** — flow POS depende |
| `POST /cash-register/close-register` | **Endpoint que dispara o Observer ADR 0183** | **MANTER** — sem ele a ponte não funciona |

Sidebar admin: ZERO referência standalone a `/cash-register` — não há entry
de menu legacy órfão. Tudo dentro do POS UI.

## Decisão

**NÃO deprecar nenhuma das 3 rotas `/cash-register/*`.** Sistema correto
reusa core UPOS pra disparar Observer da ponte ADR 0183.

Em vez de deprecação, adicionar **link no footer do modal**
`register_details.blade.php` apontando pra `/financeiro/caixa` (view canon).
Larissa abre modal rápido pelo POS **E** pode pular pra view canônica
completa quando precisar de auditoria + integração financeira.

## Justificativa

**Por que NÃO deprecar (apesar do ADR 0183 sugerir possibilidade):**

- **Modal AJAX dentro do POS** — Redirect 301 carregaria `/financeiro/caixa`
  inteira dentro do `.modal-dialog`, quebrando UX
- **Endpoint POST é o gatilho do Observer** — sem `postCloseRegister` a ponte
  não dispara, Observer fica órfão
- **Sem link standalone no sidebar** — não há tela legacy órfã pra
  deprecar; tudo é feature do POS workflow
- **Risco de regressão** > **valor de "limpeza"** — quebrar fluxo POS prejudica
  Larissa imediatamente; manter convivência custa apenas 3 linhas em web.php

**Por que adicionar link no modal:**

- Larissa descobre `/financeiro/caixa` (view canon com status ✅/⚠️ +
  drill-down `fin_titulo`) sem precisar memorizar URL
- Botão verde hue 145 (financas) coerente visualmente com sidebar v3
- Zero risco — adição não-disruptiva ao modal existente

## Consequências

**Positivas:**

- Sistema POS continua funcionando exatamente como antes (zero regressão)
- Ponte ADR 0183 segue ativa (Observer no `postCloseRegister`)
- Larissa ganha entry-point pro Financeiro a partir do modal POS
- Próximos devs leem esta errata e NÃO tentam deprecar (review_triggers
  declarado)

**Negativas / Trade-offs:**

- Existência de "2 telas pra mesma coisa" (modal AJAX rápido vs view canon
  completa) — mitigado pelo link de transição
- Manutenção dupla de UI (modal Blade legacy + Page Inertia canon) — aceitável
  até deprecação total do POS Blade futura

## Estado consolidado pós-ADR 0183 + 0184

```
┌─────────────────────────────────────────────────────────────────┐
│ CAMADA OPERACIONAL (POS, Blade legacy — mantida intacta)        │
│                                                                  │
│ /sells/pos/create (POS Larissa vende)                            │
│   ↓ click "Detalhes do Caixa"                                    │
│ Modal AJAX: GET /cash-register/register-details                  │
│   • Mostra caixa atual aberto                                    │
│   • Botão "Ver no Financeiro" → /financeiro/caixa (canon)        │
│   • Botão "Imprimir" (workflow legacy)                           │
│   • Botão "Fechar Caixa" → modal close-register                  │
│     ↓ submit form                                                │
│     POST /cash-register/close-register                           │
│       ↓ Eloquent updated event                                   │
│       CashRegisterObserver detect status='close'                 │
│         ↓ dispatch CashRegisterClosed                            │
│         OnCashRegisterClosedCreateFinanceiroTitulo Listener      │
│           ↓ TituloAutoService::sincronizarDeCashRegister         │
│           fin_titulo CRIADO origem='caixa'                       │
└─────────────────────────────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────────────────────┐
│ CAMADA FINANCEIRA (canon ADR 0183)                              │
│                                                                  │
│ /financeiro/caixa (canon Inertia React)                          │
│   • Histórico completo multi-caixa                               │
│   • Status integração ✅ Lançado / ⚠️ Pendente / —              │
│   • Botão "Lançar agora" pra backfill manual                     │
│   • Link drill-down → /financeiro/unificado?titulo=X             │
│                                                                  │
│ /financeiro/unificado · /financeiro/fluxo · /financeiro/conciliacao  │
│   • fin_titulos origem='caixa' visíveis junto receivable/payable │
│   • Metadata JSON com user_name + location_name + breakdown      │
│   • Eliana concilia depósito OFX vs título fechamento caixa      │
└─────────────────────────────────────────────────────────────────┘
```

## Implementação

1. Link adicionado em `resources/views/cash_register/register_details.blade.php`
   no `modal-footer`: `<a href="/financeiro/caixa">` com hue 145 verde fin
   coerente com sidebar v3 + tooltip "Histórico completo + status integração"

2. Esta ADR documenta a decisão de NÃO deprecar — protege contra futuro dev
   tentar limpeza prematura.

## Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

Link no modal aponta pra `/financeiro/caixa` que já tem auth + scope
`business_id` via `session('user.business_id')`. Sem cross-tenant write.

## Refs

- [ADR 0183](0183-caixa-fisico-bridge-financeiro-canon.md) — ponte canon
  (esta errata amends)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0
- PRs #1373 + #1374 (bugs SQL root cause) + #1375 (ADR 0183) + #1376/#1377/#1378 (PRs A/B/C ponte)
- Wagner discovery 2026-05-21 ("faltou deprecar o outro?")
