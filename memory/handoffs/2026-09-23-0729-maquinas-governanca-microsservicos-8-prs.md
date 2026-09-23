---
date: 2026-09-23
time: "07:29"
slug: maquinas-governanca-microsservicos-8-prs
tldr: "Análise do fluxo de máquinas de governança como microsserviços → 8 PRs mergeados (hook-bites por oportunidade, inventário lê PHP, hook compara conteúdo inteiro, resumo de steps falhos, 2 órfãos apagados, 2 recs no ledger)."
decided_by: "[W]"
cycle: "n/d (MCP indisponível)"
prs: [7729, 7730, 7733, 7734, 7735, 7737, 7738, 7740]
next_steps:
  - "deadlink-baseline tem dois escritores — decidir dono único"
  - "hook do inventário não dispara em commit só de documento (gap declarado)"
  - "baseline-folga usa git add -u (pode estagiar arquivo alheio) — medir antes de mexer"
  - "risco de design-smoke-ci declarado, não consertado"
related_adrs: ["0256", "0344", "0224"]
---

# Máquinas de governança vistas como microsserviços — 8 PRs

## O que foi feito (todos mergeados em `main`)

| PR | O quê |
|---|---|
| [#7729](https://github.com/wagnerra23/oimpresso.com/pull/7729) | `hook-bites`: zero-entrega só vale acima de um piso de **oportunidade** (50 `tool_use` somados entre sessões); abaixo disso é NÃO MEDIDO. Heartbeat não grava marcador com `horas <= 0`. Rec na LC-33 (3ª ocorrência no mesmo instrumento). |
| [#7730](https://github.com/wagnerra23/oimpresso.com/pull/7730) | Aviso do `sdd-scorecard` aponta o dono (`sdd-scorecard-publish.yml`). |
| [#7733](https://github.com/wagnerra23/oimpresso.com/pull/7733) | `maquinas-inventario` reconhece leitor PHP (literal entre aspas) e exclui o próprio teste da varredura. |
| [#7734](https://github.com/wagnerra23/oimpresso.com/pull/7734) | Hook do inventário compara o conteúdo inteiro do índice (não só `--check`); saída vazia/crash não toca o índice. |
| [#7735](https://github.com/wagnerra23/oimpresso.com/pull/7735) | `ci-steps-falhos.mjs`: resumo dos steps que falharam no step summary do `governance-script-tests` (NÃO MEDIDO sem token). |
| [#7737](https://github.com/wagnerra23/oimpresso.com/pull/7737) | Apagado `scripts/design/ds-behavior.js` (órfão). |
| [#7738](https://github.com/wagnerra23/oimpresso.com/pull/7738) | Apagados `module-group-resolve.mjs` + teste + `governance/module-group.json` + schema + step. |
| [#7740](https://github.com/wagnerra23/oimpresso.com/pull/7740) | Rec na LC-08: propus quebrar o job sem ler o `if:` dos steps (item 4 recusado com medição). |

## Verificação pós-merge (em `main`)

Inventário = gerador (606) · `sec5-derive --check` ok · deadlink ok · selftest-registry sem órfãos · `revisar-fluxos` 5/0 · testes de `hook-bites`, inventário, hook e `ci-steps-falhos` verdes.

## Deixado sem conserto (declarado)

- `deadlink-baseline` com dois escritores.
- Hook do inventário não dispara em commit só de documento.
- `git add -u` do `baseline-folga`.
- Risco do `design-smoke-ci`.

## Estado MCP no momento do fechamento

MCP `oimpresso` **indisponível** (proxy 403 no túnel) — `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` **não foram consultados**. Estado lido do git (`origin/main` = `e49b761ea`) e da API do GitHub: 0 PR aberto desta sessão, nenhum check-in agendado.
