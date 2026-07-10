---
date: "2026-07-09"
topic: "PR-C do <tela>.map.json — consumir-map.mjs (Fase 4) + prototipo_sha por conteúdo (ADR 0324) + exemplo vivo Financeiro/Unificado"
authors: [C]
related_adrs: [0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma]
---

# PR-C do `<tela>.map.json` — consumo na Fase 4 + identidade por CONTEÚDO

**Contexto:** grade v3 apontou "design→código" 4/10 (régua Figma Code Connect). Os PRs de hoje ([#4020](https://github.com/wagnerra23/oimpresso.com/pull/4020) mecanismo `gerar-map.mjs` · [#4021](https://github.com/wagnerra23/oimpresso.com/pull/4021) deconflito · [#4022](https://github.com/wagnerra23/oimpresso.com/pull/4022) âncora `data-contract`) deixaram o ciclo GERAR+VERIFICAR pronto — faltava o CONSUMO (a promessa da Fase 4 "aborta se o sha mudou → regenera" e "lê só os trechos" era prosa) e a identidade do sha estava na régua errada.

## O que a investigação provou (antes de codar)

- **O STALE do `unificado.map.json` era FALSO:** blobs de `financeiro-page.jsx`/`-ops.jsx` **idênticos** entre o sha salvo (`4e3aacfc0f`) e o "atual" (`6cb6566311` — #3528 tocou o path sem mudar conteúdo). git-sha acusa re-export que não houve **e** é cego a re-export que sobrescreve o espelho antes do commit.
- A régua certa já existia no repo: `contentHash(normalize())` de `cowork-mirror-freshness.mjs` (ADR 0324 — "hashear só conteúdo persistido em arquivo, nunca 'de memória'").

## Entregue

1. **`gerar-map.mjs`** — `prototipo_sha` vira `sha256:contentHash` (fonte única, import do freshness); `--atualizar` regenera **preservando** o preenchimento humano por id (partes que saíram do gap são removidas com aviso); CLI cruza o frontmatter `prototipo:` com a âncora **computada** do charter (`ancora.mjs`, âncora nunca no olho); `shaAtualPara`/`shaIndeterminado` exportados (roteador dual-formato, back-compat git-sha sem punição retroativa). Fix de carona: selftest não rodava no Windows (replace com `/` fixo).
2. **`consumir-map.mjs`** (novo) — o consumo da Fase 4: **portão de frescor** (stale → exit 3, ABORTA e manda `--atualizar`) + **plano de leitura** (por parte, os 2 ranges; ABRIR só ação ≠ no-op/rejeitar — a sessão abre SÓ esses ranges, economia de token vira mecanismo). Selftest hermético bite/release **sem git** (bônus do contentHash) + CLI exit codes provados.
3. **`design-code-map-check.mjs`** — staleness dual-formato via `shaAtualPara`; test.mjs +4 checks: sha256 fresco libera · conteúdo mudado **sem commit** morde · commit sem mudança de conteúdo **não** morde (o falso-STALE morto).
4. **Exemplo vivo:** `memory/requisitos/Financeiro/unificado.map.json` regenerado — 12 partes (ids agora = `slug(parte)` canônico da tabela do gap; 7 âncoras portadas dos ids fora de convenção do map à mão de 01/jul — legítimo: blobs idênticos, ranges válidos), sha `sha256:3c66ba0f55fe`, 0 TODO. Checker `--strict` exit 0; `consumir-map Financeiro/Unificado` emite 6 ABRIR / 6 ocultas.
5. **Wiring:** RUNBOOK Fase 1/4 + errata do falso-STALE; `protocolo.config.mjs` (19 scripts); `design-memory-gate.yml` (+consumir-map selftest, advisory).

## Residual honesto

- Backfill dos 14 gap.md restantes segue pendente (próxima onda, já anotada no RUNBOOK).
- Lado vivo continua linha-only (0/11 `data-contract`) — ancorar de graça quando cada região for tocada (PR-B), nunca em sweep.
- `consumir-map` confia no map; quem valida âncora/schema segue sendo o checker (1 papel por script).
