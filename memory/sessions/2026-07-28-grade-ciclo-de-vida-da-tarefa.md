---
date: "2026-07-28"
topic: "Grade do ciclo de vida da tarefa (fecha → dono → changelog → RAG) vs melhor do mercado: 5,0/10. O detector de done-ness enxerga 11 de 114 porque o denominador dele é um campo que falta em 458 das 985 US"
authors: [C, W]
type: session
module: Governance
pii: false
related_adrs:
  - 0337-emenda-0144-forward-close-por-ancora-verificada
  - 0302-ancora-fonte-unica-doneness
  - 0144-tasks-db-canonico-spec-template
  - 0070-jira-style-task-management-current-md-removed
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Grade do ciclo de vida da tarefa — 5,0/10

## TL;DR

[W] perguntou: as tarefas em aberto já foram resolvidas? Se sim, deveriam ir pro RAG, virar changelog, e ter dono — **o que diz a máquina que vigia isso, como ela deveria ser, e como testar que dispara no momento certo?**

Medido: **114 US têm âncora verificada em código e nenhuma declaração de done.** O detector que deveria vê-las (`doneness-lint`, ADR 0302) reporta **10**. A diferença não é bug de lógica — é **denominador**: ele classifica pelo campo `status:` do blockquote, e esse campo **falta em 458 das 985 US**. Das 114, **103 (90%) são invisíveis a ele por construção**.

O ciclo **não** fecha ponta a ponta. Fecha na dimensão *status* (e bem). Nas outras três — dono, changelog, RAG — não.

## O que foi medido (recibos, não impressões)

Varredura dos 91 `memory/requisitos/*/SPEC.md` de `origin/main`, 2026-07-28:

| fato | número |
|---|---|
| US totais nos SPECs | 976 |
| com âncora verificada (`verificado@sha`, sem `_pendente_`/`_parcial_`) | 316 |
| declaram done (blockquote **ou** título) | 240 |
| **entregues mas NUNCA declaradas done** | **114** |
| └ com campo `status:` → visíveis ao `doneness-lint` | 11 |
| └ **sem** campo `status:` → **invisíveis** | **103** |
| SPECs com `done_at:` (condição do CHANGELOG) | **1 de 91** |
| `Modules/*/CHANGELOG.md` | 33, dos quais **22 parados no mesmo dia** (2026-06-08) |

O que o `doneness-lint` reporta globalmente: `985 US · 527 com status: · 458 sem status:` · `CONFLITOS: 18 = 8 done-sem-âncora + 10 aberto-com-âncora`.

**As 114 não são "prontas para fechar".** Amostra de 6 módulos (Fiscal, Connector, Arquivos, PontoWr2, Jana, Superadmin): todas com `status: <ausente>` e **nenhuma declaração de done em lugar nenhum** — só a âncora. Âncora prova *implementação*, não *aceite*: o próprio `anchor-lint` conta `252 US implementada SEM aceite/DoD · 356 SEM teste que a cobre`. Fechá-las por conta própria seria inventar a declaração humana que a [ADR 0337](../decisions/0337-emenda-0144-forward-close-por-ancora-verificada.md) exige como 2º sinal — o oposto do que ela protege.

## A grade

Pesos entre parênteses. Nota ponderada = **5,0/10**.

| # | Dimensão | Melhor do mercado (2026) | oimpresso (medido) | Nota |
|---|---|---|---|---|
| 1 | **Detecção de done** (2) | DoD como critério com *evidência*; CI marca ticket sem teste/doc como não-mergeável | forward-close exige **2 sinais independentes** — declaração humana **+** âncora com paths conferidos no disco. Fail-closed, nunca reabre. Disparou hoje 13:08 nas 17 | **8** |
| 2 | **Cobertura do detector** (2) | — | vê **11 de 114**. Denominador é o campo `status:`, ausente em 458/985 US | **3** |
| 3 | **Dono no fechamento** (2) | *"todo job aberto precisa de dono ou hold documentado"*; item sem dono envelhece silencioso e é a maior fonte de atraso | as 17 fecharam `unowned`. Nada exige dono ao fechar; a sentinela diária cobre só `todo` | **2** |
| 4 | **Changelog** (1) | IA gera release notes de issues/PRs fechados; **review humano é o passo crítico**; cadência semanal correlaciona com retenção | gerador **existe e está desligado** — veredito adversarial 2026-06-16, correto: `done_at` em 1/91 SPECs sairia vazio. Mas a condição **não se moveu em 6 semanas** e ninguém é dono de destravá-la | **3** |
| 5 | **RAG / captura** (2) | Glean indexa tickets + código + docs (100+ conectores); grafo de conhecimento p/ multi-hop | `mcp:sync-memory` a cada 5 min indexa `memory/` (SPEC incluso) → `mcp_memory_documents` + Meilisearch híbrido. **Mas o evento de fechamento** (`acceptance_ref` derivado do sha, `completed_at`, quem fechou) **fica só no DB e nunca chega ao RAG** | **6** |
| 6 | **Prova de que a máquina morde** (1) | CI flagging automatizado | `gate-selftest` com fixture boa/ruim + `design-gate-bites` ("teria mordido no main"). **Paridade com boa prática, não diferencial** — fixture positiva/negativa é padrão em Semgrep/OPA há anos (lápide §5 2026-07-09) | **8** |

`(8×2 + 3×2 + 2×2 + 3×1 + 6×2 + 8×1) ÷ 10 = 4,9` → **5,0/10**

## Onde está o buraco, exatamente

O ciclo tem **quatro** dimensões e só a primeira fecha:

1. **status** → fecha, e bem. Dois sinais, fail-closed, verificado em produção.
2. **dono** → não fecha. Task encerrada não ganha dono; ninguém responde por ela depois.
3. **changelog** → não fecha. Bloqueado numa condição real (`done_at`) que ninguém está destravando.
4. **RAG** → fecha pela metade. O *texto* do SPEC é indexado; a *prova do fechamento* não.

E acima de tudo: **o detector não enxerga 90% da população**, então nada disso aparece como dívida.

## Como a máquina deveria ser

**Estender o `doneness-lint`, não criar detector novo** (§5 proíbe duplicar régua consolidada). A mudança é de **denominador**, não de lógica:

- Hoje o universo é *"US que tem campo `status:`"* (527).
- Deveria ser *"US que tem âncora"* (316) — porque a [ADR 0302](../decisions/0302-ancora-fonte-unica-doneness.md) já elegeu a **âncora** como fonte única de done-ness, e o `status:` como legado derivado. O detector contradiz a própria ADR que o rege ao usar o campo aposentado como chave de entrada.
- Estado novo, **advisory**: `entregue_sem_declaracao` — âncora `anchored_ok` + zero declaração (nem blockquote, nem título). Reporta; **nunca fecha**.

**Não pode auto-fechar.** As 114 provam implementação, não aceite. O 2º sinal humano da ADR 0337 é desenho correto, não fricção a remover.

## Como testar que dispara no momento certo

Não é "o código existe" — isso é presence-gate (LC-11, 4 lápides no §5). É **bite-test com controle negativo**, o padrão que o repo já usa:

| fixture | esperado |
|---|---|
| US com âncora `anchored_ok` + zero declaração | **flag** (exit ≠ 0 no modo estrito) |
| US com âncora + `> status: done` | não flagra |
| US com âncora `_parcial_` | não flagra |
| US sem âncora | não flagra |
| árvore limpa inteira | 0 flag espúrio — **mede-se o FP antes de instalar** |

E a prova de que roda no **momento** certo: o gate é diff-aware no PR (`anchor-drift.yml` já faz isso) — o teste é tocar um SPEC numa fixture e conferir que o job **seleciona** aquele arquivo. Chokepoint que o fluxo real não atravessa é defeito conhecido (§5 2026-07-09, `flag:set`).

## Ordem recomendada

1. **Estender o `doneness-lint`** pro denominador da âncora — advisory, forward-only, com os 5 casos de bite-test acima. Torna os 103 invisíveis visíveis.
2. **Triar as 114** com o dono decidindo (é declaração, não dedução). A tela `US-TR-309`/`US-TR-310` existe pra isso e está em review.
3. **Dono no fechamento** — a régua mais barata e a de pior nota (2/10). Hoje nada exige.
4. **`done_at`** — destravar o CHANGELOG exige materializá-lo. O forward-close **já grava `completed_at`** no DB; falta o transporte DB→SPEC. É o caminho mais curto entre o estado atual e as notas 3 e 4 subirem juntas.
5. **RAG do fechamento** — indexar o `acceptance_ref` + sha junto do SPEC, pra a Jana conseguir responder *"como esta US foi entregue?"* e não só *"o que ela pedia"*.

## Ressalvas honestas

- A grade é de **capacidade do mecanismo**, não de quantidade de artefato. Somar os dois numa nota só é o erro já catalogado (§5 2026-07-27, denominador inventado).
- As notas de mercado vêm de pesquisa de 2026-07-28 (fontes no PR). Não são benchmark auditado — são a régua pública declarada pelos fornecedores e pela literatura de prática.
- Nenhuma dimensão aqui é reivindicada como **acima** do mercado. A #6 é explicitamente paridade.
