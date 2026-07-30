---
id: requisitos-admin-deprecation-plan
---

# DEPRECATION-PLAN — Modules/Admin

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **2º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> **Vai em 2º porque sair daqui remove 3 dos 11 acopladores do Governance** (que é o 6º). Não é sequência arbitrária.

## Fase 1 — Inventário

**Gerado, não escrito:** [`SUPERFICIE.md`](SUPERFICIE.md) — **95 arquivos em 13 papéis** (`module-surface.mjs Admin --write`). Frescor 2026-07-30: `--check` **exit 0**.

Este módulo é o mais rico em artefatos de inventário do repo — além da superfície tem [`UI-CATALOG.md`](UI-CATALOG.md), [`GOVERNANCE-MATURITY-FICHA.md`](GOVERNANCE-MATURITY-FICHA.md), [`SCREEN-REVIEW-RUNBOOK.md`](SCREEN-REVIEW-RUNBOOK.md) e [`Index-visual-comparison.md`](Index-visual-comparison.md). **Todos saem com o módulo** — conferir antes se algum descreve tela que sobrevive.

Contornos: **8 telas** `.tsx` · 8 Controllers · 7 Requests · 2 Middleware · **0** tools MCP · **0** cron.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Estado |
|---|---|
| `mcp_admin_audit_log` | existe · **0 linhas** |

**Consequência:** a tabela foi migrada e **nunca recebeu escrita**. O módulo tem 8 telas em produção mas **zero trilha de auditoria própria** — o que ele grava, grava em tabela de outro dono. Riscos Tier 0 que pressupõem volume: **zero**.

⚠️ Mesma ressalva do conjunto: CT 100 **não medido**.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\Admin\'` fora da pasta, sem `memory/`/`.claude/` → **2 arquivos de código** (4 no total, 2 são `.md`).

**Mas a direção que importa é a inversa** — o que **este** módulo consome de quem vai morrer junto:

| Arquivo do Admin | Depende de |
|---|---|
| `Http/Controllers/GovernanceV4DashboardController.php` | `Modules\Governance\` |
| `Http/Controllers/ScreenReviewController.php` | `Modules\Governance\` |
| `Http/Requests/CreateInitiativeRequest.php` | `Modules\Governance\` |

Isso é **o motivo da ordem**: Admin sai antes e o Governance fica com 8 acopladores em vez de 11.

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| `mcp_admin_audit_log` | **DROP** | 0 linhas. Nada a migrar, nada a arquivar. |

**Ordem:** DROP **depois** do refactor de código (lição E3 do SRS).

## Fase 5 — Riscos Tier 0

| # | Risco | Severidade | Mitigação |
|---|---|---|---|
| **R1** | **8 telas em produção somem.** Incluem `/admin/screen-review` (a UI humana de status por tela, citada em `how-trabalhar.md`) e o dashboard Governance v4. | **alta** | Decidir receptor por tela **antes** da E3. O `screen-review` tem consumidor humano documentado em canon. |
| **R2** | Os 4 docs de inventário (`UI-CATALOG`, `GOVERNANCE-MATURITY-FICHA`, `SCREEN-REVIEW-RUNBOOK`, `Index-visual-comparison`) saem junto | média | Conferir se descrevem tela que sobrevive; se sim, mover, não apagar |
| **R3** | PII / cross-tenant | **nenhum** | 0 linhas |

Nenhum check **required** cita Admin (`governance/required-checks-baseline.json`).

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | Medir CT 100 · decidir receptor das 8 telas (**R1** — a mais importante) | ✋ [W] decide |
| **E2** | Migrar/aposentar as 8 telas + os 4 docs de inventário | ✋ [W] aprova |
| **E3** | Remover `Modules/Admin/` + rotas + permissions + `modules_statuses.json` | ✋ [W] aprova |
| **E4** | Migration DROP `mcp_admin_audit_log` | ✋ [W] aprova |
| **E5** | Smoke real em prod: `/admin/*` em 301/410 + tabela ausente | ✋ [W] confere |
| **E6** | Lápide §5 + `BRIEFING` final | — |

## Resíduo honesto

- **CT 100 não medido.**
- **As 8 telas não foram avaliadas uma a uma** — o dono do estado por tela é `npm run screen-coverage:report` + `/admin/screen-review`, que é justamente uma das telas que morrem. Ironia registrada: **a ferramenta que mediria o impacto está dentro do que vai sair.**
- **Pest não rodado** (Tier 0 → CT 100).
