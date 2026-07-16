---
name: Auditoria skills atuais — pós-Constituição (v2)
description: Re-classificação 30 skills atuais após drift detectado por skill audit-constituicao em 2026-05-09. Corrige doc original parado em 2026-05-06.
type: audit
related_adr: 0094-constituicao-v2-7-camadas-8-principios
parent_adr: 0095-skills-tiers-convencao-interna
created: 2026-05-06
updated: 2026-05-09
authors: [sonnet, claude-code]
status: live
version: 2
supersedes: [v1 — 2026-05-06]
lifecycle: active
---

# Auditoria skills atuais — pós-Constituição (v2)

> ⚠️ **Reconciliação v2 (2026-07-15):** menções abaixo a *"`mwart-comparative` aguarda Wagner aprovar SCREENSHOT síncrono / é o gate visual"* refletem o loop **v1** — **superadas** pela v2 ([ADR 0241](../../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) + [ADR 0282](../../decisions/0282-protocolo-v2-colapso-ratificacao.md)): o gate visual é **CI** (visual-regression + PR UI Judge), **não** aprovação síncrona. O agente Code **é** o designer-agente com acesso completo ao design ([PROTOCOL §0.1](../../../prototipo-ui/PROTOCOL.md)). Texto v1 preservado como histórico (append-only).

> **v2 — 2026-05-09** corrige drift detectado pela skill [`audit-constituicao`](../../../.claude/skills/audit-constituicao/SKILL.md) (recém-criada, primeira execução do audit 6-dimensional). Doc original (v1, 2026-05-06) parou no momento do PR Constituição v2 e não acompanhou as 9 skills criadas depois + 3 renames + drift triplo Tier A.
>
> **Lifecycle anterior:** v1 fica preservada como `superseded` no histórico git (commit `8e7f5657` linha 1‑208). Esta v2 substitui na íntegra.
>
> **Como ler:** Bloco A (Tier A) → B (Tier B) → C (Tier C) → D (descriptions a corrigir) → E (skills novas/renomeadas no período) → **F (lições aprendidas — NOVO)**.
>
> **Aprovação:** Wagner revisa por bloco (A–F) e marca APROVADO/AJUSTAR/RECUSAR.
> Critério primário pra Tier A: regra que precisa ser SEMPRE lembrada (não pode depender de description match).

---

## Sumário da decisão atualizada (2026-05-09)

| Categoria | Quantidade | Skills |
|---|---|---|
| **Tier A — always-on (ativas)** | 8 | brief-first, mcp-first, multi-tenant-patterns, commit-discipline, mwart-process, mwart-comparative V4, **automem-pending**, **session-start-check** |
| **Tier A — dormentes (esperando S4/S5)** | 2 | charter-first, ads-route |
| **Tier B — auto-trigger contextual** | 13 | ads-decision-flow, comparativo-do-modulo, criar-modulo, jana-arch (rename), jana-recall-flow (rename), memory-sync, migrar-modulo, publication-policy, runtime-rules-hostinger-ct100, sidebar-menu-arch (description ainda fraca), **charter-write**, **mwart-quality**, **ui-component-creator** |
| **Tier C — slash command/on-demand** | 7 | cockpit-runbook, meta-skill-roi-erp-autonomo (mismatch — ver Bloco D), oimpresso-cc-watcher-setup, oimpresso-stack, oimpresso-team-onboarding, proxmox-docker-host, **audit-constituicao** |
| **DEPRECATED (substituído)** | 1 | mwart-migrate (substituído por mwart-process — ADR 0104) |
| **Reclassificar/melhorar description** | 2 | sidebar-menu-arch, meta-skill-roi-erp-autonomo |

**Total skills no repo (verificado `ls .claude/skills/`):** 30 ativas (29 fora de DEPRECATED).
**v1 → v2 delta:** +9 skills criadas no período · 3 renames aplicados · 1 deprecada · 2 Tier A novas promovidas (automem-pending + session-start-check) · drift triplo CLAUDE.md/banner/audit doc reconciliado.

---

## Bloco A — Tier A always-on (10 skills, 8 ativas + 2 dormentes)

> ⚠️ **Wagner aprova individualmente cada uma. Cada Tier A precisa ADR justificando promoção.**
> Lista canônica oficializada em [CLAUDE.md L21-26](../../../CLAUDE.md) e refletida em [`.claude/hooks/tier-a-banner.ps1`](../../../.claude/hooks/tier-a-banner.ps1).

### A.1 — `brief-first` ✅ ATIVA (PR #129)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A (manter) |
| **Mecanismo** | Hook `SessionStart` força `mcp__oimpresso__brief-fetch` |
| **Why Tier A** | Reduz onboarding 30k→3k tokens em toda sessão |
| **ADR** | parte de [0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §camada 1 |
| **Status** | live, hook ativo |

### A.2 — `mcp-first` ✅ ATIVA (renomeada de `oimpresso-mcp-first`)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A |
| **Mecanismo** | Description match em "ler/buscar/listar" + hint via SessionStart |
| **Why Tier A** | Garante uso de tools MCP antes de Read filesystem (governança + audit log) |
| **ADR** | [0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) (zero auto-mem privada) |
| **Status** | live |

### A.3 — `multi-tenant-patterns` ✅ ATIVA (promovida B → A)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A |
| **Why Tier A** | Princípio duro #6 da Constituição (Tier 0 IRREVOGÁVEL). Vazar dado entre tenants = pior bug possível |
| **ADR** | [0093 multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md) |
| **Status** | live |

### A.4 — `commit-discipline` ✅ ATIVA (criada conforme v1 plano)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A |
| **Mecanismo** | Hook `PreToolUse` em git commit + alerta diff >300 linhas ou >1 intent |
| **Why Tier A** | 1 PR = 1 intent; ≤300 linhas (Anthropic 2026 best-practice); zero PII em commits |
| **ADR** | [0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §princípio 7 (transparência) |
| **Status** | live |

### A.5 — `mwart-process` ✅ ATIVA (NOVA — não existia em v1)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A — único caminho MWART |
| **Mecanismo** | Hook `PreToolUse` `block-mwart-violation.ps1` bloqueia Edit em `Pages/<Mod>/<Tela>.tsx` se RUNBOOK ausente; CI workflow `mwart-gate.yml` bloqueia merge |
| **Why Tier A** | "Não há caminho B" — todas as 5 fases (PLAN → BACKEND BASELINE → FRONTEND INCREMENTAL → QA → CUTOVER) são obrigatórias |
| **ADR** | [0104 processo MWART canônico — único caminho](../decisions/0104-processo-mwart-canonico-unico-caminho.md) |
| **Status** | live (criada após v1) |

### A.6 — `mwart-comparative` V4 ✅ ATIVA (NOVA — não existia em v1)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A — gate visual F1.5 + F3 + Cowork loop |
| **Mecanismo** | Bloqueia Edit em Page Inertia se `*-visual-comparison.md` ausente; sincroniza `prototipo-ui/SYNC_LOG.md`; orquestra plugin `design:*` Anthropic (6 sub-skills) |
| **Why Tier A** | Restaura loop "design supervisionado" da era Repair S2.5 com qualidade estado-da-arte. Wagner aprova SCREENSHOT, não tabela |
| **ADRs** | [0114 prototipo-ui Cowork loop formalizado](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (mãe direta) · [0107 emendation 0104 visual comparison gate F3](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) · [0109 Claude Design plugin integrado processo MWART](../decisions/0109-claude-design-plugin-integrado-processo-mwart.md) |
| **Status** | live (criada após v1) |

### A.7 — `automem-pending` ✅ ATIVA (NOVA — não existia em v1, BLOQUEADOR)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A — bloqueador anti-drift |
| **Mecanismo** | Quando user toca tópico/módulo com auto-mem stale, força carregar `AUTO-MEM-PENDING.md` + decidir "migrar pro git OU deletar". Disparos: (1) Edit/Read em `Modules/{NfeBrasil,Officeimpresso,Copiloto,Cms,Financeiro,Form,PontoWr2}/`; (2) RotaLivre/Larissa/biz=4; (3) SSH Hostinger/CT 100; (4) Asaas/concorrentes/revenue/Delphi/MCP endpoints; (5) DataController hooks UltimatePOS; (6) `session('business')`/sidebar/topnav/datatables |
| **Why Tier A** | Operadora do manifesto migração ADR 0061 — sem skill, auto-mem privada vira fonte stale silenciosa enquanto migração é gradual |
| **ADR** | [0061 zero auto-mem privada](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) |
| **Status** | live |

### A.8 — `session-start-check` ✅ ATIVA (NOVA — não existia em v1)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A — paralelismo cross-dev |
| **Mecanismo** | Auto-trigger `session_start` (após `brief-first`). Chama tool MCP `whats-active` pra detectar se outra sessão Claude do time tocou paths overlapping nas últimas 2h. Alerta passivo (não bloqueia) |
| **Why Tier A** | Cobre o único cenário de conflito não mitigado por worktree isolada + `tasks-update doing`: Claude-A vs Claude-B mexendo no mesmo arquivo simultaneamente |
| **ADR** | [0119 paralelismo sessões — whats-active Tier 1](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) |
| **Status** | live |

### A.9 — `charter-first` (DORMENTE — esperando S4)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A `enabled: false` até S4 entregar tool `charter-fetch` |
| **Mecanismo previsto** | Hook `PreToolUse` em Edit/Write em `.tsx`. Se houver `*.charter.md` ao lado, força `charter-fetch` antes |
| **Why Tier A** | Princípio duro #3 (Charter > Spec) |
| **Status** | dormente (criada com `enabled: false`); ativação prevista ~jun/2026 com S4 |

### A.10 — `ads-route` (DORMENTE — esperando S5)

| Campo | Valor |
|---|---|
| **Decisão** | TIER A `enabled: false` até S5 entregar `decide()` |
| **Mecanismo previsto** | Toda decisão custosa passa por `decide(domain, intent, payload)` |
| **Why Tier A** | Princípio duro #2 (Tiered cost) |
| **Status** | dormente; ativação **antecipada pra ~30/maio/2026** ([ADR 0106 recalibração velocidade fator 10x](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) |

---

## Bloco B — Tier B auto-trigger contextual (13 skills)

> Aprovação Wagner: bloco inteiro APROVADO/AJUSTAR.
> Critério: skill carrega contexto pesado por descrição-match. Não é always-on, mas dispara em situações recorrentes.

| Skill | Description (preview) | Decisão | Notas |
|---|---|---|---|
| `ads-decision-flow` | "Use ao trabalhar em Modules/ADS/ ou tocar fluxo de decisão automatizada..." | TIER B | ✅ ok |
| `comparativo-do-modulo` | "ATIVAR quando user pedir comparar módulo X com mercado..." | TIER B | ✅ ok — slash `/comparativo` é alias Tier C (ver D) |
| `criar-modulo` | "Use ao criar novo módulo Laravel modular..." | TIER B | ✅ ok |
| `jana-arch` | "Use ao trabalhar em Modules/Copiloto/ ou ao tocar memória/IA..." | TIER B | ✅ rename `copiloto-arch → jana-arch` aplicado |
| `jana-recall-flow` | "Use ao tocar Modules/Copiloto/Services/Memoria/..." | TIER B | ✅ rename `memoria-recall-flow → jana-recall-flow` aplicado |
| `memory-sync` | "ATIVAR após criar/editar arquivo em memory/..." | TIER B | ✅ ok |
| `migrar-modulo` | "Use ao mover, renomear, ou extrair controller/módulo..." | TIER B | ✅ ok |
| `publication-policy` | "Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção..." | TIER B | ✅ ok |
| `runtime-rules-hostinger-ct100` | "Use ANTES de SSH no Hostinger, composer install/update em servidor..." | TIER B | ✅ ok |
| `sidebar-menu-arch` | "Reconhecer, auditar e modificar a arquitetura do sidebar..." | TIER B | ⚠️ description ainda começa com substantivos (não Tier B canônico "Use ao") — ver Bloco D |
| `charter-write` | "ATIVAR quando user pedir 'criar charter da tela X'..." | TIER B | ✅ NOVA — gera draft `*.charter.md` ao lado do `.tsx` (PARA aguardando Wagner revisar Non-Goals + Anti-hooks) |
| `mwart-quality` | "Use ANTES de criar/editar tela MWART..." | TIER B | ✅ NOVA — 9 pré-flight checks (5 padrões de bug históricos PRs #138-#145). **Complementar a `mwart-process` Tier A — ver Bloco F.4** |
| `ui-component-creator` | "Use ao criar/modificar componentes React (Pages Inertia, sub-componentes em _components/, ou shareds em Components/shared/)..." | TIER B | ✅ NOVA — Cockpit Pattern V2, ADR 0110 |

**Renames aplicados (vs v1):**
- ✅ `copiloto-arch` → `jana-arch` (alinhamento Modules/Jana/)
- ✅ `memoria-recall-flow` → `jana-recall-flow`
- ✅ `oimpresso-mcp-first` → `mcp-first` (já promovida Tier A — ver A.2)

---

## Bloco C — Tier C slash command/on-demand (7 skills)

| Skill | Decisão | Justificativa |
|---|---|---|
| `cockpit-runbook` | TIER C | Geração de runbook por demanda — não dispara contextual |
| `meta-skill-roi-erp-autonomo` | TIER C | ⚠️ description é auto-trigger — ver Bloco D |
| `oimpresso-cc-watcher-setup` | TIER C | One-time setup do watcher cross-dev — não recorrente |
| `oimpresso-stack` | TIER C | One-time onboarding ao entrar no oimpresso |
| `oimpresso-team-onboarding` | TIER C | One-time setup MCP per dev — não recorrente |
| `proxmox-docker-host` | TIER C | Receita Proxmox específica — só quando trabalhar infra CT 100 |
| `audit-constituicao` | TIER C | NOVA (2026-05-09) — slash `/audit-constituicao`, audit 6-dimensional governance, ~3h manual → ~30min |

---

## Bloco D — Skills com description fraca (precisa ajuste)

| Skill | Problema | Proposta |
|---|---|---|
| `sidebar-menu-arch` | Description começa com "Reconhecer, auditar e modificar..." (substantivos, não trigger Anthropic-pattern). Diagnosticada em v1, **ainda pendente em 2026-05-09** | Reescrever: "Use ao mexer no sidebar AppShellV2, criar/alterar menu lateral, debug rota não aparece no menu, ou auditar SIDEBAR_GROUPS/DataController.modifyAdminMenu" |
| `meta-skill-roi-erp-autonomo` | Frontmatter declara `tier: C` (implícito por uso pontual) MAS description começa com "ATIVAR ao criar skill nova, usar `skill:scaffold`..." — padrão auto-trigger Tier B | Decisão pendente: **(a)** manter Tier C e ajustar description pra explícito `/meta-skill` slash; **(b)** promover formalmente Tier B (ativaria toda criação de skill). Wagner aprova |
| `comparativo-do-modulo` vs `/comparativo` | Skill Tier B + slash command Tier C com mesmo escopo. Duplicação intencional ou acidental? | **Documentado como intencional** — skill é o motor (lógica), slash é atalho de invocação humana. Análogo a `audit-constituicao` (Tier C) que tem `/audit-constituicao` |

Após ajustes Bloco D: 0 skills `description fraca` em circulação.

---

## Bloco E — Skills NOVAS criadas/renomeadas no período (delta v1 → v2)

> Mostra o que aconteceu **entre 2026-05-06 (v1)** e **2026-05-09 (v2)** — período onde o doc original ficou stale.

### E.1 — Skills NOVAS (9 criadas)

| Skill | Tier | Path | Created | Origem |
|---|---|---|---|---|
| `automem-pending` | **A** | `.claude/skills/automem-pending/SKILL.md` | 2026-05-09 | Bloqueador operadora migração ADR 0061 |
| `charter-write` | B | `.claude/skills/charter-write/SKILL.md` | 2026-05-08 | Geração de `*.charter.md` por inferência sobre `.tsx` + Controller |
| `mwart-process` | **A** | `.claude/skills/mwart-process/SKILL.md` | 2026-05-07 | [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) único caminho |
| `mwart-comparative` V4 | **A** | `.claude/skills/mwart-comparative/SKILL.md` | 2026-05-08 (V4) | [ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) Cowork loop |
| `mwart-quality` | B | `.claude/skills/mwart-quality/SKILL.md` | 2026-05-07 | 9 pré-flight checks (PRs #138-#145 lição) |
| `session-start-check` | **A** | `.claude/skills/session-start-check/SKILL.md` | 2026-05-09 | [ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) cross-dev whats-active |
| `ui-component-creator` | B | `.claude/skills/ui-component-creator/SKILL.md` | 2026-05-08 | [ADR 0110](../decisions/0110-cockpit-pattern-v2.md) Cockpit Pattern V2 |
| `audit-constituicao` | C | `.claude/skills/audit-constituicao/SKILL.md` | 2026-05-09 | Audit 6-dimensional governance pós-Constituição (instrumento que detectou este drift) |
| `oimpresso-cc-watcher-setup` | C | `.claude/skills/oimpresso-cc-watcher-setup/SKILL.md` | 2026-05-08 | Sync `~/.claude/projects/*.jsonl` → MCP server cross-dev |

### E.2 — Skills RENOMEADAS (3)

| De | Para | Motivo |
|---|---|---|
| `oimpresso-mcp-first/` | `mcp-first/` | Encurtar — já é Tier A always-on, prefixo `oimpresso-` redundante neste repo |
| `copiloto-arch/` | `jana-arch/` | Alinhamento com `Modules/Jana/` (renomeado bem antes; skill ficou stale no nome) |
| `memoria-recall-flow/` | `jana-recall-flow/` | Mesma razão acima |

### E.3 — Skills DEPRECATED (1)

| Skill | Substituído por | Motivo |
|---|---|---|
| `mwart-migrate` | `mwart-process` (Tier A) | [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) declara único caminho. `mwart-migrate` era spec preliminar; após ADR ele virou processo formal com 5 fases obrigatórias + enforcement (hook `block-mwart-violation.ps1` + CI `mwart-gate.yml`). Pasta original removida. Spec histórica em [`s2-os-listagem/05-skill-mwart-migrate.md`](../s2-os-listagem/05-skill-mwart-migrate.md) |

---

## Bloco F — Lições aprendidas (NOVO em v2)

> Esta seção não existia em v1. Documenta drift estrutural detectado pela skill `audit-constituicao` em 2026-05-09 (primeira execução do audit 6-dimensional após Constituição).

### F.1 — Drift triplo: CLAUDE.md ↔ tier-a-banner.ps1 ↔ skills-audit.md

**Sintoma observado em 2026-05-09:**

| Fonte | Tier A reportadas |
|---|---|
| [`CLAUDE.md`](../../../CLAUDE.md) L21-26 | 6 ativas (brief-first, mcp-first, multi-tenant-patterns, commit-discipline, mwart-process, mwart-comparative) + 2 dormentes (charter-first, ads-route) = **8 total** |
| [`.claude/hooks/tier-a-banner.ps1`](../../../.claude/hooks/tier-a-banner.ps1) | Antes do fix de hoje: 6 (faltavam mwart-process e mwart-comparative). Corrigido na sessão de 2026-05-09 pra **8** |
| Este doc (v1, 2026-05-06) | Listava **4 ativas + 2 dormentes** (parou antes das MWART e antes de automem-pending + session-start-check) |

**Causa-raiz:** mudanças em Tier A foram feitas em 3+ lugares ad-hoc durante PRs separados (`commit-discipline` no PR Constituição, `mwart-process` no PR ADR 0104, `mwart-comparative` no PR ADR 0114, `automem-pending` + `session-start-check` no PR ADR 0119) sem checklist obrigatório que sincronize as 3 fontes.

**Lição (vira regra):**

> ⚠️ **Qualquer mudança em Tier A (promoção/criação/dormência → ativa) requer sincronizar OBRIGATORIAMENTE as 3 fontes no MESMO PR:**
> 1. [`CLAUDE.md`](../../../CLAUDE.md) §"Skills Tier A"
> 2. [`.claude/hooks/tier-a-banner.ps1`](../../../.claude/hooks/tier-a-banner.ps1)
> 3. Este doc (`memory/sprints/s3-constituicao/03-skills-audit.md`) — Bloco A
>
> **Enforcement futuro (proposta):** check em CI workflow `mwart-gate.yml` (ou novo `tier-a-sync-gate.yml`) que faz parsing das 3 fontes e falha se número/nomes divergem. Item de backlog Tier C — abre ADR antes de implementar.

### F.2 — Skills renomeadas precisam atualizar 3 lugares também

Pattern detectado nos renames `copiloto-arch → jana-arch`, `memoria-recall-flow → jana-recall-flow`, `oimpresso-mcp-first → mcp-first`:

| Lugar | Atualizar? |
|---|---|
| `git mv .claude/skills/<old> .claude/skills/<new>` | ✅ sempre |
| `name:` no frontmatter de SKILL.md | ✅ sempre |
| Referências cross-skill (links em outras SKILL.md) | ⚠️ frequentemente esquecido |
| Este doc + CLAUDE.md + banner se Tier A | ⚠️ ver F.1 |

**Lição:** rename sem batch-grep cruzado vira link quebrado. Skill `migrar-modulo` (Tier B) já cobre rename de **módulo PHP**; falta análogo pra rename de **skill**. Item de backlog: skill `migrar-skill` ou seção em `meta-skill-roi-erp-autonomo`.

### F.3 — Tier mismatches frontmatter vs description

**Detectado:** `meta-skill-roi-erp-autonomo` declara `tier: C` (explícito ou implícito por uso pontual) mas description começa com "ATIVAR ao criar skill nova..." — esse padrão é Tier B canônico (auto-trigger por description match).

**Risco:** harness Claude Code pode disparar a skill como Tier B (matching automático) enquanto Wagner+Sonnet a tratam como Tier C (slash on-demand). Resultado: ativação inesperada inflando contexto em sessões irrelevantes.

**Lição (vira regra):**

> ⚠️ **Description começando com "ATIVAR quando..." / "Use ao..." / "Use ANTES de..." = Tier B contrato.**
> Tier C só pode ter description começando com **"Slash command `/<nome>` ..."** ou **"Use ao executar slash `/<nome>`"** — explicitando invocação humana.
> Skills Tier C com description Tier B-style devem ser reescritas OU promovidas a Tier B formalmente.

### F.4 — Overlap MWART trio (mwart-process + mwart-quality + mwart-comparative)

**Pergunta levantada pelo audit:** "ao migrar tela X pra MWART, todas 3 ativam. Duplicação ou complementar?"

**Resposta:** **complementar — cada uma cobre fase distinta do processo canônico ADR 0104:**

| Skill | Tier | Fase MWART | Função |
|---|---|---|---|
| `mwart-process` | A | F1 PLAN → F5 CUTOVER | Carrega processo canônico de 5 fases. Bloqueia se RUNBOOK ausente (hook `block-mwart-violation.ps1`). É o **trilho** |
| `mwart-comparative` V4 | A | F1.5 + F3 (gate visual) | Gera `*-visual-comparison.md` antes de codar Page Inertia + sincroniza Cowork loop. **Aguarda Wagner aprovar SCREENSHOT** ~10min síncrono. É o **gate visual** |
| `mwart-quality` | B | F2 BACKEND BASELINE + F3 FRONTEND INCREMENTAL | 9 pré-flight checks anti-bug recorrente (route() Ziggy, shape backend↔frontend, Schema column, CommonChart, DS prop contract). Pré-flight checklist no início de F2/F3 — **é o detector** |

**Lição:** redundância de carregamento Tier A é aceitável **quando cada skill cobre dimensão ortogonal** (processo vs visual vs qualidade). O custo extra de tokens é trade-off contra o custo histórico de retrabalho (PRs #143-#145 corretivos pós-prod).

**Não consolidar em uma skill mega-MWART** — separação reflete SoC brutal (princípio #5 Constituição v2).

### F.5 — Telemetria 30 dias ainda não materializada

v1 propôs: "skills NÃO arquivar nesta rodada (sem baseline). Em 30d pós-S3 → archive skills com 0 disparos".

**Em 2026-05-09 (3 dias pós-Constituição):** baseline ainda em coleta. Tabela `mcp_skill_telemetry` ativa mas insuficiente. Próxima revisão (v3 deste doc): **2026-06-06 (~30d)** — verificar candidatas a arquivamento.

---

## Plano de execução pós-aprovação v2

```bash
# 1. Bloco D — fix descriptions fracas
#    - sidebar-menu-arch: reescrever description com "Use ao..."
#    - meta-skill-roi-erp-autonomo: decidir Tier B vs C explícito (Wagner aprova)

# 2. Bloco F — sincronização triplo Tier A
#    Já reconciliado em 2026-05-09 (banner.ps1 + CLAUDE.md + este doc).
#    Próximo passo: ADR pequeno propondo gate CI tier-a-sync-gate.yml

# 3. Telemetria — coletar 30d pós-Constituição (até 2026-06-06)
#    Em v3: verificar arquivamento + promoções

# 4. Charter de skills (S4+) — alinhar com charter-first quando ativar
```

---

## Decisões pendentes Wagner (v2)

> Marque ✅ APROVADO ou ❌ AJUSTAR pra cada bloco. PR de v2 mergeia após todos com ✅.

- [ ] **Bloco A** (Tier A — 8 ativas + 2 dormentes = 10 total) — APROVAR snapshot 2026-05-09?
- [ ] **Bloco B** (13 Tier B incluindo 3 novas) — APROVAR?
- [ ] **Bloco C** (7 Tier C incluindo audit-constituicao) — APROVAR?
- [ ] **Bloco D** (sidebar-menu-arch + meta-skill-roi-erp-autonomo descriptions) — escolher fix?
- [ ] **Bloco E** (snapshot delta v1→v2 — 9 novas + 3 renames + 1 deprecated) — APROVAR registro?
- [ ] **Bloco F** (lições aprendidas — drift triplo + rename pattern + tier mismatch + overlap MWART trio) — APROVAR registro?

**Sub-decisões abertas:**
- [ ] Criar ADR pequeno propondo CI gate `tier-a-sync-gate.yml` (F.1 enforcement)?
- [ ] Criar skill `migrar-skill` análoga a `migrar-modulo` (F.2)?
- [ ] Reescrever description `meta-skill-roi-erp-autonomo` como Tier C explícito OU promover Tier B (F.3)?

---

## Histórico

- **2026-05-06** — v1 criada (sonnet) durante PR Constituição v2. 19 skills. 4 Tier A ativas + 2 dormentes
- **2026-05-09** — v2 reescrita após primeiro run de `audit-constituicao`. 30 skills (+9 -1 deprecated). 8 Tier A ativas + 2 dormentes. Bloco F NOVO (lições aprendidas drift triplo)
- **~2026-06-06 (planejado)** — v3 com telemetria 30d, decisões archive/promote
