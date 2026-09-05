---
date: "2026-09-04"
topic: "Colapso do RAGAS em agosto: o lead da flag do hybrid REFUTADO; o mecanismo dos zeros segue indecidível, e por quê"
authors: [C]
related_adrs:
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0312-decisions-search-fulltext-hybrid-docs-off
  - 0062-separacao-runtime-hostinger-ct100
us: [US-COPI-133, US-COPI-136, US-COPI-140]
---

# Agosto não foi a flag — e o que eu disse depois disso estava forte demais

## TL;DR

- **REFUTADO, e isso é firme.** As semanas 2026-08-09 (recall 0,043) e 2026-08-16 (0,0314)
  **não** foram causadas por `JANA_MCP_SEARCH_PIPELINE_DOCS=true`. A flag estava **OFF** o mês
  inteiro, confirmável a posteriori por três pernas independentes (§1).
- **A causa dos zeros, NÃO.** Eu concluí "o juiz ficou mudo" e **sustentei essa conclusão num
  argumento errado**. O mecanismo do juiz mudo é real, mas não é o único que produz o padrão
  observado — e para agosto o discriminador **não existe mais** (§2, §3).
- **O que sobrevive da aritmética:** descontados os zeros, as perguntas pontuadas em agosto
  pontuaram como em julho (0,723 vs 0,7127). Isso diz que **um subconjunto foi a zero**, não que
  tudo degradou aos poucos — e é compatível com **as duas** causas. Não escolhe nenhuma.
- **Dois defeitos vivos e separados** apareceram no caminho: o transporte da órfã falha de forma
  **insegura** (§5) e as semanas `skipped` são um terceiro problema, de retrieval (§4).

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

## 2. O erro que eu cometi, e como ele foi pego

O padrão que medi é real. Distribuição por pergunta no `evals.log` do CT 100 (zeros **exatos**
entre as falhas listadas):

| run | n_eval | n_fail | faith 0 / >0 | rel 0 / >0 | recall 0 / >0 |
|---|---|---|---|---|---|
| 2026-07-19 | 51 | 19 | 4 / 6 | 3 / 7 | 5 / 5 |
| 2026-07-26 | 51 | 20 | 3 / 7 | 2 / 8 | 6 / 4 |
| **2026-08-09** | 50 | 40 | **7 / 3** | 6 / 4 | **10 / 0** |
| **2026-08-16** | 50 | 38 | **31 / 6** | **29 / 8** | **35 / 2** |

E o mecanismo do juiz mudo existe mesmo: `RagasJudgeService::callJudge` devolve `0.0` em
sem-chave, HTTP 429 e qualquer `Throwable`, loga warning e **nunca lança** — daí
`n_judge_failed = 0` em todas as runs, um zero que não significa "nenhuma falha" e sim
"não é possível contar".

**O que eu errei foi o passo seguinte.** Afirmei que retrieval ruim não podia produzir aquilo,
porque `scoreAnswerRelevancy` não recebe o contexto. A assinatura não recebe — mas **o contexto
chega em relevancy através da RESPOSTA**: contexto pobre faz a síntese responder *"não encontrei
isso nas fontes"*, essa resposta não responde à pergunta, e um juiz **vivo** dá 0 nas três. Meu
argumento não fechava, e a conclusão que eu pendurei nele estava forte demais.

Quem pegou foi a sessão irmã *"Atacar o recall da Jana"*, com medição: **8 triplos zero em 51**
numa run do mesmo dia com o juiz **comprovadamente vivo** (zero warnings `[RAGAS]` no
`laravel.log`, com **controle positivo do canal** — uma linha escrita ao vivo aparece), causados
pelo corte de **400 chars do início** do doc em `KbAnswerService::renderFontes` — a causa que a
**US-COPI-133 já nomeava desde 2026-07-12**. Ver [#6801](https://github.com/wagnerra23/oimpresso.com/pull/6801).

## 3. Para agosto, o mecanismo é INDECIDÍVEL a posteriori

O discriminador é o log da janela — e ele expirou. O `laravel.log` do container **já não cobre**
2026-08-09/16 (controle positivo: `[2026-09-04` → 467 linhas, `[2026-08-16` → **0**). Sem ele,
as duas causas seguem no páreo:

- **(a) juiz mudo** — caminho real no código, e `n_judge_failed=0` não o exclui;
- **(b) nota real** — respostas-recusa por contexto truncado, exatamente como no #6801.

Contra **(b)** pesa um fato que ninguém explicou ainda: o corte de 400 chars é **constante** e
antigo, então ele sozinho não explica por que julho teve ~4 zeros e agosto teve 31. Contra **(a)**
pesa não haver nenhum recibo direto. **Fica aberto, e é assim que deve ficar registrado.**

**Limites adicionais:** `failures` só lista quem reprovou os pisos de faith/rel — as contagens de
zero são **piso, não total**; e `context_recall` não entra no critério por-pergunta, então a
aritmética não fecha para ele.

## 4. As semanas `skipped` são OUTRO defeito

2026-08-02, 08-23 e **08-30** tiveram `n_no_context = 51`: o retriever devolveu **zero** docs para
as 51 perguntas. A flag também não explica isso — flag é binária e constante, e não alterna
51 → 1 → 1 → 51 → 51. Em 04/09 o retrieval voltou (`n_no_context = 0`, corpus **2555** docs).
**Três das últimas cinco semanas não mediram nada**, e isso segue sem causa nomeada.

## 5. Achado colateral: o transporte falha de forma INSEGURA

No `publish.log` de 2026-08-30: o clone da órfã falhou, o script degradou para
`[trend] órfã ausente/vazia — 1ª publicação` e montou um trend de **1 semana** — e só não
destruiu as 7 semanas de histórico porque o **push também falhou** (`correct access rights`).
Se o clone falhar e o push funcionar, o `push -f` publica o trend truncado. A chave de deploy
**funciona hoje** (`git ls-remote` rc=0), então a falha foi transitória — mas o **modo** de falha
continua lá. Consequência já visível: a run de **08-30 existe no `evals.log` e não está no trend**.

## 6. O que mudou no código

O report de `jana:ragas-real-eval` passa a gravar `config_vigente` (app_env, docs_pipeline,
docs_embedder, judge_model, **topk**, max_citacoes, **corpus_docs**) e `n_triplos_zero` — nome
**neutro de propósito**, porque cravar `judge` no schema seria assar no contrato a causa que esta
sessão justamente não conseguiu provar. O `ragas-trend-compute` leva os dois para a órfã, que é o
**único** registro durável: o `latest.json` é sobrescrito toda semana e o `evals.log` é local do
host e não cobria agosto.

O valor disso ficou demonstrado no mesmo dia: as duas runs de 04/09 — recall **0,2954** e
**0,5565** — estão no disco **indistinguíveis**, porque o report não grava `topk`.

⚠️ **A hipótese de diluição não entra como causa.** *"Corpus 2,2× maior com o mesmo top-K"* foi
**refutada por medição** no #6801: a cobertura léxica do `ground_truth` contra os top-10
**inteiros** dá **0,9805** — o doc certo estava lá; o gargalo era o corte, não o ranking. E o
recall já era ~0,38 em julho, com 1153 docs. `corpus_docs` é gravado porque **muda sozinho** e
comparar runs sem ele é adivinhar, não porque explique o recall.

Pisos e `_meta.gerado_em` **intocados** — decisão de [W].

## 7. O que fica aberto para [W]

1. **O juiz devolver `0.0` em falha** continua sendo um defeito real, mesmo não sendo a causa
   provada de agosto: consertar (lançar, ou marcar a run como `skipped`) muda o que o trend
   publica e o que o `ragas_real_uptime` mede — é decisão de governança.
2. **O modo de falha inseguro do transporte** (§5): clone falho não deveria poder virar
   `push -f` de trend truncado.
3. **As três semanas `skipped`** (§4) seguem sem causa nomeada — é o que resta do colapso.
4. **A decisão de piso** registrada em 03/09 segue aberta, e o #6801 muda o terreno dela:
   com o corte consertado, faith 0,8716 · rel 0,9706 · recall 0,5565 passam os três pisos.

## 8. Nota de método (para o §5, se [W] quiser)

Dois erros meus nesta sessão, ambos da mesma família — **derivar do lugar errado**:

1. **Sonda na chave errada** (`jana.*` em vez de `copiloto.*`) devolvendo NULL, que eu quase li
   como "a flag está desligada". NULL de chave errada não mede nada.
2. **Conclusão apoiada num mecanismo não verificado**: afirmei que relevancy zerada excluía
   retrieval ruim, sem rastrear que o contexto chega em relevancy pela resposta. A varredura eu
   fiz; o **caminho causal** eu supus.

O que funcionou como defesa não foi gate nenhum: foi uma sessão irmã medindo o mesmo objeto e
discordando com número na mão.
