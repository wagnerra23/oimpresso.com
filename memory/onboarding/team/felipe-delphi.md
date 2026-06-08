---
slug: onboarding-felipe-delphi
title: "Onboarding Felipe — Delphi legacy + migração WR Comercial"
type: onboarding
authority: canonical
lifecycle: ativo
owner: wagner
target_persona: felipe
trust_level: L2
last_updated: 2026-05-15
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

# Felipe — Onboarding

## Quem você é aqui

- **Tier:** L2 OPERATOR
- **Papel:** Dev Delphi WR Comercial legacy + migração pro oimpresso (Laravel 13.6 + PHP 8.4) + suporte técnico
- **Wagner é seu L0 supervisor** — toda dúvida arquitetural escala. Wagner aprova merge de qualquer PR seu.
- **Você NÃO é DPO nem L1 governance** — não toca Constituição/SRS/policies.

## O que você pode tocar (modules_write)

- `Modules/Officeimpresso/` — espelho/import do WR Comercial Delphi
- `Modules/OficinaAuto/` — cliente legacy Martinho (CNAE 4520-0/01) candidato migração
- `Modules/ComunicacaoVisual/` — 6 saudáveis OfficeImpresso candidatos (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart)
- `memory/legacy-delphi/*` — hub canônico Delphi (você vai popular)
- Suporte read-only em qualquer Modules/<X>/ pra investigar bugs

## O que você NÃO pode tocar (modules_blocked)

- `Modules/Connector` + `Modules/Superadmin` — L0 only (só Wagner)
- `Modules/Governance` + `Modules/ADS` + `Modules/TeamMcp` — L1 governance (só Wagner)
- `memory/decisions/NNNN-*.md` existentes — append-only IRREVOGÁVEL. Você pode CRIAR ADR nova com `supersedes: [NNNN]`
- `memory/governance/CONSTITUTION.md` — supremo, só Wagner via ADR + version bump
- `memory/proibicoes.md`, `memory/regras-time.md` — canon Tier 0, só Wagner
- Production DB DDL direto — sempre migration PHP

## Skills auto-load esperadas (Tier A always-on no seu Claude Code)

- `brief-first` — chama brief-fetch PRIMEIRO em toda sessão
- `mcp-first` — tools MCP antes de Read/Glob filesystem
- `multi-tenant-patterns` — business_id Tier 0 IRREVOGÁVEL
- `commit-discipline` — 1 PR = 1 intent, ≤300 linhas, sem PII
- `preflight-modulo` — pré-flight leitura SPEC/RUNBOOK/CAPTERRA antes de Edit em Modules/<X>/

## Skills auto-trigger por description (Tier B) você verá

- `officeimpresso-source-analysis` — lê código Delphi .pas em D:\Programas\WR Comercial\app\
- `officeimpresso-financial-snapshot` — extrai receita/despesa de Firebird via Python firebird-driver
- `como-integrar` — antes de implementar feature, mapeia onde já está parcialmente feita
- `criar-modulo` — checklist 8 peças obrigatórias se for criar Modules/<X> novo

## Primeiro dia — checklist (ordem)

1. ☐ Aceitar token MCP de Wagner (chega via Vaultwarden, NUNCA email)
2. ☐ Configurar Claude Code com token (skill `oimpresso-team-onboarding` te guia)
3. ☐ Rodar `brief-fetch` — primeiro comando de toda sessão
4. ☐ Ler [CLAUDE.md](../../../CLAUDE.md) inteiro
5. ☐ Ler [memory/why-oimpresso.md](../../why-oimpresso.md) + [what-oimpresso.md](../../what-oimpresso.md) + [how-trabalhar.md](../../how-trabalhar.md) + [proibicoes.md](../../proibicoes.md)
6. ☐ Ler [governance/CONSTITUTION.md](../../governance/CONSTITUTION.md) — 10 artigos
7. ☐ Ler [governance/TRUST-TIERS.md](../../governance/TRUST-TIERS.md) — entender seu L2
8. ☐ Ler [requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](../../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) + runbooks Officeimpresso
9. ☐ Ler hub Delphi `memory/legacy-delphi/_INDEX.md` (outro agent está criando)
10. ☐ Rodar `my-work` — ver suas tasks atribuídas
11. ☐ Primeira semana: PR pequeno (≤100 linhas) pra calibrar — Wagner revisa sync

## Workflow Tier 0 — 3 fases (IRREVOGÁVEL)

Cada vez que mexer em `Modules/<X>/`:

**FASE 1 PRÉ-FLIGHT** — ANTES de qualquer Edit:
- Ler `memory/requisitos/<X>/SPEC.md` (US-XXX-NNN)
- Ler `memory/requisitos/<X>/RUNBOOK*.md` (se MWART)
- Ler `memory/requisitos/<X>/CAPTERRA*.md` (escopo aprovado)
- Ler `memory/requisitos/<X>/BRIEFING.md` (estado consolidado)
- ADRs via `decisions-search query:"<modulo lowercase>"`

**FASE 2 DURING** — mexendo:
- Commit incremental por step lógico
- `git push` WIP a cada ~30min
- `TodoWrite` mark completed após cada step
- NUNCA `git checkout` outra branch sem `stash` ou `commit`

**FASE 3 POST** — terminou:
- PR no git → CI verde → review Wagner → merge
- Atualizar BRIEFING.md do módulo (skill `brief-update` auto-trigger)

## Vetores de drift catalogados que VOCÊ pode causar (cuidado)

| Vetor | Como acontece | Defesa |
|---|---|---|
| **Descoberta Delphi não vira git** | Você lê `.pas` ou Firebird, anota local, esquece PR | Template `memory/legacy-delphi/<descoberta>.md` obrigatório; PR template com checklist "anotou descoberta nova?" |
| **Edit em prod via SSH** | "ajuste rápido" no Hostinger | PROIBIDO. Via git pull do canônico apenas. Hook `block-destructive` bloqueia comandos perigosos. |
| **Tinker em prod sem commit** | `php artisan tinker` direto → `Cache::put`/`User::update` | PROIBIDO. Use seeder OR comando artisan idempotente + commit |
| **Drift de Module Charter** | Cria Controller fora de `Modules/<X>/SCOPE.md.contains[]` | Hook `block-module-drift.ps1` (warn 4 semanas, depois block) + CI gate |
| **PII em commit/log** | CPF/CNPJ cliente vaza | CI `pii-scan` bloqueia merge; use `[REDACTED]` ou `PiiRedactor` |

## Quando escalar pro Wagner

- Toda decisão arquitetural nova (não há ADR cobrindo)
- Tocar `Modules/<X>/` fora seu modules_write
- Qualquer mudança em fiscal (NFe/NFSe/SPED) — mesmo dentro Officeimpresso, fiscal é L3 Eliana[E]
- Merge de PR seu (sempre Wagner aprova)
- Bug em prod afetando ROTA LIVRE (biz=4 piloto, 99% volume)
- Cliente legacy WR Comercial com problema migração

## Migração Delphi → Laravel — pattern

Você vai ler MUITO `D:\Programas\WR Comercial\app\*.pas` + Firebird databases. Procedimento:

1. Skill `officeimpresso-source-analysis` lê código Delphi (fonte autoritativa)
2. Skill `officeimpresso-financial-snapshot` extrai schema Firebird via Python firebird-driver
3. Documenta descoberta em `memory/legacy-delphi/<area>-<descoberta>.md` (template do hub)
4. Mapeia Delphi → Laravel: tabela Firebird X → Eloquent Modules/Officeimpresso/Entities/Y; SQL Delphi proc Z → Service Modules/Officeimpresso/Services/W
5. PR de migração incremental — sempre mantém Delphi rodando em paralelo (zero impacto físico app Delphi — Constituição § ADR 0053)

## Recursos pra você

- **Vaultwarden** (`vault.oimpresso.com`) — credenciais (NUNCA git)
- **MCP server** (`mcp.oimpresso.com`) — tools `brief-fetch`, `my-work`, `decisions-search`, `memoria-search`
- **GitHub** (privado) — repo principal `oimpresso.com`
- **Hostinger SSH** (warm-up + retry — flaky, ver `memory/how-trabalhar.md`)
- **CT 100 Proxmox** (Tailscale SSH) — daemons, MCP server, Meilisearch, Vaultwarden
- **D:\Programas\WR Comercial\app\\*.pas** — código Delphi legacy

## Como pedir ajuda

- Wagner via Claude Code chat (real-time, prefere PT-BR sem floreio)
- Comentário em PR do GitHub (assíncrono)
- Skill `como-integrar` antes de implementar feature parcial — economiza retrabalho

## Histórico

- **v1.0** (2026-05-15) — onboarding inicial pré-entrada Felipe no MCP
