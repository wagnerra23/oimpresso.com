---
date: "2026-07-18"
hour: "20:00 BRT"
duration: "4h"
topic: "Forward-only dos 5 schemas advisory de memory: 5 furos de template/skill fechados + regra oportunistica documentada (sem mass-fix)"
authors: [W, C]
outcomes:
  - "PR #4518 mergeado — 3 templates + 3 skills + README corrigidos, zero arquivo legado tocado"
  - "Append-only gate passou a excluir _TEMPLATE/_INDEX/README (fix via #4523, equivalente)"
  - "Regra oportunistica de normalizacao de legado documentada em scripts/memory-schemas/README.md"
prs: [4518, 4523]
us: []
related_adrs:
  - "0341-memory-schema-charter-spec-required-emenda-0314"
  - "0130-handoff-append-only-mcp-first"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Session log 2026-07-18 — forward-only dos schemas de memory

## TL;DR

Auditei a garantia **forward-only** do `memory-schema-gate` pras 5 familias **advisory** (session/handoff/briefing/runbook/reference) com um harness AJV que **espelha o CI** (gray-matter → Ajv/2020 → ajv-formats). Achei **5 furos, todos em template/skill** (nunca no legado): session/handoff `_TEMPLATE.md` com `date:` sem aspas (YAML tipa como `Date` → falha `type:string`); `BRIEFING-TEMPLATE.md` sem frontmatter (nascia grandfathered); `cockpit-runbook` (TEMPLATE+SKILL) com `status: active` (fora do enum) + faltando `owner`/`last_validated`; skill `memory-schema-preflight` com exemplos session/handoff stale. Corrigi os templates/skills, documentei a regra oportunistica no README, e **PR #4518 mergeou** (Wagner). Zero arquivo de conteudo legado tocado — a proibicao de mass-fix (§5 lapide 2026-07-12) foi respeitada.

## Contexto

Pedido: verificar se o forward-only das 5 familias advisory (ADR 0341 deixou charter/spec required com 0 divida; as outras 6 seguem advisory com divida medida) e **fechar os furos no template/preflight, nunca no legado**. As 5 familias tem majoritariamente "invalido" = arquivo sem frontmatter (docs pre-convencao). Distincao que muda a abordagem: session/handoff = append-only (grandfathered pra sempre); reference/briefing/runbook = docs vivos (normaliza so oportunisticamente).

## O que foi feito

- **Harness AJV** no scratchpad espelhando o step de validacao do CI. 1 fixture por familia, antes→depois. Prova empirica (nao leitura de codigo).
- **Achado real que confirmou a natureza latente:** TODO session/handoff recente no repo usa `date: "..."` (agentes quotam seguindo o skill, nao o template stale). Copia literal do template ainda gerava invalido.
- **Fixes (7 arquivos, PR #4518):** session/handoff `_TEMPLATE.md` (aspas no date); `BRIEFING-TEMPLATE.md` (frontmatter obrigatorio no topo, posicao 0); `cockpit-runbook` TEMPLATE+SKILL (`status: ativo`+`owner`+`last_validated`); `memory-schema-preflight` SKILL (exemplos corrigidos + callout no-mass-fix); `scripts/memory-schemas/README.md` (secao "Normalizacao de legado — forward-only + oportunistica").
- **Obstaculo 1 — append-only gate (required)** reprovou o fix do handoff template (o gate tratava `_TEMPLATE.md` como snapshot append-only — era por isso que o furo nunca fora corrigido). Wagner autorizou o fix do gate. Sessao paralela (#4523) fez o mesmo fix e **mergeou primeiro** com a versao `grep '[0-9]{4}-'` (equivalente, mais enxuta); Wagner atualizou minha branch com a main e a resolucao ficou limpa/unica.
- **Obstaculo 2 — module-grades-gate (required):** −1pp de drift da rubrica (nao meu diff, docs-only). Resolvido com label sancionado `module-grades-allowed-regression` + justificativa.

## Decisoes cinzentas resolvidas

| Pergunta | Decisao Wagner | Justificativa |
|---|---|---|
| Handoff template esbarra no append-only gate — corrigir o gate ou tirar o handoff do PR? | Corrigir o gate (excluir _TEMPLATE/README) | Template nao e snapshot temporal; o gate irmao (schema-gate) ja exclui. Torna o forward-only de handoff aplicavel. |
| Mergear #4518? | Sim ("merge") | Todos os required verdes; Wagner mergeou apos atualizar a branch com a main. |

## Aprendizados / pegadinhas

- **js-yaml tipa `date: 2026-07-17` (sem aspas) como objeto `Date`** → AJV `type:string` falha. Template DEVE quotar a data — nao confiar no agente lembrar (o template era stale e os agentes compensavam).
- **Frontmatter atras de comentario HTML `<!-- -->` = gray-matter nao ve** (nao parseia da posicao 0). Por isso pus o frontmatter do BRIEFING no topo (posicao 0), nao atras de comentario.
- **Gate de "append-only" sem excluir `_TEMPLATE.md` CONGELA o proprio template** — a divida so nao aparecia porque ninguem editava. Espelhar o `ignore '**/_*.md'` do schema-gate resolve.
- **Sessao paralela (#4523) fez o mesmo fix** — dup-detector (advisory) pegou. Dedup resolveu-se sozinha (o cleaner `[0-9]{4}-` do #4523 virou canonico na main).

## Proximos passos (nao-bloqueante)

- [ ] As 5 familias advisory seguem com divida de LEGADO (grandfathered) — normalizacao so **oportunistica** (quando o arquivo ja for tocado por trabalho real que paga a divida). Documentado no README.
- [ ] Promocao grace→required (briefing/reference) so apos backfill oportunistico zerar o FP por familia (ADR 0314/0341 — nunca big-bang).

## Referencias

- Handoff: [2026-07-18-2354-forward-only-schema-templates.md](../handoffs/2026-07-18-2354-forward-only-schema-templates.md)
- PR [#4518](https://github.com/wagnerra23/oimpresso.com/pull/4518) (merge 635df6c4) + [#4523](https://github.com/wagnerra23/oimpresso.com/pull/4523) (gate fix)
- [ADR 0341](../decisions/0341-memory-schema-charter-spec-required-emenda-0314.md) · proibicoes §5 (lapide 2026-07-12) · proposal estrutura-canon-memoria Fase 0
