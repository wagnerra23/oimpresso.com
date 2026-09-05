---
date: "2026-09-04"
topic: "Colapso do RAGAS em agosto: o lead da flag do hybrid REFUTADO, e a causa real nomeada com recibo (o juiz ficou mudo)"
authors: [C]
related_adrs:
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0312-decisions-search-fulltext-hybrid-docs-off
  - 0062-separacao-runtime-hostinger-ct100
us: [US-COPI-133, US-COPI-136, US-COPI-140]
---

# Agosto não foi a flag — foi o medidor

## TL;DR

- **REFUTADO.** As semanas 2026-08-09 (recall 0,043) e 2026-08-16 (0,0314) **não** foram causadas
  por `JANA_MCP_SEARCH_PIPELINE_DOCS=true`. A flag estava **OFF** o mês inteiro, e isso é
  confirmável a posteriori por três pernas independentes (§1).
- **A causa real tem recibo:** o juiz devolve `0.0` quando não consegue medir e **nunca lança** —
  então `n_judge_failed` é `0` por construção e o zero fabricado entra na média como se fosse
  qualidade. Em 08-16, **31 das 37** falhas listadas têm faithfulness **exatamente 0** (§2).
- **A aritmética fecha:** descontados os zeros, as perguntas que o juiz *conseguiu* pontuar em
  agosto pontuaram **como em julho** (0,723 vs 0,7127). A Jana não regrediu naquelas semanas.
- **O lead nasceu de um raciocínio frágil** que vale registrar: "os números são indistinguíveis,
  logo talvez a causa seja a mesma". Perto de zero é um **atrator** — várias causas independentes
  chegam lá, e a semelhança numérica não é evidência de causa comum.
- **Dois defeitos vivos e separados** apareceram no caminho: o transporte da órfã falha de forma
  **insegura** (§4) e as semanas `skipped` são um terceiro problema, de retrieval (§3).

## 1. A flag estava OFF — as três pernas

| Perna | Medição | Recibo |
|---|---|---|
| Ambiente | `JANA_MCP_SEARCH_PIPELINE_DOCS` **ausente** do docker env (congelado na *criação* do container, 2026-07-04, `RestartCount=0`), do `printenv`, e contagem **0** nos quatro `.env*`. Sem config cache. | `docker inspect` · `stat .env` → mtime **2026-07-17**, antes de agosto |
| Runtime | `config('copiloto.mcp_search.docs_pipeline')` = **false**, `env(...)` = **NULL** | tinker no `oimpresso-staging`, com controle positivo (`docs_embedder` = `qwen3_local`) |
| Invocador | as runs de agosto são do cron dom 06:00 (`ct100-jana-evals.sh`), cuja chamada é `docker exec -e DB_CONNECTION=mysql` — só isso. História **versionada**: 2 commits, ambos com **0** ocorrências da flag. Cópia implantada com mtime **2026-07-27**, e o `self-update.sh` só reescreve sob `cmp -s` → conteúdo constante em agosto. | `git log --follow` + `git grep` por commit |

Somem-se a isso uma **medição contemporânea independente**: o handoff de
[2026-07-28](../handoffs/2026-07-28-2047-jana-retrieval-degradacao-visivel.md) já registrava,
medido no runtime, `oimpresso-staging=false` e `oimpresso-mcp=true`.

⚠️ **Duas sondas minhas mediram a chave errada** (`jana.mcp_search.*` → NULL) antes de eu achar
que o `Modules/Jana/Config/config.php` é merged sob o namespace **`copiloto`**. NULL de chave
errada não é evidência de nada — a conclusão só veio da chave certa, com controle positivo.

## 2. A causa real: juiz mudo

`RagasJudgeService::callJudge` retorna `0.0` em sem-chave, HTTP 429 e qualquer `Throwable`,
loga warning e **nunca lança** a exceção que o eval conta. Daí `n_judge_failed = 0` em **todas**
as runs — um zero que não significa "nenhuma falha", significa "não é possível contar".

Distribuição por pergunta, extraída do `evals.log` do CT 100 (zeros **exatos** entre as falhas listadas):

| run | n_eval | n_fail | faith 0 / >0 | rel 0 / >0 | recall 0 / >0 |
|---|---|---|---|---|---|
| 2026-07-19 | 51 | 19 | 4 / 6 | 3 / 7 | 5 / 5 |
| 2026-07-26 | 51 | 20 | 3 / 7 | 2 / 8 | 6 / 4 |
| **2026-08-09** | 50 | 40 | **7 / 3** | 6 / 4 | **10 / 0** |
| **2026-08-16** | 50 | 38 | **31 / 6** | **29 / 8** | **35 / 2** |

E linhas inteiras `0/0/0` na mesma pergunta. Isso é assinatura de **instrumento**, não de
qualidade: `scoreAnswerRelevancy` compara pergunta×resposta e **não olha o contexto** — um
retrieval ruim derruba `context_recall`, pode derrubar `faithfulness`, e não tem por que zerar
`relevancy` *exatamente* nas mesmas linhas.

**A aritmética fecha.** 08-16: `faithfulness_avg` 0,2748 × 50 = 13,74; descontados os 31 zeros,
as 19 restantes têm média **0,723** — estatisticamente igual ao **0,7127** de julho. Idem
relevancy: 0,372 × 50 = 18,6; sem os 29 zeros, as 21 restantes dão **0,886** contra 0,8255.

**Limites declarados.** (a) `failures` só lista quem reprovou os pisos de faith/rel — as contagens
de zero são **piso, não total**; (b) `context_recall` não entra no critério por-pergunta, então a
mesma aritmética não fecha para ele; (c) o recibo **direto** (warning `[RAGAS]` no log) está
indisponível — o `laravel.log` do container já não cobre 08-09/16 (controle positivo: `[2026-09-04`
→ 467 linhas, `[2026-08-16` → 0); (d) a distribuição por-pergunta da run **com** a flag ligada de
04/09 não foi preservada, então não dá para descartar que parte daquele colapso também fosse zero
fabricado — os **89%** registrados no baseline seguem como número de **uma** medição, não constante.

## 3. As semanas `skipped` são OUTRO defeito

2026-08-02, 08-23 e **08-30** tiveram `n_no_context = 51`: o retriever devolveu **zero** docs para
as 51 perguntas. A flag também não explica isso — flag é binária e constante, e não alterna
51 → 1 → 1 → 51 → 51. Em 04/09 o retrieval voltou (`n_no_context = 0`, corpus **2555** docs).
**Três das últimas cinco semanas não mediram nada.**

## 4. Achado colateral: o transporte falha de forma INSEGURA

No `publish.log` de 2026-08-30: o clone da órfã falhou, o script degradou para
`[trend] órfã ausente/vazia — 1ª publicação` e montou um trend de **1 semana** — e só não
destruiu as 7 semanas de histórico porque o **push também falhou** (`correct access rights`).
Se o clone falhar e o push funcionar, o `push -f` publica o trend truncado. A chave de deploy
**funciona hoje** (`git ls-remote` rc=0), então a falha foi transitória — mas o **modo** de falha
continua lá. Consequência já visível: a run de **08-30 existe no `evals.log` e não está no trend**.

## 5. O que mudou no código

O report de `jana:ragas-real-eval` passa a gravar `config_vigente` (app_env, docs_pipeline,
docs_embedder, judge_model, topk, max_citacoes, **corpus_docs**) e `n_judge_zero_triples`, e o
`ragas-trend-compute` leva os dois para a órfã — que é o **único** registro durável: o
`latest.json` é sobrescrito toda semana e o `evals.log` é local do host e não cobria agosto.
Sem esses dois campos, esta sessão inteira precisaria ser repetida na próxima vez.

Pisos e `_meta.gerado_em` **intocados** — decisão de [W].

## 6. O que fica aberto para [W]

1. **O juiz devolver `0.0` em falha é o defeito de fundo.** Consertar (lançar, ou marcar a run
   como `skipped`) muda o que o trend publica e o `ragas_real_uptime` mede — é decisão de
   governança, não conserto silencioso.
2. **O modo de falha inseguro do transporte** (§4): clone falho não deveria poder virar
   `push -f` de trend truncado.
3. **A decisão de piso** registrada em 03/09 segue aberta e agora tem contexto novo: parte do
   vermelho de agosto era instrumento, não qualidade.
