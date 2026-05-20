# CLAUDE.md — primer pra Claude Code @ oimpresso

> **Sempre comece com `mcp__oimpresso__brief-fetch` (skill `brief-first` Tier A always-on).**
> Documento canônico — mudanças via ADR (ver "Como propor mudança" abaixo).
> Best-practice 2026: ≤100 linhas + `@imports` recursivos pra detalhes.

> ⚠️ **CONTEXTO DE EXECUÇÃO:** Claude aqui roda como **agente desktop (GUI)**, NÃO CLI interativo.
> - `gh` autenticado, `git push`/`gh pr create`/`gh pr merge` funcionam
> - SSH com senha digitada, `git rebase -i`, `gh auth login` **não funcionam** (sem stdin)
> - Browser via `mcp__Claude_in_Chrome__*` (tier full) ou `mcp__computer-use__*` (tier read)

## Por que existe
@memory/why-oimpresso.md

## Stack e estrutura
@memory/what-oimpresso.md

## Como trabalhar (protocolo de sessão)

1. `brief-fetch` → estado consolidado (~3k tokens) — Tier A always-on via hook `SessionStart`
2. `my-work` → minhas tasks ativas
3. `charter-fetch <page-id>` antes de editar `.tsx` que tenha `.charter.md` ao lado (ativo desde 2026-05-13 — Onda 4 C1)
4. Trabalhar (ler código, edit, test)
5. (S5+) `decide(domain, intent, payload)` se mudança custosa
6. Commit conventional + `Refs: SPRINT-N PASSO M` (skill `commit-discipline` Tier A)

@memory/how-trabalhar.md

## Skills Tier A (always-on — hook SessionStart)

- **brief-first** — força brief-fetch primeiro
- **mcp-first** — usar tools MCP antes de Read/Glob/Grep filesystem
- **multi-tenant-patterns** — Tier 0 isolation (`business_id` global scope) — [ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- **commit-discipline** — 1 PR = 1 intent, ≤300 linhas, conventional commits
- **mwart-process** — único caminho de migração Blade→Inertia (5 fases obrigatórias) — [ADR 0104](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- **mwart-comparative V4** — gate visual F1.5 + F3 estado-da-arte + loop Cowork ↔ Claude Code formalizado em [`prototipo-ui/PROTOCOL.md`](prototipo-ui/PROTOCOL.md). Orquestra Claude Design plugin Anthropic (design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis). 15 dimensões + Wagner aprova SCREENSHOT (não tabela) — [ADR 0114](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) + [ADR 0107](memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) + [ADR 0109](memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
- **charter-first** — ativo desde 2026-05-13 (Onda 4 C1) · tool MCP `charter-fetch` + hook `charter-validate` warning-mode · ver [CHARTER-S4-DOSSIER-2026-05-20.md](memory/requisitos/Jana/CHARTER-S4-DOSSIER-2026-05-20.md)
- (S5 antecipado pra ~30/maio/2026 — [ADR 0106](memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) **ads-route**

> **Estimates 2026-05-08+:** todos novos SPECs nascem recalibrados ([ADR 0106](memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) — fator 10x em tarefas codáveis com IA-pair + margem 2x; tarefas humano-limitadas (canary 7d, monitor 30d, smoke real) mantém relógio do mundo real.

> **Cliente como sinal qualificado** ([ADR 0105](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)): backlog só recebe item se cliente paga + reporta OU métrica detecta drift. Hipótese sem sinal vira ADR de feature wish, não US ativa.

Tier de cada skill em [memory/sprints/s3-constituicao/03-skills-audit.md](memory/sprints/s3-constituicao/03-skills-audit.md). Convenção interna formalizada em [ADR 0095](memory/decisions/0095-skills-tiers-convencao-interna.md).

## Constituição v2 (7 camadas + 8 princípios duros)

Documento mãe: **[ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)**.

Princípios duros:
1. Context as a product · 2. Tiered cost · 3. Charter > Spec · 4. Loop fechado por métrica · 5. SoC brutal · 6. **Multi-tenant Tier 0 IRREVOGÁVEL** · 7. Transparência · 8. Confiabilidade com fallback

Lista completa de ADRs canon via tool MCP `decisions-search` (default: só ativas — convenção lifecycle [ADR 0095](memory/decisions/0095-skills-tiers-convencao-interna.md)).

## Proibições (Tier 0 — sem ADR mãe nova é proibido)
@memory/proibicoes.md

## Time e responsabilidades
@memory/regras-time.md

## Como propor mudança

| Tipo | Caminho |
|---|---|
| ADR canon | PR + ADR Nygard + aprovação Wagner |
| ADR HISTORICAL | PR opcional, status `historical` |
| Skill Tier A | PR + ADR específica + Wagner aprova |
| Skill Tier B/C | PR + SKILL.md description "Use ao/quando..." |
| Charter (S4+) | PR + `*.charter.md` ao lado do `.tsx` |
| Mudança ADR canon existente | ❌ NÃO. Append-only. Criar nova com `supersedes: [N]` |

## Onde NÃO inventar (Tier 0)

Detalhes em `memory/proibicoes.md`. Resumo:
- Tokens MCP, schema `mcp_audit_log`, ADRs CANON, `business_id` global scope
- Centrifugo + FrankenPHP runtime CT 100 ([ADR 0058](memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md))
- Hostinger ≠ CT 100 separação ([ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md))
- ZERO auto-mem privada ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md))
- `laravel/octane` no Hostinger
- **F3 (Cowork → Inertia/React):** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) antes de Edit/Write em `Modules/<Mod>/Http/Controllers/*.php` ou `resources/js/Pages/<Mod>/<Tela>.tsx` — 6 meta-anti-padrões + 15 técnicos catalogados (sessão 2026-05-09 batch Financeiro rejeitado)

## Métricas de saúde

Rodar `php artisan jana:health-check` (ou ver schedule daily 06:00 BRT em `app/Console/Kernel.php`).
5 checks SQL: multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h, pii_leak_in_assistant_responses, profile_distiller_drift.

Se algum falhar → investigar `storage/logs/laravel.log` ALERT entries.

## Suporte ao usuário

- `/help`, `/clear`, `/compact` (slash commands Claude Code)
- Reportar bug: https://github.com/anthropics/claude-code/issues

---
**Última atualização:** 2026-05-06 — Constituição v2 ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)) aceita. CLAUDE.md reescrito de 289 → ~85 linhas (Anthropic 2026 best-practice).
