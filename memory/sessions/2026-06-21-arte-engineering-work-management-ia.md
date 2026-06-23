---
date: "2026-06-21"
topic: "Estado-da-arte — engineering work management / delivery governance em orgs aceleradas por IA. Problema real = COERÊNCIA (não perder o fio), não throughput. Compara com ADR 0294 + máquina de catraca do oimpresso. Avalia se 'fechar o loop' (shipped-log) é a peça certa."
authors: [C]
type: session
module: governance
pii: false
related_adrs:
  - 0294-metodo-dual-track-shapeup-catraca
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0070-jira-style-task-management-current-md-removed
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
---

# Estado-da-arte — engineering work management acelerado por IA (2026-06-21)

> **Raia:** governança de roadmap / backlog / changelog / entrega num contexto de velocidade anômala (~589 PRs/10d) onde o gargalo é **coerência**, não throughput. Read-only. Pesquisa (Fase 1) feita ANTES de ler `memory/` pra não contaminar.

## TL;DR

- **O melhor do ramo pra ESTE problema** não é Scrum nem SAFe — é a combinação **Linear (método de momentum + "issues over stories" + tudo conectado) + Shape Up (no-backlog, aposta expira) + Flow Framework (WIP/flow efficiency) + DX/DORA (loop fechado por métrica) + spec-driven/Spec Kit (provenance spec↔código)**. O oimpresso já sintetizou os 4 primeiros no ADR 0294 + máquina de catraca. O 5º (provenance) é onde mora o gap.
- **A métrica que importa pro problema do Wagner NÃO é DORA nem SPACE.** É **rastreabilidade/coerência**: % de trabalho entregue que tem registro, % de commits/PRs ligados a uma intenção, drift entre plano-declarado e realidade. DORA mede velocidade de pipeline (ele já é rápido demais); SPACE mede satisfação de time grande (ele não tem time). **Vanity pra ele:** deploy frequency, lead time, throughput de PR, DAU de IA. **Sinal pra ele:** drift de registro, órfãos, frescor.
- **O ADR 0294 está CERTO e é incomum** (membrana dual-track travada por catraca = estado-da-arte real, melhor que 90% do mercado, que confia em força de vontade). **Está INCOMPLETO num ponto:** fechou a porta de ENTRADA do cycle, deixou a SAÍDA aberta. "Fechar o loop" (shipped-log) **é a peça certa que falta** — e o mais importante: **já está 80% codada e não-commitada no disco**. O trabalho não é projetar, é **landar e ligar a parede**.
- **Recomendação:** commitar `shipped-log-generate.mjs` + rodar `--write` em CYCLE-08 + ligar a parede no `cycles-close`. Alto-impacto, baixo-esforço, sem pré-req bloqueante. ~2-3h IA-pair.

---

## FASE 1 — Quem é o melhor (pesquisa limpa, 2026)

| Referência | Em QUÊ é o melhor (mecanismo concreto, não buzzword) | Por que é referência pra ESTE problema |
|---|---|---|
| **Linear** (método + produto) | "Issues over user stories" (trabalho técnico **sempre** ligado a um item rastreável, nunca solto); ciclos de momentum; separa **Direction** (goals/escopo) de **Building** (execução); "build in public / launch continuously". Tudo conectado por design — você não consegue ter trabalho órfão. | É o padrão-ouro de **delivery governance de alta velocidade** com time pequeno. O produto **força coerência** estruturalmente: a UI não deixa o fio se perder. Exatamente o problema do Wagner. |
| **Shape Up** (Basecamp / Ryan Singer) | **No-backlog**: ideia não-apostada **expira**, não vira dívida que apodrece. Betting table off-cycle decide 1 caminho; ciclo enxuto executa só o apostado; cool-down entre ciclos. Dual-track (shaping off-cycle ‖ building in-cycle). | É a fonte direta do ADR 0294. Mata o "696 US apodrecendo no backlog" na raiz: aposta-ou-morre. Referência por durabilidade (vivo desde 2019, copiado por times pequenos). |
| **Flow Framework** (Mik Kersten / Tasktop) | 6 flow metrics; o par que importa: **Flow Load (WIP) → Flow Time** por Little's Law (mais WIP = tudo mais lento), e **Flow Efficiency** (% do tempo em trabalho ativo vs esperando). Common language que liga trabalho técnico a valor de negócio (traceability). | Dá a física do WIP cap do 0294 e a métrica certa de coerência (flow efficiency, não velocidade). Referência acadêmica+industrial de value-stream. |
| **DORA + SPACE + DevEx + DX AI Framework** (Forsgren, Storey, Noda, Greiler) | DORA = 4 métricas de pipeline. SPACE/DevEx = produtividade holística (feedback loops, cognitive load, flow state). **DX AI Framework (2026)** adiciona o eixo novo: medir IA por **utilização × impacto × custo**, e vigiar **code churn / PR revert rate / rework** que sobem com IA. | Referência mundial de "loop fechado por métrica". Crucial: o próprio DX/DORA **avisa que velocidade pura é vanity** e que sob IA o sinal vira **rework/churn/coerência**, não throughput. Valida o diagnóstico do Wagner. |
| **GitHub Spec Kit + spec-driven 2026** (+ paper FSE'26 "The Fast and Spurious") | Specify→Plan→Tasks→Implement: cada fase é um artefato que alimenta o próximo; `constitution.md` governa o agente; **cada diff é rastreável até a spec** ("provenance"). O paper FSE'26 mede que ganhos de velocidade com GenAI **mascaram** dívida/rework se você não medir coerência. | É o estado-da-arte do **problema novo**: orgs com agentes codando. A ideia-chave — *provenance de cada mudança até uma intenção* — é exatamente o elo que falta entre "589 PRs" e "o que foi entregue e por quê". |

**Achado-mãe da Fase 1:** ninguém no mercado combina (a) membrana dual-track travada por mecânica + (b) provenance spec↔entrega + (c) loop fechado por métrica de coerência. O melhor do mundo pega 1-2 dos 3. **O Wagner já tem (a) e (c) parcial; falta (b) e fechar o (c).**

---

## FASE 2 — Compara com o que o oimpresso já tem

| Dimensão (emergiu da Fase 1) | Estado-da-arte | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Membrana dual-track (entrada do cycle)** | Shape Up betting table + no-backlog | **ADR 0294 ACEITO**: 3 estágios (EXPLORAR→DECIDIR→EXECUTAR), WIP cap 3, parede = status enum, `plan-health.mjs` JÁ no CI (advisory), PLANS-INDEX, governance-audit. **Travado por catraca, não por força de vontade.** | **Nenhuma — supera o mercado.** Quase ninguém enforça a membrana mecanicamente. |
| **Tudo conectado / issues-over-stories (Linear)** | UI força ligação trabalho↔item | MCP tasks Jira-style (ADR 0070), `parent_plan`, cycle. Mas **commits não linkam US (drift 0/24 em 7d)** e backlog (696 US) está por módulo, desligado do cycle. | **Média.** A estrutura existe; a ligação na prática vazou. |
| **Porta de SAÍDA / shipped-log / changelog derivado** | release notes/changelog gerado da verdade (PRs), não da memória | **Loop ABERTO.** `shipped-log-generate.mjs` existe e está completo (140 linhas, funcional) mas **untracked (não commitado)**, `memory/governance/shipped/` **vazia**, nunca rodou `--write`, sem hook no `cycles-close`, sem sentinela. DS changelog drifou 10d/~80 PRs (já backfillado À MÃO hoje em 0.6.15–0.7.3 — sintoma curado, **recorrência não impedida**). | **Curta — mas é o gap.** Peça projetada e 80% codada; falta landar + ligar a parede. |
| **Provenance spec↔código (Spec Kit)** | cada diff rastreável até intenção | Programa SDD forte (anchors spec↔código, mas coverage 7.5%); commits não carregam `Refs:`. | **Média-longa**, mas **fora desta raia** (auditada na session SDD irmã, 68/100). Não atacar agora. |
| **Loop fechado por métrica de coerência** | flow efficiency, rework, drift | Daily Brief já reporta **cycle drift** (0/24!) — o sinal certo já existe. Falta a métrica de "registro completo" (shipped-log-health) e ligar drift a uma ação. | **Curta.** O brief já mede; falta a contrapartida de saída. |
| **WIP / Flow Load** | WIP cap por Little's Law | WIP cap 3 no 0294 (default). | **Nenhuma.** |
| **Roadmap único** | 1 fonte de verdade | **3 eixos que não se conversam**: `07-roadmap.md` órfão (abr/2026), goals do cycle, ROADMAPs por módulo. | **Curta** (é limpeza/arquivamento, não invenção). |

**O que JÁ É estado-da-arte (não subestimar):** a **máquina de catraca determinística** (status enum como parede + sentinelas `memory-health`/`plan-health`/`knowledge-drift` + ratchets + gates CI + governance-audit) é **genuinamente incomum**. O mercado (Linear/Shape Up/DX) entrega o *método*; quase ninguém entrega o *enforcement mecânico do método*. A filosofia "método na força de vontade morre; na catraca vive" (ADR 0256) é a vantagem estrutural do oimpresso. **Não trocar isso por nada pronto** (OPA, release-please, etc.) — seria regressivo.

---

## FASE 3 — O que está faltando (rankeado por impacto × esforço)

Esforço em IA-pair (ADR 0106: ~10x humano). Tudo nesta lista é **execução do último elo**, não paradigma novo.

| # | Gap | Impacto | Esforço (IA-pair) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **Commitar `shipped-log-generate.mjs` + rodar `--write` em CYCLE-08** (gera o registro de entrega que nunca existiu; preenche o buraco achado hoje de uma vez) | **Alto** | ~45 min | **Não.** Script pronto no disco. |
| 2 | **Ligar a parede: hook no `cycles-close` MCP** ("cycle só fecha com shipped-log gerado") + sentinela `shipped-log-health` no Daily Brief | **Alto** | ~1-2h | Depende do #1 (script commitado). |
| 3 | **DS changelog vira derivado** (bloco gerado pelo shipped-log + curadoria por cima; para de ser fonte paralela manual que drifa) | Médio | ~1h | Depende do #1. |
| 4 | **Arquivar `07-roadmap.md`** com ponteiro (roadmap de entrega = cycle; de descoberta = `_Ideias`). Mata o eixo fantasma. | Médio | ~20 min | Não. |
| 5 | **Ligar backlog→cycle**: `cycles-create` propõe corte priorizado do `_BACKLOG-GENERATED.md` por sinal/ROI (ADR 0105) em vez de metas escritas à mão | Médio | ~2-3h | Depende do #2 (loop fechado primeiro). |
| 6 | **Tratar o cycle drift 0/24** — NÃO forçando `Refs:` em todo commit (perde-se na velocidade; o shipped-log via `mergedAt`+scope já contorna). Decidir: aceitar o drift e confiar no shipped-log, OU adicionar 1 ligação leve (PR→plano). | Baixo-Médio | ~1h (decisão) | Depende do #1-2 existirem. |

### O que é OVERKILL pra ele (ser brutal)

- **DORA dashboard / deploy frequency / lead time:** ❌. Ele faz 589 PRs/10d. Velocidade não é o problema; medir velocidade é vaidade que consome tempo.
- **SPACE / DevEx surveys (satisfaction, cognitive load):** ❌. Frameworks de **time grande**. Ele é solo + IA + 4 entrando. Sem N pra survey ter sinal.
- **Merge queue / `strict:true` por causa de coerência:** ⚠️ útil pra anti-race (citado na session SDD), mas **não é deste problema** (coerência de registro). Não confundir as raias.
- **Reescrever em OPA/Rego, SAFe, Jira "de verdade", GraphRAG no backlog:** ❌❌. Regressivo. Ele já tem algo melhor.
- **Forçar `Refs: US-XXX` em todo commit por disciplina:** ❌. É força de vontade — vai falhar na velocidade (já falhou: 0/24). O shipped-log gerado da verdade (`mergedAt`+scope) é o caminho à prova de drift. Esse é o insight mais importante: **não conserte o drift de commit; torne-o irrelevante gerando o registro do que de fato mergeou.**

### Resposta direta às 4 perguntas do Wagner

1. **Melhor no ramo:** Linear (método de coerência) + Shape Up (no-backlog) + Flow Framework (WIP) + DX AI Framework (métrica sob IA) + Spec Kit (provenance). Tabela acima.
2. **Métricas que importam:** drift de registro (cycle drift, já medido!), % entregue com registro, órfãos, frescor, flow efficiency. **Vanity:** deploy freq, lead time, throughput, DAU de IA, velocity.
3. **O que ele precisa:** fechar a porta de saída do loop (shipped-log). Só isso, no curto prazo. O resto é limpeza. **Não** precisa de mais método.
4. **ADR 0294:** certo e incomum, **incompleto só na porta de saída**. "Fechar o loop" é a peça certa — e não há nada mais fundamental faltando. A máquina de catraca é o ativo; o elo aberto é o passivo.

---

## Recomendação final

**Comece pelo #1 — alto-impacto, baixo-esforço, sem pré-req bloqueante.** O `shipped-log-generate.mjs` já está completo e funcional no disco, mas **untracked** e nunca executado com `--write`. Enquanto ele não estiver commitado e rodado, "fechar o loop" é só papel (mesma regra de validade do ADR 0294 Onda 1: sem a sentinela, é só mais ADR).

**Próxima ação hoje:**
1. `git add scripts/governance/shipped-log-generate.mjs` + a proposal → 1 PR (`feat(governance): porta de saída do loop — shipped-log generator`).
2. Rodar `node scripts/governance/shipped-log-generate.mjs --since=2026-05-31 --until=2026-06-21 --cycle=CYCLE-08 --write` → gera `memory/governance/shipped/CYCLE-08.md`, o primeiro registro de entrega do projeto inteiro, que de quebra documenta os ~80 PRs achados hoje (o DS changelog já foi backfillado à mão; o shipped-log é o que **impede a recorrência**).
3. Só depois (PR seguinte): hook no `cycles-close` + sentinela `shipped-log-health` (#2). A parede vem depois do registro existir.

A casa (0294) foi construída e a porta de entrada trancada. Falta **gerar o primeiro recibo de saída e instalar a tranca da porta dos fundos** — não há reforma estrutural pendente.

## Fontes (Fase 1)
- Linear Method — https://linear.app/method
- Shape Up (Basecamp/Singer) — https://basecamp.com/shapeup/2.2-chapter-08
- Flow Framework (Kersten) — https://flowframework.org/ · https://getdx.com/blog/flow-metrics/
- DORA/SPACE/DevEx — https://www.infoq.com/articles/devex-metrics-framework/ · DX AI Framework https://getdx.com/whitepaper/ai-measurement-framework/
- "The Fast and Spurious: Developer Productivity with GenAI" (FSE'26) — https://arxiv.org/pdf/2510.24265
- GitHub Spec Kit (spec-driven, provenance) — https://github.com/github/spec-kit · https://github.blog/ai-and-ml/generative-ai/spec-driven-development-with-ai-get-started-with-a-new-open-source-toolkit/
- Agentic SDLC / governed agents 2026 — https://www.forrester.com/blogs/agentic-software-development-takes-the-lead-from-code-assistants-to-orchestrated-sdlc-agents/
