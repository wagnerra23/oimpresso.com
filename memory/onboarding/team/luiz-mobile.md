---
slug: onboarding-luiz-mobile
title: "Onboarding Luiz — Iniciante + mobile (serviço inicial)"
type: onboarding
authority: canonical
lifecycle: ativo
owner: wagner
target_persona: luiz
trust_level: L3
last_updated: 2026-05-15
mentor: felipe
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0011-alinhamento-padrao-jana
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

# Luiz — Onboarding

## Quem você é aqui

- **Tier:** L3 VERTICAL (mais conservador inicial — pra calibrar antes de subir pra L2)
- **Papel:** Iniciante no time + dev IA-pair criando módulo mobile (serviço inicial conectado ao oimpresso via API)
- **Wagner é seu L0 supervisor.** **Felipe é seu mentor de pair sync** primeira semana (PR review).
- **Você NÃO merge PR sozinho** ([regra dura `memory/regras-time.md`](../../regras-time.md)) — Felipe ou Wagner aprova.

## O que você pode tocar (modules_write)

- `Modules/Mobile/` — quando criar (siga skill `criar-modulo` + ADR 0011 imitar Jana/Repair/Project)
- `resources/js/Pages/Mobile/*` — se mobile tiver web companion
- `app/Http/Resources/Mobile/*` — API resources
- `routes/api.php` — endpoint API mobile (apenas SEU módulo, sem mexer em endpoints existentes)
- Charters `*.charter.md` do seu módulo

## O que você NÃO pode tocar (modules_blocked)

- `Modules/Connector` + `Modules/Superadmin` — L0
- `Modules/Governance` + `Modules/ADS` + `Modules/TeamMcp` — L1
- `Modules/Jana`, `Modules/NfeBrasil`, `Modules/NFSe`, `Modules/Accounting`, `Modules/Financeiro` — L2/L3 outros donos
- `Modules/Officeimpresso`, `Modules/OficinaAuto`, `Modules/ComunicacaoVisual` — Felipe (Delphi)
- **Migrations em prod** — sempre Felipe ou Wagner aprova ANTES de gerar
- `memory/decisions/NNNN-*.md` existentes — append-only
- `memory/governance/*`, `memory/proibicoes.md`, `memory/regras-time.md` — só Wagner
- Production deploy — você nunca faz solo

## Skills auto-load esperadas (Tier A always-on)

- `brief-first` — chama brief-fetch PRIMEIRO em toda sessão
- `mcp-first` — tools MCP antes de Read/Glob filesystem
- `multi-tenant-patterns` — business_id Tier 0 IRREVOGÁVEL
- `commit-discipline` — 1 PR = 1 intent, ≤300 linhas, sem PII
- `preflight-modulo` — pré-flight leitura SPEC/RUNBOOK/CAPTERRA antes de Edit em Modules/<X>/
- `charter-first` — toda Page Inertia .tsx precisa charter ao lado

## Skills auto-trigger (Tier B) que você verá

- `criar-modulo` — quando for criar `Modules/Mobile/` (checklist 8 peças obrigatórias)
- `charter-first` — toda Page Inertia .tsx precisa charter ao lado
- `como-integrar` — antes de implementar feature, mapeia o que já existe
- `mwart-process` — se for migrar Blade → React no mobile companion

## Primeiro dia — checklist (ordem)

1. ☐ Aceitar token MCP de Wagner (Vaultwarden, NUNCA email)
2. ☐ Configurar Claude Code (skill `oimpresso-team-onboarding`)
3. ☐ Rodar `brief-fetch` — primeiro comando de toda sessão
4. ☐ Ler [CLAUDE.md](../../../CLAUDE.md) + [why](../../why-oimpresso.md) + [what](../../what-oimpresso.md) + [how-trabalhar](../../how-trabalhar.md) + [proibicoes](../../proibicoes.md)
5. ☐ Ler [governance/CONSTITUTION.md](../../governance/CONSTITUTION.md) + [TRUST-TIERS.md](../../governance/TRUST-TIERS.md) — entender seu L3
6. ☐ Ler `Modules/Jana/SCOPE.md` + `Modules/Repair/SCOPE.md` + `Modules/Project/SCOPE.md` — **MÓDULOS REFERÊNCIA pra imitar** ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md))
7. ☐ Ler `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` — checklist 8 peças quando criar Modules/Mobile
8. ☐ Felipe te paira primeira semana — abre sessão Claude Code juntos
9. ☐ Primeiro PR: ≤50 linhas, scaffold inicial Modules/Mobile com 8 peças mínimas
10. ☐ Wagner + Felipe aprovam merge

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
- Iniciante: **mostra pro Felipe a cada commit** primeira semana antes de push

**FASE 3 POST** — terminou:
- PR no git → CI verde → review **Felipe primeiro** → review Wagner → merge
- Atualizar BRIEFING.md do módulo (skill `brief-update` auto-trigger)

## Vetores de drift catalogados que VOCÊ pode causar (cuidado iniciante)

| Vetor | Como acontece | Defesa |
|---|---|---|
| **Cria módulo divergente** | Não imita Jana/Repair/Project; estrutura própria | ADR 0011 obrigatório + skill `criar-modulo`. Felipe revisa estrutura antes de Wagner aprovar merge. |
| **Esquece business_id global scope** | Eloquent Model novo sem global scope tenant | Skill `multi-tenant-patterns` Tier A. Pest test biz=1 obrigatório. CI bloqueia. |
| **Skill MWART não disparou** | Edita Page .tsx sem ler charter + sem RUNBOOK | Hook `block-mwart-violation` bloqueia. Skill `mwart-process` é único caminho ADR 0104. |
| **PR ≥300 linhas** | "É escopo pequeno, junta tudo" | Skill `commit-discipline` Tier A. 1 PR = 1 intent. |
| **Mexe em endpoint existente** | API mobile precisa de campo, edita Controller de outro módulo | NÃO. Crie endpoint no SEU Modules/Mobile/Http/Controllers/Api/. Pede Felipe se cross-module. |
| **Tinker em prod sem commit** | `php artisan tinker` direto pra "testar coisinha" | PROIBIDO. Use seeder OR comando artisan idempotente + commit. |
| **Token/credencial em commit** | `.env` ou senha hardcoded "só pra testar" | CI `pii-scan` + hook `block-secrets` bloqueia. Use Vaultwarden. |

## Pair sync com Felipe (primeira semana)

- 1 hora/dia call ou Claude Code shared session
- Felipe explica: SCOPE.md, charter, multi-tenant, Tier 0, MWART, commit-discipline
- Felipe revisa cada PR seu ANTES de Wagner ver
- Após 2 PRs limpos (Felipe aprova sem cortar), Wagner avalia subir você pra L2 OPERATOR

## Quando escalar pro Felipe (mentor) ou Wagner

- **Felipe** — todo dia primeira semana, dúvida técnica/padrão, review PR antes Wagner
- **Wagner** — merge de PR (Felipe pode revisar mas Wagner aprova final), decisão arquitetural, escolha stack mobile (RN vs Capacitor vs Flutter)
- Bug em prod afetando ROTA LIVRE (biz=4 piloto, 99% volume) — Wagner direto
- Qualquer dúvida fiscal (NFe/NFSe/SPED) — Eliana[E] via Wagner

## Mobile (serviço inicial) — direção sugerida

- Stack alvo: provavelmente **React Native** ou **Capacitor** (consulta Wagner antes de codar)
- Backend: API REST em `routes/api.php` namespace `mobile`
- Auth: Sanctum tokens (consistente com MCP)
- Multi-tenant via header `X-Business-Id` validado contra token (skill `multi-tenant-patterns`)
- Primeiro feature alvo: provavelmente **consulta OS do cliente** (ConsultaOs já existe web — espelhar API)

> ⚠️ **NÃO comece a codar mobile sem ADR proposta aprovada Wagner.** Decisão arquitetural (RN vs Capacitor vs Flutter) precisa ADR canônica.

## Recursos pra você

- **Vaultwarden** (`vault.oimpresso.com`) — credenciais (NUNCA git)
- **MCP server** (`mcp.oimpresso.com`) — tools `brief-fetch`, `my-work`, `decisions-search`, `tasks-create`
- **GitHub** (privado) — repo principal `oimpresso.com`
- **Felipe** mentor (chat Claude Code direto, primeira semana 1h/dia)
- **Skills auto-load** que cobrem 80% das pegadinhas

## Como pedir ajuda

- Felipe via Claude Code chat (real-time primeira semana, prefere PT-BR)
- Wagner via Claude Code chat (decisão arquitetural, aprovação merge)
- Comentário em PR do GitHub (assíncrono)
- Skill `como-integrar` antes de implementar feature parcial — economiza retrabalho

## Histórico

- **v1.0** (2026-05-15) — onboarding inicial pré-entrada Luiz no MCP
