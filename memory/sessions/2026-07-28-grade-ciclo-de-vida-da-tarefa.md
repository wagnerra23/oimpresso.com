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

> ⚠️ **Errata do próprio autor (mesma sessão, antes do merge).** A 1ª redação dizia *"os 91 SPECs"*. **São 59.** Meu `rg 'memory/requisitos/[^/]+/SPEC\.md$'` **sem âncora `^`** casava `tests/governance-fixtures/…/memory/requisitos/X/SPEC.md` — 32 fixtures de teste entraram no denominador. Pego por revisão adversarial. Os números de US (976 · 316 · 240 · 114) vieram de script com regex **ancorada** e seguem válidos; o que estava errado era só o denominador de arquivos. Fica registrado, não apagado: é a classe **LC-08** (denominador inventado, §5 2026-07-27) na minha mão, na mesma sessão em que a catalogo.

Varredura dos **59** `memory/requisitos/*/SPEC.md` de `origin/main`, 2026-07-28:

| fato | número |
|---|---|
| US totais nos SPECs | 976 |
| com âncora verificada (`verificado@sha`, sem `_pendente_`/`_parcial_`) | 316 |
| declaram done (blockquote **ou** título) | 240 |
| **entregues mas NUNCA declaradas done** | **114** |
| └ com campo `status:` → visíveis ao `doneness-lint` | 11 |
| └ **sem** campo `status:` → **invisíveis** | **103** |
| SPECs com `done_at:` (condição do CHANGELOG) | **1 de 59** |
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
| 4 | **Changelog** (1) | IA gera release notes de issues/PRs fechados; **review humano é o passo crítico**; cadência semanal correlaciona com retenção | gerador **existe e está desligado** — veredito adversarial 2026-06-16, correto: `done_at` em 1/59 SPECs sairia vazio. Mas a condição **não se moveu em 6 semanas** e ninguém é dono de destravá-la | **3** |
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

## Como a máquina deveria ser — a proposta foi REJEITADA em review adversarial

A 1ª versão deste doc recomendava **estender o `doneness-lint`** trocando o denominador de `status:` para a âncora, com estado advisory `entregue_sem_declaracao`. [W] pediu adversário antes de implementar. **Rejeitado**, e as três razões se sustentam:

1. **Duplica régua consolidada** — a população se sobrepõe ~77% com o que o `anchor-lint` já reporta (`req_sem_aceite` 252 · `req_sem_covering_test` 353). Sinal genuinamente novo: ~19 de 113. *(Número do adversário; não re-verifiquei o cruzamento.)*
2. **FP alto** — amostra classificada do corpus: SPEC morto, DoD com checkbox aberto, e US que **declaram done em outra forma** que o detector não lê (`**Status:** ✅ implementado`, título `` `live` ``, DoD todo `[x]`). Endurecer o parser pra cobrir 4+ formas é a família de guard sintático já morta 4× no §5.
3. **A que mata — e esta eu verifiquei na fonte, não no relatório do adversário.**

### O bloqueio real: duas ADRs aceitas discordam sobre o campo `status:`

[ADR 0302](../decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md) §2, textual:

> **`status: done` deixa de existir** no fluxo novo. Done-ness não se digita — lê-se da âncora.
> **US nova nasce sem `status:`.** O `_TEMPLATE_SPEC.md` … passa a omitir o token `status:`

Conferido no template: o bloco de US tem `**Implementado em:** _pendente_` e **zero** `status:`.

[ADR 0337](../decisions/0337-emenda-0144-forward-close-por-ancora-verificada.md) §Decisão, condição **2**: o forward-close exige que *"o SPEC declara `status: done`"*.

**Consequência, e é aritmética:** toda US nascida **corretamente** sob a 0302 é, por construção, incapaz de ser fechada pelo forward-close da 0337 — porque a condição #2 lê um campo que o template canônico deliberadamente omite. Não é buraco de detector; é o motivo pelo qual as 103 estão paradas.

Isso também reenquadra o que fiz hoje nas 17: adicionar `> status: done` **funcionou** (usou o mecanismo da 0337) mas escreveu um campo que a 0302 aboliu. Nadou contra o template canônico.

**Nenhuma máquina resolve isto**, porque não existe um padrão único pra ela exigir. Qualquer gate que eu construísse enforçaria uma ADR contra a outra. A reconciliação é decisão [W]:

- **(a)** a 0337 larga a condição #2 → o fechamento passa a ser só âncora, e some a trava humana que ela existe pra ter (o receio da 0144); ou
- **(b)** a 0302 é emendada → o `status:` renasce como *declaração de aceite* (não como done-ness), e aí o template volta a emiti-lo.

## 2ª proposta — DoD como sinal mecânico — TAMBÉM rejeitada

[W] cobrou: *"qual máquina deveria verificar isso? não pode depender de humano?"* — cobrança justa, eu tinha delegado demais. Proposta nova: trocar o 2º sinal do forward-close de `status: done` (abolido pela 0302) por **DoD 100% marcado**. Escapava da objeção anterior, é legível por máquina, e o `doneness-lint` já lê checkbox.

**Morreu com prova literal.** O `[x]` é auto-certificação — o autor marca, ninguém verifica. Evidência de **ontem**, commit `7ebe9ea5d7` (PR #4906, autor `[C]` = agente), que no **mesmo commit** gravou a âncora e virou 5 checkboxes:

```
+- [x] Impressão direta via `escpos-php` ou navegador (PDF + autoprint) — **parcial**:
      entregue como download `.zpl` … **não há autoprint**
```

Um `[x]` cujo próprio texto diz *parcial* e *não há*. É a família já morta no §5: catraca sobre **campo auto-declarado** (`last_validated` 2026-07-01 · `verificado_em` 2026-07-09). E o `doneness-lint` usa DoD só como **falsificador** (`if (DONE.has(status) && dodOpen > 0)`) — inverter a seta pra usá-lo como **confirmador** destrói a justificativa que o isenta da lápide presence-gate.

Cobertura, medida: DoD 100% em **13 de 114** — e 12 são de um módulo só (Fiscal). Não é classe; é dívida pontual.

**Correção ao adversário (não aceitei tudo):** ele apresentou como escândalo que 4 shas concentram 327 âncoras (`dd3ed7c` 137 · `8af585a` 89 · `176f9bc` 67 · `dad0b11` 34), sendo commits de docs/sessions sem relação com as US. **Não é abuso — é a semântica documentada.** O `_TEMPLATE_SPEC.md` define `verificado@<sha7>` como *"commit de origin/main **em que o path foi verificado**"*, não o commit que implementou. Carimbar N US num HEAD é uso correto. O que o dado mostra é mais estreito: a âncora prova **existência de arquivo naquele commit**, não revisão individual da US.

### A linha epistêmica que resolve a pergunta do [W]

A máquina **pode** decidir sem humano — mas só se o 2º sinal for algo que ela **verifica**, não algo que o autor **escreve**:

| candidato a 2º sinal | natureza | serve? |
|---|---|---|
| `status: done` | autor escreve | não — e a 0302 aboliu |
| `- [x]` do DoD | autor escreve | **não** — provado auto-certificado ontem |
| `**Testado em:**` | **máquina verifica** (path existe, `anchor-lint` já linta via `TESTADO_RE`, já required no `entry/covers`) | **sim** |

Medido: `**Testado em:**` existe em **197 US / 23 SPECs**, e cobre **28 das 114** (25%) — o dobro do DoD, e epistemicamente de outra categoria.

**Mas continua sendo emenda de ADR**, não refactor de lint: a condição #2 é canon aceito ([ADR 0337](../decisions/0337-emenda-0144-forward-close-por-ancora-verificada.md), `decided_by: [W]`), e canon é append-only. Caminho: ADR nova citando a 0337, com medição própria — não este PR.

**Conclusão honesta:** a dependência do humano hoje não é porque julgamento exige gente. É porque **o repo não tem sinal de aceite verificável com cobertura**. Enquanto o único sinal universal for auto-declarado, tirar o humano é trocar revisão por carimbo.

## O que É acionável agora, sem tomar partido

Medido por mim: **56 US** vivem em **8 SPECs** com frontmatter `historical` ou `arquivado` — e contam nos denominadores de dois gates **required**.

| SPEC | frontmatter | US |
|---|---|---|
| `TaskRegistry` | historical | 16 |
| `Accounting` | arquivado | 16 |
| `PontoWr2` | historical | 12 |
| `LaravelAI` · `Spreadsheet` | historical · arquivado | 5 + 5 |
| `MemoriaAutonoma` | historical | 2 |

O `PontoWr2/SPEC.md:10` diz textualmente *"⚰️ **HISTORICAL** … As `US-PONT-NNN` aqui **não são contrato vivo**"* — e as 12 US dele continuam inflando `anchor_coverage`, `req_sem_aceite` e a zona-cinza. **Isto sim é padronização que a máquina pode exigir** (filtrar por frontmatter), sem escolher entre 0302 e 0337. Mas: chip separado, com FP medido antes de instalar.

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

## Ordem recomendada (revista pós-adversário)

1. **[W] decide 0302 × 0337** — nada de máquina antes disso. É o gargalo único: enquanto as duas valerem, não existe padrão a exigir.
2. **Filtrar SPEC morto dos gates** — 56 US em 8 SPECs `historical`/`arquivado`. Padronização real, sem tomar partido, com FP medido antes.
3. **Triar as 114** com o dono decidindo (é declaração, não dedução). A tela `US-TR-309`/`US-TR-310` existe pra isso e está em review.
4. **Dono no fechamento** — a régua mais barata e a de pior nota (2/10). Hoje nada exige.
4. **`done_at`** — destravar o CHANGELOG exige materializá-lo. O forward-close **já grava `completed_at`** no DB; falta o transporte DB→SPEC. É o caminho mais curto entre o estado atual e as notas 3 e 4 subirem juntas.
5. **RAG do fechamento** — indexar o `acceptance_ref` + sha junto do SPEC, pra a Jana conseguir responder *"como esta US foi entregue?"* e não só *"o que ela pedia"*.

## Ressalvas honestas

- A grade é de **capacidade do mecanismo**, não de quantidade de artefato. Somar os dois numa nota só é o erro já catalogado (§5 2026-07-27, denominador inventado).
- As notas de mercado vêm de pesquisa de 2026-07-28 (fontes no PR). Não são benchmark auditado — são a régua pública declarada pelos fornecedores e pela literatura de prática.
- Nenhuma dimensão aqui é reivindicada como **acima** do mercado. A #6 é explicitamente paridade.
