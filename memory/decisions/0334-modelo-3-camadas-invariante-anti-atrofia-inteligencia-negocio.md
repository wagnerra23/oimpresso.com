---
slug: 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
number: 334
title: "Modelo de 3 camadas (Produto ERP · Produto IA · IA-OS) + invariante anti-atrofia da inteligência de negócio"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-10"
module: governance
quarter: 2026-Q3
tags: [governanca, inteligencia-de-negocio, cliente-como-sinal, memoria, jana-bi, anti-atrofia, tres-camadas]
supersedes: []
superseded_by: []
related: [0105-cliente-como-sinal-guiar-sem-mandar, 0094-constituicao-v2-7-camadas-8-principios, 0329-doutrina-documentacao-de-processo-executavel, 0330-mapa-dos-niveis-estado-real-2026-07-constituicao, 0061-conhecimento-canonico-git-mcp-zero-automem]
---

# ADR 0334 — Modelo de 3 camadas + invariante anti-atrofia da inteligência de negócio

## Contexto

Em 2026-07-10, ao rodar a grade de réguas (medir o sistema contra o mercado), Wagner
levantou a suspeita: *"tenho dois módulos, 1 a IA e outro o ERP. A memória é essencial pra
a gestão do software-IA funcionar. Estou desconfiado que posso ter perdido minha
inteligência de negócio, pra me adequar ao mercado."*

Um adversário formal (5 forenses paralelos → acusação → defesa → juiz, tudo verificado
contra `origin/main` fresco) **confirmou a tese** — 2 forenses PERDA_FORTE, 3 PARCIAL, 0
sem-perda. O juiz refutou a rebatida-mãe da defesa ("293 docs de negócio novos em 60d" era
artefato do commit de restauração `8cd20a3486`; o authoring genuíno do último mês foi **3
SPEC e 0 de domínio**).

**Nunca escrevemos a relação entre as camadas** — e é na fronteira não-escrita que a deriva
mora. Este ADR documenta o sistema em 3 camadas e instala o invariante + alarme que faltavam.

**Evidência (verificada, datada 2026-07-10):**
- Share de escopo-negócio nos merges: **37% → 23% → 15%** (mai→jun→jul). Escopo `governance`
  isolado: **75 → 263 → 189** (2,4/dia → 18,9/dia ≈ **8×** no crossover de junho). Sem alarme,
  ninguém ouviu.
- **`client_signal` = 0 ocorrências no código** (a peça que a [ADR 0105] define pra virar
  "Larissa pergunta" em backlog), 14 meses depois. **Nenhum cycle ativo** (2 fontes). O loop
  do 0105 está quebrado nas duas primeiras casas (`cycle_goal → client_signal`).
- **Jana-BI (produto) congelada:** `SaleInsightAgent` parado no PR #1040 (projeto em ~#4000);
  recall que "melhorou" (7%→70%) é sobre corpus de **processo** (`mcp_memory_documents` =
  ADR/handoff), não fatos do cliente. Conserto US-COPI-130 não começou (recall 0.38).
- **Memória:** `memory/dominios/wr-comercial/` (415 arquivos, 26 anos de schema legado WR)
  íntegro mas **0 commits desde 09/jun**, desconectado do produto. Razão processo:negócio
  em memória ≈ **80:1**.

**Diagnóstico do juiz:** não é atrofia do **músculo** (quando entra cliente pagante o vertical
ainda dispara — Martinho/OficinaAuto biz=164 prova). É atrofia do **nervo** (o aparelho de
sentir/rotear sinal do cliente nunca foi instalado). Pausa disciplinada correta no OUTPUT que
azedou em atrofia do ÓRGÃO SENSOR.

## Decisão

### 1. As 3 camadas são canon (não confundir)

| Camada | O que é | Onde vive |
|---|---|---|
| **(A) Produto ERP** | módulos que o cliente usa | `Modules/Vestuario`, `Financeiro`, `NfeBrasil`, `Repair`, `ComunicacaoVisual`, `OficinaAuto`… |
| **(B) Produto IA** | a Jana: IA + memória do **negócio do cliente**, responde a Larissa sobre faturamento/metas/produtos com **dado real** | `Modules/Jana` (capacidade-de-negócio, não plumbing) |
| **(C) IA-OS / governança** | meta-camada que gere **como** o software é construído | gates CI, réguas, SDD, hooks, ADRs, memória de processo |

### 2. Invariante duro (o que "não pode acontecer de novo")

> **(C) existe pra servir (A)+(B). (C) nunca cresce sistemicamente enquanto (A)+(B) atrofia
> sem sinal de cliente.** Governança-meta é CAPEX de negócio (velocidade + segurança do
> cliente + onboarding do time), não um fim em si. Quando a régua/regulação passa a dirigir a
> priorização no lugar do sinal do cliente, a régua virou o produto — e isso é a deriva.

### 3. O critério que separa atrofia de pausa disciplinada

Não é *"construiu pouca feature?"* — é **"a capacidade de sentir e agir sobre sinal está
intacta e conectada, ou apodreceu?"**. **Músculo** (capacidade de construir) pode pausar sem
sinal (0105 correto). **Nervo** (sentir/rotear o sinal: `client_signal` + cycle de negócio)
**tem que existir e estar conectado, sempre** — deixá-lo apodrecer é atrofia, não disciplina.

### 4. A memória serve ao NEGÓCIO, não só ao processo

- **Dois corpora com índices distintos:** (a) FATOS/DOMÍNIO do cliente (`dominios/`,
  `clientes/`, fatos ROTA LIVRE) e (b) PROCESSO (`decisions/`, `sessions/`, `handoffs/`,
  `governance/`). A Jana-BI recupera do índice **(a)** — hoje recupera do (b), por isso
  "melhorou o recall" e continua burra sobre faturamento.
- **Máquina sai do cofre:** `*.proto-baseline.json`, `visual-comparison`, `UI-CATALOG` não
  moram em `memory/requisitos/<Modulo>/` (poluem a inteligência de negócio com plumbing).
- O cofre `wr-comercial` deixa de ser dump de schema e vira camada anticorrupção **viva** que
  alimenta os verticais + a Jana.

### 5. O alarme que morde (guarda-corpo, não teto rígido)

**Rejeitado o teto rígido ≤35% de governança** — criminalizaria o CAPEX legítimo e a auto-poda
que já existe ([ADR 0271], [0314]). No lugar:
- **Sentinela advisory** [`scripts/governance/negocio-vs-governanca-ratio.mjs`](../../scripts/governance/negocio-vs-governanca-ratio.mjs):
  trackeia o ratio semanal **feature-de-negócio ÷ PR-de-governança** e emite WARN quando
  governança domina a janela (o crossover de junho teria disparado). Advisory — required é só
  Tier-0 ([ADR 0314]); esta é trend-alarm, não gate bloqueante.
- **0105 morde no lado do processo:** todo PR de governança-meta cita o sinal-de-cliente/métrica
  que o justifica; sem isso é feature-wish e sai do cycle (política; enforcement por PR-body é
  passo futuro).

## Justificativa

O sistema tinha 3 camadas de fato mas 0 documentação da relação entre elas — e a ausência de
alarme deixou o crossover de 8× passar silencioso. Documentar sem mecanismo seria teatro (o
anti-padrão que a [ADR 0329] e o §5 de proibições já condenam, reforçado no PR #4074 desta data).
Por isso: doutrina **+** sentinela que morde. O teto rígido foi rejeitado porque a defesa provou
(verificado) que a governança é CAPEX real e já se auto-poda — punir o estoque erraria o alvo; o
que precisa mudar é o **fluxo** (redirecionar energia pra A/B quando o nervo detecta sinal).

## Consequências

**Backlog de recuperação (ordem por ROI — do veredito; cada um é trabalho à parte que Wagner
prioriza, não coberto por este ADR):**

1. **[A/B · ROI #1 · custo zero] Cravar um cycle com goal de NEGÓCIO** — resolver FIN-004
   (cobrança ROTA LIVRE, HITL pendente) + 1 pergunta que a Larissa faz de verdade. Destrava o
   loop do 0105.
2. **[B] Executar US-COPI-130** — Jana-BI recall **0.38 → ≥0.60**, medido antes→depois com
   `jana:ragas-real-eval`. Usar a régua cara pra MELHORAR, não só medir.
3. **[memória+produto] Construir o `client_signal` (US-INFRA-002)** — o órgão sensor do 0105,
   hoje 0-hit.
4. **[memória] Bipartir o corpus + índice Jana-BI próprio + tirar machine-files de
   `requisitos/<Modulo>/`.**
5. **[memória→A] Descongelar `wr-comercial` como camada anticorrupção viva** pro Vestuario
   (único vertical em prod, com só 8 arquivos em requisitos).

**Imediato (neste ADR):** a sentinela `negocio-vs-governanca-ratio.mjs` + self-test entram
junto; wiring como workflow advisory semanal é o passo seguinte.

## Verificação

- Adversário `adversario-inteligencia-negocio` (8 agentes, 2026-07-10): 2 PERDA_FORTE, 3
  PARCIAL, 0 sem-perda; juiz refutou a rebatida da defesa com número.
- `git grep client_signal` em app/Modules/database/routes/resources = **0**.
- `cycles-active` = "Nenhum cycle ATIVO"; `brief-fetch` = "Cycle: — (—)".
- Sentinela roda contra `origin/main` e reproduz o crossover — governança em
  `gov/(gov+neg)`: **mai 38% → jun 64% → jul 78%**; o alarme dispara **agora** (jul 78% >
  limiar 65%), confirmando a suspeita com número vivo. Self-test `.test.mjs` prova
  classificação + limiar (15/15 — morde no crossover, libera no equilíbrio).

## Referências

- [ADR 0105 — cliente como sinal, guiar sem mandar](0105-cliente-como-sinal-guiar-sem-mandar.md) (o loop que este ADR reconecta)
- [ADR 0329 — doutrina de documentação de processo executável](0329-doutrina-documentacao-de-processo-executavel.md) (doc sem bite = teatro)
- [ADR 0330 — mapa dos níveis, estado real 2026-07](0330-mapa-dos-niveis-estado-real-2026-07-constituicao.md)
- [ADR 0061 — conhecimento canônico git/MCP](0061-conhecimento-canonico-git-mcp-zero-automem.md) (memória como cofre)
- Veredito do adversário + grade de réguas — sessão 2026-07-10 (Artifact rev.2 + este ADR)
