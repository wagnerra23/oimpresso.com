---
name: reguas-do-sistema
description: >
  ATIVAR quando Wagner pedir "grade de réguas", "onde sou fraco vs mercado",
  "quais ideias estão acima do mercado", "reaplique a grade", "compare meu
  processo/IA OS com os melhores", OU em cadência: ao fechar uma leva de chips
  da grade anterior, trimestralmente, ou quando o mapa-dos-níveis ganhar
  sucessora. É o irmão do `capterra-senior` apontado pro PROCESSO/IA OS (não
  pra um módulo de produto): pesquisa quem põe a barra 2026 por dimensão,
  REFUTA toda claim de superioridade própria, VERIFICA cada fraqueza contra o
  repo VIVO antes de dar nota (lição 7/9 de 2026-07-09), e entrega grade com
  evidência + chips prontos + rejeitados→proibições §5. Dispara o workflow
  versionado `.claude/workflows/reguas-do-sistema.js`.
tier: B
status: active
version: 1.0
authority: canonical
related_adrs: [0329-doutrina-documentacao-de-processo-executavel, 0330-mapa-dos-niveis-estado-real-2026-07-constituicao]
---

# Skill: reguas-do-sistema — medir o IA OS contra quem põe a barra

> **Origem (2026-07-09, Wagner):** *"tenho que me comparar a técnicas (réguas) acima do
> mercado… crie o método de como pesquisar e documentar as réguas, para deixar sempre
> arrumado o sistema."* Método destilado da rodada real daquele dia (27 agentes de pesquisa
> + refutação + o desafio do Wagner que derrubou 7/9 notas por mecanismo-já-existente).

## O ciclo que mantém o sistema arrumado (onde esta skill entra)

```
MEDIR (esta skill) → VERIFICAR no repo → CORRIGIR (chips) → TRAVAR (sentinela/gate)
      ↑                                                            ↓
      └────────── índices contínuos apontam o próximo ←── OPERAR ──┘
```

Duas fases do ciclo **já são máquina contínua** (TRAVAR: gate-selftest/sentinelas ·
APONTAR: doc-freshness-score, adr-proposto-parado, DORA/outcome-metrics). Esta skill
é a fase **MEDIR** — periódica, contra o mercado.

## Os dois eixos que a grade mede (para não repetir o ponto cego)

O array `DIMS` cobre **dois eixos** — não confundir:

1. **CONSTRUIR-E-GOVERNAR** (6 dims originais): spec/governança, design→código, memória/conhecimento, orquestração adversarial, evals-outcome (DORA/agente-DEV) e ERP-IA-produto.
2. **RODAR-E-OBSERVAR** (4 dims add 2026-07-10): `observabilidade-agente`, `qualidade-drift-ia-producao` (a Jana viva em prod — distinta do outcome do agente-DEV), `seguranca-do-agente`, `custo-eficiencia`.

Até 2026-07-10 a grade só media o eixo 1 — o loop de RODAR-E-OBSERVAR a IA que o sistema produz (a Jana em produção) nunca virava régua, apesar de o rastreador "FECHAR O LOOP DO IA-OS" listar 2 desses como P0 pendentes. Ponto cego registrado na **[ADR 0333](../../../memory/decisions/0333-emenda-0330-eixo-rodar-e-observar-submedido.md)** (emenda à 0330 — Propriedade 5 da doutrina [0329] fechando sobre a própria grade). **Isto só adiciona a MEDIÇÃO** — construir observabilidade/drift/gate é trabalho Tier-0 à parte (decisão de custo do Wagner).

## Como rodar

```
Workflow({ scriptPath: ".claude/workflows/reguas-do-sistema.js",
           args: { base: "<worktree FRESCO do origin/main>" } })
```
Pré-requisito: `git worktree add --detach <path> origin/main` (nunca medir em checkout stale
— guard de base-freshness existe por isso). `args.dimensoes` opcional pra rodada parcial
(re-medir só a dimensão de um chip concluído).

## As 6 regras duras do método (violou = grade rejeitada)

1. **Dossiê do mapa VIVO, nunca de memória** — a Fase 0 lê o `mapa-dos-niveis` corrente
   (0330 ou sucessora) + proibições §5. Dossiê de cabeça = falso-negativo garantido.
2. **Só dimensão grade-ável entra** — critério objetivo (número, artefato verificável ou
   gate on/off). "Qualidade" não é régua; *change-failure-rate* e *% cross-platform* são.
3. **Toda claim "estou acima" passa pelo REFUTADOR** (contexto zero, default derrubar).
   Rodada de referência: 26 claims → 0 acima-puras. Sem refutação, a grade é ego.
4. **Nenhuma nota sem VERIFICAÇÃO no repo vivo** — toda fraqueza apontada pela pesquisa é
   caçada no repo (workflows/scripts/skills/hooks/registries) ANTES da nota. Lição 7/9:
   os mecanismos existiam, invisíveis. Achado existia-mas-invisível → **indexar no mapa**
   (é a Propriedade 5 da doutrina 0329 fechando o loop).
5. **Régua sempre com fonte** (produto/feature/prática publicada + link) e **nota sempre
   com evidência** (file:line ou prova de ausência). Retrato **datado** — envelhece por
   design; drift material → re-rodar, nunca editar o retrato velho.
6. **Saída vira ação ou lápide**: fraqueza real → chip (com as ressalvas do adversário
   embutidas no prompt); proposta rejeitada → proibições §5 (não re-propor); claim
   refutada → registrada (não re-alegar sem re-verificar).

## Onde registrar (fecha o protocolo)

- **Artifact** navegável da grade (padrão: tabela técnica × régua × nota × degrau).
- **Session log** `memory/sessions/YYYY-MM-DD-reguas-<escopo>.md` com placar + links.
- Achados "existia-mas-invisível" → **emenda/sucessora do mapa-dos-níveis** (indexar).
- Rejeitados → `memory/proibicoes.md` §5 (código) ou `PROCESSO_MEMORIA_CC §5` (design).
- Chips → `spawn_task` com verify-antes-de-construir + Tier-0 + para-no-PR.

## Anti-padrões (desta skill)

- ❌ Rodar sem refutação ("10 dimensões dizem que estou acima" — não valem sem cético).
- ❌ Nota por pesquisa-só (a pesquisa NÃO vê o repo; 7/9 provou).
- ❌ Perseguir a nota (Goodhart; errata 0159): a grade aponta ONDE trabalhar — o índice
  sobe como consequência de trabalho real, nunca como alvo.
- ❌ Re-medir tudo a cada chip: rodada parcial por dimensão (`args.dimensoes`) é mais barata.
