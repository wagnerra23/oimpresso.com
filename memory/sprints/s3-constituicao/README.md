# Sprint 3 — Constituição v2 + Skills Tier A + CLAUDE.md reescrito

> **Status:** 📝 RASCUNHO COMPLETO — Sonnet preencheu 4 arquivos substantivos a pedido do Wagner ("Crie voce a constituição para eu aprovar"). Aguarda revisão + aprovação por bloco.
>
> **Driver:** Wagner aprova; Sonnet executa após aprovação.
>
> **Pré-requisito:** Sprint 1 + Sprint 2 com postmortem aprovado.

---

## Objetivo

Entregar a base canônica da **Constituição v2 Oimpresso** — 7 camadas (L1–L7) com governança formal, skills tieradas, CLAUDE.md enxuto (≤100 linhas com `@imports`), e ADRs canônicas atualizadas.

Baseia-se nos achados do deep-dive [s3-constituicao-deep-dive.md](../research/s3-constituicao-deep-dive.md):
- CLAUDE.md ≤100 linhas (não 350) com imports `@path/to/file.md`
- `tier:` no SKILL.md NÃO é canônico Anthropic — convenção interna documentada em ADR
- "Agent Constitution" virou pauta enterprise 2026 — estamos na vanguarda
- 5 skills Tier A finalizadas (3 ativas + 2 dormentes esperando S4/S5)

---

## Conteúdo deste pacote (esqueleto)

| Arquivo | Tipo | Status | O que tem |
|---|---|---|---|
| `01-adr-constituicao-v2.md` | ADR mãe canon | 📝 RASCUNHO COMPLETO | 7 camadas + 8 princípios duros + métricas |
| `02-adr-skills-tiers.md` | ADR skills tier A/B/C | 📝 RASCUNHO COMPLETO | Convenção interna + mecanismo enforcement |
| `03-skills-audit.md` | Tabela auditoria 19 skills | 📝 RASCUNHO COMPLETO | 5 blocos (A-E) com decisão por skill |
| `04-claude-md-novo.md` | Novo CLAUDE.md ~95 linhas | 📝 RASCUNHO COMPLETO | Template + 5 arquivos importados propostos |
| `05-checklist-wagner.md` | Passo-a-passo de rollout | 📝 ESQUELETO | A revisar após aprovação 01-04 |
| `06-rollback-plan.md` | Plano de reversão | 📝 ESQUELETO | A revisar após aprovação 01-04 |

---

## Sequência de rollout (estimado: 5–7 dias)

```
1. Wagner aprova §13 do ROTEIRO-MESTRE (blocos A–G)             ~30min
2. Sonnet preenche 6 arquivos numerados (rascunhos)              ~1d
3. Wagner revisa cada arquivo, marca [APROVADO|RECUSADO|PARCIAL] ~2h
4. Implementação:
   4.1 Mover conteúdo do CLAUDE.md atual pra 5 arquivos novos    ~3h
   4.2 Reescrever CLAUDE.md como índice ≤100 linhas              ~2h
   4.3 Auditar 19 skills (já pré-classificadas em 03)            ~2h
   4.4 Mover skills arquivadas pra .claude/skills/_archive/      ~30min
   4.5 Promover Tier A as 5 (brief-first, mcp-first, charter-first dormente, commit-discipline, ads-route dormente) ~2h
   4.6 Commitar 2 ADRs novas (constituicao-v2 + skills-tiers)    ~30min
   4.7 Atualizar hook SessionStart pra forçar brief-fetch        ~30min
5. Postmortem: medir tokens médios/sessão antes vs depois         ~1d soak
```

---

## Métricas de sucesso (mede após 7 dias do rollout)

| Métrica | Como mede | Alvo |
|---|---|---|
| CLAUDE.md tamanho | `wc -l CLAUDE.md` | ≤100 linhas |
| Skills Tier A no ar | `ls .claude/skills/ \| grep -v _archive` | 5 |
| Skills auditadas | tabela em 03-skills-audit.md | 19/19 |
| Token médio onboarding | `mcp_audit_log` agg per session | -30% vs baseline pré-S3 |
| ADRs novas commitadas | `git log --grep="constituicao\|skills-tiers"` | 2 |

---

## Riscos

- 🔴 **CLAUDE.md atual ~390 linhas tem instruções críticas** — risco de regredir comportamento se podar errado. Mitigação: rollback plan em 06.
- 🟡 **Skills auto-trigger podem não disparar consistentemente sem telemetria histórica** — coletar baseline 7 dias antes de auditar.
- 🟡 **Hook `SessionStart` pode falhar silenciosamente** — testar com Felipe primeiro.
- 🟢 **Wagner pode discordar de decisões PROMOVER/ARQUIVAR** — fluxo prevê aprovação em uma rodada antes de mover arquivo.

---

## Rollback

Se algo quebrar pós-rollout:

1. `git revert` dos 2 commits do S3 (CLAUDE.md + skills moves)
2. Skills voltam de `.claude/skills/_archive/` automaticamente
3. ADR mãe vira `status: paused`, `lessons` documenta motivo
4. Hook SessionStart revertido pra versão anterior

Detalhes técnicos em `06-rollback-plan.md`.

---

## Decisões pendentes pra Wagner ANTES de começar

> Estas precisam estar resolvidas antes do passo 1 da sequência.

- [ ] Aprovar/recusar §13 do ROTEIRO (7 blocos A–G)
- [ ] Confirmar que Sprint 1 e Sprint 2 estão com postmortem fechado
- [ ] Decidir se quer testar 1 sessão real com `brief-fetch` antes de codar S3
- [ ] Definir se ADR 0093 (multi-tenant Tier 0) entra em S3 ou separado

---

## Próximo passo concreto

**Quando você der OK:** Sonnet preenche os 6 arquivos com rascunhos (custo ~$0.50, ~1h de trabalho). Você revisa cada um e marca aprovação antes de qualquer mudança em produção.

📌 **Importante:** este dossier NÃO está pronto pra execução. Os 6 arquivos `.md` abaixo são esqueletos vazios. Wagner dirige; Sonnet aguarda ordem explícita pra preencher.
