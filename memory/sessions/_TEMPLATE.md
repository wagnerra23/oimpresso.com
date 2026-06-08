<!--
  USE COMO BASE — NÃO EDITAR (canônico).
  Copie pra `memory/sessions/{{YYYY-MM-DD}}-{{slug-kebab}}.md`.
  Validado pelo CI gate `memory-schema-gate-extended.yml`.

  Regras filename: `^YYYY-MM-DD-<slug-kebab>.md$`
    OK:    2026-05-15-audit-implement-d6-schema-gate.md
    OK:    2026-05-15-fsm-pipeline-canon.md
    ❌:    2026-05-15-Nome-com-Maiusculas.md  (case)
    ❌:    15-05-2026-foo.md                  (formato data)

  Override emergencial: `<!-- schema-allowlist: <razão> -->`.
-->
---
date: {{YYYY-MM-DD}}
hour: "{{HH:MM BRT}}"
duration: "{{2.5h}}"
topic: "{{Resumo 1 linha do que aconteceu nesta sessão}}"
authors: [W, C]   # W=Wagner F=Felipe M=Maiara L=Luiz E=Eliana C=Claude
outcomes:
  - "{{O que foi entregue}}"
  - "{{Decisão tomada}}"
prs: []           # [123, 456]
us:  []           # ["US-COPI-001"]
related_adrs: []  # ["0094-constituicao-v2-7-camadas-8-principios"]
---

# Session log {{YYYY-MM-DD}} — {{título curto}}

## TL;DR

{{1-3 frases do que aconteceu, decisão tomada, próximo passo}}

## Contexto

{{Por que esta sessão aconteceu — pain point, request Wagner, follow-up de sessão anterior}}

## Cronologia

| Quando | Evento |
|---|---|
| {{HH:MM}} | {{evento}} |
| {{HH:MM}} | {{evento}} |

## Entregas

- **{{Artefato 1}}** — {{caminho}} ({{N linhas}})
- **PR #{{N}}** — {{título}} → {{merged|aberto|draft}}
- **ADR {{NNNN}}** — {{título}} → {{accepted|proposed}}

## Decisões cinzentas resolvidas

| Pergunta | Decisão Wagner | Justificativa |
|---|---|---|
| {{...}} | {{...}} | {{...}} |

## Aprendizados / pegadinhas

- {{Lição que vale pro próximo agente}}

## Próximos passos (não-bloqueante)

- [ ] {{follow-up}}
- [ ] {{follow-up}}

## Referências

- Handoff: [{{YYYY-MM-DD-HHMM-slug}}.md](../handoffs/{{YYYY-MM-DD-HHMM-slug}}.md)
- ADRs: [{{NNNN}}](../decisions/{{NNNN-slug}}.md)
