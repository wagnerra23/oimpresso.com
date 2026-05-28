---
date: 2026-05-28
session: governance framework Sprint 2 + reavaliação Claude 4.8 (sessão grande, 16 PRs)
status: 16 PRs mergeados em main; 2 itens pendentes pra Wagner decidir
continua: handoff anterior 2026-05-28-handoff-governance-framework-PR1-merged.md
---

# Handoff — Governance Framework Sprint 2 + Reavaliação Claude 4.8

## 🎯 Onde paramos

Sessão MUITO grande (16 PRs). Começou continuando o framework Drift (ADR 0216 PR1)
e evoluiu pra reavaliação arquitetural completa à luz do Claude 4.8.

## 📦 16 PRs mergeados nesta sessão

### Framework Drift + Supply chain (PRs #1874-#1889)
- #1874 ADR 0216 Framework DriftChecker + 4 checkers + 5 ADRs (0216-0219, 0221)
- #1875 Handoff PR1 + 2 feedback memos canon
- #1876 symfony/yaml 4 CVEs (v8.0.8→v8.0.13)
- #1877 ADR 0018 frontmatter fix (3 drifts adr_link_rot)
- #1880 batch 10 CVEs (8 packages symfony/phpseclib/fpdi)
- #1884 ChartersFreshnessChecker (ADR 0220 — fecha 5/5 Top 5)
- #1885 Renovate config (ADR 0222 — supply chain proativo)
- #1887 NpmAuditChecker (ADR 0223 — 6º checker frontend)
- #1889 npm audit fix (3 CVEs engine.io/protobuf/ws → 0)

### Reavaliação Claude 4.8 (PRs #1890-#1896)
- #1890 **ADR 0224** — triagem hooks block vs advisory (rebaixa 1 semântico)
- #1892 **ADR 0225** — skills Tier A 8→5 (rebaixa 7 pra auto-trigger)
- #1895 **ADR 0226** — Brief v2 (régua 3.5k→8k, 1M-aware)
- #1896 docs reavaliação 4.8 (fix links quebrados das 3 ADRs)

## ✅ Estado do framework Drift

```
6 checkers · todos verdes · 0 drift total:
✓ composer_audit · ✓ npm_audit · ✓ multi_tenant_scope
✓ adr_link_rot · ✓ charters_freshness · ✓ routes_zombie
```

14 CVEs resolvidas (PHP+JS) · supply chain coberto reativo (Composer/Npm audit
checkers) + proativo (Renovate config ADR 0222, aguarda Wagner aprovar GitHub App).

## 🚨 2 PENDÊNCIAS PRA WAGNER DECIDIR

### 1. PR #1891 — ADR 0225 DUPLICADA (conflito de sessão paralela)

Existe um **PR #1891 aberto** ("docs(adr): 0225 recalibração skills Tier A pós-Claude 4.8")
criado por sessão/agente PARALELO 2026-05-28 18:28. É uma proposta CONCORRENTE da
ADR 0225 com diagnóstico MAIS RIGOROSO que a minha (mergeada #1892):
- **#1891 (aberto):** mediu **25 de 66 skills Tier A (38% drift, 3x)** — measurement-based
- **#1892 (mergeado, canon):** "8→5" — estimate-based (eu usei o banner como proxy)

**Decisão Wagner:** (a) fechar #1891 como superseded por #1892 (perde a medição 25/66),
OU (b) harvest a medição 25/66 do #1891 numa ADR 0225-emenda follow-up (mais completa),
OU (c) reverter #1892 e usar #1891 (mais rigoroso). **NÃO resolvi autonomamente** —
é decisão de governança + tem trabalho de outra sessão envolvido.

Recomendação: **(b)** — manter #1892 canon + criar emenda com a medição 25/66 do #1891
(o número real importa pro time MCP). Fechar #1891 citando a emenda.

### 2. ADRs 0227 + 0228 da reavaliação 4.8 — DEFERIDAS (com razão)

Reavaliação 4.8 ([sessions/2026-05-28-reavaliacao-projeto-claude-4.8.md](2026-05-28-reavaliacao-projeto-claude-4.8.md))
tinha 5 simplificações. **3 feitas (0224/0225/0226). 2 deferidas:**

**0227 (MWART single-layer)** — REQUER REPENSAR. A reavaliação sugeriu "colapsar 3
camadas pra CI-only", MAS investigação mostrou que o hook `block-mwart-violation`
é **DETERMINÍSTICO** (checa se RUNBOOK-<tela>.md existe). Pela regra da ADR 0224
(*"determinístico = fica block"*), o hook DEVE continuar block. E a 0225 JÁ rebaixou
a camada de skill (mwart-process → Tier B). Então o estado atual já é: hook determinístico
(correto) + CI gate autoritativo + skill auto-trigger. **A recomendação original conflita
com a 0224.** 0227 provavelmente vira "documentar que MWART enforcement já está right-sized
pós-0225" OU não-fazer. Decisão: repensar em sessão dedicada.

**0228 (subagent orchestration nativo)** — EVOLUIR, não simplificar. Migrar 1 fluxo
(cascade-review) pro Agent SDK nativo. É piloto + precisa telemetria antes/depois.
Merece sessão dedicada (~3h). NÃO cabe em fim de sessão grande.

## 🧠 Conhecimento canon estabelecido (pra próxima sessão)

### Convenções DriftChecker (ADR 0216)
- Severity 5: critical|high|medium|low|info (Datadog)
- Enforcement 3: advisory|warn|block (Sentinel)
- Cadence 5: on_commit|on_pr|hourly|daily|weekly
- Persist `mcp_alertas_eventos` tipo `drift_<checker>`; channel `governance:drift`
- Novo checker = ~80 linhas + ADR Nygard filha (~150 linhas)

### Critério hook (ADR 0224)
- `block` = determinístico-obrigatório (path match, bytes, regex sintática, Tier 0/LGPD)
- `advisory` = semântico/lembrete (exit 0 + mensagem)
- 9 hooks deny intactos + post-merge-ui-smoke; só block-claim-without-evidence rebaixado

### Skills Tier A pós-recalibração (ADR 0225)
- MANTER (5): multi-tenant-patterns, commit-discipline, incident-done-checklist,
  memory-first-secret-search, hostinger-dns-autonomy
- AUTO-TRIGGER (7): brief-first, mcp-first, mwart-process, mwart-comparative,
  charter-first, preflight-modulo, wagner-protocol-enforce
- ACHADO: PROTOCOLO WAGNER já é Tier 0 via proibicoes.md REGRA ZERO (@import CLAUDE.md)

### Brief v2 (ADR 0226)
- MAX_TOKENS 8192 (gerador) / 8000 (validator); env GOVERNANCE_BRIEF_TARGET_TOKENS
- 7 seções canon + LGPD intactos

## ⏳ Ações Wagner (irreduzíveis)

1. Decidir PR #1891 (recomendo opção b — emenda com medição 25/66)
2. Aprovar Renovate GitHub App (github.com/apps/renovate) — ADR 0222 dorme até lá
3. Próxima sessão: smoke canary 7d cron governance:audit 06:35 (completa 2026-06-04)

## Pegadinhas técnicas desta sessão

- Pest 4 + Laravel: `uses(Tests\TestCase::class)` explícito por file (facade root)
- `composer dump-autoload` + `config:clear` após adicionar Checker novo
- Slot cron 06:15 BRT disputado (4 schedules) — usar 06:35
- Commit pode cair em branch errada se `git checkout` intermediário trocar HEAD —
  cherry-pick onto fresh branch from origin/main resolve
- main locked por worktree agent (agent-a9594b1d) — usar `gh pr merge --admin`,
  não `git checkout main` local
