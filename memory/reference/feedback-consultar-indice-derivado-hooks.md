---
name: Consultar o índice DERIVADO de hooks antes de re-derivar com Read/Grep
description: O estado dos hooks Claude Code (quais existem, wiring do settings.json, runtime node/powershell, gates CI, órfãos/fantasmas) já vive num índice AUTO-GERADO e drift-checado — `.claude/hooks/_HOOKS-INDEX.md` (via `hooks-manifest-generate.mjs`, invariante 0 fantasmas). E "a migração .ps1→.mjs funciona?" já tem suíte pronta (31 `.test.mjs` + 2 wiring `settings-portlote*-registration.test.mjs` + `hooks-manifest-generate --check`). Quando perguntarem sobre hooks/migração, CONSULTAR/RODAR esses artefatos — NÃO re-derivar catando arquivo por arquivo com Read/Grep.
date_captured: 2026-07-20
captured_in_session: chip-produto-contexto (perguntas sobre migração de hooks .ps1→.mjs + "está indexado/testado?")
applies_to: qualquer pergunta sobre estado/teste dos hooks; por analogia, qualquer área com índice derivado + suíte (telas, gates)
severity: média
related_adr: [0256]
---

# Feedback — o índice já existe; consulte antes de re-derivar

> **Origem:** 2026-07-20. Wagner perguntou se a migração de hooks `.ps1→.mjs` estava
> feita/testada/indexada. Em vez de abrir os índices que **já existem**, saí catando
> arquivo por arquivo com Read/Grep por ~10 turnos, chamei de "gap" coisas que existiam
> (o teste de wiring), respondi em pedaços e **fiz o Wagner lembrar que a informação
> existia**. Palavras dele: *"eu tenho que ficar lembrando que existe se já foi feito,
> isso é ridiculo. gasta tokens e eu que tenho que lembrar."*

## A regra

Antes de re-derivar estado de hooks com Read/Grep, **consultar o que já é derivado +
enforçado** ([ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)
— *derivado+enforçado sobrevive; escrito+lembrado apodrece*):

| Pergunta | Onde já está (NÃO re-derivar) |
|---|---|
| Quais hooks existem, wiring, runtime, gates, órfãos/fantasmas? | `.claude/hooks/_HOOKS-INDEX.md` (auto-gerado; `node scripts/governance/hooks-manifest-generate.mjs --check` acusa drift; invariante **0 fantasmas** = nenhuma ref quebrada) |
| A migração `.ps1→.mjs` funciona? | `node --test (Get-ChildItem .claude\hooks\*.test.mjs).FullName` (31 testes) + `node scripts/governance/settings-portlote-ps1-mjs-registration.test.mjs` (lote 1) + `settings-portlote2-nudges-registration.test.mjs` (lote 2) |
| Isso roda no CI? | `governance-script-tests.yml` (cada `.test.mjs`) + `gate-selftest.yml` job `hooks-selftest` (advisory) |

## Prova (rodado 2026-07-20, tudo verde)

- Suíte unitária: `tests 31 · pass 31 · fail 0` (exit 0)
- Wiring lote 1 + lote 2: `[PASS]` — 10 hooks invocados como `node`, matcher certo, **0 `.ps1` na wiring**
- Manifesto: em sync · **0 fantasmas**

## Generalização (mesmo reflexo pra outras áreas)

- **Telas** → `node scripts/qa/screen-coverage-map.mjs --screen <Mod>/<Tela>` (trio + scorecard + e2e + RUNBOOK/proto-baseline, com flag de ambiguidade)
- **Gates CI** → `governance/required-checks-baseline.json` + `scripts/governance/gates-registry.json`

Sinal de degradação (`memory/how-trabalhar.md` §"Reconhecer degradação de sessão"):
**trabalhar via Read/Grep sem consultar o índice derivado / o brief primeiro.**
