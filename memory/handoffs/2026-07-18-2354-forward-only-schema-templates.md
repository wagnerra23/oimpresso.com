---
date: "2026-07-18"
time: "23:54 BRT"
slug: "forward-only-schema-templates"
tldr: "Forward-only das 5 familias advisory de memory verificado com harness AJV; 5 furos de template/skill fechados (zero legado tocado); PR #4518 mergeado. Append-only gate agora exclui _TEMPLATE (fix via #4523)."
decided_by: [W]
prs: [4518, 4523]
next_steps:
  - "Nada pendente — trabalho landado. Normalizacao de legado segue oportunistica (README scripts/memory-schemas)."
related_adrs:
  - "0341-memory-schema-charter-spec-required-emenda-0314"
  - "0130-handoff-append-only-mcp-first"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Handoff 2026-07-18 23:54 BRT — forward-only dos schemas de memory

## TL;DR

As 5 familias **advisory** do `memory-schema-gate` (session/handoff/briefing/runbook/reference) agora nascem schema-validas do template/skill canonico — provado com harness AJV que espelha o CI. **5 furos de template/skill fechados**, **zero arquivo legado tocado** (mass-fix segue proibido). PR **#4518 mergeado** na main (635df6c4).

## Estado MCP no momento do fechamento

> MCP tools live (`mcp__oimpresso__*`) indisponiveis nesta sessao desktop — snapshot via brief do SessionStart + git/gh.

### brief (SessionStart)
```
Brief #373 · cycle sem foco ativo · HITL pending Wagner: 2 (FIN-004, runbook on-prem)
Sem incidentes/ADS escalacoes nas 24h. ADRs recentes: 0340-0343.
```

### git/gh (verificado)
```
origin/main HEAD inclui: 635df6c4 fix(governance): forward-only templates (#4518)
                          2c847a30 append-only gate exclui _TEMPLATE (#4523)
Ambos PRs MERGED. governance-gate.yml handoff grep = '[0-9]{4}-' (unico, limpo).
```

## O que aconteceu

Auditoria forward-only + fechamento de furos, so em template/skill:

| Familia | Furo | Fix |
|---|---|---|
| session · handoff | `date:` sem aspas (YAML → objeto Date) | aspas nos `_TEMPLATE.md` |
| briefing | template sem frontmatter (grandfathered) | frontmatter obrigatorio no topo |
| runbook | `status: active` + faltando owner/last_validated | `status: ativo` + owner + last_validated (cockpit-runbook) |
| reference | (ja valido via preflight skill) | — |

+ README `scripts/memory-schemas/` ganhou secao "Normalizacao de legado — forward-only + oportunistica"; skill `memory-schema-preflight` corrigida.

## Artefatos gerados

- 7 arquivos no PR #4518 (3 templates + 3 skills + README) — mergeado.
- `memory/sessions/2026-07-18-forward-only-schema-templates.md` (session log desta sessao).
- Fix do append-only gate landou via #4523 (versao `[0-9]{4}-`, equivalente ao meu).

## Persistencia

- **git:** #4518 + #4523 mergeados na main.
- **MCP:** webhook GitHub→MCP propaga os docs em ~2min.
- **BRIEFING:** N/A (nao tocou modulo de produto).

## Proximos passos pra retomar

Nada bloqueante. Se for mexer em legado de memory: normalizar **so oportunisticamente** (quando o arquivo ja for tocado por trabalho que paga a divida) — nunca codemod em lote. Ver `scripts/memory-schemas/README.md §Normalizacao de legado`.

## Licoes catalogadas

- Fixar o **template** (nao o legado) e o caminho forward-only correto — confirmado empiricamente (harness AJV).
- Gate append-only que nao exclui `_TEMPLATE.md` congela o proprio template.
- Sessao paralela fez o mesmo fix (#4523) — dup-detector advisory pegou; dedup resolveu-se sozinha.

## Pointers detalhados

- Session log: [2026-07-18-forward-only-schema-templates.md](../sessions/2026-07-18-forward-only-schema-templates.md)
- [ADR 0341](../decisions/0341-memory-schema-charter-spec-required-emenda-0314.md) · [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) · proibicoes §5 (2026-07-12)
- PRs [#4518](https://github.com/wagnerra23/oimpresso.com/pull/4518) · [#4523](https://github.com/wagnerra23/oimpresso.com/pull/4523)
