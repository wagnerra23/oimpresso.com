---
name: preflight-modulo
description: BLOQUEADOR — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em Modules/<X>/. PRÉ-FLIGHT obrigatório: ler memory/requisitos/<X>/SPEC.md + RUNBOOK*.md + CAPTERRA*.md + charter da página + decisions-search ADRs relacionadas + skill como-integrar se feature parcial. Regra Primária Tier 0 IRREVOGÁVEL (memory/proibicoes.md). Workflow 3 fases obrigatório (PRE-FLIGHT/DURING/POST). Tier A always-on — pareada com hook .claude/hooks/modulo-preflight-warning.ps1. Wagner regra 2026-05-15: "vai mexer no módulo ler briefing e se mexer salva o progresso. mexe não registra altera sem ler as regras do módulo fica sempre errando." Time MCP (Felipe/Maiara/Eliana/Luiz) entra em breve — sem essa skill ativa, fica zona.
---

# preflight-modulo — Workflow Tier 0 obrigatório ao tocar Module/

> ⛔⛔⛔ **Regra Primária Tier 0 IRREVOGÁVEL — workflow 3 fases obrigatório.** Skill ATIVA automaticamente ANTES de qualquer Edit/Write/MultiEdit em `Modules/<X>/` ou tabela do módulo X. Pareada com hook `.claude/hooks/modulo-preflight-warning.ps1` (defesa-em-profundidade).
>
> Wagner palavras textuais (2026-05-15):
>
> *"vai mexer no módulo ler briefing e se mexer salva o progresso. (...) mexe não registra, altera sem ler as regras do módulo fica sempre errando, caramba se organiza caralho seja responsável porra. vão entrar os outros no MCP e isso vai ficar uma zona caralho"*

## Quando ativar (trigger automático)

Skill matcha quando Claude está prestes a tocar:

- `Modules/<X>/Http/Controllers/...` (PHP backend)
- `Modules/<X>/Services/...` ou `Modules/<X>/Jobs/...`
- `Modules/<X>/Entities/...` (Eloquent models)
- `Modules/<X>/Observers/...`
- `Modules/<X>/Database/Migrations/...`
- `resources/js/Pages/<X>/<Tela>.tsx` (Inertia React frontend)
- Tabela DB do módulo X via tinker/SQL direto (BLOQUEADO — usar seeder/migration)

## Workflow 3 fases — obrigatório

### FASE 1 — PRÉ-FLIGHT (ANTES de qualquer Edit/Write)

**Leitura obrigatória DO MÓDULO ESPECÍFICO** que vai tocar:

| Artefato | Como ler | Quando obrigatório |
|---|---|---|
| `brief-fetch` Tier A | Tool MCP (skill `brief-first` já força no SessionStart) | Sempre — Tier A always-on |
| `memory/requisitos/<X>/SPEC.md` | Read direto | Sempre antes de mexer no módulo X |
| `memory/requisitos/<X>/RUNBOOK*.md` | Read | Se MWART (Pages Inertia) — ADR 0104 único caminho |
| `memory/requisitos/<X>/CAPTERRA*.md` | Read | Pra checar inventário/escopo aprovado |
| `Modules/<X>/Charter.md` ou `*.charter.md` ao lado do `.tsx` | Read | Charter S4+ Inertia — ADR 0094 §3 |
| ADRs relacionadas | `decisions-search query:"<modulo lowercase>"` Tool MCP | Se decisão arquitetural relevante |
| `memory/proibicoes.md` | Sempre (Tier 0 atemporal) | Sempre |
| Skill `como-integrar` | Spawn skill | Se feature parcialmente feita no projeto |

**Sintomas de violar Fase 1 (= bug garantido):**

- "Vou mexer rápido no Inbox" sem ler `memory/requisitos/Whatsapp/SPEC.md`
- "Edit em `Sells/Create.tsx`" sem ler `Sells/Create.charter.md`
- "Adicionar coluna em `messages`" sem ler ADR 0093 multi-tenant
- "Criar controller novo" sem checar skill `criar-modulo` + ADR 0011 modular nWidart

### FASE 2 — DURING (mexendo)

**Salvar progresso INCREMENTALMENTE.** Wagner não tolera "vou commitar depois":

| Frequência | Ação obrigatória |
|---|---|
| Cada step lógico (1 arquivo + 1 ideia completa) | `git commit` parcial OR `TodoWrite` mark completed |
| A cada ~30min de trabalho | `git push` da WIP branch (mesmo incompleto) |
| Antes de spawnar agent paralelo | Commit baseline atual |
| Antes de `git checkout` outra branch | `git commit` OR `git stash push -m "wip-<contexto>"` |
| Edit em DB direto (SQL/tinker) | **PROIBIDO** sem seeder/migration imediato (idempotente) |
| Edit em arquivo direto via SSH no Hostinger/CT 100 | **PROIBIDO** sempre — `git pull` apenas |

**Sintomas de violar Fase 2:**

- "trabalho de 2h perdido por checkout sem stash"
- "esqueci de commitar e fechei terminal"
- "rodei tinker em prod e ninguém sabe o que fiz"
- "mexi no daemon CT 100 direto na pasta `/srv/build/`"

### FASE 3 — POST (mexeu, registra)

Toda mudança operacional DEVE virar git + tests + docs canon:

| Mexeu em... | Caminho obrigatório |
|---|---|
| Código Module (PHP/TS/React) | PR no git → CI verde → merge |
| Comando artisan / cron | PR + entry em `app/Console/Kernel.php` + log estruturado |
| Schema DB (DDL) | Migration PHP + Pest sobrevive re-run + ADR se decisão arquitetural |
| INSERT/UPDATE direto no DB (tinker, SQL, phpMyAdmin) | Seeder OR comando artisan idempotente OR backfill job + commit |
| Arquivo no servidor (SSH Hostinger, CT 100, daemon source) | **PROIBIDO** — via git pull do canônico apenas |
| Cache `Cache::put`/`Cache::forget` ad-hoc em prod | Observer ou comando artisan registrado, NUNCA tinker direto sem commit |

**Se Wagner aprovar Tier 0 superadmin "ajuste rápido" em emergência:** Claude marca log com `// DRIFT TIER 0 — Wagner aprovou em <data>, follow-up PR em <hash>` E spawna PR follow-up imediato.

## Tabela: leitura PRÉ-FLIGHT por tipo de Edit

| Vai editar... | LEIA ANTES (obrigatório) |
|---|---|
| `Modules/<X>/Http/Controllers/...` | `memory/requisitos/<X>/SPEC.md` (US-XXX-NNN) |
| `resources/js/Pages/<X>/<Tela>.tsx` | charter `<Tela>.charter.md` + skill `mwart-process` (ADR 0104) |
| `Modules/<X>/Database/Migrations/...` | ADR 0093 (multi-tenant) + Schema existente |
| Comando artisan novo | skill `criar-modulo` + Console/Kernel.php pattern |
| Service/Job que toca prod biz=1 | ADR 0101 (tests biz=1) + skill `multi-tenant-patterns` |
| Observer/Event | ADR 0143 FSM (se aplicável) + proibições deste arquivo |

## Por que isso MAIS agora (2026-05-15+)

1. **Time MCP entra em breve** (Felipe/Maiara/Eliana/Luiz) — sem workflow estrito, drift escala N× pessoas
2. **Maratona WhatsApp 14-15/mai** mostrou que **TODOS os 5 drifts** catalogados vieram de violação de FASE 1 (mexer sem ler) ou FASE 2 (não salvar progresso)
3. **MCP server `mcp.oimpresso.com`** expõe estado vivo pro time — drift = dado errado servido a Felipe/Maiara

## Defesa-em-profundidade

Esta skill é **camada 1 (ensina)**. Defesas adicionais:

- **Camada 2 (avisa pós-Edit):** [`.claude/hooks/modulo-preflight-warning.ps1`](../../hooks/modulo-preflight-warning.ps1) — warning stderr quando Claude edita Module/ sem ter lido briefing
- **Camada 3 (proíbe):** [`memory/proibicoes.md`](../../../memory/proibicoes.md) §"REGRA PRIMÁRIA" — Tier 0 IRREVOGÁVEL
- **Camada 4 (detalha + histórico):** [`memory/reference/feedback-modulo-mexeu-registra-sempre.md`](../../../memory/reference/feedback-modulo-mexeu-registra-sempre.md) — 5 vetores catalogados maratona WhatsApp + 7 defesas automáticas
- **Camada 5 (MWART específico):** [`.claude/hooks/block-mwart-violation.ps1`](../../hooks/block-mwart-violation.ps1) — BLOQUEIA `.tsx` sem RUNBOOK existir (ADR 0104)

## Comportamento Claude esperado

**Quando esta skill ativa** (Edit/Write em `Modules/<X>/`):

1. **VERIFICAR** se já leu briefing do módulo X nesta sessão (via TodoWrite ou Read history)
2. Se NÃO leu → **PARAR + ler primeiro** (Fase 1 PRÉ-FLIGHT)
3. Se já leu → prosseguir com Edit, MAS:
   - Confirmar branch é WIP apropriada (não `main`)
   - Plan commit incremental ANTES de começar
   - TodoWrite marca progresso por step
4. Após Edit → Fase 3 POST: commit + PR + docs

**Se Wagner ordenar "Tier 0 superadmin ajuste rápido emergência":** Claude prossegue MAS marca o log com `// DRIFT TIER 0 — Wagner aprovou <data>, follow-up PR pendente` E spawna PR follow-up na mesma sessão.

## Conflito resolution

- **Não conflita com `mwart-process`** — esta skill é GERAL (qualquer Module/), `mwart-process` é ESPECÍFICA (Pages Inertia .tsx). MWART é um caso particular: se vai editar `.tsx`, leia esta skill (preflight geral) + `mwart-process` (MWART específico).
- **Não conflita com `commit-discipline`** — esta é PRÉ-Edit (antes), commit-discipline é PRÉ-commit (depois). Workflow 3 fases tem ambas.
- **Não conflita com `multi-tenant-patterns`** — esta é meta (workflow), multi-tenant é prática (código). Workflow inclui ler ADR 0093 como parte da Fase 1 PRÉ-FLIGHT.

## Sources

- [`memory/proibicoes.md`](../../../memory/proibicoes.md) §REGRA PRIMÁRIA — Tier 0 IRREVOGÁVEL
- [`memory/reference/feedback-modulo-mexeu-registra-sempre.md`](../../../memory/reference/feedback-modulo-mexeu-registra-sempre.md) — detalhe + 5 vetores
- [`memory/sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md`](../../../memory/sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) — dossier estado-da-arte memória 2026 (Anthropic + DDD)
- [`memory/handoffs/2026-05-15-1010-...md`](../../../memory/handoffs/2026-05-15-1010-whatsapp-maratona-encerrada-12prs-ui-brave-validada-regra-primaria.md) — handoff fechamento maratona com 5 vetores drift
- Skill `mwart-process` Tier A (ADR 0104 único caminho `.tsx`)
- Skill `commit-discipline` Tier A (Fase 3 POST)
- Skill `como-integrar` (se feature parcial)
