---
title: "Método de planejamento: dual-track + Shape Up travado por catraca (incubadora → aposta → cycle)"
status: proposed
date: "2026-06-20"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated
  - 0070-jira-style-task-management-current-md-removed
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0094-constituicao-v2-7-camadas-8-principios
origem: "Wagner 2026-06-20: 'quando peço pra evoluir os planos e pesquisar melhores possibilidades sinto que o sistema fica louco'. Diagnóstico: trabalho de descoberta (divergente) e de entrega (convergente) dividem a MESMA superfície, sem membrana. A sessão de 2026-06-20 (atendimento automático: plano → estado-da-arte → ROI → PLANS-INDEX) foi o caso vivo do sintoma."
---

# Método de planejamento — dual-track + Shape Up travado por catraca

## Contexto

**Sintoma (Wagner):** "tenho o MCP tasks e o ciclo, mas quando peço pra **evoluir os planos e pesquisar melhores possibilidades**, o sistema fica louco."

**Diagnóstico:** existem dois modos de trabalho com naturezas opostas, e hoje eles usam a **mesma superfície**:

- **Descoberta (divergente)** — pesquisar, evoluir plano, estado-da-arte, ROI, comparar opções. É pra ser abundante e contraditório. Saudável.
- **Entrega (convergente)** — tasks, cycle. É pra ser enxuta, 1 caminho, rastreada. Saudável.

Sem uma **membrana** entre as duas, a descoberta despeja em cima da entrega: doc novo que contradiz o cycle, opção que vira task sem decisão, plano que ninguém fechou. O "fica louco" é o nome clínico disso. Medição em 2026-06-20 ([PLANS-INDEX](../../requisitos/_processo/PLANS-INDEX.md)): **15 planos, 0 com `reviewed_at`, 0 ligados ao MCP** — os planos se perdem porque não há parede nem ritual de convergência.

**Não é falta de estrutura.** As peças já existem e foram montadas por instinto — faltava nomeá-las e ligá-las:

| Peça existente | Papel no método |
|---|---|
| [`requisitos/_Ideias/`](../../requisitos/_Ideias/BRIEFING.md) ("incubadora, espera sinal") | pool de pitches / trilha Discovery |
| [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal) | disciplina de aposta (só aposta com sinal) |
| `decisions/proposals/` | pitches formais aguardando aposta |
| cycle + tasks MCP ([ADR 0069](../0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated.md)/[0070](../0070-jira-style-task-management-current-md-removed.md)) | build cycle / trilha Delivery |
| [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md) + [0270](../0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md) | catraca/sentinela/decaimento (anti-podridão) |
| **FALTA** | a **membrana** (betting table) + **WIP cap** + nome do método |

## Decisão

Adotar **dual-track travado por catraca**: descoberta e entrega são trilhas separadas, com uma membrana de aposta entre elas, **enforçada mecanicamente** (status/sentinela/WIP) — não por força de vontade.

### Os 3 estágios

1. **EXPLORAR** (Discovery · divergente) — pesquisa, estado-da-arte, ROI, drafts de plano. Mora em `_Ideias`, session docs e plano com `status: proposto`. **Regra dura: não toca o cycle, não cria task.** Ilimitado, bagunça permitida — é o laboratório.
2. **DECIDIR** (a membrana / betting table) — **1 ritual**: escolhe 1 caminho por ROI + sinal ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) → vira ADR/proposal aceito OU plano `status: ativo` com **gate-de-saída (DoD)** e **kill-condition**. Wagner aprova. **Só 1 caminho cruza.**
3. **EXECUTAR** (Delivery · convergente) — só o decidido vira tasks MCP com `parent_plan=<slug>` dentro de um cycle. O cycle fica enxuto porque só entra o que foi apostado.

### As invariantes mecânicas (a catraca — por que sobrevive)

- **A parede é o `status` enum:** `proposto` = incubadora; `ativo`/`em-execução` = cruzou a membrana. *Um plano só vira `em-execução` quando existe task MCP `parent_plan` num cycle.* Essa frase É a parede.
- **WIP cap no cycle:** máx N planos `em-execução` por cycle (contagem no [PLANS-INDEX](../../requisitos/_processo/PLANS-INDEX.md)). Incubadora é ilimitada; a linha de produção, não.
- **Descoberta é off-cycle por padrão:** pesquisa nunca entra nos goals do cycle direto — só pela membrana.
- **Sentinela `plan-health`** (estende `memory-health.mjs`, [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md) Onda 1): flaga `status` ausente, `reviewed_at` > 30d, plano órfão (sem `parent_plan`), drift (status ≠ realidade das tasks), `superseded` sem ponteiro. Sai no Daily Brief + gate advisory no CI.
- **Declaração de modo:** todo pedido/sessão declara **EXPLORAR** (diverge, nada entra no cycle) ou **EXECUTAR** (converge, cria task). Default = EXPLORAR (mais seguro).

## Por quê (justificativa)

1. **O gargalo é decisão, não execução.** Com IA-pair 10x ([ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md)), construir é barato; o caro é decidir e não perder o fio. Dual-track ataca foco/decisão — Scrum otimiza throughput, que não é o problema.
2. **Shape Up "sem backlog, aposta expira" mata o "planos se perdem"** na raiz: ideia não-apostada não vira dívida que apodrece.
3. **WIP cap protege time pequeno + sessões paralelas** (vetor de caos conhecido) de espalhar.
4. **Betting com sinal já é a [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)** — o método não é importado, é o nosso, formalizado.
5. **O melhor método sobrevive sem força de vontade.** Por isso a versão certa é a *travada por catraca* (status enum + sentinela + WIP), não a de boa intenção. Método que depende de disciplina humana morre; método na catraca vive ([ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)).

## Linhagem (de onde vem)

Síntese de: **dual-track development** (Desirée Sy 2007; Cagan/Patton) + **Double Diamond** (Design Council 2005) + **Shape Up** (Basecamp/Ryan Singer 2019: shaping off-cycle · betting table · no-backlog) + **Kanban** (WIP limits) + **Stage-Gate** (Cooper 1986). Ancorado na máquina local de catraca/decaimento ([ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)/[0270](../0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md)) e na disciplina de sinal ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)).

## Consequências

**Positivas:** planos param de se perder (cada um tem status/dono/frescor/gate); cycle limpo (só entra o apostado); descoberta ganha casa legítima (deixa de poluir); rastreável fim-a-fim (pitch → aposta → tasks).

**Custo/negativas:** precisa do gerador `plans-index` + sentinela `plan-health` (custo determinístico, sem LLM no caminho crítico); risco de **over-ceremony** — mitigação: versão mínima viável = *declarar modo + 1 gate de aposta + WIP cap*, nada além; exige o hábito de declarar o modo (a defesa mecânica cobre quando o hábito falha).

## Alternativas consideradas

- **Scrum / backlog infinito** — ❌ apodrece (é o sintoma atual: planos sem dono nem revisão).
- **Kanban puro (fluxo contínuo, sem cycles)** — ⚠️ flui, mas sem *shaping* a divergência nunca fecha. WIP limit **adotado**, o resto não.
- **"Só se organizar mais"** — ❌ depende de força de vontade; é exatamente o estado que falhou.
- **Não fazer nada** — ❌ o "fica louco" continua e os planos seguem se perdendo.

## Implementação (ondas)

- **Onda 0 (feita 2026-06-20):** [PLANS-INDEX](../../requisitos/_processo/PLANS-INDEX.md) criado; convenção `## Status vivo` definida; esta proposal.
- **Onda 1:** gerador `plans-index` (mesmo padrão do índice de ADR) + sentinela `plan-health` (estende `memory-health.mjs`) + 1 linha no Daily Brief.
- **Onda 2:** generalizar `parent_audit → parent_plan` (skill `audit-to-backlog`); WIP cap como check; "declarar modo" vira parte do protocolo de sessão (CLAUDE.md).
- **Onda 3:** retrofit do `## Status vivo` nos 15 planos existentes (status + `reviewed_at`) + triagem dos 4 "revisar".

## Refs

- [PLANS-INDEX](../../requisitos/_processo/PLANS-INDEX.md) · [_Ideias](../../requisitos/_Ideias/BRIEFING.md)
- ADRs: [0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) · [0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md) · [0270](../0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md) · [0069](../0069-taskregistry-mcp-tools-canonico-tasks-md-deprecated.md) · [0070](../0070-jira-style-task-management-current-md-removed.md) · [0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md) · [0094](../0094-constituicao-v2-7-camadas-8-principios.md)
- Skills: `audit-to-backlog` · `brief-update` · `sync-mem`
