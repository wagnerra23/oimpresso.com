---
id: requisitos-template-feature-requirements
---

<!--
  TEMPLATE CANÔNICO — gerado exclusivamente por `npm run feature:init`; nunca copie à mão.
  Depois da geração, cure os {{...}} na pasta criada pela máquina.
  Validado por: node scripts/governance/feature-lint.mjs (advisory).
  A US continua no SPEC.md do módulo — este arquivo DETALHA, aponta, nunca duplica a decisão.
-->
---
feature: {{slug-kebab}}
module: {{PascalCase — igual à pasta memory/requisitos/<Mod>/}}
us: ["US-{{MOD}}-{{NNN}}"]
parent_plan: {{parent-plan}}
created: "{{YYYY-MM-DD}}"
---

# Requirements — {{título curto da feature}}

> **US-mãe:** [US-{{MOD}}-{{NNN}}](../../SPEC.md) · **Sinal (ADR 0105):** {{cliente paga+reporta OU métrica em drift — sem sinal, isto não deveria existir}}

## User story

**Como** {{persona real — ver §1 Personas do SPEC}}
**Quero** {{capacidade}}
**Para** {{outcome de negócio mensurável}}

## Clarifications (fase Clarify — Spec Kit 2026)

> Desambiguação estruturada ANTES do plan. Populada pelo agente [`wagner-understand`](../../../../.claude/agents/wagner-understand.md)
> (+ skill `wagner-request-refiner`): pedido vago → perguntas dirigidas → respostas gravadas AQUI.
> Persistir no contrato (vs só no session log efêmero) evita que sessão futura re-pergunte.
> Feature sem ambiguidade real: `_nenhuma — pedido não-ambíguo_`.

- **{{YYYY-MM-DD}}** — P: {{pergunta de ambiguidade}} → R: {{decisão}}.

## Acceptance criteria (EARS — ADR 0306 importou de Kiro)

> **6 formas EARS canônicas:** ubíqua (`O SISTEMA DEVE`) · evento (`QUANDO <gatilho>, O SISTEMA DEVE`) ·
> estado (`ENQUANTO <estado>, O SISTEMA DEVE`) · indesejado (`SE <condição>, ENTÃO O SISTEMA DEVE`) ·
> opcional (`ONDE <feature> presente, O SISTEMA DEVE`) · complexa (combinação das anteriores).
> Cada AC precisa de **forma de prova** (Pest, SQL, curl, screenshot) — AC improvável de provar = reescrever.

- **AC-1** — QUANDO {{gatilho}}, O SISTEMA DEVE {{resposta observável}}. _Prova: {{como se verifica}}._
- **AC-2** — SE {{condição indesejada}}, ENTÃO O SISTEMA DEVE {{comportamento seguro}}. _Prova: {{...}}._
- **AC-3** — O SISTEMA DEVE {{invariante — ex: escopo `business_id` em toda query (Tier 0, ADR 0093)}}. _Prova: Pest cross-tenant biz=1 vs biz=99._

## Fora de escopo

- {{o que explicitamente NÃO entra — cite a US/feature onde vive, se houver}}
- {{...}}

## Referências

- SPEC: [SPEC.md](../../SPEC.md) (US-{{MOD}}-{{NNN}}) · ADRs: {{links}} · Charter/casos (se toca tela): {{`<Tela>.casos.md`}}
