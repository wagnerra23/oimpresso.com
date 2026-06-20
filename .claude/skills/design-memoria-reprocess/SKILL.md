---
name: design-memoria-reprocess
description: >
  ATIVAR quando (a) o Claude Design enviar handoff com bloco `## new_design_memories`;
  (b) um doc de design for criado/editado/aposentado (`prototipo-ui/*.md`,
  `memory/requisitos/_DesignSystem/*.md`, `*.charter.md`); (c) um ADR UI novo com
  `supersedes` for mergeado; (d) novo golden de arquétipo / bump de token (DS v5) /
  mudança de método; OU (e) user pedir "reprocessar índice de design", "atualizar
  memórias de design", "destravar fluxo de design", "/design-memoria-reprocess".
  Mantém o INDEX-DESIGN-MEMORIAS.md vivo SEM quebrar a estrutura — append-only +
  ratchet + freshness + idempotente. Tier B auto-trigger. Refs ADR proposto
  governanca-evolucao-doc-design, ADR 0230/0231/0233, INDEX-DESIGN-MEMORIAS.md.
tier: B
---

# design-memoria-reprocess — reprocessar a doc de design sem quebrar a estrutura

> **Workflow canônico de evolução.** Mantém [`INDEX-DESIGN-MEMORIAS.md`](../../../memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) como fonte única, vivo e coerente. Decisão-mãe: ADR proposto `governanca-evolucao-doc-design`.

## Princípio (o que NÃO fazer)
- ❌ Reprocessar o fluxo inteiro a cada mudança (churn, custo).
- ❌ Reescrever o índice do zero (quebra rastreabilidade).
- ❌ Deletar entrada aposentada (append-only — vira "histórico/stale").
- ✅ Incremental + idempotente: rodar 2× = mesmo resultado.

## QUANDO reprocessar — 3 gatilhos em camadas

| Gatilho | Dispara quando | O que rodar |
|---|---|---|
| **G1 INCREMENTAL** | commit toca 1 doc de design | atualiza só a **linha daquele doc** no índice (§2/§3) + freshness dele |
| **G2 RECONCILIAR** | handoff Claude Design com `new_design_memories` **OU** ADR UI novo com `supersedes` | re-roda **§4 conflitos + §6 stale** (só o afetado), aplica regra de ouro |
| **G3 REPROCESSO TOTAL** | novo arquétipo/golden · bump token (DS v5) · mudança de método | **sweep completo** — N agentes paralelos por área (ADR 0231) → reconsolida o índice |

## COMO reprocessar — passos idempotentes (G1/G2)

1. **LER estado:** abrir o índice + `git diff` desde o último reprocesso (ponteiro no rodapé do índice).
2. **CLASSIFICAR** o que mudou: doc novo / editado / aposentado / ADR superseded / `new_design_memories` do handoff.
3. **APLICAR regra de ouro** (§0 do índice) mecanicamente: ADR recente c/ supersedes vence · DS v4 > v3 · código real > doc · UI-0013 hierarquia · data recente.
4. **ATUALIZAR só as linhas afetadas:** §2 (positivo) / §3 (negativo) / §4 (conflito — append linha nova, move o vencido p/ "aposentado") / §6 (stale). **Nunca reescreve o todo.**
5. **VALIDAR:** o índice não cita ADR `superseded` como canon vigente; toda linha tem fonte real (RTM, ADR 0230 Inv. B); ratchet OK (nada rebaixado/deletado).
6. **REGISTRAR:** bump do ponteiro `last_reprocess: <data>` no rodapé + 1 linha no changelog do índice.
7. **(só G3)** spawnar os agentes paralelos, depois consolidar no parent.

## Contrato do handoff Claude Design (input de G2)
Todo handoff do Claude Design DEVE carregar:
```
## new_design_memories
- tipo: golden | conflito | anti-padrao | token | doc-novo
- ref: <path ou ADR>
- resumo: <1 linha>
```
> **Enforcement:** o hook `design-handoff-reprocess` detecta esse bloco e dispara esta skill (G2). O handoff **declara**; o hook **executa** — não é lembrete educado (lembrete falha; hook garante — ADR 0224 + R12).

## Invariantes (ADR 0230)
- **A — idempotência + anti-regressão:** rodar 2× = mesmo resultado; mudança estrutural cria teste com a justificativa medida do porquê não voltar.
- **B — rastreabilidade (RTM):** toda linha cita a fonte que a originou.

## Saída
Índice atualizado (incremental) + ponteiro `last_reprocess` + linha de changelog. Em G3, também: ranking screen-grade + baseline ratchet. NUNCA commit/push automático sem aprovação humana (R10).
