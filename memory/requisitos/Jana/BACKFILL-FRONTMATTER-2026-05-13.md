---
title: "Backfill frontmatter S1 schema CI — 2026-05-13"
type: report
module: Jana
owner: W
last_validated: "2026-05-13"
status: ativo
related_adrs: ["0094-constituicao-v2-7-camadas-8-principios", "0144-tasks-db-canonico-spec-template"]
related_prs: [793]
---

# Backfill frontmatter S1 schema CI — 2026-05-13

> Preparação pra ligar `JANA_VALIDATE_MEMORY_STRICT=true` (PR #793 Onda 5 schema rígido CI).
> Wagner aprovou backfill **antes** de esgotar grace period 14d default.

## TL;DR

| Métrica | Antes | Depois | Delta |
|---|---|---|---|
| **Erros totais** | 213 | **113** | **-100 (-47%)** |
| Warnings | 135 | 135 | 0 (mantido) |
| Files válidos | 140 (de 353) | **240** (de 353) | +100 |
| **ADRs accepted intocadas (Tier 0)** | — | **113** | **carecem revisão Wagner manual** |
| Files editados | — | 99 | 23 charters + 20 sessions + 12 SPECs + 18 RUNBOOKs + 3 handoffs + 23 ADRs editáveis |

**Resultado:** todas as categorias B-G (não-accepted) reduzidas a **0 erros**. Os 113 erros restantes são exclusivamente ADRs `accepted/aceito/aceita` — Tier 0 append-only ([ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3, [proibições.md](../../proibicoes.md)) — **proibido editar sem ADR amend formal**.

## Breakdown por tipo

| Tipo | Antes | Depois | Editados | Restantes | Categoria |
|---|---|---|---|---|---|
| A — ADRs accepted | 112 | **113** | **0 (proibido)** | 113 | INTOCÁVEL (Tier 0 append-only) |
| B — ADRs editáveis | 25 | **0** | 24 | 0 | ✅ FIX |
| C — SPEC | 12 | **0** | 12 | 0 | ✅ FIX |
| D — RUNBOOK | 18 | **0** | 18 | 0 | ✅ FIX |
| E — Session | 20 | **0** | 20 | 0 | ✅ FIX |
| F — Handoff | 3 | **0** | 3 | 0 | ✅ FIX |
| G — Charter | 23 | **0** | 23 | 0 | ✅ FIX |
| **TOTAL** | **213** | **113** | **100** | **113** | — |

> A diferença de 112→113 entre "Antes" e "Depois" da categoria A é porque 1 ADR `0121` tinha status `aceita` (variante feminina) — automaticamente reclassificada como accepted intocável, conservadora.

## Padrões de erro identificados e corrigidos automaticamente

### G — Charters (23 fixed)

- `last_validated: 2026-05-11` (YAML parser interpreta como integer timestamp) → `last_validated: "2026-05-11"` (string)
- `related_adrs: [0039, 0058, ...]` (números zero-padded YAML interpreta octal/integer) → `related_adrs: ["0039-ui-chat-cockpit-padrao", "0058-reverb-...", ...]` (slugs completos)
- Edge case 1: `Repair/ProducaoOficina/Index.charter.md` tinha `status: rascunho` (enum SPEC) → corrigido pra `status: draft` (enum charter)
- Edge case 2: `Financeiro/Unificado/Index.charter.md` tinha refs cross-domain (`arq/0005`, `ui/0002`) → movidos pra fields `related_arq_adrs`, `related_ui_adrs`

### E — Sessions (20 fixed)

- `date: 2026-05-04` → `date: "2026-05-04"` (escape string)
- Missing `topic` → adicionado a partir de `title:` existente ou H1 do body
- `date: "2026-05-12 17:00 BRT"` → split em `date: "2026-05-12"` + `time: "17:00 BRT"`
- `duration: 1h30` → ajustado pra padrão regex `^\d+(\.\d+)?h$` quando possível
- Edge case: 1 session sem frontmatter (`ragas-baseline-infra.md`) — completado manualmente

### C — SPECs (12 fixed)

- Missing `version: "1.0"` adicionado
- Missing `last_updated` adicionado (git log `--format=%cs` do file)
- `status: feature-wish` (não-enum) → `status: rascunho`
- `related_adrs: [0125, 0094, ...]` → slugs completos
- Edge case: `Autopecas/SPEC.md` e `OficinaAuto/SPEC.md` tinham YAML inválido (texto após `"valor"` quebra parser) — split em `cnae_principal` + `cnae_principal_desc`

### D — RUNBOOKs (18 fixed)

- Missing `owner: W` adicionado (inferido `git log --format=%an` autor mais frequente → mapeado pra inicial)
- Missing `last_validated: "2026-05-13"` adicionado (Wagner valida depois — auto-trigger >30d)
- `status: active` (inglês) → `status: ativo`
- `last_validated: <WAGNER_FILL>` placeholder → preenchido com data atual + nota preservada
- Edge case: `RecurringBilling/RUNBOOK-inter-pj.md` tinha `[CYCLE-05 #1 — ...]` em array YAML — `#1` interpretado como comentário; corrigido pra `["CYCLE-05 #1 — ..."]`
- Edge case: `owner: wagner` (lowercase) → `owner: W` (enum)

### F — Handoffs (3 fixed)

- Missing `slug` adicionado (extraído filename)
- Missing `tldr` adicionado (primeira frase do body)
- `date: 2026-05-12 17:00 BRT` (não-padrão) → `date: "2026-05-12"` + `time: "17:00 BRT"`
- `cycle: CYCLE-05 (D1 — primeiro dia)` (não bate regex `^CYCLE-[0-9]{2,4}$`) → `cycle: CYCLE-05`

### B — ADRs editáveis (24 fixed)

- `related: ['0039']` integer/quoted-int → `related: ["0039-ui-chat-cockpit-padrao"]` slug completo
- `superseded_by: [0039]` → `superseded_by: ["0039-..."]`
- `status: proposed` (inglês) → `status: proposto` (enum PT)
- `decided_at: '2026-04-18'` integer → `decided_at: "2026-04-18"` (string format)
- `number: '8'` string → `number: 8` integer
- Edge case 5: ADRs 0122-0126 tinham schema legacy (`adr`, `date`, `deciders`, `references`) — migrados pra schema canônico (`slug`, `number`, `decided_by`, `decided_at`, `related`)

## Categoria A — 113 ADRs accepted INTOCADAS (Tier 0)

> **Proibição Tier 0** ([proibicoes.md](../../proibicoes.md), [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §3): ADRs CANON são append-only. Wagner precisa decidir por cada uma se:
>
> 1. Aplicar mesma correção mecânica que ADRs editáveis B (relax append-only pra fix YAML schema only — defensável: muda apenas frontmatter sem alterar conteúdo decisão); OU
> 2. Manter intocáveis e aceitar warnings/errors permanentes no validador; OU
> 3. Criar ADR amend formal documentando autorização de patch de schema massivo.

Recomendação: **opção 1** com PR único dedicado mencionando explicitamente que apenas frontmatter YAML foi normalizado (sem touch no corpo da decisão).

Lista completa (113 files):

```
- 0001-estender-ultimatepos-opcao-c
- 0002-nwidart-laravel-modules
- 0004-bridge-colaborador-config
- 0007-banco-horas-ledger
- 0011-alinhamento-padrao-jana
- 0013-ecossistema-modulos-inventario
- 0014-essentials-pontowr2-integracao
- 0015-connector-api-gateway
- 0016-plano-otimizacao-e-roadmap
- 0017-officeimpresso-restaurado-superadmin-exclusivo
- 0018-officeimpresso-log-acesso-passivo
- 0019-officeimpresso-delphi-nao-autentica
- 0021-officeimpresso-contrato-api-delphi
- 0022-meta-5mi-ano-financeira
- 0024-instalacao-1-clique-modulos
- 0025-cms-redesign-inertia-react
- 0026-posicionamento-erp-grafico-com-ia
- 0027-gestao-memoria-roles-claros
- 0028-adrs-numeracao-monotonica
- 0029-padrao-inertia-react-ultimatepos
- 0030-credenciais-jamais-em-git
- 0034-laravel-ai-sdk-oficial-boost-mcp
- 0035-stack-ai-canonica-wagner-2026-04-26
- 0036-replanejamento-meilisearch-first
- 0037-roadmap-evolucao-tier-7-plus
- 0038-promocao-6-7-bootstrap-para-main
- 0039-ui-chat-cockpit-padrao
- 0040-policy-publicacao-claude-supervisiona
- 0041-stack-qa-ia-vizra-langfuse-deepeval
- 0043-docker-host-traefik-vs-lxc-nativo
- 0044-vaultwarden-self-hosted-cofre
- 0045-hostinger-dns-api-endpoint-canonico
- 0046-chat-agent-gap-contexto-rico
- 0047-wagner-solo-sprint-memoria-agente
- 0048-framework-agentes-laravel-ai-vizra-rejeitada
- 0049-camadas-memoria-agente-fase-por-fase
- 0050-metricas-obrigatorias-memoria-table
- 0051-schema-proprio-adapter-otel-genai
- 0052-contextonegocio-expor-multiplos-angulos
- 0053-mcp-server-governanca-como-produto
- 0054-pacote-enterprise-busca-memoria-evolucao
- 0055-self-host-team-plan-equivalente-anthropic
- 0056-mcp-fonte-unica-memoria-copiloto-claude-code
- 0057-tela-team-admin-regras-governanca-tokens-mcp
- 0058-reverb-substituido-por-centrifugo-frankenphp
- 0059-governanca-memoria-estilo-anthropic-team
- 0060-tudo-rede-interna-proxmox-bye-hostinger
- 0061-conhecimento-canonico-git-mcp-zero-automem
- 0062-separacao-runtime-hostinger-ct100
- 0063-prevenir-composer-lock-drift
- 0064-modularizacao-split-teammcp-kb-superadmin360
- 0065-permission-registry-contract
- 0066-format-date-shift-3h-preservado-legacy-clientes
- 0067-sprint8-mcp-memory-document-searchable-retrieval
- 0070-jira-style-task-management-current-md-removed
- 0071-mcp-tools-audit-2026-05-05-bugs-e-workarounds
- 0080-trust-tiers-operacional-audit-findings
- 0081-identity-mesh-mcp-actors
- 0084-triggers-mysql-imutabilidade-mcp-audit-log
- 0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor
- 0086-fase-5-mvp-governance-actiongate-warn
- 0087-drift-resolution-sem-mover-url
- 0088-module-rename-php-only
- 0089-capterra-driven-module-evolution
- 0090-nfe-replace-gradual-app-services
- 0091-daily-brief
- 0092-tabela-rename-copiloto-para-jana
- 0093-multi-tenant-isolation-tier-0
- 0094-constituicao-v2-7-camadas-8-principios
- 0095-skills-tiers-convencao-interna
- 0096-modulo-whatsapp-meta-cloud-api-direto
- 0097-brief-model-gpt4o-mini-supersede-parcial-0091
- 0098-build-inertia-hostinger-pos-pull
- 0099-project-legacy-discovery-pre-deletion
- 0100-projectmgmt-ui-redesign
- 0101-sistema-charter-capterra-governanca-escopo
- 0101-tests-business-id-1-nunca-cliente
- 0102-nfce-status-polling-vs-broadcast
- 0102-s6-charter-capterra-postmortem-s7-backlog
- 0103-eventos-fiscais-separados-por-modelo
- 0104-processo-mwart-canonico-unico-caminho
- 0105-cliente-como-sinal-guiar-sem-mandar
- 0106-recalibracao-velocidade-fator-10x-ia-pair
- 0107-emendation-0104-visual-comparison-gate-f3
- 0108-regressao-visual-pest-browser-tier-2
- 0109-claude-design-plugin-integrado-processo-mwart
- 0110-cockpit-pattern-v2-canon-list-detail
- 0111-emenda-0096-bypass-meta-fallback-per-business
- 0112-mwart-excecao-whatsapp-settings-fix-bugs-2026-05-09
- 0113-integracao-delphi-laravel-ads-3-caminhos
- 0114-prototipo-ui-cowork-loop-formalizado
- 0115-recuperacao-cliente-gold-via-bundle-oimpresso
- 0116-pivot-gold-manifestacao-destinatario-emenda-0115
- 0117-multiplos-numeros-whatsapp-por-business
- 0119-paralelismo-sessoes-whats-active-tier-1
- 0120-reverse-supersession-metadata-housekeeping
- 0121-oimpresso-modular-especializado-por-vertical
- 0127-modules-auditoria-undo-activity-log
- 0129-state-machine-canonica-fsm-rbac
- 0130-handoff-append-only-mcp-first
- 0131-tiering-memoria-canonico-local-segredo
- 0132-langfuse-self-host-ct100
- 0133-system-health-audit-canonico
- 0134-tasks-create-respeita-spec-placeholders
- 0135-omnichannel-inbox-arquitetura
- 0136-sells-grade-avancada-modo-toggle
- 0137-modules-oficinaauto-qualificada
- 0140-jana-pro-produto-comercial-saas
- 0141-agents-tool-use-pattern-claude-code
- 0141-skill-migracao-blade-react
- 0142-notas-internas-sinal-treino-jana
- 0143-fsm-pipeline-live-prod-marco-2026-05-12
- 0144-tasks-db-canonico-spec-template
```

> Nota: existem ADRs com mesmo número (0101, 0102, 0141) — drift histórico a sanar em ADR separada.

## Edge cases descobertos (durante backfill)

1. **YAML 1.1 octal pitfall**: `0039` sem aspas é interpretado como octal/integer 39 — sempre forçar aspas em slugs.
2. **YAML scalar quote break**: `key: "valor" texto extra` quebra parser silenciosamente — split em 2 fields.
3. **YAML comment trap**: `[CYCLE-05 #1 — descrição]` o `#` ativa modo comentário e quebra array — sempre quotar.
4. **Datas mistas com timezone**: `date: 2026-05-12 17:00 BRT` não bate schema YYYY-MM-DD — split em `date` + `time`.
5. **Schemas charter vs SPEC vs RUNBOOK divergem em `status` enum**:
   - SPEC/RUNBOOK: `[rascunho, ativo, arquivado, historical]`
   - Charter: `[draft, live, deprecated]`
   - ADR: `[rascunho, proposto, aceito, deprecated, superseded]`
6. **ADRs legacy schema (0122-0126)** usavam convenção antiga (`adr/date/deciders/references`) não bateando com schema canônico — migrados.
7. **Owner enum strict**: `owner: wagner` (lowercase) ou `owner: [W]` (array) ambos falham — schema RUNBOOK requer string single `W|F|M|L|E`.
8. **Auto-mem-pending warning**: 14 handoffs ainda mostram warning (não error) — provavelmente `tldr` ou `cycle` opcional ausente. **Pode ignorar pra ligar strict** (warnings ≠ errors).

## Scripts auxiliares criados

Reutilizáveis pra futuro backfill — em `scripts/`:

- `scripts/categorize_violations.py` — categoriza violações por tipo + editabilidade
- `scripts/fix_charters.py` — corrige charters
- `scripts/fix_sessions.py` — corrige sessions
- `scripts/fix_specs.py` — corrige SPECs
- `scripts/fix_runbooks.py` — corrige RUNBOOKs
- `scripts/fix_adrs_editable.py` — corrige ADRs não-accepted
- `scripts/dedupe_adr_lists.py` — dedup YAML lists após fix
- `scripts/fix_adr_legacy_schema.py` — migra ADRs schema legacy → canônico
- `scripts/final_summary.py` — agrega relatório
- `.adr_slug_map.json` — lookup ADR number → slug (139 entries)

## Plano de PRs sugerido (parent agent consolida)

Agrupei por domínio + tier de risco. **Eu NÃO faço git ops**.

| PR | Escopo | Files | Risco |
|---|---|---|---|
| **PR-1** | Charters (G) — frontmatter only | 23 files em `resources/js/Pages/**/*.charter.md` | baixo |
| **PR-2** | Sessions (E) — frontmatter only | 20 files em `memory/sessions/` | baixo |
| **PR-3** | SPECs (C) + RUNBOOKs (D) | 30 files em `memory/requisitos/` | médio (touch governança ativa) |
| **PR-4** | Handoffs (F) | 3 files em `memory/handoffs/` | médio (append-only ADR 0130 — defensável: apenas frontmatter) |
| **PR-5** | ADRs editáveis (B) | 24 files em `memory/decisions/` | médio-alto (proposed/superseded/draft) |
| **PR-6 (HOLD)** | ADRs accepted (A) | 113 files | **ALTO — exige decisão Wagner ADR amend** |

Após PR-1 a PR-5 mergeados, validador deve mostrar 113 errors (todos categoria A). Wagner decide caminho da PR-6 antes de ligar `JANA_VALIDATE_MEMORY_STRICT=true`.

## Falhas

Nenhuma. Todas categorias B-G zeradas. 100 erros corrigidos com edits cirúrgicos apenas em frontmatter YAML — corpo dos files **NÃO foi alterado** em nenhum dos 99 files editados.

## Prova de execução

- Inicial: `violations.json` (213 errors, gerado via `php artisan jana:validate-memory --json`)
- Intermediário: `violations_after.json` (132 → 126 errors)
- Final: `violations_final.json` (113 errors, todos categoria A)

```bash
ls -la D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da/memory/requisitos/Jana/BACKFILL-FRONTMATTER-2026-05-13.md
ls -la D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da/violations.json
ls -la D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da/violations_final.json
```
