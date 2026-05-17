---
slug: 0168-protocolo-wagner-sempre-tier-A-irrevogavel
number: 0168
title: "PROTOCOLO WAGNER SEMPRE — 10 regras canon Tier A always-on (Constituição v2 emenda)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-17
module: null
quarter: 2026-Q2
tags: [governance, constituicao, skills-tier-A, wagner-protocol, automation]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0094, 0095, 0061, 0093, 0101, 0104, 0106, 0114, 0143, 0167]
pii: false
review_triggers:
  - "Wagner pergunta 'o que eu sempre solicito?' em 2+ sessões diferentes"
  - "Nova regra Rxx adicionada ao protocolo"
  - "Time MCP (Felipe/Maiara/Eliana/Luiz) reporta violação reincidente"
---

# ADR 0168 — PROTOCOLO WAGNER SEMPRE Tier A IRREVOGÁVEL

## Contexto

Wagner palavras textuais 2026-05-17, sessão `stupefied-noether-89f83d`:

> *"quero que prepare o protocolo. e sempre faça. não é justo eu sempre ficar pedindo a mesma coisa. mantenha o conhecimento agregado e automatize não me irrite. apreenda. se torne especialista. crie maneira de entender e lembra do que tem que executar. crie um agente especializado em entender."*

A sessão `stupefied-noether-89f83d` catalogou **5 incidentes** de regras esquecidas — todas itens que Wagner já reportou em sessões anteriores e que existem em algum lugar do canon (`memory/reference/feedback-*.md` + skills Tier A + proibições.md). O problema **não é falta de conhecimento canônico** — é falta de **enforcement automático** que sobrevive a `/clear`, /compact, troca de sessão, troca de dev (Felipe/Maiara/Eliana/Luiz entrando no MCP).

5 incidentes catalogados nesta sessão:

1. **Smoke real ausente** — Claude propôs checklist pós-merge pra Wagner fazer manual, em vez de abrir Brave e verificar real.
2. **Edits no path errado** — Claude editou `D:\oimpresso.com\<arquivo>` (main repo) em vez de `D:\oimpresso.com\.claude\worktrees\stupefied-noether-89f83d\<arquivo>` (worktree). ~4h de trabalho recuperados via `git stash` que Wagner salvou manualmente.
3. **Auto-mem privada** — Claude escreveu feedback canon em `~/.claude/projects/*/memory/` antes de mover pra git canon (`memory/reference/`).
4. **Cópia parcial proposta** depois de Wagner aprovar screenshot integral.
5. **Gap legacy não migrado** — Cowork rewrite #1032 não montou `SellsDateFilter`/`GroupBy`/`SellsToggleViewMode` — detectado SÓ via verificação Brave pós-merge.

Skills Tier A existentes (`brief-first`, `mcp-first`, `multi-tenant-patterns`, `commit-discipline`, `charter-first`, `mwart-process`, `mwart-comparative`, `preflight-modulo`) cobrem peças individualmente — mas não há **CONTRATO unificado** que enumera tudo que Wagner sempre solicita.

## Decisão

**Formalizar PROTOCOLO-WAGNER-SEMPRE.md como canon Tier 0 IRREVOGÁVEL**, equiparado à Constituição v2 ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)) mas focado em **comportamento de Claude por sessão**, não em arquitetura do projeto.

Triade de artefatos canônicos:

| Artefato | Path | Função |
|---|---|---|
| **PROTOCOLO** | [`memory/reference/PROTOCOLO-WAGNER-SEMPRE.md`](../reference/PROTOCOLO-WAGNER-SEMPRE.md) | Documento canônico — 10 regras (R1-R10) detalhadas, cada uma com trigger, ação, sinal de violação, doc base |
| **Skill enforcement** | [`.claude/skills/wagner-protocol-enforce/SKILL.md`](../../.claude/skills/wagner-protocol-enforce/SKILL.md) | Tier A always-on — carrega protocolo no SessionStart de toda sessão |
| **Agent decoder** | [`.claude/agents/wagner-understand.md`](../../.claude/agents/wagner-understand.md) | Subagente proativo — Claude pai spawn ANTES de executar pedido cru não-trivial. Decodifica em estrutura + cruza com protocolo + inventaria projeto + lista pegadinhas + plug-points + tasks atômicas |

As 10 regras canônicas:

| # | Regra | Trigger |
|---|---|---|
| **R1** | Smoke real obrigatório (não narração) | Após merge/deploy/declarar "funcionando" |
| **R2** | Cópia literal quando design aprovado | Wagner aprovou screenshot do prototype |
| **R3** | Workflow 3 fases (PRE-FLIGHT + DURING + POST) | Edit `Modules/<X>/` |
| **R4** | Multi-tenant Tier 0 IRREVOGÁVEL | Edit Model/Controller/Service/Job |
| **R5** | PT-BR + Economia de crédito | SEMPRE |
| **R6** | Cliente ROTA LIVRE biz=4 NUNCA em test/smoke | Pest + smoke prod |
| **R7** | Charter `live` + visual-comparison antes Edit Page | `Pages/<Mod>/<Tela>.tsx` |
| **R8** | Branch + worktree disciplina | Trabalho em worktree filha |
| **R9** | ZERO auto-mem privada | Tentativa Write `~/.claude/projects/*/memory/` |
| **R10** | Aprovação humana antes commit/push/merge | git push / `gh pr merge` |

## Justificativa

**Por que formalizar em ADR (não só feedback canon)?**

Feedback canon é catálogo histórico ("Wagner disse X em DD-MM"). ADR é **decisão arquitetural vinculante** que:

1. **Sobrevive `/clear` e troca de sessão** porque o protocolo é carregado via skill Tier A em todo SessionStart.
2. **Vincula time MCP entrando** — Felipe/Maiara/Eliana/Luiz herdam o protocolo automaticamente quando suas sessões Claude carregam a Constituição v2.
3. **CI enforcement preparável** — futuro hook `wagner-protocol-check.yml` pode validar PRs contra as 10 regras (ex: R1 exige screenshot anexo se merge em rota Page).
4. **Append-only** — ADRs canon não são editadas; emendas viram ADRs novas com `supersedes_partially`. Garante histórico.

**Por que agente `wagner-understand` separado da skill `wagner-request-refiner` Tier B existente?**

| Aspecto | `wagner-request-refiner` (skill Tier B reactive) | `wagner-understand` (agent proativo) |
|---|---|---|
| Trigger | Wagner manda 3+ items num turno | ANTES de Claude executar pedido cru não-trivial |
| Output | Lista decomposta no chat | Doc estruturado em `memory/sessions/` |
| Cruza com protocolo? | Não explícito | SIM (Fase 2 obrigatória) |
| Inventário projeto? | Leve | Profundo (Fase 3 dedicada) |
| Persistente cross-session? | Não | SIM (markdown em git) |

São **complementares** — request-refiner decompõe no chat rápido; understand faz dossiê pra execução guiada quando o pedido tem peso (>1h work) ou alta ambiguidade.

**Por que NÃO virar hook PowerShell bloqueador?**

Hooks PowerShell já cobrem 3 das 10 regras (R3 via `modulo-preflight-warning.ps1`, R7 via `block-mwart-violation.ps1`, R9 via `block-automem.ps1`). As outras 7 regras dependem de **contexto semântico** (R1 "decla rar funcionando", R2 "Wagner aprovou screenshot", R5 "PT-BR") — não dá pra bloquear via regex em hook.

A skill Tier A always-on **carrega o protocolo em memória de trabalho** de Claude no SessionStart, e Claude faz auto-check antes de cada turno terminar. Hook reforça onde dá; skill cobre o resto via processo declarativo.

## Consequências

**Positivas:**
- ✅ Wagner não repete "smoke real?", "copia literal?", "leu charter?" — automático
- ✅ Time MCP (Felipe/Maiara/Eliana/Luiz) entra alinhado com protocolo desde 1ª sessão
- ✅ Cross-session continuity — `/clear` ou nova sessão recarrega protocolo
- ✅ Auditável — Wagner pode pedir "audita meu protocolo na sessão X" e Claude lista regras seguidas/violadas
- ✅ Extensível — nova regra Rxx vira append no PROTOCOLO + skill recarrega automaticamente
- ✅ Documentação executável — não é só doc legacy esquecido em `memory/`

**Negativas / Trade-offs:**
- 🟡 +1 skill Tier A always-on (custo ~500 tokens por sessão pra carregar protocolo) — aceitável vs economia de "Wagner pedindo de novo"
- 🟡 Subagente `wagner-understand` quando ativado consome ~5k-15k tokens em research — só ativa em pedidos não-triviais (>1h work est.)
- 🟡 Manutenção: cada nova regra Rxx exige update em 3 arquivos (PROTOCOLO + skill + ADR errata) — fluxo formalizado mas não trivial

**Riscos mitigados:**
- 🛡️ Perda de conhecimento canônico em `/clear` (protocolo recarrega via Tier A)
- 🛡️ Drift entre devs do time MCP (mesmo protocolo aplica a todos)
- 🛡️ Wagner frustração reincidente ("não é justo eu sempre ficar pedindo")
- 🛡️ Onboarding de novos devs Claude Code no projeto (protocolo é entrada obrigatória)

## Referências

- ADR 0094 [Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md) — documento mãe
- ADR 0095 [Skills tiers convenção](0095-skills-tiers-convencao-interna.md)
- ADR 0061 [Conhecimento canônico git/MCP zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- ADR 0093 [Multi-tenant Tier 0](0093-multi-tenant-isolation-tier-0.md)
- ADR 0101 [Tests biz=1 nunca cliente](0101-tests-business-id-1-nunca-cliente.md)
- ADR 0104 [MWART processo canônico](0104-processo-mwart-canonico-unico-caminho.md)
- ADR 0106 [Recalibração velocidade fator 10x IA-pair](0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- ADR 0114 [Cowork loop formalizado](0114-prototipo-ui-cowork-loop-formalizado.md)
- ADR 0143 [FSM pipeline live prod biz=1](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- ADR 0167 [Errata 0130 índice handoff histórico longo](0167-errata-0130-indice-handoff-historico-longo.md)
- [PROTOCOLO-WAGNER-SEMPRE.md](../reference/PROTOCOLO-WAGNER-SEMPRE.md) — canon detalhado
- [skill wagner-protocol-enforce](../../.claude/skills/wagner-protocol-enforce/SKILL.md)
- [agent wagner-understand](../../.claude/agents/wagner-understand.md)
- Feedback canon irmãos:
  - [feedback-design-literal-copy-quando-aprovado.md](../reference/feedback-design-literal-copy-quando-aprovado.md)
  - [feedback-modulo-mexeu-registra-sempre.md](../reference/feedback-modulo-mexeu-registra-sempre.md)
  - [feedback-nunca-publicar-credenciais.md](../reference/feedback-nunca-publicar-credenciais.md)
  - [feedback-baileys-7x-decisao-irreversivel.md](../reference/feedback-baileys-7x-decisao-irreversivel.md)
