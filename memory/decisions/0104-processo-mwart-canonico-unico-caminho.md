---
slug: 0104-processo-mwart-canonico-unico-caminho
number: 104
title: "Processo MWART canônico — único caminho de migração Blade→Inertia"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0011-alinhamento-padrao-jana
  - 0023-inertia-v3-upgrade
  - 0039-ui-chat-cockpit-padrao
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
pii: false
---

# ADR 0104 — Processo MWART canônico (único caminho de migração Blade→Inertia)

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Não supersede:** ADRs 0011, 0023, 0039, 0093 (todos seguem válidos como base técnica). Este ADR adiciona **camada de processo** sobre eles.

---

## Contexto

O ERP tem ~78 páginas Inertia migradas + ~18 telas Blade ainda por migrar. As migrações até hoje aconteceram **ad-hoc** — cada dev (Wagner, Maíra, Felipe, Luiz, Eliana) seguia o que lembrava de RUNBOOKs e ADRs anteriores. Resultado: bugs recorrentes catalogados em `.claude/skills/cockpit-runbook/GOTCHAS.md`:

- Persistent Layout faltando (envolve `<AppShell>` inline → shell duplicado)
- `sessionStorage` em vez de `localStorage` (estado some na nova aba)
- `route()` chamada antes de Ziggy estar instalado (PR #180 — 161 erros TS silenciosos)
- `format_date()` em campo "agora" (shift +3h)
- Cor crua Tailwind em vez de tokens (`bg-blue-500` → quebra dark mode)
- Audit cockpit-runbook modo B feito **pós-merge** (PR #173 Whatsapp/Conversations) — refactor caro depois

Wagner pediu 2026-05-08, durante migração da `/sells/create`:

> "Anote como o processo deve ser. Falhas não são aceitáveis. Não pode ter 2 caminhos de desenvolvimento. Garantir que a equipe toda trilhe pelo melhor caminho sem pular etapa ou fazer coisa errada."

A equipe é pequena (5 pessoas, com `[L]` iniciante e `[E]` esposa fazendo IA-pair) — sem processo formal, conhecimento se dispersa em sessões individuais. Auto-mem privada virou ADR 0061 ZERO. **Restou: ADR canon + skill Tier A + enforcement automatizado.**

## Decisão

Adotar **5 fases obrigatórias e sequenciais** como **único caminho** de migração de tela Blade legacy para Inertia/React no oimpresso. Sem caminho alternativo.

```
┌─ F1 ─────────┐  ┌─ F2 ──────────┐  ┌─ F3 ────────┐  ┌─ F4 ────────┐  ┌─ F5 ────────┐
│ PLAN         │→ │ BACKEND       │→ │ FRONTEND    │→ │ QA          │→ │ CUTOVER     │
│ RUNBOOK+SPEC │  │ BASELINE      │  │ INCREMENTAL │  │ HARDENING   │  │ + SUNSET    │
└──────────────┘  └───────────────┘  └─────────────┘  └─────────────┘  └─────────────┘
```

### F1 — PLAN (RUNBOOK + SPEC)

**Skill que dispara:** `cockpit-runbook` (manual: `/cockpit-runbook tela X`)
**Outputs obrigatórios:**
- `memory/requisitos/<Mod>/RUNBOOK-<tela>.md` (11 seções)
- `memory/requisitos/<Mod>/SPEC.md` (1 epic + N subtasks com `blocked_by` chain)

**Gate de saída:** PR `docs(<mod>): RUNBOOK + SPEC migração MWART <tela>` mergeado em `main`.

### F2 — BACKEND BASELINE

**Skills:** `mwart-quality` + `multi-tenant-patterns` + `commit-discipline` (Tier A)
**Escopo da US `<MOD>-002`:**
- Action dual no controller (Blade fallback + `Inertia::render` se header `X-Inertia` + flag `useV2<Tela>=true` em `pos_settings`)
- Feature flag default OFF — comando artisan `<mod>:enable-v2 <biz>` liga/desliga em <30s
- **Pest tests baseline** — ≥5 fixtures cobrindo casos reais do `store()` (à vista, prazo, desconto %, fixo, frete, split). Rodar e passar **antes de mexer em qualquer linha do `store()`**.

**Gate de saída:** Pest tests passam, rollback testado, PR ≤ 300 LOC mergeado.

### F3 — FRONTEND INCREMENTAL

**Skills:** `mwart-quality` (auto), `cockpit-runbook` modo B (audit por PR)
**Forma:** **1 PR = 1 US**, cada PR ≤ 300 LOC, cada PR com **audit cockpit-runbook modo B ≥ 70 antes de mergear** (CRITICAL bloqueia merge).

Sequência típica:
- US-MOD-003 — skeleton + AppShellV2 + props contract
- US-MOD-004 — triagem visibilidade (campos sempre visíveis vs colapsáveis)
- US-MOD-005..007 — features incrementais (ex: produtos, pagamento, atalhos+draft)

**Gate de saída:** todas USs F3 mergeadas, score audit ≥ 70 em cada PR.

### F4 — QA HARDENING

**Skill:** `cockpit-runbook` modo B comprehensive
**Checklist obrigatório:**
- [ ] Audit cockpit-runbook modo B comprehensive — score ≥ 80 (CRITICAL=0, WARN=0)
- [ ] Smoke em `business_id=1` (Wagner WR2 SC) — **NUNCA `business_id=4`** ([ADR 0101](0101-tests-business-id-1-nunca-cliente.md))
- [ ] Canary 7 dias só com Wagner usando flag ON em biz=1
- [ ] Backup DB das tabelas críticas antes de habilitar pra cliente real
- [ ] Rollback plan documentado em comentário da US (comando exato + tempo de rollback)

**Gate de saída:** zero incidente nos 7 dias canary.

### F5 — CUTOVER + SUNSET

**Skill:** `commit-discipline` + `memory-sync`
**Sequência:**
1. Aviso prévio ao cliente (WhatsApp/ligação) — humano-no-loop
2. `php artisan <mod>:enable-v2 <biz_cliente> --on` — ativa flag pra cliente real
3. Monitorar 30 dias: contar vendas/operações criadas vs erros em `storage/logs/laravel.log`
4. Após 30 dias **sem incidente reportado:**
   - Deletar Blade legacy (view + partials)
   - Auditar JS legacy associado, remover funções não-usadas
   - Remover branch dual no controller (single response)
   - Remover comando artisan `<mod>:enable-v2`

**Gate de saída:** PR de remoção do legacy mergeado, audit final do `Pages/<Mod>/<Tela>.tsx` ≥ 80.

## Enforcement (3 camadas)

Sem enforcement, processo vira papel. 3 camadas em profundidade crescente:

### Camada 1 — Skill Tier A `mwart-process`

Always-on via hook `SessionStart`. Lembra agent do caminho a cada sessão. Conteúdo curto (~80 linhas) com 5 fases + skills associadas + gates. **Recusa silenciosamente** ajudar em mudança de tela MWART se uma fase anterior não completou (ex: Edit em `Pages/<Mod>/<Tela>.tsx` sem RUNBOOK existente).

### Camada 2 — Hook PreToolUse `block-mwart-violation.ps1`

Bloqueia em runtime:
- `Edit`/`Write` em `resources/js/Pages/<Mod>/<Tela>.tsx` se `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` não existe (F1 incompleta)
- `Edit`/`Write` em controller chamando `Inertia::render('<Mod>/<Tela>')` se Pest baseline ausente (F2 incompleta)

Hook retorna mensagem PT-BR explicando qual fase pular gerou bloqueio + comando pra corrigir.

### Camada 3 — CI workflow `.github/workflows/mwart-gate.yml`

Trigger: PR que toca `resources/js/Pages/<Mod>/<Tela>.tsx`. Verifica:
- RUNBOOK existe em `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md`?
- SPEC.md tem ≥1 US do tipo `MWART migration` referenciando esta tela?
- Audit cockpit-runbook modo B (rodado via comando) gerou score ≥ 70?
- Pest baseline da fase F2 passa?

Falha = "PR não pode mergear até resolver fases F1-F2".

## Consequências

### Boas

- **Equipe inteira segue mesmo caminho.** Wagner, Maíra, Felipe, Luiz, Eliana — todos sabem qual a próxima etapa só de ler o ADR.
- **Audit pré-merge previne refactor pós-merge caro.** PR #173 Whatsapp custou retrabalho — não acontece de novo se modo B é gate de F3.
- **Onboarding novo dev em <1h.** Lê este ADR + a skill `mwart-process` + 1 RUNBOOK exemplo (Sells/) → pronto.
- **Métrica observável.** % PRs MWART com score audit ≥70 antes de merge → tendência crescente esperada.
- **Rollback sempre disponível.** Feature flag obrigatória — bug em produção rola back em <30s sem deploy.

### Ruins / mitigações

- **Velocidade:** cada migração ganha 4-6h de overhead pelos gates (RUNBOOK + Pest baseline + audits per-PR). **Mitigação:** evita 20-40h de refactor pós-bug. ROI claro.
- **Skills Tier A:** mais tokens em cada sessão. **Mitigação:** `mwart-process` é curto (~80 linhas). Tier A justifica-se porque é processo crítico, não exploratório.
- **CI gate pode falsoar.** Audit modo B é semi-objetivo (CHECKLIST §G.4 reconhece). **Mitigação:** Wagner pode aprovar exceção em PR específico via comentário `/mwart-override <razão>` que registra em ADR per-tela.

## Alternativas consideradas

- **A — Manter ad-hoc** com docs como recomendação. **Rejeitada:** falhas se repetem (PR #173, bugs Persistent Layout, Ziggy ausente). Wagner explicitou que isso é inaceitável.
- **B — Só skill Tier A, sem ADR canon nem CI.** **Rejeitada:** skill é lembrete, não trava. Sem ADR canon, próxima geração de devs não tem fonte da verdade. Sem CI, dev experiente "pula" no calor da entrega.
- **C — Só CI gate sem skill nem ADR.** **Rejeitada:** CI é trava no fim — dev gasta horas fazendo errado e descobre no merge. Skill Tier A trava cedo, no ato.

Decisão final: **3 camadas combinadas.** Skill (lembra) + hook (trava no ato) + CI (trava no merge). Defesa em profundidade.

## Plano de migração

1. **Fase 0 — Hoje (PR #236 expandido):**
   - [x] Este ADR criado
   - [x] Skill `mwart-process` criada em `.claude/skills/mwart-process/SKILL.md`
   - [x] CLAUDE.md atualizado adicionando `mwart-process` aos Tier A
   - [ ] PR mergeado em main → webhook indexa pro MCP

2. **Fase 1 — Próximo PR (US-MWART-001):**
   - [ ] Hook `block-mwart-violation.ps1` em `.claude/hooks/`
   - [ ] CI workflow `.github/workflows/mwart-gate.yml`
   - [ ] Atualizar `mwart-quality` SKILL.md com referência a este ADR
   - [ ] Atualizar `cockpit-runbook` SKILL.md idem

3. **Fase 2 — Backfill (US-MWART-002):**
   - [ ] As ~78 telas Inertia já existentes ganham audit cockpit-runbook modo B
   - [ ] SPEC retroativo onde não existe
   - [ ] Score baseline registrado em `mcp_pages_audits` (tabela nova) pra trending

4. **Próxima migração de tela** (`/sells/create`, `/sells`, `/sells/{id}/edit`, etc):
   - Segue o processo F1→F5 sem desvio
   - Skills + hook + CI já ativos

## Refs

- [ADR 0011 — Padrãa Jana](0011-alinhamento-padrao-jana.md) — base estrutural
- [ADR 0023 — Inertia v3](0023-inertia-v3-upgrade.md) — base técnica
- [ADR 0039 — Chat Cockpit](0039-ui-chat-cockpit-padrao.md) — layout-mãe
- [ADR 0093 — Multi-tenant Tier 0](0093-multi-tenant-isolation-tier-0.md) — isolation IRREVOGÁVEL
- [ADR 0094 — Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md) — princípio "SoC brutal"
- [ADR 0095 — Skills Tiers](0095-skills-tiers-convencao-interna.md) — convenção Tier A/B/C
- [ADR 0101 — Tests biz_id=1 nunca cliente](0101-tests-business-id-1-nunca-cliente.md) — F4 smoke
- [GOTCHAS.md cockpit-runbook](../../.claude/skills/cockpit-runbook/GOTCHAS.md) — bugs catalogados que motivaram este ADR
- [RUNBOOK Sells/create — primeiro caso real](../requisitos/Sells/RUNBOOK-create.md)
- [SPEC Sells — primeiro epic + 8 subtasks aplicando processo](../requisitos/Sells/SPEC.md)

## Designer

**Decisão por Wagner** em sessão 2026-05-08, durante planejamento da migração `/sells/create`. Frase exata gravada como justificativa primária: *"Falhas não são aceitáveis. Não pode ter 2 caminhos de desenvolvimento."*

---

**Última atualização:** 2026-05-08
