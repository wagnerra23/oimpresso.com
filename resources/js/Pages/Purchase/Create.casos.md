---
id: resources-js-pages-purchase-create-casos
casos: Nova Compra · /purchases/create
irmaos: Create.charter.md (lei) · Create.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o contrato da grade (1 célula = 1 variation_id, 1 POST único) e o ownership das variations são duráveis — não mudam quando a tela ganhar campo novo.
owner: wagner
last_run: "2026-09-05"
last_run_ci: "✅ 2026-09-05: os 10 arquivos de tests/Feature/Purchase/ entraram na allowlist do purchase-pest.yml (#6824/#6827/#6841). Medido em origin/main b863647741: 0 orfaos no diretorio e uc-lane-coverage --check EXIT=0. Os 7 UC desta tela tem lane; 3 com defesa de COMPORTAMENTO (PurchaseGradeMatrixTest, que emite request e cujo bloco cross-tenant hoje roda em MySQL real) e 4 ESTRUTURAL (Wave2Create*, presence-gate LC-11). Nao restatear a mao: rode scripts/qa/uc-lane-coverage.mjs. ⚠ O TEXTO A SEGUIR ESTA SUPERADO e fica como fato datado - os dois numeros dele ja nasciam falsos (a pasta tem 10 arquivos, nao 8; e 4 ja rodavam quando foi escrito): 🔴 A DIVIDA CONTINUA ABERTA — NENHUM teste de tests/Feature/Purchase/ roda em lane alguma (8 arquivos orfaos de CI); o uc-lane-coverage reprova estes UC por isso e esta certo. Ver §Divida de prova. O bump de 2026-09-04 -> 2026-09-05 NAO paga essa divida e nao afirma execucao: ele registra a REVISAO exigida pelo G-6 depois que o .tsx foi tocado. O que foi revisado, e verificavel: o diff contra 153a65b558 (commit que criou este arquivo) e exatamente 5 atributos `data-contract` em <Card>, zero mudanca de logica/props/copy; e nenhum dos 7 UC depende de atributo/DOM/seletor (grep por data-contract|atributo|DOM|seletor|className neste arquivo: rc=1, zero hits) — os 7 sao de backend/tenant/dado. TENTATIVA DE PROVA REAL, e por que ela NAO vale como recibo: rodei tests/Feature/Purchase/ no CT 100 (90 passed, 6 failed, 6 skipped, 214 assertions), mas o checkout do container esta em c1abe9548f (2026-08-26) e NAO contem as ancoras (grep -c data-contract = 0). Run de outra arvore nao prova esta — citar aquele numero aqui seria medir a coisa errada."
---

# Casos de Uso & Aceite — Nova Compra (`/purchases/create`)

> **Âncora:** os UC derivam do
> [`RUNBOOK-purchase-create.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-create.md)
> (§3 dual-path · §4 dois modos de entrada · §5 endpoint da grade · §8 validação · §10 invariantes
> Tier 0) cruzado com o [`Create.charter.md`](Create.charter.md) v2 (Goals · Non-Goals · R-PUR-001..004)
> — **nunca do `Create.tsx`** ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Convergência C1:** esta é a tela onde a grade tam×cor vive. O charter declara como Non-Goal que
> **não nasce** `Pages/Compras/Create.tsx`; o cockpit `/compras` delega para cá. Contrato registrado
> na proposta [`compras-purchase-convergencia-c1`](../../../../memory/decisions/proposals/compras-purchase-convergencia-c1.md).

---

## ✅ Dívida de prova — os 7 UC têm lane; 3 com defesa de COMPORTAMENTO

> ✅ **Atualização de 2026-09-05 — o texto abaixo continua verdadeiro para o dia em que foi escrito
> e fica inteiro; o que mudou está aqui.** A leva [#6824](https://github.com/wagnerra23/oimpresso.com/pull/6824)
> / [#6827](https://github.com/wagnerra23/oimpresso.com/pull/6827) / [#6841](https://github.com/wagnerra23/oimpresso.com/pull/6841)
> fechou o diretório: **os 10** arquivos de `tests/Feature/Purchase/` estão na allowlist do
> [`purchase-pest.yml`](../../../../.github/workflows/purchase-pest.yml). Medido em `origin/main`
> (`b863647741`): `node scripts/governance/test-lane-coverage.mjs --json` dá **0 órfãos** naquele
> diretório, e `node scripts/qa/uc-lane-coverage.mjs --check --baseline governance/uc-lane-baseline.json`
> sai **EXIT=0**. Nenhum UC desta tela aponta mais para teste que lane nenhuma roda.
>
> ⚠️ **Ganhar lane não muda a NATUREZA da prova.** Os status foram reconciliados em dois níveis,
> não um: `✅ comportamento · na lane` onde o teste emite request/`actingAs`, e
> `🧪 estrutural · na lane` onde ele casa texto no fonte (presence-gate, LC-11) — classificado
> contando requests por arquivo, não no olho. Um presence-gate que agora roda deixou de ser
> "verde impossível"; **continua** provando forma, não comportamento.
>
> ⚠️ **Dois números do bloco abaixo já nasciam falsos:** a pasta tem **10** arquivos, não 8; e a
> perna *"workflows que citam `tests/Feature/Purchase` → 0"* mediu uma janela em que a lane ainda
> não existia. Não restateie à mão — rode os dois comandos acima.

> ⚠️ **Correção da v1 deste arquivo (2026-09-05), registrada e não apagada.** A v1 dizia que os
> `Wave2*` e o miolo do `PurchaseGradeMatrixTest` "executam", e chamava a dívida de *cobertura
> mista*. **Era falso.** Eu medi a perna do **skip** (`markTestSkipped`) e **não medi a perna da
> lane**. Quem pegou foi o gate `uc-lane-coverage` (advisory) do CI, reprovando os 7 UC desta tela
> com *"existe e NENHUMA lane roda"*. O gate estava certo; eu estava errado.

Medição em `origin/main` (2026-09-05), **três pernas**, todas contadas:

| perna | resultado |
|---|---|
| workflows que citam `tests/Feature/Purchase` (`git grep -c -- .github/`) | **0** |
| linhas `Purchase` em `.github/ci-sqlite-pest.list` (542 linhas) | **0** |
| arquivos de teste em `tests/Feature/Purchase/` | **8** |

**Os 8 arquivos de teste do Purchase são órfãos de CI.** Nenhuma lane os executa — nem a MySQL por
módulo, nem a sqlite curada do `ci.yml`. Isso é uma camada **acima** da quarentena e muda o
diagnóstico inteiro:

| teste / bloco | requests | presença | executa? |
|---|---:|---:|---|
| [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php) — 6 casos do parser (`GradeLayoutBuilder`, lógica pura) | — | — | 🔴 **não — sem lane** (o código é bom; ninguém o acorda) |
| idem — 6 casos estruturais (rota · scope no controller · wiring da Page) | — | 19 | 🔴 não — sem lane |
| idem — `describe('Tier 0 cross-tenant (synthetic sqlite)')` | 2 | — | 🔴 não — **sem lane E em quarentena** (dupla) |
| [`Wave2CreateInertiaTest`](../../../../tests/Feature/Purchase/Wave2CreateInertiaTest.php) | 0 | 35 | 🔴 não — sem lane |
| [`Wave2CreateBaselineTest`](../../../../tests/Feature/Purchase/Wave2CreateBaselineTest.php) | 1 | 9 | 🔴 não — sem lane |

**Duas camadas de falha, e a de cima é a que ninguém tinha nomeado:**
1. **Sem lane** — o teste nunca é invocado. Nas palavras do próprio gate: *"teste fora de toda lane
   é 'verde impossível': existe, pode estar vermelho há meses, e nenhum PR o acorda."*
2. **Quarentena** — mesmo ganhando lane, o bloco cross-tenant pularia em MySQL por
   `markTestSkipped`. Skip sai **exit 0** ([LC-13](../../../../memory/LICOES_CODE.md)).

**Consequência:** **nenhum** UC desta tela tem defesa ativa — nem comportamental, nem estrutural. O
`Status` de todos é `🔴 sem lane`. O UC-PURCRE-04, que a v1 celebrava como "o único com prova real",
tem de fato o melhor teste escrito das quatro telas — e ele também não roda.

**Por que o conserto não está neste PR:** o gate diz, com todas as letras, *"conserto NÃO é mexer na
allowlist por conta própria — por que ela existe (custo de CI? teste instável escondido?) é decisão
[W]"*. Concordo: pôr 8 arquivos numa lane muda custo de CI e pode acordar vermelhos antigos. É
decisão do dono, com chip aberto.

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURCRE-01 | SPA recebe React; Blade legacy preservado | must | RUNBOOK §3 | `Wave2CreateInertiaTest` · `Wave2CreateBaselineTest` | 🧪 estrutural · na lane |
| UC-PURCRE-02 | Endpoint da grade recusa produto de outro tenant | must `[T0]` | RUNBOOK §10 · charter R-PUR-001 | `PurchaseGradeMatrixTest` | ✅ comportamento · na lane |
| UC-PURCRE-03 | `store()` recusa `variation_id` forjado de outro tenant | must `[T0]` `[V0]` | RUNBOOK §10 · charter R-PUR-001 | `PurchaseGradeMatrixTest` | ✅ comportamento · na lane |
| UC-PURCRE-04 | A grade nunca abre vazia — degrada 2D → 1 eixo → single | must | RUNBOOK §5 · charter Non-Goal 2 | `PurchaseGradeMatrixTest` | 🧪 comportamento · na lane (sem veredito no manifesto) |
| UC-PURCRE-05 | 1 célula = 1 `variation_id`, num POST único | must `[V0]` | RUNBOOK §4 · charter Goals | `Wave2CreateInertiaTest` | 🧪 estrutural · na lane |
| UC-PURCRE-06 | Dropdown de filiais respeita `permitted_locations` | must `[T0]` | RUNBOOK §10 · charter R-PUR-002 | `Wave2CreateBaselineTest` | 🧪 estrutural · na lane |
| UC-PURCRE-07 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | RUNBOOK §10 | `Wave2CreateInertiaTest` | 🧪 estrutural · na lane |

---

## UC-PURCRE-01 · SPA recebe React; Blade legacy preservado · `must`

- **Persona:** Maiara/Felipe lançam a compra pelo Cockpit; o Blade ainda atende acesso direto.
- **Aceite:** Dado `PurchaseController@create` · Quando o request traz o header Inertia ou `?v=2`
  · Então renderiza a Page `Purchase/Create`. E, como **controle negativo**, o GET normal continua
  devolvendo a view Blade legacy, com o gate `purchase.create`, o escopo por `business_id` da sessão
  e o `isSubscribed` **preservados**.
- **Teste:** [`Wave2CreateInertiaTest`](../../../../tests/Feature/Purchase/Wave2CreateInertiaTest.php)
  — *"Controller create() tem dual path"* · *"Controller PRESERVA path Blade legacy (dual safe)"*;
  [`Wave2CreateBaselineTest`](../../../../tests/Feature/Purchase/Wave2CreateBaselineTest.php) —
  *"PRESERVA permission check purchase.create (Tier 0)"* · *"PRESERVA business_id global scope da
  sessão"* · *"PRESERVA isSubscribed gate"*.
- **Contrato:** RUNBOOK §3 · [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) (F5 CUTOVER é humano e não ocorreu).
- **Regressão que defende:** o `store()` é **compartilhado** pelos dois paths. Mexer no create React
  achando que o Blade morreu quebra quem entra pela URL — e ninguém reporta o que não sabe que existe.
- **Status: 🧪 estrutural · na lane** — o `Baseline` emite 1 request, mas as asserções de gate são
  casamento de texto no fonte.

---

## UC-PURCRE-02 · Endpoint da grade recusa produto de outro tenant · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — o endpoint devolve nomes de variação e custo unitário;
  responder para id alheio entrega catálogo e preço de custo do vizinho.
- **Aceite:** Dado um produto do negócio 98 · Quando o usuário do negócio 1 chama
  `GET /purchases/grade-matrix?product_id={id}` · Então recebe **404** (`ModelNotFoundException` do
  `firstOrFail` escopado). E, como **controle positivo**, o mesmo endpoint para um produto do próprio
  negócio responde com a grade montada — sem o par, um `abort` incondicional passaria.
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  — *"gradeMatrix resolve produto com scope business_id + firstOrFail (Tier 0 → 404 cross-tenant)"*
  (estrutural) · *"usuário biz=1 NÃO resolve produto de biz=99"* e *"same-tenant biz=1
  resolve o próprio produto e monta a grade 2D"* (comportamento, **em quarentena**).
- **Contrato:** RUNBOOK §10 (invariante ✅ explícito) · charter R-PUR-001 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** `gradeMatrix` é endpoint **novo** (US-COM-005) e o model `Transaction`
  desta controller já teve IDOR real. Endpoint auxiliar chamado por `fetch` é o lugar clássico onde
  o scope é esquecido: ele não aparece na tela, aparece na aba de rede.
- **Status: ✅ comportamento · na lane** — o assert que confere o `firstOrFail`
  escopado no fonte existe — mas **nenhuma lane o executa**, e o par 404/200 que provaria o
  comportamento ainda soma a quarentena por cima. Defesa nominal, zero defesa efetiva.

---

## UC-PURCRE-03 · `store()` recusa `variation_id` forjado de outro tenant · `must` `[T0]` `[V0]`

- **Persona:** Wagner — este é o caminho de **escrita**: aceitar um `variation_id` alheio grava
  `purchase_lines` e, com status `received`, **move estoque** de produto que não é do tenant.
- **Aceite:** Dado um POST para `/purchases` cujo array `purchases[]` traz `variation_id` de produto
  do negócio 98 · Quando o usuário do negócio 1 submete · Então o `store()` **recusa** — a validação
  de ownership roda antes de `createOrUpdatePurchaseLines`, e nenhuma linha é gravada.
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  — *"store() valida ownership Tier 0 das variations (anti payload forjado cross-tenant)"*
  (estrutural) — escrito, citado pelo UC, e **sem lane que o execute**.
- **Contrato:** RUNBOOK §10 (invariante ✅) · charter R-PUR-001 ·
  [proibicoes §REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE](../../../../memory/proibicoes.md).
- **Regressão que defende:** a grade transformou a tela num emissor de **N linhas por submit**. Antes,
  forjar payload rendia uma linha; agora rende a matriz inteira. E o efeito não é só leitura: é
  estoque movido e valor gravado — o território da Regra Mestre, onde o canon exige dupla prova.
- **Status: ✅ comportamento · na lane** — o assert confere no fonte que a
  validação de ownership existe; **nenhum teste ativo submete um payload forjado e observa a recusa**.
  Sendo `[T0]` **e** `[V0]`, é o UC desta tela que mais pede prova real.

---

## UC-PURCRE-04 · A grade nunca abre vazia — degrada 2D → 1 eixo → single · `must`

- **Persona:** **Larissa @ ROTA LIVRE (biz=4)** — vestuário, 50+ modelos por entrega. Uma grade que
  abre vazia trava a entrada do dia inteiro.
- **Aceite:** Dado um produto qualquer · Quando o operador o escolhe no combobox da grade · Então o
  backend detecta o layout e **sempre** devolve algo utilizável: nomes de variação compostos e
  parseáveis → `2d`; nomes simples → `matrix-1d` (linhas = variações reais, 1 coluna Qtd); produto
  não-variável → `single`. Combinação ambígua ou mista **cai para 1 eixo**, nunca para grade
  quebrada. O `mode` detectado é logado — nunca fallback silencioso.
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  — *"2D: nomes compostos P/Preto viram grade tam×cor"* · *"hífen também monta a grade"* · *"1 eixo:
  nomes simples caem pra matrix-1d"* · *"single: produto não-variável vira input único"* ·
  *"ambíguo cai pra 1 eixo — nunca grade quebrada"* · *"misto cai pra 1 eixo"*.
- **Contrato:** RUNBOOK §5 (helper de detecção + tabela de modos) · charter §Non-Goals
  (*"NÃO força 2D: catálogo sem variação composta cai pra grade de 1 eixo — nunca grade vazia
  silenciosa"*).
- **Regressão que defende:** o UltimatePOS guarda variação em **1 eixo**; o 2D só "acende" se o
  catálogo usar nomes compostos. Uma otimização no parser que trate o caso ambíguo como erro em vez
  de degradar transforma um catálogo mal nomeado numa tela morta — e o catálogo é do cliente, não
  nosso.
- **Status: 🧪 comportamento · na lane (sem veredito no manifesto)** — **o único UC das quatro telas cuja defesa é comportamento real — e que,
  mesmo assim, não roda.** Os 6 casos exercitam o `GradeLayoutBuilder` de verdade (lógica pura,
  driver-agnostic), incluindo os dois caminhos de degradação que mais importam: ambíguo e misto.
  É o caso que melhor mostra o custo da ausência de lane: o teste está **escrito, correto e mudo**.
  > **Por que não `✅`:** a primeira versão deste arquivo escreveu `✅` — e o **G-7 reprovou**
  > (`status:unverified`). O gate está certo e a regra é a que este próprio documento prega: `✅` só
  > vale com teste **verde no manifesto** (`scripts/casos-test-results.json`, via `npm run
  > casos:results`), nunca com a leitura de quem escreveu o caso. Minha leitura do código é
  > afirmação; o manifesto é veredito. Promover a `✅` é passo de quem rodar a lane, não deste PR.

---

## UC-PURCRE-05 · 1 célula = 1 `variation_id`, num POST único · `must` `[V0]`

- **Persona:** Larissa — preenche a matriz com Tab/Enter e salva **uma vez**; N linhas nascem juntas.
- **Aceite:** Dado a grade preenchida · Quando o operador aciona "Adicionar à compra" · Então cada
  célula com quantidade > 0 vira **uma** `PurchaseLineDraft` com o `variation_id` real daquela
  célula, empilhada no mesmo array `linhas` do modo manual · e o submit envia **um** POST para
  `/purchases` com todas as linhas. O `transform` normaliza as chaves para **todas** as linhas
  (manual e grade), respeitando o contrato de `createOrUpdatePurchaseLines`.
- **Teste:** [`Wave2CreateInertiaTest`](../../../../tests/Feature/Purchase/Wave2CreateInertiaTest.php)
  — *"Page pluga o modo grade tam×cor (US-COM-005)"* · *"Page submete POST /purchases via
  useForm.post"* · *"Page tem repeater de itens (state linhas + adicionar/remover)"* · *"Page calcula
  totais reativos via useMemo"*.
- **Contrato:** RUNBOOK §4 (*"mesmo `linhas` state, mesmo POST"*) + o aviso *"contrato de linha (não
  inventar)"* · charter §Goals.
- **Regressão que defende:** o RUNBOOK avisa que `createOrUpdatePurchaseLines` lê
  `purchase_line_tax_id` — **não** `tax_id` — e roda a normalização numérica pt-BR em quantidade e
  preços. Uma chave renomeada "para ficar consistente" faz imposto sumir sem erro nenhum. É
  território da [Regra Mestre de valor](../../../../memory/proibicoes.md).
- **Status: 🧪 estrutural · na lane** — os asserts provam que o modo grade está *plugado* no arquivo; **não**
  provam a expansão célula→linha nem o formato do payload.

---

## UC-PURCRE-06 · Dropdown de filiais respeita `permitted_locations` · `must` `[T0]`

- **Persona:** operador de uma filial não pode lançar compra no estoque de outra.
- **Aceite:** Dado um usuário com acesso à filial A · Quando abre a tela · Então o dropdown de
  filiais lista **apenas** A — o recorte vem de `BusinessLocation::forDropdown` filtrado por
  `business_id`, não de filtro no cliente.
- **Teste:** [`Wave2CreateBaselineTest`](../../../../tests/Feature/Purchase/Wave2CreateBaselineTest.php)
  — *"Controller create() PRESERVA BusinessLocation::forDropdown filtrado por business_id"*.
- **Contrato:** RUNBOOK §10 (invariante ✅ `permitted_locations`) · charter R-PUR-002.
- **Regressão que defende:** lançar compra na filial errada não dá erro — dá estoque no lugar errado,
  descoberto no inventário semanas depois.
- **Status: 🧪 estrutural · na lane** — casamento de texto; nenhum usuário com filial restrita é montado.

---

## UC-PURCRE-07 · A Page não decide tenant — `business_id` vem das props · `must` `[T0]`

- **Persona:** Wagner — o front nunca é a autoridade sobre de quem é o dado.
- **Aceite:** Dado `Create.tsx` · Quando o arquivo é lido · Então **não** existe `business_id`
  hardcoded, e `createInertia` **não** usa `withoutGlobalScopes` sem o comentário `SUPERADMIN`.
- **Teste:** [`Wave2CreateInertiaTest`](../../../../tests/Feature/Purchase/Wave2CreateInertiaTest.php)
  — *"Page NÃO tem business_id hardcoded (Tier 0 IRREVOGÁVEL)"* · *"Controller createInertia NÃO usa
  withoutGlobalScopes sem comentário SUPERADMIN"* · *"Controller createInertia PRESERVA business_id
  Tier 0"*.
- **Contrato:** RUNBOOK §10 (proíbe `business_id` hardcoded na Page **e** proíbe
  `auth()->user()->business_id` no controller — o canon UPOS é a sessão).
- **Regressão que defende:** um `business_id` fixo parece constante de config em review e só se
  revela no segundo tenant.
- **Status: 🧪 estrutural · na lane** — o contrato aqui *é* a ausência de um literal no arquivo, então o
  presence-gate seria o instrumento certo. Só que ele tambem nao roda: instrumento certo, nunca acionado.

---

## `[BACKLOG]` — achados sem contrato em 2 fontes, ou sem teste que os defenda

- `[BACKLOG]` 🔴 **Tirar o `describe('Tier 0 cross-tenant (synthetic sqlite)')` da quarentena.** São
  os 2 casos que provariam o comportamento dos UC 02 e 03 — hoje defendidos só no plano estrutural.
  O motivo do skip está escrito no arquivo (*"schema sintético… incompatível com MySQL persistente"*),
  então o conserto é de **fixture**, não de asserção. Ao consertar, medir **as duas pernas** —
  inclusão (a lane roda?) e subtração (allowlist/quarentena exclui?) — e provar pelo **contador de
  assertions**, nunca pelo nome no log ([proibicoes §5](../../../../memory/proibicoes.md) 2026-08-02
  + emenda 2026-08-12).
- `[BACKLOG]` **A expansão célula→linha não tem teste de payload.** UC-PURCRE-05 prova que o modo
  está plugado, não que o POST sai com o formato que `createOrUpdatePurchaseLines` espera
  (`purchase_line_tax_id`, normalização numérica pt-BR). É `[V0]`: o teste devido monta o payload e
  confere o gravado, e a mudança que ele defende exige **dupla prova + antes→depois** pela Regra Mestre.
- `[BACKLOG]` **Modo manual não vincula produto real** (`product_id`/`variation_id` nulos, texto
  livre — RUNBOOK §4). É decisão de produto declarada no MVP1, não defeito. Vira UC se/quando [W]
  decidir exigir vínculo.
- `[BACKLOG]` **Schema 2D nativo.** O charter marca como Non-Goal (*"NÃO cria/edita produto pra montar
  2 eixos nativos — precisaria ADR de schema"*). Fica fora até existir a ADR.

---

## ⚠️ Divergências que precisam de [W] (não corrigidas aqui — são INTENÇÃO)

1. **A grade aguarda smoke/canary de [W].** O charter v2 traz
   `status_note: "F3 implementado + modo grade tam×cor (US-COM-005, aguarda smoke/canary Wagner)"`.
   Nenhum arquivo destrava isso — é aprovação humana em `biz=4` (Larissa), com o roteiro pronto no
   RUNBOOK §11.
2. **Um `[T0]` `[V0]` sem prova de comportamento.** UC-PURCRE-03 toca **valor e estoque** e sua
   defesa ativa é estrutural. Pela [Regra Mestre](../../../../memory/proibicoes.md), mudança nesse
   caminho exige dupla prova e antes→depois — e uma das provas (o teste) precisa sair da quarentena
   antes. Risco declarado, não conserto silencioso.
3. **O charter está `status: draft`.** Os UC derivam dos Non-Goals dele; se [W] mudar algum, este
   arquivo muda junto.
