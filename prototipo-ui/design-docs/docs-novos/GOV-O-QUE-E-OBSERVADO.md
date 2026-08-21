---
id: reference-gov-o-que-e-observado
name: Governança — O que é observado
description: Quatro instrumentos, quatro perguntas diferentes — saber qual abrir é metade do diagnóstico — e o ponto cego que o próprio canon registra.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: governanca
nav_order: 30
lente: [operar, construir]
---

# Governança — O que é observado

> Quatro instrumentos, cada um respondendo uma **pergunta diferente**. Abrir o errado custa a
> primeira meia hora de todo diagnóstico.

| A pergunta | O instrumento | Onde |
|---|---|---|
| A IA está cara, lenta ou alucinando? | **Langfuse** — trace por empresa ([ADR 0132](../decisions/0132-langfuse-self-host-ct100.md)) | CT 100 |
| Onde o request gastou o tempo? | **Jaeger + OTel** ([ADR 0162](../decisions/0162-otel-collector-prod-observability.md)) | CT 100 |
| O sistema está saudável hoje? | `jana:health-check` — checagens SQL diárias | agendado |
| Os módulos estão apodrecendo? | **vital-signs** — regerado à noite | governança |

## Saúde da documentação também é observada

- [`docs:loop`](../../scripts/governance/documentation-loop.mjs) — tira um retrato e compara com
  o anterior: doc que envelheceu junto com o código que ele descreve, doc órfão, staleness de
  briefing.
- [`docs:relink --detect`](../../scripts/governance/doc-auto-relink.mjs) — link que vai virar 404.
- [`ds:report`](../../scripts/ds-report.mjs) — desvio do design system.

Ver [Qualidade e CI](TECNICO-QUALIDADE-CI.md) para o que reprova o merge e o que só avisa.

## O ponto cego — declarado, não escondido

A régua do projeto media bem **construir-e-governar** e mal **operar**: o eixo *rodar-e-observar*
está sub-medido, e isso está registrado em ADR
([0333](../decisions/0333-emenda-0330-eixo-rodar-e-observar-submedido.md)), não em conversa.

Um sistema que sabe onde não enxerga é diferente de um que acha que enxerga tudo. Quando um
número desta página parecer bom demais, a primeira pergunta é **o que ele não mede**.
