---
status: proposal
title: DS lint — eixo valor-vs-token fecha por FORMA (completo por construção); component-substitute é lista curada
proposed_by: Wagner + Claude
proposed_at: "2026-07-14"
relates_to:
  - 0209-eslint-9-flat-config
---

# PROPOSAL — regras `ds/*` de valor fecham por FORMA, não por enumeração

> **Status:** `proposal` — a implementação já entrou junto (regras `ds/no-raw-palette-color` + `ds/no-os-btn` no `eslint.config.js`, doc em `prototipo-ui/REGRAS_DS_LINT.md §1`, baseline regravado). Este doc registra a **política** pra Wagner aceitar/promover a ADR canônica (ou emendar a 0209).

## Contexto

O ratchet `ds/*` ([ADR 0209](../0209-eslint-9-flat-config.md)) pegava nativos + hex cru + radius, mas resíduos de cor (`stone-*`, `text-red-400`) e classe de shell (`os-btn`) passavam batido: **nenhuma regra os cobria**. A reação natural — "adiciona uma regra enumerando os cinzas" — é o próprio defeito: é **incompleta por construção**, sempre um leak atrás (mesma lição da âncora-guard, `memory/proibicoes.md` 2026-06-30). Wagner cravou a pergunta certa: *"sempre vai faltar isso? tem que virar máquina se sim"*.

## Decisão

Separar as regras `ds/*` em dois tipos, com tratamento diferente:

1. **Eixo valor-vs-token** (cor, radius, shadow — valor cru onde devia ser token): **fecha por FORMA.** A regra casa o *shape* do eixo, não os valores conhecidos. O palette Tailwind é **conjunto fechado** (22 nomes × 11 steps), então `no-raw-palette-color` casa `<prefixo-de-cor>-<qualquer-nome-do-palette>-<step>` e **nenhum valor cru novo passa** (`red-400`, `amber-700`, cor imprevista) — completo por construção pra o eixo. Só muda se o Tailwind inventar nome novo (raro → 1 update).
   - Fica seguro por causa do par **forma-completa + ratchet**: a regra acende em centenas de usos legados, o `lint:baseline:write` absorve toda a dívida, e só o **delta novo** quebra CI — sem flag day.

2. **Component-substitute** (`os-btn`→`<Button>`, `<select>`→`<Select>`): **lista curada e finita.** Não tem shape genérico sem pegar scaffold legítimo (`os-page-h`/`os-drawer-head` não têm substituto ainda → não entram). A lista cresce só quando um shell class ganha equivalente DS.

## Residual honesto (fica humano/charter — não vira máquina)

- **Uso semântico errado**: `text-foreground` onde era `text-muted-foreground`. Nenhum lint pega "tokenizado, mas no token errado".
- **Spacing/layout drift** e família de classe nova não-mapeada.

## Consequências

- Eixo de cor deixa de ter leak novo — o gap medido (`config/eslint-baseline.json` = 2532 violações / 497 entradas) só desce.
- `no-adhoc-status-text` (enumerado `text-rose/emerald`) foi **absorvido** por `no-raw-palette-color` e removido (evita dupla-contagem no mesmo span).
- Provado por sonda stdin: casa `stone-50`/`red-400`/`gray-200`/`hover:text-emerald-600` + `os-btn`; **não** casa `text-foreground`/`bg-card`/`border-border` (zero falso-positivo).
