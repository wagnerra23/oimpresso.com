---
page: /financeiro/prova-viva
component: resources/js/Pages/Financeiro/ProvaViva.tsx
owner: wagner
status: live
last_validated: "2026-06-07"
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-FICHA.md
related_adrs: [0253-primitivos-layout, 0013-constituicao-ui-v2-camadas, 0093-multi-tenant-isolation-tier-0]
related_us: []
related_prototype: design-handoff "Financeiro - Prova Viva (primitivos).html" (Cowork chat46, 2026-06-07)
related_decisions: memory/decisions/0253-primitivos-layout.md (critério de pronto = tela 100% primitivos)
tier: A
charter_version: 1
---

# Page Charter — /financeiro/prova-viva

> **Status:** prova viva (pilot) — fecha o **critério de pronto** da [ADR 0253](../../../../memory/decisions/0253-primitivos-layout.md):
> "≥1 tela piloto composta 100% por primitivos (zero `flex` solto, zero `.css`)".
> **Dados são MOCK** no próprio `.tsx` — é prova de LAYOUT, não a Visão Unificada de produção.
> Persona de referência: **Eliana [E]** (financeiro) + **Larissa** (balcão 1280px).

---

## Mission (1 frase)

Provar, numa tela real do app (dentro do `AppShellV2`), que a tela Financeiro densa —
hero de saldo, KPIs A receber/A pagar com ageing, ledger unificado por data e drawer de
domínio (conciliação/fiscal/cobrança) — se compõe **100% pelos primitivos de layout**
(`Box · Stack · Inline · Grid · Container · Text`), só com tokens do `@theme`, sem `.css`
de tela nem `<div className="flex">` solto.

---

## Goals (PRECISA TER)

- Tela 100% primitivos: todo arranjo via `Box/Stack/Inline/Grid/Container/Text` (ADR 0253).
- Identidade roxa marcante (hero `oklch(0.55 0.15 295)`), densidade ERP, frescor — fiel à
  prova viva aprovada no loop Cowork (chat46 2026-06-07).
- 3 camadas de hierarquia: Hero (Tier 1) → A receber / A pagar (Tier 2) → Realizado (Tier 3).
- Ledger denso agrupado por data, acento de urgência (atrasado/vencendo), tag de categoria.
- Drawer lateral 452px (clique na linha): FSM stepper + Conciliação + Fiscal + Cobrança + Detalhe.
- Tokens reais (`card/muted/success/warning/destructive/primary/page-cream`) — claro+escuro de fábrica.

## Non-Goals (NÃO faz)

- ❌ **Substituir a Visão Unificada** (`Financeiro/Unificado/Index`) — ela é a landing de produção.
- ❌ Ligar conciliação/fiscal/cobrança a dado real — é casca de domínio (mock). Não dizer "está feito".
- ❌ Consultar DB / dado de tenant — read-only sem query de negócio (Tier 0 trivial por construção).
- ❌ `rounded-xl+`, `font-bold` em h1, cor crua `bg-(gray|red)-N`, inglês em UI cliente-facing.
- ❌ ⌘K funcional, seleção/bulk, filtros server-side — fora do escopo da prova de layout.

## UX Targets

- Cabe em 1280px sem scroll horizontal (Larissa) — grade reflua (`sm:`/`xl:` cols).
- h1 26px · KPI hero na type-scale token (`4xl`) · escala warm semântica, zero cor crua.
- 0 erros JS no console.

## Adaptações vs. protótipo HTML (honram o contrato real dos primitivos)

- `rounded="lg"` no lugar de `rounded-xl` (proibição DS: raio ≤ lg em operacional).
- Tamanhos de número na type-scale (`4xl/3xl/2xl/base/sm`) no lugar de `text-[44px]` solto.
- Canvas = token real `bg-page-cream` (não o `--page` lavanda inventado no mock).
- `family="mono"` cai no mono do sistema até o token `--font-mono` existir (Tier 0 de [W]).

## Refs

- [ADR 0253 — Primitivos de layout](../../../../memory/decisions/0253-primitivos-layout.md)
- [ADR UI-0013 — Constituição UI v2](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- [ADR 0093 — Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- Design-handoff Cowork "Financeiro - Prova Viva (primitivos).html" (chat46, 2026-06-07)
