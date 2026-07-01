---
slug: onboarding-maiara-suporte
title: "Onboarding Maiara — Suporte Delphi + dev all-around"
type: onboarding
authority: canonical
lifecycle: ativo
owner: wagner
target_persona: maiara
trust_level: L2
last_updated: 2026-05-15
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
pii: false
---

# Maiara — Onboarding

## Quem você é aqui

- **Tier:** L2 OPERATOR
- **Papel:** Suporte Delphi (WR Comercial legacy) + dev all-around. "Faz tudo" — mas com disciplina.
- **Wagner é seu L0 supervisor** — toda dúvida arquitetural escala. Wagner aprova merge de qualquer PR seu.
- **Você NÃO é DPO nem L1 governance** — não toca Constituição/SRS/policies.
- **Versátil por design:** entra ticket de suporte WR Comercial legacy (Delphi/Firebird) E feature nova no oimpresso (Laravel). Mesma pessoa, contextos diferentes — disciplina de PRÉ-FLIGHT muda por área.

## O que você pode tocar (modules_write)

- `Modules/Crm/` — atendimento + tickets (seu chão principal — você triagar muito ticket aqui)
- `Modules/Sells/` — vendas legadas + novas (⚠️ **ROTA LIVRE biz=4 é cliente piloto, 99% volume Laravel** — cuidado especial em qualquer change)
- `Modules/Repair/` — Kanban OS (shared infra entre verticais — Vestuário/ComVis/OficinaAuto consomem)
- `Modules/Inventory/` — estoque
- `Modules/Purchase/` — compras
- Suporte **read-only** em qualquer `Modules/<X>/` pra investigar bugs reportados (lê pra entender, não edita sem cair na coluna acima)

## O que você NÃO pode tocar (modules_blocked)

- `Modules/Connector` + `Modules/Superadmin` — L0 only (só Wagner)
- `Modules/Governance` + `Modules/ADS` + `Modules/TeamMcp` — L1 governance (só Wagner)
- **`Modules/NfeBrasil` + `Modules/NFSe` + `Modules/Accounting` + `Modules/RecurringBilling`** — L3 fiscal/financeiro, só Eliana[E] (advogada+financeiro) ou Wagner. Você lê pra suportar; **NÃO mexe sem aprovação**.
- `Modules/Jana/` (Jana IA, ex-Copiloto) — só Wagner ou Luiz[L+C] com par; envolve memória persistente + LGPD
- `memory/decisions/NNNN-*.md` existentes — append-only IRREVOGÁVEL. Pode CRIAR nova com `supersedes:` mas Wagner aprova antes.
- `memory/governance/CONSTITUTION.md`, `memory/proibicoes.md`, `memory/regras-time.md`, `memory/why-oimpresso.md`, `memory/what-oimpresso.md`, `memory/how-trabalhar.md` — canon Tier 0, só Wagner
- `.claude/skills/`, `.claude/hooks/`, `.claude/agents/`, `.github/workflows/` — infra governança
- **Production DB DDL direto** (`ALTER TABLE`, `CREATE PROCEDURE` via tinker/phpMyAdmin/SQL prompt) — sempre migration PHP. Hook `block-destructive` bloqueia comandos perigosos.
- **Edição direta via SSH** em Hostinger ou CT 100 — sempre via git pull do canônico

## Skills auto-load esperadas (Tier A always-on — via SessionStart hook)

- `brief-first` — força `brief-fetch` primeiro
- `mcp-first` — tools MCP antes de Read/Glob/Grep filesystem
- `multi-tenant-patterns` — toda query precisa `business_id` (Tier 0 IRREVOGÁVEL)
- `commit-discipline` — 1 PR = 1 intent, ≤300 linhas, conventional commits, sem PII
- `preflight-modulo` — bloqueador antes de Edit/Write em `Modules/<X>/`

## Skills auto-trigger (Tier B) você verá frequentemente

- `ticket-triage` — triagem de atendimento (você usa MUITO — esse é seu pão diário)
- `module-completeness-audit` — antes de marcar US `done`, valida cobertura
- `oimpresso-team-onboarding` — primeira vez no Claude Code (1× setup)
- `oimpresso-stack` — primer da stack Laravel 13.6 + PHP 8.4 + Inertia + Pest
- `criar-modulo` — se Wagner pedir scaffold de módulo novo (raro pra você)
- `officeimpresso-source-analysis` — quando ticket WR Comercial legacy precisar entender comportamento Delphi real (lê `.pas` em `D:\Programas\WR Comercial\app\`)
- `officeimpresso-financial-snapshot` — discovery financeiro de cliente legacy via Firebird
- `wagner-request-refiner` — quando Wagner mandar lista de pedidos curtos, decompõe em tasks atômicas

## Primeiro dia — checklist (ordem rígida)

1. ☐ Aceitar token MCP de Wagner (chega via **Vaultwarden**, NUNCA email/WhatsApp/chat — feedback canon)
2. ☐ Configurar Claude Code com token (skill `oimpresso-team-onboarding` guia)
3. ☐ Rodar `brief-fetch` — **primeiro comando de toda sessão, sem exceção** (Tier A bloqueador)
4. ☐ Ler [CLAUDE.md](../../../CLAUDE.md) + [memory/why-oimpresso.md](../../why-oimpresso.md) + [what-oimpresso.md](../../what-oimpresso.md) + [how-trabalhar.md](../../how-trabalhar.md) + [proibicoes.md](../../proibicoes.md) + [regras-time.md](../../regras-time.md)
5. ☐ Ler [governance/CONSTITUTION.md](../../governance/CONSTITUTION.md) + [TRUST-TIERS.md](../../governance/TRUST-TIERS.md) + [IDENTITY-MESH.md](../../governance/IDENTITY-MESH.md)
6. ☐ Ler SPECs do seu chão: [requisitos/Crm/SPEC.md](../../requisitos/Crm/SPEC.md) + [Sells/SPEC.md](../../requisitos/Sells/SPEC.md) + [Repair/SPEC.md](../../requisitos/Repair/SPEC.md) + [Inventory/SPEC.md](../../requisitos/Inventory/SPEC.md)
7. ☐ Ler [reference/feedback-modulo-mexeu-registra-sempre.md](../../reference/feedback-modulo-mexeu-registra-sempre.md) — 5 vetores de drift + 7 defesas automáticas
8. ☐ Rodar `my-work` + `my-inbox` — tasks atribuídas + caixa entrada
9. ☐ Primeira semana: 1-2 tickets suporte (P1/P2) + 1 PR pequeno ≤100 linhas. Wagner revisa sync com você no início.

## Workflow Tier 0 — 3 fases (IRREVOGÁVEL)

Wagner Tier 0 IRREVOGÁVEL (2026-05-15): *"vai mexer no modulo ler briefing e se mexer salva o progresso. (...) mexe não registra altera sem ler as regras do modulo fica sempre errando, caramba se organiza caralho seja responsavel porra. vao entrar os outros no MCP e isso vai ficar uma zona caralho."*

**REGRA "mexeu, registra" sozinha NÃO é suficiente.** Workflow completo (3 fases obrigatórias):

| Fase | Quando | O que fazer | Sintoma de violação |
|---|---|---|---|
| **PRÉ-FLIGHT** | ANTES de qualquer Edit/Write em `Modules/<X>/` | Ler `SPEC.md` + `RUNBOOK*.md` + `CAPTERRA*.md` + ADRs relacionadas + charter da página + skills aplicáveis | "Vou mexer rápido sem ler" → bug em prod |
| **DURING** | Mexendo no código | Commit incremental por step lógico; `git push` WIP a cada ~30min; **NUNCA** `git checkout` outra branch sem `stash` ou `commit` | "trabalho de 2h perdido" / "esqueci de commitar" |
| **POST** | Mexeu | PR no git + CI verde + Wagner aprova merge + docs canon atualizados (BRIEFING.md, SPEC.md se mudou contrato) | "depois eu commito" / drift entre prod e git |

**PRÉ-FLIGHT leitura obrigatória por tipo de Edit (você):**

| Vai editar... | LEIA ANTES |
|---|---|
| `Modules/Crm/Http/Controllers/...` | `memory/requisitos/Crm/SPEC.md` + skill `ticket-triage` |
| `Modules/Sells/Http/Controllers/...` | `memory/requisitos/Sells/SPEC.md` + ADR 0143 FSM (se touch `current_stage_id`) + skill `multi-tenant-patterns` |
| `Modules/Repair/Http/Controllers/...` | `memory/requisitos/Repair/SPEC.md` + ADR 0143 FSM + RUNBOOK do Kanban |
| `Modules/Inventory/...` | `memory/requisitos/Inventory/SPEC.md` |
| `resources/js/Pages/<X>/<Tela>.tsx` | charter `<Tela>.charter.md` + skill `mwart-process` (ADR 0104) |
| `Modules/<X>/Database/Migrations/...` | ADR 0093 (multi-tenant Tier 0) + Schema existente |
| Service/Job que toca prod biz=1 | ADR 0101 (tests usam biz=1, NUNCA cliente real biz=4) + skill `multi-tenant-patterns` |

**Por que isso importa MAIS pra Maiara:** você é **versátil "faz tudo"** — risco maior de pular PRÉ-FLIGHT por reflexo "rápido pra fechar ticket". Resistir.

## Vetores de drift catalogados que VOCÊ pode causar (cuidado especial)

| Vetor | Como acontece | Defesa |
|---|---|---|
| **"Ajuste rápido em prod"** (vetor #1 catalogado) | Cliente liga pedindo, você abre tinker/phpMyAdmin/SSH e edita direto pra "resolver agora" | PROIBIDO Tier 0 IRREVOGÁVEL. Sempre via migration PHP, seeder ou comando artisan idempotente. Hook `block-destructive` bloqueia comandos perigosos. Se Wagner aprovar emergência: marca log `// DRIFT TIER 0 — Wagner aprovou <data>, follow-up PR <hash>` + spawna PR follow-up imediato. |
| **PR sem ler SPEC do módulo** | Pula `preflight-modulo`, edita Controller, quebra contrato US-XXX-NNN existente | Skill `preflight-modulo` Tier A bloqueia mentalmente; hook `modulo-preflight-warning` avisa. PARA, lê SPEC.md, depois edita. |
| **Mexe em fiscal sem L3** | Cliente reclama de NFe rejeitada, você abre `Modules/NfeBrasil/` pra ajustar | NÃO. **Escala pra Eliana[E] ou Wagner.** NfeBrasil/NFSe/Accounting/RecurringBilling é L3. Você pode LER pra suportar o ticket (entender o erro, copiar log redacted pro cliente), nunca editar. |
| **PII em commit/log/ticket** | CPF/CNPJ cliente em ticket Crm vaza pro git via commit message ou log estruturado | CI `pii-scan` bloqueia merge; use `[REDACTED]` ou `PiiRedactor` helper. Em ticket: substituir o CPF (formato `XXX.XXX.XXX-XX`) por `[CPF-REDACTED]` antes de copiar pra commit/PR. |
| **Edit em ADR existente** | "Só ajustando contexto da ADR 0143" | PROIBIDO append-only. Crie **nova** ADR com `supersedes: [0143]`. Hook `block-memory-drift` bloqueia edit em accepted records. |
| **Touch em ROTA LIVRE biz=4 sem aviso prévio** | Mudança em `Modules/Sells/` afeta cliente piloto 99% volume sem canary | Cutover sempre com aviso prévio Larissa + canary 7d (mwart-process F5). Pequena mudança backend → smoke local biz=1 (ADR 0101) primeiro, depois deploy. |
| **Tests usando biz=4 em vez de biz=1** | Copy-paste de teste antigo, esquece de trocar `business_id` | ADR 0101 IRREVOGÁVEL: testes SEMPRE biz=1 (placeholder), NUNCA cliente real biz=4. Pest fixture deve criar business novo, não reutilizar prod. |

## Quando escalar pro Wagner (ou Eliana[E] pra fiscal)

- **Toda decisão arquitetural nova** (não há ADR cobrindo o problema) → Wagner
- **Bug fiscal** (NFe/NFSe/SPED/Contábil/Asaas refund) → Eliana[E] primeiro (advogada+financeiro), depois Wagner
- **Bug em prod afetando ROTA LIVRE (biz=4)** → Wagner imediato + comunicação Larissa
- **Merge de PR** → sempre Wagner aprova (regra L2)
- **Cliente legacy WR Comercial** com problema que exige mudança no Delphi (`.pas`) → Wagner decide se vale tocar (legacy off-limits salvo emergência); pra investigar comportamento use skill `officeimpresso-source-analysis`
- **Cliente novo querendo customização** que sai do escopo Vestuario/ComunicacaoVisual canônico → escala Wagner antes de prometer ao cliente
- **Feature nova solicitada por cliente** sem sinal qualificado (ADR 0105 — backlog só com cliente pagante + sinal) → Wagner decide se vira ADR feature-wish ou US ativa
- **Suspeita de incidente WhatsApp** (daemon CT 100 ban/connection failure) → agent `whatsapp-doctor` ou Wagner; **NÃO pareie canal novo sem skill `baileys-update-procedure`**

## Padrão de ticket suporte → task MCP

1. Cliente abre ticket (via `Modules/Crm/`, WhatsApp, ou email centralizado)
2. Skill `ticket-triage` ativa — classifica **P0/P1/P2/P3**:
   - **P0** = prod down ROTA LIVRE (biz=4) ou fiscal bloqueado → Wagner imediato (telefone/WhatsApp direto)
   - **P1** = bug afetando workflow crítico sem workaround → você ataca hoje, comunica cliente
   - **P2** = bug com workaround OU melhoria solicitada → entra fila normal
   - **P3** = "seria legal ter" → vira ADR feature-wish (ADR 0105) se sem sinal qualificado, ou task MCP se cliente pagante
3. Se P0 → Wagner imediato (não tente sozinha)
4. Se bug com fix conhecido → você abre task MCP via `tasks-create` (skill `mcp-first`), faz PR, Wagner aprova merge
5. Se feature nova → escala pro Wagner decidir backlog (ele cruza com cycles-active + cliente pagante)
6. **Sempre:** `tasks-comment <ID>` ao avançar; `tasks-update <ID> status:done` ao fechar

## Caso especial: ticket WR Comercial legacy (Delphi/Firebird)

Você é a especialista Delphi do time. Quando ticket diz "no WR Comercial não tá funcionando":

1. **NÃO assume** que é o oimpresso novo (Laravel). Pergunta versão / printscreen.
2. Se for legacy Delphi → ativar skill `officeimpresso-source-analysis` pra ler `.pas` em `D:\Programas\WR Comercial\app\` (fonte autoritativa, não chuta via Firebird)
3. Se precisar entender dados financeiros do cliente → skill `officeimpresso-financial-snapshot` (Firebird via firebird-driver Python; já tem queries-template validadas)
4. Bug no Delphi que exige mudança no `.pas`: **escala Wagner**. Legacy é off-limits salvo decisão dele (custo migração > custo fix em alguns casos).
5. Se cliente legacy pode migrar pro oimpresso novo → flag pra Wagner (pré-vendas + discovery)

## Recursos pra você

- **Vaultwarden** (`vault.oimpresso.com`) — credenciais (token MCP, senhas SSH, API keys). **NUNCA git, NUNCA email, NUNCA chat.**
- **MCP server** (`mcp.oimpresso.com`) — tools `brief-fetch`, `my-work`, `my-inbox`, `tasks-create`, `tasks-update`, `tasks-comment`, `decisions-search`, `cycles-active`, `cycle-goals-track`
- **GitHub** (repo privado) — PRs com conventional commits + `[M]` sigla + `Refs: SPRINT-N`
- **Hostinger SSH** (shared hosting, app web prod) — flaky, **sempre warm-up curl + retry** (ver [how-trabalhar.md](../../how-trabalhar.md) §SSH Hostinger)
- **CT 100 Proxmox** (daemons: FrankenPHP, Centrifugo, Meilisearch, MCP server, Vaultwarden, daemon WhatsApp) — Tailscale SSH; primeira sessão pede re-auth via URL (Wagner aprova)
- **WhatsApp daemon** (CT 100, ban-aware) — **não pareie canal sem skill `baileys-update-procedure`** (5 traps catalogados)
- **Banco prod Hostinger** (MySQL `oimpresso`) — read via tooling, **escrita só via migration PHP** (NUNCA tinker/phpMyAdmin direto)
- **Banco Firebird WR Comercial** (cliente legacy) — read pra discovery/suporte via firebird-driver Python (skill `officeimpresso-financial-snapshot`)
- **Herd local** (PHP 8.4) — dev local oimpresso
- **Pest v4** — testes (rodar local ou CT 100 via Tailscale, **NUNCA Hostinger** — proibido)

## Convenção em commits/PRs (você)

Sigla `[M]` em conventional commit:

```
fix(crm): triagem ticket P1 não classificava com OS aberta [M]
feat(sells): adicionar filtro por cliente em listagem [M]
docs(adr): aceitar 0145 cancelamento parcial [M]
```

Pareada com Claude: `[M+C]`. PR sempre ≤300 linhas; ≤100 ideal nas primeiras semanas.

## Histórico

- **v1.0** (2026-05-15) — onboarding inicial pré-entrada Maiara no MCP. Wagner ADR 0080 Trust Tiers operacional + ADR 0079 Constituição 7 camadas + ADR 0081 Identity Mesh.
