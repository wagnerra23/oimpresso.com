---
slug: 0169-errata-0168-runbook-onda-cowork-canon
number: 0169
title: "Errata ADR 0168 — RUNBOOK-onda-cowork.md como artefato 4º da triade governance"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-17
module: null
quarter: 2026-Q2
tags: [governance, errata, ondas-cowork, design-system, playbook]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0094, 0104, 0107, 0114, 0141, 0143, 0168]
pii: false
review_triggers:
  - "Time MCP (Felipe/Maiara/Eliana/Luiz) reporta dúvida sobre como aplicar Ondas"
  - "Onda completa entregue mas Wagner descobre gap NÃO catalogado pós-smoke"
  - "Cada vez que módulo novo é migrado via Cowork → atualizar §case studies do RUNBOOK"
---

# ADR 0169 — Errata 0168: RUNBOOK-onda-cowork.md como 4º artefato canônico

## Contexto

[ADR 0168](0168-protocolo-wagner-sempre-tier-A-irrevogavel.md) formalizou a **triade governance**:

1. `memory/reference/PROTOCOLO-WAGNER-SEMPRE.md` — canon comportamental (11 regras)
2. `.claude/skills/wagner-protocol-enforce/SKILL.md` — enforcement Tier A
3. `.claude/agents/wagner-understand.md` — subagent decodificador

Wagner pergunta 2026-05-17 *"como deveria ser as próximas ondas para garantir a aplicação do novo Design?"* — explicita a necessidade de **playbook concreto operacionalizável** que cataloga:

- 12 fases sequenciais obrigatórias por Onda
- Critérios objetivos de "Onda completa" (gate)
- Estimate fator 10x ADR 0106 por tipo de Onda
- Anti-padrões catalogados de Ondas anteriores
- Pattern reusável pra outros módulos com prototype Cowork (14 prototypes inventariados)

ADRs canônicas são **append-only** ([proibições.md](../proibicoes.md)) — não posso editar 0168 retroativamente. Esta ADR 0169 é **errata leve** que adiciona o RUNBOOK como **4º artefato** da triade (vira tetra) sem modificar os 3 originais.

## Decisão

Formalizar [`memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md`](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) como **artefato canônico 4º** da governance pós-PROTOCOLO, equiparado às skills Tier A em status mas vivendo em `_DesignSystem/` (com os outros RUNBOOKs de Design).

Tetra completa:

| Artefato | Path | Função |
|---|---|---|
| **PROTOCOLO** | `memory/reference/PROTOCOLO-WAGNER-SEMPRE.md` | Canon comportamental — 11 regras R1-R11 |
| **Skill enforcement** | `.claude/skills/wagner-protocol-enforce/SKILL.md` | Tier A always-on — carrega protocolo no SessionStart |
| **Agent decoder** | `.claude/agents/wagner-understand.md` | Subagent proativo decodifica pedido cru |
| **RUNBOOK playbook** | `memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md` | 12 fases operacionais + gate Onda completa + estimate fator 10x + anti-padrões + pattern reusável |

Adiciona **regra de transparência de gaps** como feedback canon:

- [`memory/reference/feedback-ondas-cowork-transparencia-de-gaps.md`](../reference/feedback-ondas-cowork-transparencia-de-gaps.md) — cada PR de Onda inclui seção "NÃO INCLUI" no commit body com gaps remanescentes catalogados explicitamente. Transparência > otimismo.

## Justificativa

**Por que RUNBOOK não é só docs?**

- Operacionalmente referenciado pelo PROTOCOLO §"Como Claude AGREGA conhecimento novo" (R11 cita o RUNBOOK como base pra Ondas)
- Skill `wagner-protocol-enforce` Tier A carrega o RUNBOOK indiretamente via PROTOCOLO refs
- Agent `wagner-understand` Fase 4 referencia RUNBOOK quando o pedido envolve Cowork copy
- CI gate futuro pode validar que PR de Onda tem seção "NÃO INCLUI" no body (auto-check via gh api)

**Por que separar do PROTOCOLO?**

- PROTOCOLO é comportamental (11 regras de quando/como Claude age)
- RUNBOOK é técnico-operacional (12 fases de COMO entregar uma Onda específica)
- Misturar criaria PROTOCOLO de 800 linhas — perde foco do "se torne especialista em comportamento"

**Case study validador:**

Sells/Index Onda 1 (PR #1032 + #1034 + #1035 governance) — 2026-05-17 sessão `stupefied-noether-89f83d`:

- ✅ PR #1032 entrega Visual Base + R1 Fundação
- ❌ Não catalog ou DateFilter/GroupBy/Grade toggle como "NÃO incluído" → Wagner detectou via smoke Brave
- ✅ PR #1034 corretivo Onda 1.5 reintegra
- ✅ PR #1035 governance formaliza PROTOCOLO 10 → 11 regras (R11 errata pós-segundo turno Wagner)

Lição: **RUNBOOK formaliza a transparência de gaps** desde Onda 1 — evita Ondas corretivas reativas.

## Consequências

**Positivas:**

- ✅ Wagner enxerga próximas Ondas sem precisar perguntar "o que tá faltando?"
- ✅ Time MCP (Felipe/Maiara/Eliana/Luiz) tem playbook único pra Ondas Cowork — não precisa improvisar
- ✅ Estimate fator 10x ADR 0106 aplicado por tipo de Onda (planejamento de cycle mais preciso)
- ✅ 14 módulos com prototype Cowork em `prototipo-ui/prototipos/` têm trilha clara de migração
- ✅ Cross-session continuity preservada (skill Tier A recarrega PROTOCOLO + RUNBOOK linkado)

**Negativas / Trade-offs:**

- 🟡 Mais 1 documento canônico pra manter (atualizar §case studies quando módulo novo migra)
- 🟡 Tetra (4 artefatos) é mais complexa que triade (3) — onboarding novo dev é 30-45min vs 20min
- 🟡 Risco de drift entre RUNBOOK + PROTOCOLO se atualizar só um — mitigado pelo PROTOCOLO §11 incidentes que CITA Ondas corretivas

**Riscos mitigados:**

- 🛡️ Onda corretiva reativa (PR #1034) — RUNBOOK F10 obriga catalogar gaps pós-smoke
- 🛡️ Improviso de cada dev no time MCP — playbook canônico padroniza
- 🛡️ Wagner frustração reincidente "o que faltou?" — transparência catalogada no commit body

## Referências

- ADR 0168 [PROTOCOLO WAGNER SEMPRE Tier A IRREVOGÁVEL](0168-protocolo-wagner-sempre-tier-A-irrevogavel.md) — documento base
- ADR 0094 [Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md) — mãe
- ADR 0104 [MWART processo canônico](0104-processo-mwart-canonico-unico-caminho.md)
- ADR 0107 [Visual-comparison gate F3](0107-emendation-0104-visual-comparison-gate-f3.md)
- ADR 0114 [Cowork loop formalizado](0114-prototipo-ui-cowork-loop-formalizado.md)
- ADR 0141 [Migração Blade React skill](0141-migracao-blade-react-skill.md)
- ADR 0143 [FSM pipeline live prod biz=1](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [RUNBOOK-onda-cowork.md](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) — playbook canônico (este ADR formaliza)
- [feedback-ondas-cowork-transparencia-de-gaps.md](../reference/feedback-ondas-cowork-transparencia-de-gaps.md) — regra de transparência
- [PROTOCOLO-WAGNER-SEMPRE.md](../reference/PROTOCOLO-WAGNER-SEMPRE.md) — 11 regras canon
- Case study: PR #1032 + #1034 + #1035 (sessão `stupefied-noether-89f83d`)
