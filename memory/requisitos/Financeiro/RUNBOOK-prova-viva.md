---
id: requisitos-financeiro-runbook-prova-viva
slug: runbook-prova-viva
title: "RUNBOOK — Tela /financeiro/prova-viva (prova viva dos primitivos de layout)"
type: runbook
status: ativo
last_validated: "2026-06-07"
owner: W
applies_to: resources/js/Pages/Financeiro/ProvaViva.tsx + Modules/Financeiro/Http/Controllers/ProvaVivaController.php
canonical_session: design-handoff Cowork "Financeiro - Prova Viva (primitivos).html" (chat46, 2026-06-07)
---

# RUNBOOK — /financeiro/prova-viva

## O que é

Tela-piloto que fecha o **critério de pronto** da [ADR 0253](../../decisions/0253-primitivos-layout.md):
a tela Financeiro densa composta **100% pelos primitivos de layout** (`Box · Stack · Inline · Grid ·
Container · Text`) — zero `flex` solto, zero `.css` de tela, só tokens do `@theme`.

⚠️ **Dados são MOCK** no próprio `.tsx` (`ROWS`). É prova de **layout**, não dado real.
**NÃO substitui** `Financeiro/Unificado/Index` (landing de produção). Conciliação/fiscal/cobrança no
drawer são casca de domínio (da rubrica 9.75), não ligadas a DB.

## Como acessar

- Rota: `GET /financeiro/prova-viva` → `ProvaVivaController@index` → `Inertia::render('Financeiro/ProvaViva')`.
- Guard: `auth` + `can:financeiro.dashboard.view` (mesmo da Fluxo/DRE).
- Não há link na sidebar (acesso direto por URL — é pilot).

## Arquitetura

| Peça | Caminho |
|---|---|
| Page | `resources/js/Pages/Financeiro/ProvaViva.tsx` (dentro do `AppShellV2`) |
| Charter | `resources/js/Pages/Financeiro/ProvaViva.charter.md` |
| Controller | `Modules/Financeiro/Http/Controllers/ProvaVivaController.php` (read-only) |
| Rota | `Modules/Financeiro/Routes/web.php` (grupo operacional) |
| Teste | `Modules/Financeiro/Tests/Feature/ProvaVivaControllerTest.php` |
| Primitivos | `resources/js/Components/layout/` (ADR 0253) |

## Multi-tenant (Tier 0 · ADR 0093)

Trivial **por construção**: o controller não faz query de negócio (nenhum `business_id`),
a tela só renderiza mock no front. Não há vazamento possível porque não há dado de tenant.

## Como validar (gates)

```bash
npm run typecheck        # 0 erro novo de ProvaViva
npm run lint             # eslint ProvaViva 0/0
npm run no-mock:check    # verde (mock de display não é flagado)
npm run foundation:check # verde
npm run conformance:check # verde
# critério ADR 0253: sem flex/grid solto nem .css de tela
grep -nE 'className="[^"]*\bflex\b' resources/js/Pages/Financeiro/ProvaViva.tsx | grep -vE 'inline-flex|flex-1|flex-col|place-items'
php artisan test Modules/Financeiro/Tests/Feature/ProvaVivaControllerTest.php
```

## Manutenção / pegadinhas

- **Primitivos são canon** (ADR 0253): qualquer arranjo novo vai por `Box/Stack/Inline/Grid/Text`, nunca `<div className="flex">`.
- **Sem token novo**: cores/raios/espaços só da escala DS v6. Hero usa gradiente via `style` (brand hero aprovado [W]) — exceção pontual, não replicar em cards.
- `family="mono"` usa mono do sistema até o token `--font-mono` (IBM Plex Mono) existir no `@theme` (decisão Tier 0 de [W]).
- Se virar tela de produção com dado real: criar Service + props (shape `kind/desc/who/cat/nf/st/amt/...`), aplicar global scope `business_id`, e migrar `ROWS` mock → payload do controller.

## Refs

- [ADR 0253 — Primitivos de layout](../../decisions/0253-primitivos-layout.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- visual-comparison: `memory/requisitos/Financeiro/prova-viva-visual-comparison.md`
