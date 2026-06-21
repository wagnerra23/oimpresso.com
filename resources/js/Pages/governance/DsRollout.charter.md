---
page: /governance/ds-rollout
component: resources/js/Pages/governance/DsRollout.tsx
owner: wagner
status: draft
last_validated: "2026-06-12"
parent_module: Governance
related_adrs: [189, 190, 209, 239, 240, 114]
tier: A
charter_version: 1
---

# Page Charter — /governance/ds-rollout

> **Status:** draft — aguardando aprovação visual de Wagner (gate F2 do PROTOCOL: Wagner aprova SCREENSHOT, não tabela).
> Tradução F3 (PROTOCOL §2) do protótipo Cowork `DS Rollout - Ondas e Testes.html` (handoff claude.ai/design, sessão 2026-06-12).
> Origem: Wagner perguntou "quantas ondas pra portar todo o DS? com teste pra ver se tudo foi aplicado" → o protótipo respondeu com o plano + o **Ledger de Conformidade**.

---

## Mission

Mostrar, numa tela só, o **plano medível** de portar o Design System inteiro pro padrão canônico — em ~16–18 ondas, 4 blocos (Fundação já existe → portar telas → Atendimento por último → trava) — e o **Ledger de Conformidade DS**: o placar por-tela × por-teste que prova "tudo aplicado" mecanicamente, não na palavra de ninguém. Wagner libera onda a onda olhando o verde do placar.

---

## Goals — Features (faz)

- AppShellV2 + `<PageHeader>` canon (`@/Components/PageHeader` v3.8 · ADR 0189/0190) — **não** o `shared/PageHeader` congelado.
- Banda de métrica via `<KpiGrid cols=4>` + `<KpiCard>` shared (7→1–2 fundação · 14 portar · 2 atendimento · 1 trava).
- Tabelas de onda (Bloco A/B/C) e tabela "Medição real @main" reconstruídas com `<Card>` + tabela Tailwind semântica.
- **Ledger** renderizado da prop `census` (linhas = telas, colunas = tokens-0-cru · primitivos · probe G1–G13 · dark · [W] aprovou) + barra de progresso.
- Callouts de correção pós-git (a contagem foi re-baselinada contra o repo real nesta sessão).
- 3 cards de prova (antes/depois · probe automático · diff zero).
- Fidelidade visual ao protótipo **sem** bloco `<style>` OKLCH cru — todo estilo é primitiva DS + paleta semântica Tailwind + `dark:` (o protótipo usava cor crua, justo o débito que o plano combate).

---

## Non-Goals — Features (NÃO faz)

- ❌ **Não executa** nenhuma onda (tokenizar cor, portar tela) — isso é Tier 0 e passa pelo gate por-onda de Wagner (`_PROPOSTA` → ADR → [W] decide).
- ❌ **Não** computa o census ao vivo ainda — hoje `census` é snapshot estático no `DsRolloutController`. O próximo passo nomeado é `scripts/ds-ledger.mjs` rodar `ds-report.mjs` + `conformance-gate` por Page.
- ❌ Não edita `tokens`/`foundations.css`/`components` (decisão de fundação, PR revisado).
- ❌ Sem WebSocket/real-time — refresh manual.

---

## UX Targets

- p95 first-paint < 500ms (render estático, zero I/O no controller).
- 0 erros JS console.
- Paleta semântica: primary roxo (accent) · emerald (pos/probe) · amber (atenção/referência) · rose (cor crua/gap).
- Dark mode coeso via `dark:` (herda AppShellV2) — sem override OKLCH per-tela.

---

## UX Anti-patterns

- ❌ Bloco `<style>` com OKLCH cru / cor fora de token (o protótipo Cowork tinha — aqui é proibido; barraria `conformance-gate`).
- ❌ Importar `@/Components/shared/PageHeader` (congelado · `pageheader-gate` falha CI em tela nova).
- ❌ Hand-roll de header/KPI inline (canon = `<PageHeader>` + `<KpiCard>`).
- ❌ Marcar `status: live` sem aprovação visual de Wagner.

---

## Refs

- Protótipo Cowork: `DS Rollout - Ondas e Testes.html` (handoff claude.ai/design 2026-06-12).
- [ADR 0189/0190 PageHeader canon + primary roxo universal](../../../../memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md)
- [ADR 0209 ratchet](../../../../memory/decisions/0209-ratchet-cor-crua-stylelint.md) · [ADR 0239 gov DS git=SSOT] · [ADR 0240 evidência fecha task]
- Gates do "teste": `scripts/ds-report.mjs` · `scripts/conformance-gate.mjs` · `scripts/design-identity-grade.mjs` · `scripts/a11y-ratchet.mjs`
