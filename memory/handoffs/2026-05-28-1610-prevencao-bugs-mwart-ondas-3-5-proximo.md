---
date: '2026-05-28'
hour: '16:10 BRT'
topic: "Handoff curto — retomada Ondas 3-5 prevenção bugs MWART (R8 + R10 raiz pendentes)"
duration: 8h
authors: [W, C]
---

# Handoff retomada — Ondas 3-5 prevenção bugs MWART

## Estado MCP (1 linha)

Onda 1 (Larastan + ESLint + LogContext) + Onda 2 (3 PHPStan rules + doc) **completas** em main. R7 + R9 raiz **prevenidos**. R8 + R10 raiz **pendentes Ondas 3 + 5**.

## Comando único de retomada

```bash
# Hook SessionStart já roda brief-fetch.
# Depois disso:
Read memory/sessions/2026-05-28-batch-larissa-r7-r10-ondas-1-2-prevencao.md
# E:
mcp__oimpresso__tasks-list module:Infra status:todo
```

Sem precisar re-ler audit anteriores ou ADRs. Session log tem todos os ponteiros.

## Decisão pendente Wagner (gating)

| # | Item | Bloqueia |
|---|---|---|
| 1 | Aceitar ADRs 0208-0213 (`status: proposto` → `aceito`) | Onda 3+ formalmente |
| 2 | Cycle MCP: fechar CYCLE-06 + abrir CYCLE-07 OR rollover Martinho | Mira semana |
| 3 | Smoke biz=4 Larissa (task MCP #15 BLOQUEADO) | Validação real dos 13 PRs hoje |

Item 1 e 2 podem ser feitos por single line commits canônicos. Item 3 precisa Wagner agir (Larissa ou superadmin).

## Próxima onda recomendada

**Onda 4 — TanStack Query** (US-_DS-008/009/010/011/012/013, ~13h IA-pair):

- Substitui meu fix R7 (`AbortController + sentinela`) por lib madura
- Cobre TODA tela MWART futura sem pattern manual
- Zero risco prod (frontend-only, ratchet ESLint pega regressão)

Alternativa: **Onda 3 Wayfinder** (R8 raiz) — mas Wayfinder ainda beta, risco maior.

## Bloqueios documentados

- **Task MCP #15** — smoke biz=4 Larissa real (Wagner-account só vê biz=1)
- **GraphQL rate-limit** — hoje esgotou às 13:18, reset 14:18 BRT (próxima sessão estará ok)

## Pointers detalhados (consultar on-demand)

| Tópico | Arquivo |
|---|---|
| Session log completo | `memory/sessions/2026-05-28-batch-larissa-r7-r10-ondas-1-2-prevencao.md` |
| Dossier estado-da-arte 6 frentes | `memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md` |
| Audit Sells/Create Larissa (origem) | `memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md` |
| 6 ADRs canon propostos | `memory/decisions/0208..0213-*.md` |
| Feedback Wagner cadence | `memory/reference/feedback-ondas-multi-pr-sem-perguntar-2026-05-28.md` |
| 27 tasks MCP em SPECs | `memory/requisitos/Infra/SPEC.md` + `memory/requisitos/_DesignSystem/SPEC.md` |
| Anti-padrões catalogados | `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` (AP-18 + AP-2 + AP-13 novos) |

## 13 PRs do dia (índice rápido)

R7 #1824 · R8 #1828 · R9 #1830 · R10 #1832 · ADRs #1837 · Tasks #1839 · Larastan #1850 · LogContext #1852 · ESLint #1854 · NoSilentFallback #1862 · NoMissingTenantScope #1866 · NoNopMutation #1868 · LICOES AP-18 #1869 · session log #1872

## Não-objetivos da próxima sessão

- ❌ Re-fazer diagnose (já tem dossier + 6 ADRs)
- ❌ Re-criar tasks MCP (27 já no backlog)
- ❌ Atacar Onda 2.2 NoInventedModel (skipada — PHPStan nativo cobre `class.notFound`)
- ❌ Validar PRs Onda 1/2 em biz=4 (bloqueado — task #15)
