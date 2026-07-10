# CLAUDE.md — primer pra Claude Code @ oimpresso

> **Sempre comece com `mcp__oimpresso__brief-fetch` (skill `brief-first` — auto-trigger no SessionStart, ADR 0225).**
> Documento canônico — mudanças via ADR (ver "Como propor mudança" abaixo).
> Best-practice 2026: enxuto (~110 linhas) + `@imports` recursivos pra detalhes.

> ⚠️ **CONTEXTO DE EXECUÇÃO:** Claude aqui roda como **agente desktop (GUI)**, NÃO CLI interativo.
> - `gh` autenticado, `git push`/`gh pr create`/`gh pr merge` funcionam
> - SSH com senha digitada, `git rebase -i`, `gh auth login` **não funcionam** (sem stdin)
> - Browser via `mcp__Claude_in_Chrome__*` (tier full) ou `mcp__computer-use__*` (tier read)

## Por que existe
@memory/why-oimpresso.md

## Stack e estrutura
@memory/what-oimpresso.md

## Como trabalhar (protocolo de sessão)

1. `brief-fetch` → estado consolidado (~3k tokens) — auto-trigger via hook `SessionStart` (ADR 0225)
2. `my-work` → minhas tasks ativas
3. (S4+) `charter-fetch <page-id>` antes de editar `.tsx` que tenha `.charter.md` ao lado
4. **Antes de tocar UI** (Pages/Components/css): ler [Constituição UI v2 · ADR UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) + [PT aplicável](memory/requisitos/_DesignSystem/padroes-tela/) + rodar [PRE-MERGE-UI](memory/requisitos/_DesignSystem/PRE-MERGE-UI.md)
4b. **Antes de tocar design-memory** (`prototipo-ui/**` · charters · `*.casos.md` · build visual): ler [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](prototipo-ui/PROCESSO_MEMORIA_CC.md) (raiz do método anti-regressão — §5 REGRESSÕES PROIBIDAS + NÚCLEO 13 invariantes) + [`memory/LICOES_CC.md`](memory/LICOES_CC.md) (L-01… — lista viva, hoje até L-27). No fim da build, rodar `node prototipo-ui/ds-guard.mjs <arquivos tocados>` (§8) e, ao formalizar, `node prototipo-ui/integrity-check.mjs` (§15). _REGRESSÃO É INACEITÁVEL._
5. Trabalhar (ler código, edit, test)
6. (S5+) `decide(domain, intent, payload)` se mudança custosa
7. Commit conventional + `Refs: SPRINT-N PASSO M` (skill `commit-discipline` Tier A)

@memory/how-trabalhar.md

## Skills — Tier A (núcleo always-on) + auto-trigger (ADR 0225)

<!-- AUTO:SKILLS-BEGIN — gerado por scripts/governance/skills-index-generate.mjs (fonte única: frontmatter .claude/skills/*/SKILL.md). NÃO editar à mão; rode --write. -->
**Tier A** (núcleo always-on — segurança/LGPD/disciplina, carregam em toda sessão):
- **commit-discipline** — 1 PR = 1 intent, ≤300 linhas, conventional commits
- **hostinger-dns-autonomy** — não escalar pro Wagner ação automatizável
- **incident-done-checklist** — DoD com smoke real ANTES de declarar pronto (R1)
- **memory-first-secret-search** — consultar `_INDEX-SECRETS` antes de buscar token
- **multi-tenant-patterns** — Tier 0 isolation (`business_id` global scope) — [ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)

**Auto-trigger** (Tier B — disparam por path/intenção/momento, ADR 0225):
- **brief-first** _(session_start)_ — força brief-fetch no início
- **charter-first** _(path)_ — dispara ao editar `.tsx` com `.charter.md`
- **constituicao-ui-aware** _(path)_ — Constituição UI v2 + PT aplicável + PRE-MERGE-UI antes de Edit em Pages/Components/css ([ADR UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md))
- **mcp-first** _(intent)_ — tools MCP antes de filesystem
- **mwart-comparative** _(path)_ — gate visual F1.5 + loop Cowork ↔ Code (V4, [`prototipo-ui/PROTOCOL.md`](prototipo-ui/PROTOCOL.md)). Orquestra Claude Design plugin (design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis). 15 dimensões + gate visual via CI ([ADR 0241](memory/decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) emenda 0107; Protocolo v2 [ADR 0282](memory/decisions/0282-protocolo-v2-colapso-ratificacao.md)); merge de `.tsx` segue humano ([ADR 0283](memory/decisions/0283-handoff-loop-zero-paste.md)) — [ADR 0114](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) + [ADR 0107](memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) + [ADR 0109](memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
- **mwart-process** _(path)_ — único caminho Blade→Inertia (5 fases) — [ADR 0104](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- **preflight-modulo** _(path)_ — dispara em Edit `Modules/<X>/`
- **session-start-check** _(session_start)_ — whats-active pós-brief — detecta sessão paralela tocando o mesmo path ([ADR 0119](memory/decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))

**Dormente** (tier A com `enabled: false`):
- **ads-route** — roteia mudança custosa via `decide(domain,intent,payload)` — ativa quando S5 entregar ADS Universal
<!-- AUTO:SKILLS-END -->

> **Estimates 2026-05-08+:** todos novos SPECs nascem recalibrados ([ADR 0106](memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) — fator 10x em tarefas codáveis com IA-pair + margem 2x; tarefas humano-limitadas (canary 7d, monitor 30d, smoke real) mantém relógio do mundo real.

> **Cliente como sinal qualificado** ([ADR 0105](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)): backlog só recebe item se cliente paga + reporta OU métrica detecta drift. Hipótese sem sinal vira ADR de feature wish, não US ativa.

Tier de cada skill no índice GERADO [.claude/skills/_SKILLS-INDEX.md](.claude/skills/_SKILLS-INDEX.md) (fonte única = frontmatter; `skills-index-generate.mjs --write`). Convenção interna formalizada em [ADR 0095](memory/decisions/0095-skills-tiers-convencao-interna.md); histórico da auditoria S3 em [03-skills-audit.md](memory/sprints/s3-constituicao/03-skills-audit.md).

## Constituição v2 (7 camadas + 8 princípios duros)

Documento mãe: **[ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)**. **Estado REAL das camadas** (retrato datado e verificado — o diagrama da 0094 congelou em 2026-05): **[ADR 0330](memory/decisions/0330-mapa-dos-niveis-estado-real-2026-07-constituicao.md)**.

Princípios duros:
1. Context as a product · 2. Tiered cost · 3. Charter > Spec · 4. Loop fechado por métrica · 5. SoC brutal · 6. **Multi-tenant Tier 0 IRREVOGÁVEL** · 7. Transparência · 8. Confiabilidade com fallback

Lista completa de ADRs canon via tool MCP `decisions-search` (default: só ativas — convenção lifecycle [ADR 0095](memory/decisions/0095-skills-tiers-convencao-interna.md)).

## Constituição UI v2 (4 camadas · UI-0013)

Documento mãe UI: **[ADR UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)** (accepted 2026-05-24).

Hierarquia: **Fundações** (tokens cor/tipo/espaço · imutável via ADR) → **Shell** (AppShellV2 + PageHeader · 1× pro app) → **Padrão de Tela** ([PT-01 Lista](memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md) · 5-7 templates) → **Módulo** (varia). Camada superior **herda** das inferiores e **nunca contradiz**.

Regra-mestre: pedido vago = agente **pergunta** antes de implementar (skill `wagner-request-refiner` + agente `wagner-understand` operacionalizam). Sidebar light **DEFINITIVO** ([UI-0019](memory/requisitos/_DesignSystem/adr/ui/0019-sidebar-light-definitivo-supersede-0009-0014.md) — Wagner 2026-07-07 "revogue as anteriores"; supersede UI-0009 + UI-0014; dark-sidebar de protótipo NUNCA gera gap).

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
| Ratificação ADR (`proposto→aceito`) | PR flip SÓ da linha `status:` + índice regenerado + **label `adr-metadata-normalization`** (exceção ADR 0257 — sem ela o gate Append-only falha). Merge [W] = ato. Receita: [memory/decisions/README.md](memory/decisions/README.md) |

## Onde NÃO inventar (Tier 0)

Detalhes em `memory/proibicoes.md`. Resumo:
- Tokens MCP, schema `mcp_audit_log`, ADRs CANON, `business_id` global scope
- Centrifugo + FrankenPHP runtime CT 100 ([ADR 0058](memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md))
- Hostinger ≠ CT 100 separação ([ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md))
- ZERO auto-mem privada ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md))
- **Expor/rodar** `laravel/octane`/MCP tools no Hostinger (os pacotes vivem no vendor do deploy, mas runtime/daemon/tool exposta lá NUNCA — `MCP_TOOLS_EXPOSED=false`)
- **F3 (Cowork → Inertia/React):** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) antes de Edit/Write em `Modules/<Mod>/Http/Controllers/*.php` ou `resources/js/Pages/<Mod>/<Tela>.tsx` — 6 meta-anti-padrões + 15 técnicos catalogados (sessão 2026-05-09 batch Financeiro rejeitado)
- **Fonte de design ≠ Figma** ([ADR 0299](memory/decisions/0299-figma-nao-e-fonte-de-design.md)): fonte = protótipo Cowork (`prototipo-ui/`) + Design System + charter. Figma/Notion/screenshot/link **só com Wagner explícito** ("figma") — bloqueado por `block-figma-without-optin` (PreToolUse fail-closed). Resolva a fonte em [INDEX-DESIGN-MEMORIAS.md §0](memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md)

## Métricas de saúde

Rodar `php artisan jana:health-check` (ou ver schedule daily 06:00 BRT em `app/Console/Kernel.php`).
5 checks SQL: multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h, pii_leak_in_assistant_responses, profile_distiller_drift.

Se algum falhar → investigar `storage/logs/laravel.log` ALERT entries.

## Suporte ao usuário

- `/help`, `/clear`, `/compact` (slash commands Claude Code)
- Reportar bug: https://github.com/anthropics/claude-code/issues

---
**Última atualização:** 2026-06-02 — Passo **4b** always-read de design-memory: [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](prototipo-ui/PROCESSO_MEMORIA_CC.md) + [`memory/LICOES_CC.md`](memory/LICOES_CC.md) (método anti-regressão do loop Cowork; defesas mecânicas DS-GUARD §8 + integrity-check §15). Landeado via handoff Cowork, autorizado por Wagner.

**2026-05-24** — Constituição UI v2 ([ADR UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) + [UI-0014](memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md)) aceita. Adicionado passo 4 protocolo UI + seção Hierarquia UI.

**2026-05-06** — Constituição v2 ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)) aceita. CLAUDE.md reescrito de 289 → ~85 linhas (Anthropic 2026 best-practice).
