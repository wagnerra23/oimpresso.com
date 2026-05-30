---
slug: governanca-evolucao-doc-design
number: null  # atribuir no aceite (próximo livre — cuidado colisão paralela tipo ADR 0180)
title: "Governança de evolução da documentação de design — append-only + índice fonte-única + ratchet + freshness gate + reprocesso por gatilho"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-30"
module: governance
quarter: 2026-Q2
tags: [governance, design-system, documentacao, append-only, ratchet, freshness, reprocesso, event-driven, idempotente, claude-design]
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0220-charters-freshness-checker-adapter
  - 0230-metodo-governance-scorecard
  - 0233-ativacao-memoria-momento-decisao
  - 0235-ds-v4-accent-roxo-universal
authors: [W, C]
---

# ADR (proposto) — Governança de evolução da documentação de design

## Contexto

A sessão 2026-05-30 consolidou 88 docs de design num índice mestre ([INDEX-DESIGN-MEMORIAS.md](../../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md)) + golden + pré-flight + método. Wagner: *"como vai evoluir no tempo a documentação sem quebrar a estrutura?"*. Risco real: o `CLAUDE_COWORK_PRIMER` envelheceu silenciosamente de 2026-05-09 a 30 (3 semanas → DS v3→v4, golden, pré-flight não chegaram a ele). Sem mecanismo, o índice repete esse apodrecimento.

## Decisão

Documentação de design evolui pelas **mesmas máquinas que o código** — append-only, ratchet, gate de CI, event-driven — separando **esqueleto** (raro, ADR-gated) de **recheio** (frequente, livre).

### Princípio mestre — esqueleto vs recheio
- **Esqueleto** (contrato, muda só via ADR): hierarquia 4 camadas (UI-0013), os 4 blocos do pré-flight, as categorias do índice, a regra de ouro.
- **Recheio** (dado, muda livre): qual golden, valor de token, telas em drift, notas screen-grade.
> Quebra de estrutura só acontece se esqueleto mudar sem ADR. Recheio não tem como quebrar moldura.

### As 5 máquinas
1. **Append-only + supersedes** — nunca editar canon aceito; o velho vira `historical` (coluna "aposentado" do índice, nunca delete). *(já lei — ADR 0094 + governance-gate.yml)*
2. **Índice = fonte única** — todo doc de design criado/aposentado obriga 1 linha no INDEX-DESIGN-MEMORIAS. Atualizar o índice faz parte do "pronto". *(wiring: skill `design-memoria-reprocess`)*
3. **Ratchet** — golden novo só entra se bate o atual (promotion); nota de tela só sobe; regra de ouro resolve conflito mecanicamente. *(padrão existe — module-grades/eslint baseline)*
4. **Freshness gate** — checker varre docs citando ADR `superseded` ou token velho → marca §6 stale automaticamente; CI falha se doc cita canon aposentado como vigente. *(wiring: estender ADR 0220)*
5. **Indireção (pré-flight)** — consumidor lê o resolvedor, não o doc cru; doc muda → só o ponteiro muda. *(já criado — ADR 0233 JIT)*

### Modelo de reprocesso — gatilhos em camadas (event-driven, idempotente)
| Gatilho | Quando | O que roda | Custo |
|---|---|---|---|
| **G1 INCREMENTAL** | commit toca `prototipo-ui/*.md` ou `_DesignSystem/*.md` | atualiza a linha do doc no índice + freshness dele | trivial |
| **G2 RECONCILIAR** | **Claude Design envia handoff com `new_design_memories`** OU ADR UI novo com `supersedes` mergeado | re-roda §4 conflitos + §6 stale (só o afetado) | médio |
| **G3 REPROCESSO TOTAL** | mudança estrutural (novo arquétipo/golden, bump token v5, mudança de método) | sweep completo (N agentes paralelos, ADR 0231) | alto, raro |

### Contrato do handoff Claude Design (o input de G2)
Todo handoff do Claude Design carrega bloco estruturado:
```
## new_design_memories
- tipo: golden | conflito | anti-padrao | token | doc-novo
- ref: <path ou ADR>
- resumo: <1 linha>
```
**Enforcement:** um **hook** detecta o bloco e dispara a skill `design-memoria-reprocess` (G2) — NÃO o handoff "pedindo educadamente" (lembrete é esquecível; hook é garantido — ADR 0224, R12).

### Invariantes (herdados ADR 0230)
- **A — Idempotência + anti-regressão:** rodar 2× = mesmo resultado; o reprocesso nunca rebaixa nem deleta (append-only). Toda mudança estrutural cria teste com a justificativa medida do porquê não voltar.
- **B — Rastreabilidade (RTM):** toda linha do índice cita a fonte (doc/ADR/handoff) que a originou.

## Consequências
- ✅ índice nunca apodrece (atualizar é parte do "pronto" + freshness automático)
- ✅ conflito não reaparece (regra de ouro mecânica + ratchet)
- ✅ Claude Design sempre lê o estado atual (indireção pré-flight)
- ⚠️ exige ligar 2 wirings: skill `design-memoria-reprocess` (G1/G2) + freshness-checker de design (estender ADR 0220) + hook do handoff
- ⚠️ número do ADR a atribuir no aceite (evitar colisão paralela — lição ADR 0180)

## Estado-da-arte (validação)
Event-driven + incremental + idempotente + append-only é o padrão de: reindex incremental de RAG, compilação incremental, git hooks por-diff, e o próprio webhook git→MCP do projeto. Reprocessar tudo-sempre seria anti-padrão (churn). A intuição "dispara quando chega memória nova" está correta; o refino é tier + hook + idempotência.
