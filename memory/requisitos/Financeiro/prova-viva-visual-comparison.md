---
id: requisitos-financeiro-prova-viva-visual-comparison
slug: prova-viva-visual-comparison
title: "Financeiro — Comparativo visual da tela Prova Viva (primitivos de layout)"
type: visual-comparison
module: Financeiro
status: approved
date: 2026-06-07
canon_reference: design-handoff Cowork "Financeiro - Prova Viva (primitivos).html" (chat46, aprovado no loop de design 2026-06-07)
blade_source: n/a (greenfield — prova de layout, não existe equivalente legacy)
inertia_target: resources/js/Pages/Financeiro/ProvaViva.tsx
controller_new: Modules/Financeiro/Http/Controllers/ProvaVivaController::index()
stories: []
related_adrs: [0253, ui/0013, 0093]
---

# Comparativo visual — Financeiro · Prova Viva (primitivos)

> **Tipo de tela:** prova viva (pilot) — fecha o critério de pronto da [ADR 0253](../../decisions/0253-primitivos-layout.md):
> tela 100% composta pelos primitivos de layout (`Box · Stack · Inline · Grid · Container · Text`), zero `flex` solto, zero `.css` de tela.
> **Persona alvo:** Eliana [E] (financeiro) + Larissa (balcão 1280px).
> **Dados são MOCK** — é prova de layout, não dado real. NÃO substitui `Financeiro/Unificado/Index` (landing de produção).

## Origem & aprovação

O gabarito veio do loop Cowork (chat46, 2026-06-07): a tela Financeiro reconstruída do zero
**só com primitivos**, aprovada pelo verificador em múltiplas rodadas (identidade roxa marcante →
densidade ERP + frescor → drawer de domínio conciliação/fiscal/cobrança → hierarquia em 3 camadas).
Wagner pediu a "ponte pro [CL]" — este `.tsx` é a tradução fiel que fecha o critério-de-pronto da ADR 0253.

## Mapeamento componente × primitivo (15 dimensões)

| Bloco da tela | Composição (primitivos) |
|---|---|
| Header (breadcrumb + h1 + lentes + busca + ações) | `Inline`/`Stack` + `Text` + botões locais |
| Hero "saldo previsto" (roxo, sparkline, trend, breakdown) | `Box rounded=lg` + `Stack`/`Inline` + `Text`/`Num` (gradiente via style — brand hero aprovado [W]) |
| A receber (ageing bar embutido) | `Box bg=card border` + `Stack`/`Inline` + `Num` |
| A pagar (próximo vencimento) | `Box bg=card border` + `Stack`/`Inline` + `Num` |
| Realizado · maio (faixa fina Tier 3) | `Box bg=card border` + `Inline` + `Num` |
| Filtros densos + conta + view-mode | `Inline` (wrap) + botões/pills locais |
| Ledger denso agrupado por data | `Box bg=card border` + `Grid` (template cols) + `Stack divider` + `Text` |
| Drawer de detalhe 452px | `Stack` painel + `Inline`/`Box` + `FSM`/`Sec`/`KV` (todos primitivos) |
| Footer (totais + atalhos) | `Box bg=card border` + `Inline divider` + `Kbd` |

## Adaptações vs. mock (honram o contrato real dos primitivos / DS)

- `rounded="lg"` no lugar de `rounded-xl` (proibição DS: raio ≤ lg em operacional).
- Números na **type-scale token** (`4xl/3xl/2xl/base/sm`) no lugar de `text-[44px]` solto.
- Canvas = token real `bg-page-cream` (não o `--page` lavanda inventado no mock).
- Sem token novo (ADR 0253 regra 2). `family="mono"` cai no mono do sistema até `--font-mono` existir (Tier 0 de [W]).

## Tokens (DS v6 — não inventar)

- Cor âncora roxo `oklch(0.55 0.15 295)` (`primary`) · semânticos `success`/`warning`/`destructive` · superfícies `card`/`muted`/`page-cream`.
- Claro + escuro de fábrica (tudo via `@theme` — nenhuma cor crua).

## Critério de pronto (ADR 0253) — status

- [x] Tela 100% primitivos — `grep` sem `className="...flex"` solto nem `.css` de tela.
- [x] `tsc` sem erro novo · ESLint 0 · gates `no-mock`/`foundation`/`conformance` verdes.
- [x] Pest GUARD (render + permission gate) + charter ao lado do `.tsx`.
- [ ] `artisan route:list` / Pest — rodar no CI (worktree de implementação sem `vendor/`).

## Refs

- [ADR 0253 — Primitivos de layout](../../decisions/0253-primitivos-layout.md)
- [ADR UI-0013 — Constituição UI v2](_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- Charter: `resources/js/Pages/Financeiro/ProvaViva.charter.md`
