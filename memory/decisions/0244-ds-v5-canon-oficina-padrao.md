---
slug: 0244-ds-v5-canon-oficina-padrao
number: 244
title: "DS v5 = Design System único ativo (v4 lápide) · Oficina = tela-padrão/semente do DS · Inbox 9.75 = régua de nota congelada · âmbar da Oficina escopado preserva o roxo canon"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-06-02"
decided_at: "2026-06-02"
module: governance
quarter: 2026-Q2
tier: CANON
trust_level: tier-0-irrevogavel
tags: [governance, design-system, ds-v5, cowork-loop, oficina, inbox-9.75, roxo-canon, accent-escopado, anti-regressao, claude-design]
related:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0190-primary-button-roxo-universal-295
  - 0235-ds-v4-accent-roxo-universal
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0243-processo-memoria-evolucao-design-cowork
related_adrs: [0094, 0107, 0114, 0190, 0235, 0239, 0241, 0243, "UI-0013"]
parent_charter: mission.constituicao-v2
supersedes: []
authors: [wagner, claude-code]
---

# ADR 0244 — DS v5 canon · Oficina = tela-padrão · Inbox 9.75 = régua congelada

> **Status:** ✅ Decidida por Wagner 2026-06-02 (*"acho que pode fazer a adr e merge"* — OK explícito pra [CL] numerar/mergear sob soberania [ADR 0238](0238-soberania-constituicao-wagner.md)). Numerada **0244** (próximo livre — o draft do Cowork chamava `_PROPOSTA-0245`, nome de rascunho; o número canônico é 0244 pra não deixar gap).
> **Escopo:** decisão de **processo/design** (qual DS é único, qual tela é semente, qual é a régua). A consolidação de tokens descrita aconteceu **dentro do protótipo Cowork** — **não** manda mudança de token no repo (ver Consequências §"Repo").

## Contexto

O loop de design do oimpresso ([ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)) acumulou **dois Design Systems no protótipo Cowork**: o v4 (`tokens.css` + `design-system.css`) e o v5 (`ds-v5/tokens.css` + `components.css`), brigando — fonte de drift e re-decisão. Faltava também separar dois papéis que a palavra "tela-padrão" misturava:

- **Gabarito de método** — a tela cuja *nota* é a régua que todas perseguem.
- **Semente do DS** — a tela cujos *padrões/componentes* graduam pro DS primeiro.

Na sessão 2026-06-02, Wagner pediu "trocar tudo sem problemas, com auditoria". O [CC] auditou os tokens do host Cowork (167 `var(--x)` em 19 CSS; gap real de 29 tokens que só o v4 provia + 20 value-shifts de polish; **o `--accent` roxo idêntico nos dois**), fundiu o v4 no v5 via camada COMPAT aditiva, trocou o `<link>` do host pro `ds-v5/tokens.css` e verificou 6 telas + console limpos. Wagner escolheu **Oficina como tela-padrão** (onde o trabalho real acontece, já com o trio charter/decisoes/casos) e **Inbox 9.75** segue como régua congelada.

Esta ADR **consolida** (não contradiz): [0114](0114-prototipo-ui-cowork-loop-formalizado.md), [0190](0190-primary-button-roxo-universal-295.md) (roxo canon `oklch(0.55 0.15 295)`), [0235](0235-ds-v4-accent-roxo-universal.md) (roxo universal), [0239](0239-governanca-design-system-git-ssot-regressao-ia.md), [0243](0243-processo-memoria-evolucao-design-cowork.md) e a Constituição UI v2 ([UI-0013](../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)).

## Decisão

1. **DS v5 = único DS ativo no protótipo Cowork.** `ds-v5/tokens.css` é a fonte única de tokens do host; o v4 (`tokens.css` / `design-system.css`) vira **lápide** (mantido só pros `.html` legados em `_arquivo/`). A fusão é aditiva (v5 absorveu os 29 tokens v4 via alias `var()`), reversível e value-preserving — **o roxo canon e o IBM Plex foram preservados**.
2. **Oficina = tela-padrão / semente do DS.** Os padrões/componentes provados na Oficina graduam pro DS primeiro (princípio "DS é piso, não teto"; padrão só sobe tela→DS *depois de graduar*, nunca o DS desce forçando a tela a regredir).
3. **Inbox 9.75 = régua de nota congelada** (gabarito de método). Permanece intocada; é a referência contra a qual as outras telas são medidas. Os dois papéis (semente ≠ régua) ficam **separados** de propósito.
4. **Âmbar da Oficina = accent escopado** (`.oficina-scope{ --accent: … }`), **nunca** troca o token global. O roxo canon [ADR 0190](0190-primary-button-roxo-universal-295.md) / [ADR 0235](0235-ds-v4-accent-roxo-universal.md) segue intacto fora do escopo.

## Consequências

**Design (Cowork):** 1 só DS de tokens vivo no host (v5). Trabalho novo nasce no v5; drift v4↔v5 acaba. Oficina dirige a evolução do DS aterrada no uso real; Inbox 9.75 protege o teto de qualidade.

**Repo (importante — escopo honesto):** o repo **NÃO** precisa da "fusão v4→v5". Os tokens do repo já são roxo + neutros quentes (`resources/css/cockpit.css .cockpit{}` + `resources/css/inertia.css @theme{}`, ambos `oklch(0.55 0.15 295)`). A migração de tokens descrita foi limpeza **interna do Cowork**. O que tocou o repo nesta onda foi só o que já estava drift: o accent inline do `AppShellV2` re-azulava o roxo (corrigido em **[PR #2119](https://github.com/wagnerra23/oimpresso.com/pull/2119)** — default 220→295) e `background:#fff`→`var(--surface)` no bundle financeiro (−30 hex). Forçar os nomes do v5 (`--sunken` etc.) no repo seria churn sem ganho — **não fazer** (L-26: o repo é UltimatePOS híbrido; o legado Blade é outro programa).

**Governança:** numeração sob soberania [W] ([ADR 0238](0238-soberania-constituicao-wagner.md)); append-only ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)).

## Alternativas consideradas

- **Manter v4 e v5 em paralelo** — rejeitada: é a fonte do drift que originou a decisão.
- **Oficina como régua única (aposentar a Inbox 9.75)** — rejeitada: baixaria a régua de 9.75→9.5 e usaria uma tela ainda em iteração (F1 build) como gabarito. Manter os papéis separados ganha os dois.
- **Trocar `--accent` global pro âmbar da Oficina** — rejeitada: regrediria o roxo canon (ADR 0190); âmbar fica escopado.

## Referências
- Loop Cowork↔Code: [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) · governança DS: [0239](0239-governanca-design-system-git-ssot-regressao-ia.md) · método de evolução: [0243](0243-processo-memoria-evolucao-design-cowork.md)
- Roxo canon: [ADR 0190](0190-primary-button-roxo-universal-295.md)
- Implementação repo desta onda: PR #2119 (accent roxo canon + reforço) · handoff `memory/handoffs/2026-06-02-1716-design-handoff-appshell-roxo-reforco.md`
