---
slug: 0225-recalibracao-skills-tier-a-pos-4-8
number: 225
title: "Recalibração skills Tier A pós-Claude 4.8 — 25 eager → ~3 Tier 0 + resto auto-trigger/hook"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: '2026-05-28'
module: Infra
quarter: 2026-Q2
tags: [skills, tier-a, context-engineering, claude-4.8, recalibracao, memoria]
supersedes: []
supersedes_partially:
  - 0095-skills-tiers-convencao-interna
  - 0168-protocolo-wagner-sempre-tier-A-irrevogavel
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0224-hooks-block-vs-advisory-claude-4.8-aware
  - 0213-audit-creates-tasks-loop-fechado
pii: false
review_triggers:
  - "Skills Tier A voltam a passar de ~5 (drift de inflação)"
  - "Nova geração de modelo (4.9/5.x) muda premissa de atenção"
---

# ADR 0225 — Recalibração skills Tier A pós-Claude 4.8

## Contexto

Continuação do roadmap da reavaliação [`memory/sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md`](../sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md) §7 (ADR 0225 proposta) — após [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) (hooks block vs advisory, aceito).

A reavaliação estimou "7+ skills Tier A always-on". **Inventário empírico 2026-05-28 mediu pior:**

```
grep tier:A | always-on | BLOQUEADOR em .claude/skills/*/SKILL.md
→ 25 de 66 skills (38%) auto-marcadas como crítica/eager
```

**Quando 25 skills gritam "BLOQUEADOR/always-on", a atenção do 4.8 não prioriza nenhuma.** É o anti-padrão de atenção: muito eager dilui. Prova decisiva da mesma sessão: o R12 (fechamento) era Tier A always-on e **mesmo assim falhou** numa sessão longa (200+ turnos) — carregou no início, diluiu no meio, perdeu no fim. Só voltou a funcionar quando migrado pra **hook UserPromptSubmit no momento exato** (ativação lazy).

Premissa original (maio/2026, Constituição v2 [ADR 0095](0095-skills-tiers-convencao-interna.md)): "modelo esquece regra entre turnos → carregar Tier A always-on". Com Claude 4.8 (1M context, instruction-following muito melhor), essa premissa **encolheu de lei física pra otimização de margem** (tese da reavaliação §1).

## Decisão

**Triar as 25 skills eager: manter ~3 genuinamente Tier 0; rebaixar o resto pra auto-trigger (description-match) ou enforcement-no-momento (hook).**

### Princípio canônico (novo)

> **"Se pode ser hook determinístico OU auto-trigger por description/path, NÃO é Tier A always-on."**
> Tier A always-on reserva-se a proteção Tier 0 que (a) independe de modelo e (b) não tem trigger contextual claro. Enforcement-no-momento (hook PreToolUse/PostToolUse/UserPromptSubmit) é **superior** a always-on pra atenção em sessão longa — dispara deterministicamente, não depende de atenção sobreviver.

### Triagem das 25 (veredito por skill)

**MANTÉM Tier A always-on (núcleo Tier 0 — proteção que independe de modelo):**

| Skill | Razão |
|---|---|
| `multi-tenant-patterns` | Tier 0 IRREVOGÁVEL ADR 0093 — vazar tenant = P0. Sem trigger contextual único (toca qualquer Model/Controller/Job) |
| `commit-discipline` | Disciplina de qualidade pré-commit que nenhum modelo dispensa (reavaliação §6) |

**REBAIXAR pra auto-trigger Tier B (já disparam por path/keyword — eager era redundante):**

| Skill | Trigger natural | Enforcement real já existe |
|---|---|---|
| `mwart-process` | Write `Pages/*.tsx` | hook `block-mwart-violation` (block) |
| `mwart-comparative` | Write Page Inertia | hook + CI mwart-gate |
| `mwart-quality` | Write `Modules/*Controller` | — (description-match basta) |
| `preflight-modulo` | Edit `Modules/<X>/` | hook `modulo-preflight-warning` (advisory) |
| `constituicao-ui-aware` | Write `Pages/*.tsx` / css | hook charter-validate |
| `ui-component-creator` | Write Components | description-match |
| `charter-first` | Edit `.tsx` c/ `.charter.md` | hook charter-validate (advisory) |
| `charter-write` | "criar charter" | description-match |
| `cockpit-runbook` | "runbook da tela" | description-match |
| `migracao-blade-react` | "migrar Blade" | description-match |
| `automem-pending` | path auto-mem stale | hook `block-automem` (block) faz enforcement real |
| `module-completeness-audit` | antes de US done | description-match |

**REBAIXAR pra advisory/sugestão (cobertas por hook OU redundantes com CLAUDE.md):**

| Skill | Razão |
|---|---|
| `brief-first` | Hook SessionStart já chama `brief-fetch`. Skill é redundante |
| `mcp-first` | Reavaliação §3 #2: exploração filesystem agora barata, warning vira ruído |
| `wagner-protocol-enforce` | Protocolo é 1 doc + hooks específicos (R12 hook, etc). Always-on dilui (provado R12) |
| `session-start-check` | Auto-trigger session_start já é o mecanismo; absorver no brief |

**JÁ corretas (Tier B lazy/hook — só remover label "BLOQUEADOR" inflado da description):**

`encerrar-sessao` (criada hoje, lazy+hook — correta), `incident-done-checklist`, `memory-first-secret-search`, `hostinger-dns-autonomy`, `memory-sync`, `personas-resolve`, `pre-adr-introspect`, `wagner-request-refiner` — todas Tier B de fato; o "BLOQUEADOR" na description é dívida cosmética (não há enforcement always-on real por trás).

**DORMENTE (não conta):** `ads-route` (S5).

### Resultado-alvo

| Antes | Depois |
|---|---|
| 25 skills eager/BLOQUEADOR | **~2-3 Tier A** (multi-tenant, commit-discipline) |
| Atenção diluída em 25 prioridades | Atenção focada; resto dispara no momento certo |

### Mecânica de execução (append-only, NÃO big-bang)

1. Skills rebaixadas: editar frontmatter `tier: A` → `tier: B`; remover "always-on"/"BLOQUEADOR" da description onde não há hook de enforcement real
2. Sincronizar 3 fontes (lição F.1 da skills-audit): `CLAUDE.md` §Skills Tier A + `tier-a-banner.ps1` + `s3-constituicao/03-skills-audit.md`
3. Emenda parcial (não supersede total) de 0095 + 0168 — `supersedes_partially`
4. 1 PR por lote temático (MWART skills / UI skills / protocolo skills) ≤300 linhas — commit-discipline

## Justificativa

**Por que ~2-3 e não 0:** multi-tenant + commit-discipline protegem invariantes Tier 0 que independem da geração do modelo (reavaliação §6). Não são "lembrete que o modelo internaliza" — são guarda-corpos de catástrofe.

**Por que rebaixar e não deletar:** as skills continuam valiosas — só mudam de eager pra lazy. O conteúdo dispara quando relevante (description-match/hook), liberando atenção quando irrelevante.

**Por que enforcement-no-momento > always-on:** evidência empírica R12 (hoje). Hook dispara deterministicamente no momento exato; always-on aposta que a atenção sobreviva 200 turnos — e não sobrevive. Confirmado pela best-practice Anthropic 2026 (ADR 0224): "hook pra determinístico; comportamento pra CLAUDE.md/skill (~80% aderência, suficiente pro 4.8)".

**Por que agora:** [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) (pré-req) já aceito. Esta é a 2ª peça da recalibração — a de maior impacto sobre atenção/memória de TODA sessão de TODO dev do time MCP.

## Consequências

**Positivas:**

- Atenção do 4.8 foca no trabalho, não em 25 lembretes eager
- SessionStart mais leve (menos overhead fixo)
- Skills disparam no momento relevante (description-match) — contexto certo, hora certa
- Pareado com 0224 (hooks) + 0213 (audit-to-backlog) = recalibração coerente

**Negativas / Trade-offs:**

- Risco: skill rebaixada não disparar quando deveria (description-match imperfeito). Mitigação: skills com enforcement Tier 0 real (multi-tenant, mwart-block, automem-block) têm HOOK por trás — o hook é o guarda-corpo, a skill é o contexto
- Curva: time MCP precisa entender "Tier A agora é exceção, não regra"
- Manutenção 3-fontes-sync (já é lição catalogada F.1)

**O que NÃO rebaixar (contra-veredito — não é sobre modelo):**

- `multi-tenant-patterns` Tier A (Tier 0 ADR 0093)
- Hooks `block-*` determinísticos (ADR 0224 já triou — automem, destructive, pii, mwart-violation, etc PERMANECEM block)
- R1 smoke real + R10 aprovação (qualidade, não memória)

## Referências

- [`memory/sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md`](../sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md) §3 #3, §4 #2, §7 (roadmap 0225)
- [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md) — hooks block vs advisory (pré-req)
- [ADR 0095](0095-skills-tiers-convencao-interna.md) — convenção tiers (emenda parcial)
- [ADR 0168](0168-protocolo-wagner-sempre-tier-A-irrevogavel.md) — PROTOCOLO Tier A (emenda parcial)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípio 1 Context as a product)
- Inventário empírico 2026-05-28: 25/66 skills Tier A/BLOQUEADOR (sessão `frosty-greider-83ab2f`)
