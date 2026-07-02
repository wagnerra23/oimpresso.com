# memory/ — Índice navegável (~2.300 docs)

> ℹ️ Contagens aqui são aproximadas (navegação). A lista canônica de ADRs é **GERADA**: [`decisions/_INDEX-GENERATED.md`](decisions/_INDEX-GENERATED.md) (ADR 0258).

> Mapa pra navegar `memory/`. Para **estado VIVO** (cycle ativo, tasks, brief), use tools MCP: `brief-fetch`, `my-work`, `cycles-active`, `decisions-search`.
> Documento canônico — atualizar quando criar nova categoria. Reorg: ver [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13](requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) §5 (G2).

## 🏛️ LEI MÁXIMA — a Constituição (ler ANTES de tudo)

> Documento **supremo** do projeto. Toda decisão, skill, ADR e código **herda** disto e **nunca contradiz**. Append-only (mudança só via nova ADR com `supersedes`).

| Doc | O que é | Status |
|---|---|---|
| **[ADR 0094 — Constituição v2](decisions/0094-constituicao-v2-7-camadas-8-principios.md)** | **MÃE** — 7 camadas + 8 princípios duros (Multi-tenant Tier 0 · Context-as-product · Charter>Spec · Loop fechado por métrica · SoC brutal · Transparência · Confiabilidade c/ fallback · Tiered cost) | aceito (vigente) |
| [governance/CONSTITUTION.md](governance/CONSTITUTION.md) | Versão operacional renderizada (artigos) | vigente |
| **[ADR UI-0013 — Constituição UI v2](requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)** | Constituição da **UI** (4 camadas: Fundações→Shell→Padrão de Tela→Módulo) | aceito |
| [ADR 0079](decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) · [ADR 0078](decisions/0078-constituicao-uma-frase-skill-unidade-evolucao.md) | Histórico — origem das 7 camadas + meta-skill "1 frase" | superseded por 0094 |

## 🗺️ COMO O SISTEMA FUNCIONA (arquitetura · escopo · responsabilidades)

> "Como funciona, qual escopo, para que serve, como reconstruir responsabilidades." Estilo **arc42** (vista geral → building blocks → deployment → crosscutting → decisões). As peças vivem em git e **delegam o detalhe à fonte viva** (não duplicar = não apodrece).

| Camada (arc42) | Doc canônico |
|---|---|
| **Vista geral + escopo + responsabilidade/módulo** | **[governance/ARCHITECTURE.md](governance/ARCHITECTURE.md)** — vista 1-slide · módulos estado/destino · **trust level por módulo** · princípios modulares · fases. **COMECE AQUI pra entender o sistema.** |
| **Building blocks (detalhe por módulo)** | `Modules/<X>/SCOPE.md` (36 — missão/responsabilidade de cada um) + [modulos/INDEX.md](modulos/INDEX.md) (catálogo auto-gerado por `php artisan module:specs`) |
| **Deployment / onde roda / como acessar** | **[reference/INFRA-ACESSO-CANON.md](reference/INFRA-ACESSO-CANON.md)** (Hostinger · CT100 · MCP · Meilisearch · deploy) |
| **Crosscutting** | [governance/](governance/) (Constituição · Trust-Tiers · Enforcement · Drift Framework → `governance:audit`) |
| **Decisões (por quê)** | [decisions/](decisions/) (ADRs Nygard) — via `decisions-search` |

> ⚠️ `01-project-overview.md` e `03-architecture.md` são **PontoWr2-era (PODRES — "Laravel 10 / Módulo de Ponto")**. NÃO usar; ver governance/ARCHITECTURE.md.

## 🎯 Norte, Protocolo & Skills Tier A (normativo — mesmo nível da Constituição)

> O que **decide o trabalho** e **como toda sessão DEVE operar**. Tão Tier 0 quanto a Constituição. Antes ficava ausente/enterrado no índice — regressão de priorização corrigida na auditoria 2026-05-29 (a Constituição foi só o 1º caso de uma classe).

| Doc | O que é |
|---|---|
| **[NORTE-ROI.md](NORTE-ROI.md)** | **Norte único** — meta R$ [redacted Tier 0]M/ano ([ADR 0022](decisions/0022-meta-5mi-ano-financeira.md)). Toda decisão passa por aqui antes de virar trabalho. + [11-metas-negocio.md](11-metas-negocio.md) |
| **[reference/PROTOCOLO-WAGNER-SEMPRE.md](reference/PROTOCOLO-WAGNER-SEMPRE.md)** | **REGRA ZERO (R1–R11)** — Tier 0 IRREVOGÁVEL, toda sessão DEVE executar. Skill `wagner-protocol-enforce` |
| **[how-trabalhar.md](how-trabalhar.md)** | Protocolo de sessão: `brief-fetch` → `my-work` → (charter) → trabalhar → `decide` → commit |
| **Skills Tier A (always-on)** | `brief-first` · `mcp-first` · `memory-first-secret-search` · `multi-tenant-patterns` · `commit-discipline` · `mwart-process` · `wagner-protocol-enforce` — convenção [ADR 0095](decisions/0095-skills-tiers-convencao-interna.md) · audit [s3-skills-audit](sprints/s3-constituicao/03-skills-audit.md) |
| **[proibicoes.md](proibicoes.md)** | Proibições Tier 0 + **Multi-tenant Tier 0** ([ADR 0093](decisions/0093-multi-tenant-isolation-tier-0.md), "pior bug possível") |

## Comece aqui (onboarding 7 docs)

| # | Documento | Quando ler |
|---|---|---|
| 0 | [`../CLAUDE.md`](../CLAUDE.md) | **Primeiro sempre** — primer agentes IA |
| 1 | [`why-oimpresso.md`](why-oimpresso.md) | Visão produto (ERP modular vertical, R$ [redacted Tier 0]M meta) |
| 2 | [`what-oimpresso.md`](what-oimpresso.md) | Stack (L13.6 + PHP 8.4 + Inertia v3 + React 19) |
| 3 | [`how-trabalhar.md`](how-trabalhar.md) | Protocolo sessão (brief-fetch → my-work → work) |
| 4 | [`proibicoes.md`](proibicoes.md) | Tier 0 IRREVOGÁVEIS |
| 5 | [`regras-time.md`](regras-time.md) | Time (Wagner / Maiara / Felipe / Luiz / Eliana) |
| 6 | [`how-bridge-cloud-local.md`](how-bridge-cloud-local.md) | Transferir trabalho nuvem ↔ local |

## 🔐 Secrets & Credenciais (canon ÚNICO)

> ⛔ Tier 0: agente DEVE consultar PRIMEIRO antes de qualquer busca por token/API key/password. Skill `memory-first-secret-search` Tier A bloqueante.

- **[`_INDEX-SECRETS.md`](_INDEX-SECRETS.md)** — índice canon de TODOS secrets do projeto (~20 entradas): Hostinger DNS, Hostinger MySQL, MinIO CT 100, Vaultwarden, Centrifugo, Whatsmeow, Meta Cloud, Asaas, Sicoob, Mailgun, Anthropic, OpenAI, Langfuse, GitHub PAT. Ponteiro (não valor) + status (active/expired/pending/locked) + frequência rotação.
- Skills correlatas: [`memory-first-secret-search`](../.claude/skills/memory-first-secret-search/SKILL.md) (busca canônica) + [`hostinger-dns-autonomy`](../.claude/skills/hostinger-dns-autonomy/SKILL.md) (autonomia Hostinger).

## 🏛️ Governance & Decisões (~220 ADRs)

- **[decisions/](decisions/)** — todas ADRs Nygard, **append-only**. Status: `accepted | proposed | historical | superseded`
- [decisions/_INDEX-LIFECYCLE.md](decisions/_INDEX-LIFECYCLE.md) — índice oficial por lifecycle
- [decisions/_TEMPLATE.md](decisions/_TEMPLATE.md) · [decisions/_SCHEMA.md](decisions/_SCHEMA.md) — pra criar nova
- [decisions/proposals/](decisions/proposals/) — drafts pré-aceite

**ADRs canônicas mais citadas:**
- [0094 Constituição v2](decisions/0094-constituicao-v2-7-camadas-8-principios.md) (mãe — 7 camadas + 8 princípios)
- [0093 Multi-tenant Tier 0](decisions/0093-multi-tenant-isolation-tier-0.md) · [0095 Skills Tiers](decisions/0095-skills-tiers-convencao-interna.md)
- [0104 Processo MWART canônico](decisions/0104-processo-mwart-canonico-unico-caminho.md) · [0114 Cowork loop](decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [0121 Modular vertical](decisions/0121-oimpresso-modular-especializado-por-vertical.md) · [0143 FSM Pipeline LIVE](decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [0061 Zero auto-mem](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) · [0131 Tiering memória](decisions/0131-tiering-memoria-canonico-local-segredo.md)
- [0144 DB canon SPEC template](decisions/0144-tasks-db-canonico-spec-template.md) — mais recente

[Lista completa via MCP `decisions-search`]

## 📦 Requisitos por Módulo (~70 pastas em requisitos/)

`memory/requisitos/<Mod>/SPEC.md` é canônico por módulo. Estrutura típica: `SPEC.md` + `CAPTERRA-FICHA.md` + `CAPTERRA-INVENTARIO.md` + `RUNBOOK-*.md` + `adr/` + `audits/`.

**Verticais especializados** ([ADR 0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md)):
- [Vestuario/](requisitos/Vestuario/) — ✅ em produção (ROTA LIVRE biz=4, CNAE 4781-4/00)
- [ComunicacaoVisual/](requisitos/ComunicacaoVisual/) — 🟡 em construção (CNAE 1813-0/01)
- [OficinaAuto/](requisitos/OficinaAuto/) — ⏸️ aguardando sinal (Martinho candidato)
- [Autopecas/](requisitos/Autopecas/)

**Core comum:** _(⚰️ = pasta tombstoneada/HISTORICAL — estado vivo está no receptor indicado no BRIEFING da pasta)_
- [Jana/](requisitos/Jana/) — IA + memória (`ARCHITECTURE.md`, `RUNBOOK-*.md`, auditorias 2026-05-13) — ex-`Copiloto/` (rename ADR 0088; ghost-dir migrado 2026-07-01)
- [Financeiro/](requisitos/Financeiro/) · [FinanceiroAvancado/](requisitos/FinanceiroAvancado/) ⚰️→Financeiro · [NfeBrasil/](requisitos/NfeBrasil/) · [NFSe/](requisitos/NFSe/)
- [Repair/](requisitos/Repair/) (Kanban OS shared) · [Sells/](requisitos/Sells/) · [Purchase/](requisitos/Purchase/) · [Inventory/](requisitos/Inventory/) · [Produto/](requisitos/Produto/)
- [RecurringBilling/](requisitos/RecurringBilling/) · [MemCofre/](requisitos/MemCofre/) ⚰️→SRS · [Crm/](requisitos/Crm/) · [Chat/](requisitos/Chat/) ⚰️→Jana · [Whatsapp/](requisitos/Whatsapp/) · [EvolutionAgent/](requisitos/EvolutionAgent/) ⚰️MATAR

**Plataforma/auxiliares:**
- [ADS/](requisitos/ADS/) (decisão automatizada) · [Admin/](requisitos/Admin/) · [Auditoria/](requisitos/Auditoria/) · [BI/](requisitos/BI/) ⚰️MATAR · [Cms/](requisitos/Cms/) · [Comissao/](requisitos/Comissao/) · [Essentials/](requisitos/Essentials/) · [Garantia/](requisitos/Garantia/) · [Grow/](requisitos/Grow/) ⚰️MATAR · [Manufacturing/](requisitos/Manufacturing/) · [Marketplaces/](requisitos/Marketplaces/) · [Pcp/](requisitos/Pcp/) · [PontoWr2/](requisitos/PontoWr2/) ⚰️→Ponto · [ProjectMgmt/](requisitos/ProjectMgmt/) · [TaskRegistry/](requisitos/TaskRegistry/) ⚰️→TeamMcp · [Officeimpresso/](requisitos/Officeimpresso/) · [Accounting/](requisitos/Accounting/) · [Arquivos/](requisitos/Arquivos/) · [LaravelAI/](requisitos/LaravelAI/) ⚰️→Jana · [MemoriaAutonoma/](requisitos/MemoriaAutonoma/) ⚰️→Jana · [Mwart/](requisitos/Mwart/) · [SRS/](requisitos/SRS/)

**Infra (não-módulo):** [Infra/](requisitos/Infra/) — RUNBOOKs CT 100, Hostinger SSH, criar-modulo, branch protection, MWART-gate
**Cross-cutting:** [_DesignSystem/](requisitos/_DesignSystem/) · [_Ideias/](requisitos/_Ideias/) · [_processo/](requisitos/_processo/) · [_COMPARATIVOS_INDEX.md](requisitos/_COMPARATIVOS_INDEX.md) · [_TEMPLATE_capterra_ficha.md](requisitos/_TEMPLATE_capterra_ficha.md) · [_Roadmap_Faturamento.md](requisitos/_Roadmap_Faturamento.md) · [requisitos/INDEX.md](requisitos/INDEX.md)

## 📚 Knowledge & Reference

- **[reference/](reference/)** — conhecimento canon migrado de auto-mem (post-G1, ADRs [0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)/[0131](decisions/0131-tiering-memoria-canonico-local-segredo.md))
  - 🖥️ **[reference/INFRA-ACESSO-CANON.md](reference/INFRA-ACESSO-CANON.md)** — **mapa ÚNICO de acesso a TODA máquina** (CT 100 via `tailscale ssh root@ct100-mcp`, Hostinger SSH, deploy do MCP, Meilisearch, DNS, secrets). Claude NUNCA diz "não tenho acesso". + [hostinger.md](reference/hostinger.md)
- [modulos/](modulos/) — 44 specs auto-geradas (36 ativos · re-index 2026-05-29) via `php artisan module:specs` + [INDEX.md](modulos/INDEX.md) + [RECOMENDACOES.md](modulos/RECOMENDACOES.md)
- [governance/](governance/) — [CONSTITUTION.md](governance/CONSTITUTION.md), [TRUST-TIERS.md](governance/TRUST-TIERS.md), [IDENTITY-MESH.md](governance/IDENTITY-MESH.md), [ENFORCEMENT.md](governance/ENFORCEMENT.md), [MODULE-DRIFT-MIGRATION-PLAN.md](governance/MODULE-DRIFT-MIGRATION-PLAN.md)
- [comparativos/](comparativos/) — análises CAPTERRA + concorrentes (memória, RAG, sites)
- [audits/](audits/) — auditorias históricas ([2026-05-pre-sales/](audits/2026-05-pre-sales/))
- [research/](research/) — prospecção (auto, vestuário, OfficeImpresso receitas, sells heatmap, clientes-legacy)
- [sales/](sales/) — outbound tracking ([2026-05/](sales/2026-05/))
- [dominios/](dominios/) — glossários verticais ([wr-comercial/](dominios/wr-comercial/), [_patterns/](dominios/_patterns/), [_template/](dominios/_template/))
- [clientes-legacy/](clientes-legacy/) — perfis clientes legacy ([rota-livre.md](clientes-legacy/rota-livre.md))
- [mwart-inventory/](mwart-inventory/) — inventário migração Blade→React

## 📝 Sessions & Handoffs

- **[handoffs/](handoffs/)** — ~55 handoffs **append-only** ([ADR 0130](decisions/0130-handoff-append-only-mcp-first.md)). Sempre ler o mais recente.
- [08-handoff.md](08-handoff.md) — índice "Últimos handoffs" (top 5)
- **[sessions/](sessions/)** — ~227 session logs narrativos (YYYY-MM-DD-slug.md)
- [CHANGELOG.md](CHANGELOG.md) — eventos estruturais cronológicos (Keep a Changelog)

## 🌌 Sprints & Programs

- [sprints/ROTEIRO-MESTRE.md](sprints/ROTEIRO-MESTRE.md)
- [sprints/s1-daily-brief/](sprints/s1-daily-brief/) — Daily Brief ([ADR 0091](decisions/0091-daily-brief.md))
- [sprints/s2-os-listagem/](sprints/s2-os-listagem/) — MWART contract
- [sprints/s3-constituicao/](sprints/s3-constituicao/) — Constituição v2 ([ADR 0094](decisions/0094-constituicao-v2-7-camadas-8-principios.md)) + Skills audit
- [sprints/s6-charter-capterra/](sprints/s6-charter-capterra/) — Charters + metrics + ci-gate
- [sprints/research/](sprints/research/) — deep-dives s3-s7
- [cycles/](cycles/) — propostas cycle (estado VIVO via MCP `cycles-active`)

## 🔧 Onboarding legacy PontoWr2 (numerados 00-11)

Mantidos por compatibilidade (PontoWr2 origem do projeto). Para core moderno, ver [why/what/how acima](#comece-aqui-onboarding-7-docs).

- [00-user-profile.md](00-user-profile.md) · [01-project-overview.md](01-project-overview.md) · [02-technical-stack.md](02-technical-stack.md) · [03-architecture.md](03-architecture.md) · [04-conventions.md](04-conventions.md) · [05-preferences.md](05-preferences.md) · [06-domain-glossary.md](06-domain-glossary.md) · [07-roadmap.md](07-roadmap.md) · [08-handoff.md](08-handoff.md) · [09-modulos-ultimatepos.md](09-modulos-ultimatepos.md) · [11-metas-negocio.md](11-metas-negocio.md)
- [COMO_PEDIR_NOVA_TELA_OU_MODULO.md](COMO_PEDIR_NOVA_TELA_OU_MODULO.md) · [COMPARATIVO_TELAS_BLADE_VS_REACT.md](COMPARATIVO_TELAS_BLADE_VS_REACT.md) · [REQUISITOS_FUNCIONAIS_PONTO.md](REQUISITOS_FUNCIONAIS_PONTO.md) · [OPUS-MISSION-BRIEF.md](OPUS-MISSION-BRIEF.md) · [officeimpresso-spec.md](officeimpresso-spec.md) · [migrations.md](migrations.md)

## 🚨 Onde NÃO ir (Tier 0 IRREVOGÁVEL)

- **`Modules/<X>/`** = código vivo (use [CLAUDE.md](../CLAUDE.md) + tools MCP) ≠ `memory/requisitos/<Mod>/` (especificação)
- **ADRs CANON são append-only** — NUNCA editar `accepted`. Criar nova com `supersedes: [N]`
- **Auto-mem privada `~/.claude/projects/*/memory/`** bloqueada por hook ([ADR 0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)). Escape valves: [ADR 0131](decisions/0131-tiering-memoria-canonico-local-segredo.md)
- **PII real (CPF/CNPJ cliente)** NUNCA em commit/PR/log — use `[REDACTED]` ou `PiiRedactor`
- **`memory_backup/`** = arquivo histórico, não tocar
- **Tasks NÃO em markdown** ([ADR 0070](decisions/0070-jira-style-task-management-current-md-removed.md)) — use tools MCP `tasks-*`

---
**Última atualização:** 2026-05-29 — **auditoria de indexação & priorização** (Wagner). (1) Constituição promovida a LEI MÁXIMA. (2) Novo bloco "Norte, Protocolo & Skills Tier A" — NORTE-ROI, PROTOCOLO-WAGNER-SEMPRE, how-trabalhar e Skills Tier A estavam ausentes/enterrados (mesma classe de regressão da Constituição). (3) Contagens corrigidas (handoffs 15→~55, sessions 81→~227, ADRs 148→~220, docs 1.536→~2.300, modulos 29→44). Links quebrados de `requisitos/INDEX.md` (7) corrigidos. Pendência: contagens não têm regen automático (drift volta).
**2026-05-13** — reescrito de 64 linhas (stale, só PontoWr2) → mapa completo. Gap reportado em [AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md](requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md) §5 (G2 P0).
