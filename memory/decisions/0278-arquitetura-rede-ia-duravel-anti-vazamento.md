---
slug: 0278-arquitetura-rede-ia-duravel-anti-vazamento
number: 278
title: "Arquitetura durável de automação multi-IA (anti-vazamento, thread-aware, em rede)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-15"
module: governance
tags: [governanca, automacao, multi-ia, anti-vazamento, lease, durabilidade, sdd]
supersedes: []
superseded_by: []
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0276-decisao-pelo-fluxo-classes-pares-adversariais
---

> **Numerado e aceito por [W] em 2026-06-15** (ADR 0238: numerar = aceitar). A
> **versão mínima** (Fases 0+1) está implementada em **PR #2781** (D1 — `mcp_work_leases`).
> Reconciler/gate-MCP (Fases 3-4) ficam para quando o lease provar valor (sinal qualificado, ADR 0105).

# ADR 0278 — arquitetura durável de automação multi-IA (anti-vazamento, thread-aware, em rede)

> **Pedido [W] 2026-06-15:** *"a lista está sempre furando sem estrutura, precisa ser constantemente relembrada do que foi decidido. Quero um plano de automatização que sobreviva ao tempo dessa automatização, que considere as threads sempre, que viva e sobreviva em rede de várias IAs de vários funcionários e entenda o mecanismo da empresa com frentes variadas. Planeje a estrutura, pesquise profundamente."*
> **Método:** workflow `arq-rede-ia-duravel` (10 agents, ~1M tokens): 5 pesquisadores SOTA 2025-2026 + 3 inventários internos + síntese + **refutador adversarial** (ADR 0276). Esta proposta lidera pela versão **refutada** (sem os overclaims do blueprint). [W] numera se aceitar (ADR 0238).

## Os 5 requisitos (régua de sucesso, palavras do [W])
1. **NÃO-VAZA** — decisão fica registrada e é re-imposta automaticamente; nada meio-feito sem estado explícito.
2. **SOBREVIVE AO TEMPO** — persiste além da sessão; auto-mantida (anti-bit-rot).
3. **THREAD-AWARE SEMPRE** — N agentes/sessões em paralelo sem colidir/duplicar.
4. **REDE MULTI-IA / MULTI-FUNCIONÁRIO** — estado canônico compartilhado + governança.
5. **ENTENDE O MECANISMO + FRENTES VARIADAS** — cada IA pega frentes diversas em paralelo.

## Tese central (corrigida pelo refutador)
O oimpresso **NÃO precisa de Temporal/CRDT/A2A/GraphRAG/Letta** — tem ~80% do substrato SOTA já construído (MCP server [ADR 0053], git-canon [ADR 0061], brief-fetch [ADR 0091], `whats-active` [ADR 0119], knowledge-survival [ADR 0256], scorecard/gates [ADR 0275]). O gap é que a confiabilidade está em **disciplina** (o agente *lembrar* de marcar `doing`, *lembrar* de checar duplicata) onde os 5 requisitos exigem **mecanismo** (a infra re-impõe o estado decidido). Esse é o fio único de Temporal/ArgoCD/Spec-Kit: **desired-state declarado + loop que re-impõe** — usando o Postgres do MCP e o git que já existem, **zero infra paralela**.

> ⚠️ **Duas correções DURAS do refutador (verificadas no código canônico, não no worktree órfão):**
> - **O gate server-side é CONSTRUIR, não "ligar".** O `ActionGate` é HTTP middleware (`Modules/Governance/Http/Middleware/ActionGate.php`) wired a **zero rotas**; as mutações que precisam ser barradas são **tools MCP** (`Laravel\Mcp\Server\Tool`) que **não passam por route middleware**. "warn→strict + plugar" é falso — exige construir um gate na camada MCP. (`ENFORCEMENT.md "1/8"` citado pelo blueprint **não existe** — número inventado.)
> - **O SPOF real é o watcher per-máquina** que alimenta `mcp_cc_sessions`/`mcp_cc_messages`. `whats-active`, o lease e o brief §8 só enxergam quem roda esse watcher → **Cursor e IA-de-outro-funcionário ficam invisíveis, e o sistema não sabe que está cego.** Falha silenciosa que se disfarça de "tudo livre".

## RECOMENDAÇÃO — comece pela versão mínima ("se só der pra fazer uma coisa")
Um único PR coerente que satisfaz os 5 requisitos no mínimo, **sem** o que ainda não existe:
1. **`mcp_work_leases` só com `UNIQUE(task_id) WHERE active` + TTL (30min) + heartbeat** + tools `tasks-claim`/`tasks-heartbeat`/`whats-locked`. (SEM lock-por-path — ver "o que cortar".) → R3 anti-colisão real (caso M1 batido), R4 estado canônico de quem-faz-o-quê.
2. **Carimbo `agent_id + human_principal` em TODA mutação de `mcp_tasks`** (claim/comment/status), reusando `mcp_audit_log`. → R4 governança auditável, atravessa qualquer IA que use as tools MCP (não depende de `.claude/`).
3. **Wire `whats-active`/`session-start-check` no `settings.json` SessionStart** (hoje grep=0 — a defesa existe e está **desconectada**) **+ check RUIDOSO "estou sendo ingerido em `mcp_cc_sessions`?"** que falha alto se o watcher não roda. → R3 + fecha o SPOF (Furo 2).

Por que o mínimo basta: **R1** (trabalho-em-voo deixa de ser invisível — vive no Postgres, não no contexto), **R2** (TTL+heartbeat se auto-limpa, sem cron), **R3** (compare-and-set real), **R4** (estado canônico + carimbo de ator), **R5** (`triage`+`my-work`+`whats-active` já dão descoberta; o claim dá execução sem corrida; o domínio já cabe no brief-fetch ~3k tokens).

## Roadmap faseado (custos CORRIGIDOS — sem a mentira de "ligar")
| Fase | O quê | Reusa | Constrói | Req |
|---|---|---|---|---|
| **0 (hoje, ~1 PR)** | wire `whats-active` no SessionStart + check "estou-ingerido?" + guard `block-empty-index-commit` | hook infra, `whats-active` | wiring + 1 hook | R3 |
| **0b (hoje, ~1 PR)** | **`gate-selftest` → required** (prova que TODA catraca morde; sem ele tudo é teatro) | gate-selftest GT-G6 | flip required | R2 |
| **1 (mínimo acima)** | `mcp_work_leases` `UNIQUE(task_id)` + claim/heartbeat + carimbo de ator | MCP server, brief, audit_log | 1 tabela + 3 tools | R3,R4 |
| **2** | estado explícito: `acceptance_ref`/`blocked_by` em `mcp_tasks` (NÃO `unblock_predicate` livre) | TasksUpdateTool, module-completeness-audit | colunas + validação | R1 |
| **3** | **gate na camada MCP** (CONSTRUIR — não é flip): barra `tasks-update→done` sem `acceptance_ref`, claim sem lease, backfill-canon sem entry no ledger G5. Atravessa Cursor/IA-externa | ActorResolver, audit_log, ledger G5 | **gate MCP novo** | R1,R4 |
| **4** | **reconciler** `governance:reconciler` (CONSTRUIR — são 5 detectores reais, NÃO "promover sdd-avaliador"): R-A doing-órfã, R-B done-sem-DoD, R-E blocked_by-resolvido. Nasce **detect-only**→assisted | Kernel schedule, memory-health, sdd-avaliar (validador adversarial) | command + detectores | R1,R2 |
| **5** | imunológico armado: armar 7/10 métricas (harness nightly), advisory→required pelo calendário ADR 0275, `sdd-avaliar` cron quinzenal | scorecard, gates-registry | harness fix + flips | R2 |

## O que CORTAR (over-engineering pego pelo refutador)
- **`unblock_predicate` como texto livre + R-E.** Não é verificável por máquina → PARKED apodrece → volta o M9. Substituir por **`blocked_by: <task_id/PR#>`** (verificável: PR mergeou? task done?).
- **`UNIQUE(scope_path)` no lease.** Em monorepo com cross-cutting (`_DesignSystem`/PageHeader/tokens) vira contenção/deadlock OU granular-demais (volta o M1). Manter só `UNIQUE(task_id)`; overlap de path vira **alerta** (`whats-active` já faz), não bloqueio.
- **Brief v2 §9/§10** (parked-predicate + constraints 24h). §8 (leases ativos) basta; o resto incha o ativo mais precioso (~3k tokens).
- **Reconciler R-C/R-D** (fonte≠espelho) na v1 — já têm gates próprios (foundation-ratchet, knowledge-ghost); não duplicar.
- **A narrativa "Fase = ligar"** no gate MCP — renomear pra "construir gate na camada MCP" e só fazer **após** o lease provar valor (sinal qualificado, ADR 0105).

## Decisão arquitetural dura (anti-buzzword)
**MCP é o barramento canônico — NÃO adotar A2A nem agent-mesh peer-to-peer.** Peer-to-peer amplifica erro **17,2×** vs **4,4×** do orchestrator-worker centralizado (MAST/NeurIPS 2025); A2A abre agent-card spoofing sem ganho para single-org; 79% das falhas multi-agente são spec-drift + coordination-breakdown — **transporte não resolve isso**, e o oimpresso já tem o difícil (estado canônico + living-spec). Topologia: **orchestrator-worker sobre o MCP**; humano (Wagner) decide, IA executa (com claim+lease), IA adversária refuta (ledger G5, gerador ≠ juiz).

## Modos de falha + contenção (condensado)
- **Gate só lado-cliente** (Cursor/IA-externa sem `.claude/`) → gate na camada MCP (Fase 3, construir).
- **SPOF watcher per-máquina** → check ruidoso "estou-ingerido?" (Fase 0) — torna a cegueira **alta**, não silenciosa.
- **Lease órfão (IA crasha)** → TTL+heartbeat; reconciler R-A devolve ao pool. (Nuance: se o *watcher* morre com agente vivo, heartbeat para com trabalho rodando → o check de Fase 0 mitiga.)
- **Auto-remediação por LLM gera drift** → reconciler nasce detect-only; auto-fix só low-risk com validador determinístico no fim (`sdd-avaliar`).
- **Advisory-perene ("a suite mente")** → `gate-selftest` required (Fase 0b) + calendário ADR 0275.
- **`--no-checkout` deleção em massa** (M4) → guard `block-empty-index-commit` + 14 required + enforce_admins.
- **Vazamento multi-tenant** (pior bug, Tier 0) → `business_id` global scope [ADR 0093 IRREVOGÁVEL]; reconciler nunca cruza tenant.

## Decisões que ficam para o [W]
1. **Aprovar a versão mínima (Fases 0+0b+1) como 1º passo?** (recomendado — barato, ataca R3+R2, prova valor com sinal antes de construir reconciler/gate-MCP).
2. **TTL default do lease** (recomendo 30min, ADR 0119).
3. **Quando construir o gate-MCP (Fase 3)** — só após o lease provar valor.
4. Numerar como ADR (esta proposta) se aceita.

## Frase única
**Construa o lease mínimo e torne o watcher auto-detectável; só depois, com sinal, decida se o reconciler e o gate-MCP valem o custo de CONSTRUÍ-los.** O diagnóstico do blueprint é certo ("os mecanismos certos já existiam e foram burlados") — mas o caminho honesto é não repetir o pecado: o que falta é **construído**, não "ligado".

---
**Pointers:** scorecard adversarial `memory/sessions/2026-06-15-sdd-avaliacao-adversarial-scorecard.md` · run workflow `wf_adf328e6-b1a` (blueprint + refutação completos no transcript) · âncora viva `Modules/Jana/Mcp/Tools/WhatsActiveTool.php` (ADR 0119 Tier 1, Tier 2 lease dormente = o elo a construir).
