# Recepção do pacote 16/09 e os dois universos do espelho — 2026-09-16

> Sessão `sleepy-pike-8779ac`. 3 PRs mergeados ([#7414](https://github.com/wagnerra23/oimpresso.com/pull/7414), [#7422](https://github.com/wagnerra23/oimpresso.com/pull/7422), [#7437](https://github.com/wagnerra23/oimpresso.com/pull/7437)).
> Estado pro próximo turno: [handoff 17:50](../handoffs/2026-09-16-1750-recepcao-pacote-e-os-dois-universos.md).

## 1 · O pedido trazia a receita manual; o repo tinha máquina

[W] entregou o ZIP do handoff 20 com uma lista de 8 tarefas: limpar o lote velho, receber 45 arquivos, `aplicar-payload --dry`, aplicar. Antes de executar, procurei o dono do tema e achei o **`receber-handoff.mjs`**, criado em 2026-09-10 por decisão [W] no dia (*"o objetivo é eu exportar uma única vez, sem depender de uma receita manual em cada importação"*). Ele contém os passos 1–4 da lista e acrescenta quatro que a manual não tem: auditoria do `sync/` que veio, classificação por 3 pontos, **guarda de regressão** e reconciliação do `_ds/` pelo dono.

Rodei a rota canônica. O pacote veio **`CONFORME`** — primeira vez que a recepção não precisou descartar o `sync/` (o de 07/09 saía `FORA-DO-CONTRATO`). E o gerador canônico fechou um bundle cujo `bundleId` é **idêntico ao ativo desde 14/09**: delta `+0 ~0 -0 =278`. Não havia nada a promover.

Não rodei `--apply`. Com delta zero ele não escreveria conteúdo nenhum, mas reescreveria `state/active-bundle.json`, `application-report.json` e `applications.json` com carimbo novo — um commit afirmando "importado" sobre importação que não houve. Efeito colateral evitado que a sessão irmã tinha avisado: as 9 `proto-baseline.json` regeneradas por ela no #7405 seguem íntegras, porque o `prototipo_sha` não se moveu.

**Divergência 281 × 278:** 5 arquivos, todos `_ds/**` — 3 `woff2` fora do fechamento do `entry` e 2 resolvidos por regra (espelho vence, #7096). A sessão irmã depois provou por hash que os 3 são **byte-idênticos** ao `-400`: quatro nomes, um arquivo, e o CSS canônico aponta os 4 pesos para ele. Verifiquei por conta própria com controle positivo antes de aceitar.

## 2 · Os 36 do `cowork-inbox`, e a premissa que eu tinha invertido

Reportei que 9 dos 17 divergentes tinham o espelho à frente e que trazer reverteria conteúdo. [W]: *"traz os 36"*.

Ao medir para executar, o contador me pegou: copiei **21** arquivos onde esperava **19**. Reabrindo, os 17 divergentes têm **um único commit** (`ba8e812d687`, #7256) — **nunca foram editados deste lado**, foram importados. Logo a errata `[CL] 2026-09-08` que eu tratara como trabalho nosso é conteúdo da conta, e a [ADR 0398 D1](../decisions/0398-espelho-cowork-recebe-a-arvore-da-conta.md) (*"espelho que muda a forma do original não é espelho"*) deixa de ser obstáculo e passa a ser o argumento.

O `jaEsteveNoEspelho()` não me avisou por razão estrutural: com um commit só ele fatia o HEAD (`commits.slice(1)`) e não sobra nada para comparar — **ausência de histórico para sondar**, que eu havia lido como evidência negativa.

Desceram os 36, com as 510 linhas substituídas tabeladas no PR e recuperáveis por `git show ba8e812d687:<path>`. Sete gates rodados pós-sobreposição, incluindo o `deadlink-gate` **no modo que o CI roda** (`--check`) — solto ele sai 0 sempre.

## 3 · A devolutiva, e um achado que já estava morto

[W] pediu para mandar o achado do patrimônio de volta ao Cowork. Antes de reenviar, re-medi se ele ainda valia — e não valia:

```
arquivo ........... 15.724 B (na errata)  →  29.625 B (hoje)
padrão com `&&` ... 6 sítios              →  1 ocorrência, e é COMENTÁRIO (:29)
string superadmin .. 0                    →  7
guardas vivas ...... `||`, dois `if` sequenciais, em :108 :297 :338 :387 :429 :467
controle positivo .. grep -c function = 22 (a sonda lê o arquivo)
```

O D1 foi consertado em **2026-09-08, o mesmo dia da errata** — e o docblock do fix achou um **segundo defeito** que a errata não viu: o `|| subscription` anulava o gate inteiro, então o `&&` nunca se manifestou em produção e consertar só ele teria sido inerte. A errata subestimava: dizia *"bloqueia o técnico"*, era *"o gate não existia"*.

A nota virou uma devolutiva com seção **"⛔ não restaure isto"**.

⚠️ **E o pedido de restauração que ela fazia CAIU no mesmo dia** — [ADR 0404](../decisions/0404-ultimo-importado-e-autoridade-do-espelho.md) (#7439), *"o que vale sempre deve ser o último importado"*, com `supersedes_partially: [0398]`. A auditoria leu os **ZIPs originais 16–20** em vez de tomar o espelho como substituto do export anterior: o índice do Patrimônio tem **9.533 B nos cinco**, o do Sidebar **26.514 B nos cinco**, e 18→19→20 teve **zero arquivo modificado ou removido**. Os 510 cortes que eu medi comparavam o Git **enriquecido pela restauração/fusão documentada na própria ADR 0398** com o importado — não dois exports da conta. Meu *"um único commit, logo nunca editado deste lado"* era verdadeiro no nível do COMMIT e enganoso no nível do CONTEÚDO, porque o enriquecimento aconteceu **dentro** daquele import. O que sobrevive do CODE_NOTES: o §2 (o D1 já estava consertado) e os 8 paths inexistentes — a tratar **na origem**, nunca no espelho. Canal: `CODE_NOTES.*` em `memory/reference/prototipo-ui/` (10 precedentes, mesmo assunto). Quase escrevi em `handoffs/` — errado: aquilo é **intake de ordem de serviço** (ADR 0283/0285, push em `main` → HMAC → `handoff-submit` → pending), e escrever lá injetaria uma OS falsa.

## 4 · O achado do dia: o espelho tem dois universos

[W] pediu para medir o FP de uma guarda de regressão para o `cowork-inbox`. A medição matou a guarda (N=1 importação no corpus; o `[3b]` verbatim pegaria zero) e achou outra coisa: o `liveOnly` classificava **327** paths versionados como *"existe no vivo e NUNCA desceu"*, porque o universo do manifesto filtrava por extensão de build e a subárvore inteira (378 arquivos, 87% `.md`) ficava fora.

**Tentei o conserto óbvio e ele ficou pela metade.** Alinhar o filtro ao `roleForPath` do `bundle-contract` — dono declarado de *"o que é conteúdo do espelho"* — levou os falso-ausentes de **327 → 38**. Mas os 38 **seguiam no disco**: continuavam falsos, só menos. O predicado do contrato responde *"isto é material de bundle?"*, que é a pergunta do **frescor**; a do `liveOnly` é **continência**, e continência é `existsSync`. Revertido.

O conserto que fecha são **dois universos com nome** na mesma função (`universo: 'frescor' | 'conteudo'`), um por pergunta. Falso-ausentes → **0**; `--live-only` faltando 44 → 37. Os 6 modos que o CI roda saem **byte-idênticos** antes/depois, capturados antes de eu tocar o arquivo. Bite-test de 5 asserts (3 controles do default + par MORDE/SOLTA sobre a consequência), provado por **mutação**: sem o parâmetro, 2 caem e a suíte sai 1.

## 5 · O que me pegou, três vezes, foi um número

Nenhuma das três defesas que funcionaram foi revisão de código:

- **21 ≠ 19** — o contador de arquivos copiados abriu a premissa invertida do §2.
- **327 → 38 que não zerava** — o resíduo que não some é a assinatura de predicado do tipo errado, não do limiar errado. Virou lápide §5 (emenda da 2026-08-25).
- **o `assert` da âncora** — barrou uma reescrita de PR body onde eu havia escrito o emoji como par de surrogate. Aí a segunda metade da lição cobrou: o script abria o destino com `io.open(p,'w')`, e o **ledger foi truncado a 0 bytes** (518.610 → 0) antes do encode falhar. Recuperado por `git checkout HEAD --`, zero perda, refeito com `encode` antes do `open`.

Ledger: **4** `- **rec**` (LC-08 ×3 → 96, LC-26 ×1 → 15) + a lápide na fonte, com o derivado regerado (`sec5-derive --check` verde, **201** limites — 200 meus + 1 do #7440, resolvido append-only). A 3ª rec da LC-08 é a refutação do §3: a ADR 0404 nomeou que meu delta comparava o Git enriquecido contra o importado.

## 6 · Erro de método que vale mais que os três

Duas vezes nesta sessão eu **recomendei a [W] uma ação cuja premissa não estava medida** — e na segunda ele respondeu *"merge"* em cima dela. A premissa era que o motivo do galho `.md` estava vencido; sondar levou um comando e mostrou que o `exportPlan` **recusa `.md` até hoje**, por decisão [W] de 11/09 pinada em teste. O que salvou foi a sonda vir antes do primeiro `Edit`, não o meu julgamento.

A regra que fica: **recomendação ao dono é afirmação, e afirmação exige medição** — o mesmo padrão que o §5 cobra de achado e de contagem vale para a frase *"o conserto é X"*.
