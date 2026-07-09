---
slug: 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
number: 330
title: "Mapa dos níveis — estado real 2026-07 das 7 camadas da Constituição v2 (emenda de status à 0094)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-09"
module: governance
quarter: 2026-Q3
tags: [governanca, constituicao, camadas, niveis, mapa, status]
supersedes: []
superseded_by: []
related: [0094-constituicao-v2-7-camadas-8-principios, 0225-skills-tier-a-recalibracao-claude-4.8, 0257-adr-status-lifecycle-kind-modelo-canonico, 0264-governanca-executavel-trio-dominio-e2e, 0329-doutrina-documentacao-de-processo-executavel]
pii: false
review_triggers: []
---

# ADR 0330 — Mapa dos níveis: estado real 2026-07 das 7 camadas (emenda de status à 0094)

> **Status:** `proposto` (2026-07-09, redação [CC]). Aguarda ratificação de Wagner. **Não altera nenhuma decisão da [0094]** (append-only respeitado — mesma mecânica da 0327): atualiza só o **retrato de status** das camadas, congelado em maio/2026, que fazia o mapa oficial mentir por omissão. Origem: Wagner 2026-07-09 — *"não tem níveis? … quero me achar melhor"* — + auditoria dos guias centrais (146 claims verificadas, 27 podres).

## Contexto

A [0094] definiu as 7 camadas com um diagrama de status ("L3 ⚠️ 19 skills", "L5 ⚠️ 92→30 ADRs", "L6 🔲 S4 futuro"). Como ADR aceito é **append-only**, ninguém podia atualizar o diagrama — e ninguém criou a sucessora. Resultado: o sistema evoluiu por baixo (charters viraram LIVE, skills quadruplicaram, gates required nasceram) enquanto o mapa oficial seguia dizendo outra coisa. Quem lê a 0094 hoje conclui que "não tem níveis funcionando" — quando o que não tem é **retrato atualizado**.

## Decisão

O estado real das 7 camadas em **2026-07-09** (cada número verificado no repo na data — comandos na §Verificação):

| Camada | 0094 dizia (2026-05) | Estado REAL 2026-07 |
|---|---|---|
| **L7 Daily Brief** | ✅ PROD | ✅ **PROD** — `brief-fetch` vivo (v2, ADR 0226, 1M-aware), 6×/dia, hook SessionStart injeta |
| **L6 Charters** | 🔲 S4 (futuro) | ✅ **LIVE** — **146 charters** ao lado das telas + **36 `.casos.md`** + `casos-gate` **required** ([0264]) + IT2 charter↔`.tsx` no integrity-check |
| **L5 ADRs canon** | ⚠️ "92→30" (meta de poda) | ⚠️ **332 arquivos / 310 ativos** — a poda pra 30 nunca aconteceu (decidir: aceitar volume ou podar). Defesas novas: índice **GERADO** ([0258]), Check L "proposto-vs-realizado" (required via memory-health), sentinela `adr-proposto-parado` (scan 2026-07-09: **7 decididos invisíveis em proposals/ · 3 numerados presos · 30 propostos >14d**) |
| **L4 Playbooks** | 🔲 S6 (futuro) | 🔸 **PARCIAL** — RUNBOOKs existem aos montes em `memory/requisitos/**`, mas o contrato `last_tested` da 0094 tem **zero adoção** em 14 meses (contrato morto; decidir: adotar ou revogar o campo) |
| **L3 Skills** | ⚠️ 19 skills | ✅ **73 skills** — tiers recalibrados pela [0225] (5 Tier A núcleo + auto-trigger B). Dívida: tier declarado em **4 fontes que divergem** (frontmatter/CLAUDE.md/banner/audit) — conserto = gerador fonte-única (US-GOV-052/P32) |
| **L2 ADS Universal** | 🔲 S5 (`decide()`) | 🔸 **DORMENTE** — módulo ADS existe e opera (Brain B), mas o firewall `decide(domain,intent,payload)` prometido não é o gate universal; skill `ads-route` segue dormente |
| **L1 MCP Core** | ✅ PROD | ✅ **PROD** — tools + memória (docs na casa dos milhares — contagem viva em `/copiloto/admin/memoria`) + audit + tokens de time; team MCP operando |

**Camada transversal que a 0094 não previa e hoje É a espinha do enforcement** (nasceu depois, ADRs 0264/0271/0314/0327): **89 workflows CI**, **24 required checks** (política "required = só Tier-0" da 0314 + exceções conscientes tipo 0327), `gate-selftest` required ("quem vigia os vigias": toda catraca prova bite/release contra fixtures), **~55 hooks** PreToolUse (43 `.ps1` + 12 `.mjs` — dívida cross-plataforma, US-GOV-052/P24) e sentinelas de staleness (briefing-code, visual-comparison, adr-proposto-parado, knowledge-drift ghost+tombstone).

**Regra de manutenção deste mapa:** este ADR é um **retrato datado**. Quando o drift material acumular, cria-se a sucessora (`0330 → superseded_by`), nunca se edita este. Quem detecta o drift não é memória: são as sentinelas acima + a auditoria de guias (repetível).

## Justificativa

- A pergunta do dono ("não tem níveis?") tinha resposta errada por **omissão de retrato**, não por ausência de arquitetura. Um mapa datado e verificado devolve a orientação ("me achar melhor") sem criar guia paralelo — é **emenda de status**, padrão já usado na 0327.
- Cada número foi **verificado no repo na data** (doutrina [0329], Propriedade 5): nada aqui é estimativa.

## Consequências

**Positivas:** ponto único de orientação sobre "em que pé está cada camada"; CLAUDE.md aponta pra cá; as 3 decisões pendentes ficam explícitas (poda ou aceite do volume de ADRs · contrato `last_tested` adotar/revogar · gerador de tier de skill).
**Negativas / trade-off:** retrato datado envelhece — mitigado pela regra de sucessora + sentinelas que acusam drift.
**Riscos mitigados:** decisões tomadas com base no diagrama 2026-05 (ex.: "charters são futuro" quando são LIVE required).

## Verificação (comandos usados em 2026-07-09)

`ls .claude/skills | wc -l` → 73 · `ls memory/decisions/*.md | grep -cE '/[0-9]{4}-'` → 332 (`_INDEX-GENERATED.md`: 310 ativos) · `find resources/js/Pages -name '*.charter.md' | wc -l` → 146 · `*.casos.md` → 36 · hooks: 43 `.ps1` + 12 `.mjs` · workflows: 89 · required: 24 ([0327]) · scan `adr-proposto-parado` → A:7/B:3/C:30.

## Referências

[0094] Constituição v2 (mãe — decisões e princípios INALTERADOS) · [0225] recalibração de skills · [0264] governança executável · [0258] índice gerado · [0314] poda de gates (proposto — required=só-Tier-0) · [0327] padrão emenda-de-status · [0329] doutrina de documentação · auditoria dos guias centrais 2026-07-09 (146 claims; PRs #4015-#4018 corrigiram as 27 podres).
