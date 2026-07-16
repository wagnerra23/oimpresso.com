---
slug: 0338-ds-lint-eixo-valor-token-fecha-por-forma
number: 338
title: "DS lint — eixo valor-vs-token fecha por FORMA (completo por construção); component-substitute é lista curada"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: '2026-07-14'
module: _DesignSystem
quarter: 2026-Q3
tags: [enforcement, static-analysis, eslint, design-system, anti-drift, ratchet]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0209-eslint-9-flat-config
pii: false
review_triggers:
  - "Tailwind adiciona nome de cor novo ao palette (set fechado muda → atualizar a regra)"
  - "Nasce um novo eixo valor-vs-token (ex: sombra/opacidade) sem regra de forma"
  - "Regra ds/* nova proposta por enumeração de leaks conhecidos (sinal de recaída)"
---

# ADR 0338 — DS lint: eixo valor-vs-token fecha por FORMA

> Emenda operacional ao [ADR 0209](0209-eslint-9-flat-config.md) (ratchet ESLint 9). Não o supersede — adiciona a **política de como escrever regra `ds/*`**. Promovida do proposal `ds-lint-eixo-valor-token-fecha-por-forma` (removido nesta mesma mudança).

## Contexto

O ratchet `ds/*` (0209) pegava nativos, hex cru (`-[#`) e radius, mas resíduos de cor (`stone-*`, `text-red-400`) e classe de shell (`os-btn`) passavam batido: **nenhuma regra os cobria**. A reação natural — "adiciona uma regra enumerando os cinzas conhecidos" — é o próprio defeito: **incompleta por construção**, sempre um leak atrás (mesma classe de erro da âncora-guard, `memory/proibicoes.md` 2026-06-30: adivinhar por nome/lista é furado por design). Wagner cravou a pergunta certa: *"sempre vai faltar isso? tem que virar máquina se sim"*.

## Decisão

Regras `ds/*` classificam-se em dois tipos, com tratamento diferente:

1. **Eixo valor-vs-token** (cor, radius, shadow — valor cru onde devia ser token semântico): **fecha por FORMA.** A regra casa o *shape* do eixo, não os valores conhecidos. O palette Tailwind é **conjunto fechado** (22 nomes × 11 steps), então a regra `ds/no-raw-palette-color` casa `<prefixo-de-cor>-<qualquer-nome-do-palette>-<step>` e **nenhum valor cru novo passa** (`red-400`, `amber-700`, cor imprevista) — **completo por construção** pra aquele eixo. Só muda se o Tailwind inventar nome novo (raro → 1 update; ver `review_triggers`).

2. **Component-substitute** (`os-btn`→`<Button>`, `<select>`→`<Select>`): **lista curada e finita.** Não tem shape genérico sem pegar scaffold legítimo (`os-page-h`/`os-drawer-head` não têm substituto ainda → não entram). A lista cresce só quando um shell class ganha equivalente DS.

**Regra de recaída:** propor regra `ds/*` de valor por *enumeração* de leaks conhecidos é anti-padrão — se o eixo tem forma, fecha por forma.

## Justificativa

Fica seguro por causa do par **forma-completa + ratchet**: a regra de forma acende em centenas de usos legados, o `npm run lint:baseline:write` absorve toda a dívida (`config/eslint-baseline.json`), e só o **delta novo** quebra CI (`eslint-gate.yml`, delta>0). Completude sem flag day — a dívida atual é perdoada, o gap só desce.

Provado por sonda stdin na entrega (PR #4265): `no-raw-palette-color` casa `bg-stone-50`/`text-red-400`/`border-gray-200`/`hover:text-emerald-600`; `no-os-btn` casa `os-btn`; tokens (`text-foreground`/`bg-card`/`border-border`) **não** disparam — zero falso-positivo.

## Consequências

- Eixo de cor deixa de ter leak novo — o gap medido (baseline 2532 violações / 497 entradas na promoção) só decresce.
- `ds/no-adhoc-status-text` (enumerado `text-rose/emerald`) foi **absorvido** por `no-raw-palette-color` e removido (evita dupla-contagem no mesmo span).
- **Residual honesto (fica humano/charter, não vira máquina):** uso semântico errado (`text-foreground` onde era `text-muted-foreground`), spacing/layout drift, família de classe nova não-mapeada. Nenhum lint pega "tokenizado, mas no token errado".
- A confiança termina no set fechado do palette: se o Tailwind mudar o palette, a regra precisa de update consciente (não silencioso) — daí o `review_trigger`.

## Referências

- [ADR 0209](0209-eslint-9-flat-config.md) — ratchet ESLint 9 flat-config (base emendada)
- `prototipo-ui/REGRAS_DS_LINT.md §1` — spec das regras + seção "por que FORMA > enumeração"
- `eslint.config.js` — bloco DS guard (`ds/no-raw-palette-color`, `ds/no-os-btn`)
- `memory/proibicoes.md` (2026-06-30) — âncora-guard: adivinhar por nome/lista é incompleto por construção (mesma classe de erro)
- PR #4265 — implementação + baseline regravado
