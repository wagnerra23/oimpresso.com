---
id: resources-js-pages-purchase-create-casos
casos: Nova Compra · /purchases/create
irmaos: Create.charter.md (lei) · Create.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o contrato da grade (1 célula = 1 variation_id, 1 POST único) e o ownership das variations são duráveis — não mudam quando a tela ganhar campo novo.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "✅ 2026-09-05: os 10 arquivos de tests/Feature/Purchase/ entraram na allowlist do purchase-pest.yml (#6824/#6827/#6841). Medido em origin/main b863647741: 0 orfaos no diretorio e uc-lane-coverage --check EXIT=0. Os 7 UC desta tela tem lane; 3 com defesa de COMPORTAMENTO (PurchaseGradeMatrixTest, que emite request e cujo bloco cross-tenant hoje roda em MySQL real) e 4 ESTRUTURAL (Wave2Create*, presence-gate LC-11). Nao restatear a mao: rode scripts/qa/uc-lane-coverage.mjs. ⚠ O TEXTO A SEGUIR ESTA SUPERADO e fica como fato datado - os dois numeros dele ja nasciam falsos (a pasta tem 10 arquivos, nao 8; e 4 ja rodavam quando foi escrito): 🔴 A DIVIDA CONTINUA ABERTA — NENHUM teste de tests/Feature/Purchase/ roda em lane alguma (8 arquivos orfaos de CI); o uc-lane-coverage reprova estes UC por isso e esta certo. Ver §Divida de prova. O bump de 2026-09-04 -> 2026-09-05 NAO paga essa divida e nao afirma execucao: ele registra a REVISAO exigida pelo G-6 depois que o .tsx foi tocado. O que foi revisado, e verificavel: o diff contra 153a65b558 (commit que criou este arquivo) e exatamente 5 atributos `data-contract` em <Card>, zero mudanca de logica/props/copy; e nenhum dos 7 UC depende de atributo/DOM/seletor (grep por data-contract|atributo|DOM|seletor|className neste arquivo: rc=1, zero hits) — os 7 sao de backend/tenant/dado. TENTATIVA DE PROVA REAL, e por que ela NAO vale como recibo: rodei tests/Feature/Purchase/ no CT 100 (90 passed, 6 failed, 6 skipped, 214 assertions), mas o checkout do container esta em c1abe9548f (2026-08-26) e NAO contem as ancoras (grep -c data-contract = 0). Run de outra arvore nao prova esta — citar aquele numero aqui seria medir a coisa errada."
---

> ℹ️ **`last_run` 2026-10-26 → 2026-10-27 (G-6): entraram os casos de backlog de 27/10, sem id.** A única mudança em `Create.tsx` depois de 26/10 é o merge do próprio #9872 (receber em partes), cujos casos (UC-PURCRE-12 e 13) já estão neste arquivo; a data do commit é a do squash. Os casos novos são `[BACKLOG]` (unidade da compra com a conta pronta, compra a partir da nota do fornecedor, nota de entrada de pessoa física e NCM do produto criado na compra). Nenhum UC foi reexecutado.

> ℹ️ **`last_run` 2026-10-20 → 2026-10-26 (G-6): entraram o UC-PURCRE-12 (pedidos abertos + estoque por dois caminhos) e o UC-PURCRE-13 (a tela traz o pedido).** A `Create.tsx` ganhou o bloco "Receber de um pedido de compra" e o campo `purchase_order_ids`; nenhum UC antigo mudou nem foi reexecutado.

> ℹ️ **`last_run` 2026-10-16 → 2026-10-20 (G-6): entraram os UC-PURCUS-01..06 (US-COM-026).** O bump é a revisão do arquivo ao ganhar o bloco novo; nenhum UC antigo foi reexecutado. Os novos estão 🧪 até a lane `compras-pest` rodar `AplicarCustoDoRecebimentoTest`.

> ℹ️ **`last_run` 2026-10-11 → 2026-10-13 (G-6): só cor no `Create.tsx`.** O #9201 trocou 6 `className` do bloco da grade (`stone-*` → `border-border`/`bg-muted`/`text-foreground`/`text-muted-foreground`) para seguir o tema escuro. Nenhum UC mudou nem foi reexecutado: os 7 UC são de backend, tenant e dado, e nenhum depende de cor.

> ℹ️ **`last_run` 2026-09-05 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Create.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **1 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/Purchase/Create.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

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
| UC-PURCRE-08 | Salvar aceita a data que a própria tela manda (`datetime-local`, ISO) | must | charter Goals (1 POST único) · smoke prod 2026-09-24 | `PurchaseDataIsoTest` | 🧪 comportamento · na lane |
| UC-PURCRE-10 | Salvar grava a compra com o payload da tela (sem bloco de pagamento) | must | charter Goals (1 POST único) · reclamação ROTA LIVRE 2026-10-16 | `PurchaseGradeMatrixTest` | 🧪 comportamento · na lane |
| UC-PURCRE-11 | Fornecedor buscado pelo nome e escopado ao negócio; item sem produto volta como erro do campo | must `[T0]` | charter Goals · reclamação ROTA LIVRE 2026-10-16 · ADR 0093 | `PurchaseGradeMatrixTest` | 🧪 comportamento · na lane |

---

## UC-PURCRE-01 · SPA recebe React; Blade legacy preservado · `must`

- **Persona:** Maiara/Felipe lançam a compra pelo Cockpit; o Blade ainda atende acesso direto.
- **Aceite:** Dado `PurchaseController@create` · Quando o request traz o header Inertia ou `?v=2`
  · Então renderiza a Page `Purchase/Create`. E, como **controle negativo**, o GET normal continua
  devolvendo a view Blade legacy, com o gate `purchase.create`, o escopo por `business_id` da sessão
  e o `isSubscribed` **preservados**.
- **Atualização (cutover pelo menu · thread 03, D1 [W] 2026-10-07):** o aceite acima descreve o
  estado até então. Desde esse cutover o GET comum abre a Page React e o Blade só abre com
  `?classico=1` (`?v=2` segue valendo). O `store()` continua compartilhado pelos dois. Prova de
  comportamento: [`ComprasSemXInertiaTest`](../../../../tests/Feature/CutoverMenu/ComprasSemXInertiaTest.php).
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

## UC-PURCRE-08 · Salvar aceita a data que a própria tela manda · `must`

- **Persona:** quem compra pela tela React — Larissa (biz=4) no canary da grade.
- **Aceite:** Dado uma empresa com `date_format` = `d/m/Y` · Quando a tela envia `transaction_date`
  como o `<input type="datetime-local">` produz (`2026-09-24 14:37` ou `2026-09-24T14:37`) · Então o
  back converte para `2026-09-24 14:37:00` e **não** estoura. E o formato da empresa
  (`24/09/2026 14:37`, o que a Blade manda) continua valendo.
- **Teste:** [`PurchaseDataIsoTest`](../../../../tests/Feature/Purchase/PurchaseDataIsoTest.php).
- **Contrato:** charter Goals (a compra sai num POST único) + smoke em prod biz=1 de 2026-09-24:
  a grade montou 4 linhas certas e o `store()` caiu em Carbon *"Trailing data"* no `uf_date()` —
  **nenhuma** compra salva pela tela React passava em empresa com data brasileira. Os testes de
  `store()` já existentes mandavam a data no formato da empresa, por isso ficavam verdes.
- **Regressão que defende:** o front e o back discordando do formato da data, sem teste que use o
  formato do front.
- **Status: 🧪 comportamento · na lane** — exercita o conversor que `store()` e `update()` chamam.

## UC-PURCRE-09 · "Comprar" da lista de produtos abre a compra com o produto · `must` `[T0]`

- **Persona:** quem cuida do estoque e, olhando a lista de produtos (`/products`), decide repor um item.
- **Aceite:** Dado um produto do próprio negócio, ativo e com controle de estoque · Quando abre
  `/purchases/create?produto={id}` (o "Comprar" da lista) · Então a tela já entra com esse produto, pelo
  **mesmo caminho da busca** (grade se for variável, uma linha se for simples, custo vindo do grade-matrix).
  Produto de outro negócio, inativo ou sem controle de estoque **não** entra: a tela abre vazia, igual sem
  o parâmetro.
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  (bloco MySQL: próprio negócio entra · biz=99 não entra · sem controle de estoque não entra).
- **Contrato:** protótipo `prototipo-ui/cowork/Felipe/produto-blade.jsx` (ação "Comprar" da lista) +
  pedido [F] 2026-10-11 *"abre preenchido com o produto"*. A regra de quem entra é a da busca desta tela
  (`getProducts`: negócio da sessão, ativo, `enable_stock = 1`) — não inventada aqui.
- **O que NÃO muda:** nenhum cálculo. O custo e as linhas saem do `escolherProduto` que a busca já usa;
  o produto chega à tela como se a pessoa o tivesse escolhido.
- **Status: 🧪 comportamento · na lane** — os 3 casos batem na rota com o tenant semeado.

---

## UC-PURCRE-10 · Salvar grava a compra com o payload da tela (sem bloco de pagamento) · `must`

- **Persona:** quem lança compra pela tela React — Larissa (biz=4) reportou em 2026-10-16 que nenhuma
  compra salvava ("Algo deu errado, por favor, tente novamente mais tarde").
- **Aceite:** Dado fornecedor, filial e uma linha de item · Quando a tela envia o POST como o `enviar()`
  do `Create.tsx` monta — **sem** a chave `payment` · Então a compra é gravada a prazo
  (`payment_status = due`), com o total e a quantidade enviados.
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  (bloco MySQL, dentro de transação revertida).
- **Contrato:** charter Goals (a compra sai num POST único) + reclamação do cliente. A tela não tem bloco
  de pagamento; o `store()` passava `$request->input('payment')` (null) ao `createOrUpdatePaymentLines`,
  cujo `foreach` sobre null vira `ErrorException` no Laravel e cai no catch genérico.
- **O que NÃO muda:** nenhum cálculo de valor ou estoque — só a ausência de pagamento deixa de quebrar.
- **Status: 🧪 comportamento · na lane**

## UC-PURCRE-11 · Fornecedor pelo nome, escopado ao negócio; item sem produto vira erro do campo · `must` `[T0]`

- **Persona:** quem lança compra pela tela React (Larissa, biz=4).
- **Aceite:** Dado a tela de nova compra · Quando a pessoa busca o fornecedor pelo **nome** (não mais
  pelo id numérico) · Então o `contact_id` enviado é o do contato escolhido. Se o POST chegar com
  fornecedor de **outro negócio**, ou com item sem `product_id`/`variation_id` (linha digitada à mão),
  o `store()` volta ao formulário com o erro de cada campo e **não grava** — em vez do "algo deu errado".
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  (bloco MySQL; fornecedor do biz=99 contra usuário do biz=98).
- **Contrato:** reclamação do cliente + ADR 0093 (o `contact_id` vinha cru do navegador e não era
  conferido contra o negócio). A validação saiu de dentro do `try` do `store()`, onde a
  `ValidationException` era engolida pelo catch genérico.
- **O que NÃO muda:** nenhum cálculo de valor ou estoque.
- **Status: 🧪 comportamento · na lane**

---

## Compra recebida atualiza custo e preço (US-COM-026 · UC-FLX-17)

> **Âncora:** o UC-FLX-17 do catálogo de fluxos (etapa Recebido: *"compra recebida atualiza custo e
> propõe preço"*) e a regra "Atualizar o custo dos insumos" da aba Fluxos (`RegrasCompra`, parâmetros
> `metodo` e `preco`), registrados na US-COM-026 do [SPEC de Compras](../../../../memory/requisitos/Compras/SPEC.md).
> Esta tela é a dona porque é onde a compra nasce recebida; editar para recebida e mudar o status na
> lista chamam o mesmo serviço. Prova: `AplicarCustoDoRecebimentoTest` (lane compras-pest). VALOR: regra mestre.

## UC-PURCUS-01 · Só recalcula o custo · `must` valor

**Dado** a regra gravada com custo médio e "Só recalcula o custo", a variação a 11,00 de custo e 16,50 de preço
(imposto 10%, margem 50%) e uma compra recebida de 10 un × 11,00 **Quando** entra recebida a compra de 10 un × 13,20
**Então** o custo vira 12,10 (11,00 sem imposto), o preço fica 16,50 e a margem passa a 36,3636%.

Status: 🧪

## UC-PURCUS-02 · Aplica o preço mantendo a margem · `must` valor

**Dado** a mesma compra e a regra em "Aplica o novo preço sozinho" **Então** o preço vira 16,50 sem imposto e 18,15
com imposto, e a margem continua 50%. Com "Último preço" o custo é 13,20 e o preço 19,80.

Status: 🧪

## UC-PURCUS-03 · Propõe o preço sem mudar · `must` valor

**Dado** a regra em "Propõe novo preço" **Então** o custo vira 12,10, o preço fica 16,50 e nasce uma proposta
pendente com custo 11,00 → 12,10 e preço 16,50 → 18,15, ligada à compra. Aprovar a proposta é follow-up.

Status: 🧪

## UC-PURCUS-04 · Compra pendente não mexe em custo nem preço · `must`

**Dado** a regra gravada **Quando** a compra fica pendente ou pedida **Então** custo e preço não mudam, e a edição
de produto pela compra do core fica desligada para não gravar o último preço por cima.

Status: 🧪

## UC-PURCUS-05 · Empresa sem a regra segue como antes · `must`

**Dado** a empresa que nunca gravou a regra, ou que a desligou **Quando** a compra é recebida **Então** nada novo
acontece: o core segue como antes, gravando o último preço quando a edição de produto pela compra está ligada.

Status: 🧪

## UC-PURCUS-06 · Só a variação da empresa da compra · `must` `[T0]`

**Dado** a regra gravada na outra empresa **Quando** ela recebe uma compra com a variação desta **Então** a variação
desta não muda e nenhuma proposta nasce.

Status: 🧪

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

## Entrada de notas e unidade da compra (pedido de 2026-10-23)

> Casos sem id: entram quando tiverem teste. Hoje o SPED (`Fiscal/Sped.casos.md`) gera só as notas
> emitidas pela empresa: `SpedIcmsIpiGeneratorService` monta C100/C170/C190 a partir das emissões, e o
> próprio backlog do SPED lista as entradas como fora do escopo. Os registros citados abaixo são do
> leiaute da EFD ICMS/IPI; o que entra e como é decisão do contador.
>
> ⚠️ Os quatro casos de SPED abaixo (compra, energia, comunicação e CT-e) dependem do [W]: o charter
> do SPED tem entre os Non-Goals aprovados por ele "❌ Entradas (NF-e contra o CNPJ via DF-e
> manifestada) — só saídas por ora" (`Fiscal/Sped.charter.md`). O "por ora" deixa a porta aberta, mas
> quem reabre é ele. Verificado contra as regras do [W] em 2026-10-23.

- `[BACKLOG]` **Compra com unidade de medida diferente da cadastrada** `[V0]` `[E0]` — Dado o produto
  cadastrado em m² e o fornecedor que vende em rolo de 50 m², Quando lanço a compra de 2 rolos,
  Então o estoque entra com 100 m², o custo fica por m² (valor do rolo dividido por 50) e a nota
  continua mostrando "2 rolos". A conversão fica lembrada para aquele fornecedor e produto, e vale
  também na nota importada pelo XML. Hoje a tela antiga (Blade) aceita as subunidades do produto
  (`sub_unit`, com o multiplicador do cadastro); a compra React não tem a escolha da unidade.
  Aceite adicional (comparação com concorrentes, 2026-10-23): o mesmo produto aceita mais de uma
  conversão por fornecedor (rolo de 50 m² e rolo de 25 m², por exemplo), e a nota importada usa a
  conversão da unidade que veio no XML. Fonte: Omie (até 3 fatores de conversão por produto e
  fornecedor, com a unidade da última nota lembrada).
  Aceite adicional (suporte, 2026-10-27: Extreme, Techpress, Copylan, Mecânica Casa Grande, Art Laser
  e Zoom precisam do suporte em 80% das conversões): a tela faz a conta. Na primeira nota ela
  pergunta "1 CX deste fornecedor tem quantos UN?" e mostra a conta pronta, por exemplo "2 CX × 12 =
  24 UN · custo 60,00 ÷ 12 = 5,00 por UN"; nas próximas, a conversão já vem e a pessoa confirma. O
  fator é sugerido pela unidade tributável da nota (`qTrib` ÷ `qCom`, quando difere da comercial),
  pela subunidade do cadastro ou, no rolo, por largura × comprimento; e a linha avisa quando o custo
  convertido sai muito diferente do último custo do produto. O vínculo produto × fornecedor
  (`compras_produto_fornecedor`, de 2026-10-27) ainda não guarda unidade nem fator.
  - Pedido: P-069
  - Âncora: Subunidade do produto (`sub_unit`) · vínculo produto × fornecedor (`compras_produto_fornecedor`, ADR de entrada por XML) · `Purchase/Create.casos.md`
  - Fazer: A tela faz a conta. Na primeira nota pergunta "1 CX deste fornecedor tem quantos UN?" e mostra "2 CX × 12 = 24 UN · custo 60,00 ÷ 12 = 5,00 por UN"; nas próximas, a conversão já vem e a pessoa confirma. Sugere o fator pela unidade tributável da nota (`qTrib` ÷ `qCom`), pela subunidade do cadastro ou por largura × comprimento no rolo, e avisa quando o custo convertido sai muito diferente do último custo. Aceita mais de uma conversão por fornecedor (Omie aceita até 3).
  - Hoje: part | Em parte | A tela antiga aceita as subunidades do cadastro; a compra nova não. O vínculo produto × fornecedor (27/10) ainda não guarda unidade nem fator, e o leitor do XML (PR #10024) lê só a unidade comercial
  - Comunicação visual: ok | Sim | chapa, rolo e bobina
  - Clientes: Extreme, Techpress, Martinho, Copylan, Mecânica Casa Grande, Max Comunicação, ArtLaser, Zoom
  - Etiquetas: valor, part, cv
- `[BACKLOG]` **Notas de compra entram no SPED** `[V0]` — Dado uma compra lançada com a nota do
  fornecedor (modelo 55), Quando gero o SPED da competência, Então a nota sai como entrada (C100 com
  indicador de entrada, C170 e C190), com o crédito de ICMS que o regime da empresa permitir. Hoje o
  SPED não traz nenhuma entrada.
  - Pedido: P-070
  - Âncora: SPED da competência (`SpedIcmsIpiGeneratorService` gera só as notas emitidas) · `Purchase/Create.casos.md`
  - Fazer: Levar as compras com nota ao SPED como entrada; o crédito segue o regime da empresa.
  - Hoje: miss | Não tem | O SPED não traz nenhuma entrada
  - Comunicação visual: ok | Sim | empresa no regime normal
  - Clientes: Extreme, Safety
  - Etiquetas: valor, miss, cv, decisao
- `[BACKLOG]` **Nota de energia elétrica entra no SPED** `[V0]` — Dado a conta de energia da empresa
  (modelo 06 ou 66), Quando lanço a nota na entrada com valor, base e ICMS, Então ela sai no SPED da
  competência nos registros C500 e C590, sem mexer em estoque. Hoje a conta pode entrar como despesa,
  sem os dados fiscais da nota, e o SPED não tem esse registro.
  - Pedido: P-071
  - Âncora: SPED da competência (`SpedIcmsIpiGeneratorService` gera só as notas emitidas) · `Purchase/Create.casos.md`
  - Fazer: Lançar a nota de energia com os dados fiscais e gerar C500/C590.
  - Hoje: miss | Não tem | A conta entra como despesa, sem os dados fiscais; o SPED não tem o registro
  - Comunicação visual: ok | Sim | empresa no regime normal
  - Clientes: Extreme, Safety
  - Etiquetas: valor, miss, cv, decisao
- `[BACKLOG]` **Nota de comunicação entra no SPED** `[V0]` — Dado a conta de telefone e internet
  (modelo 21, 22 ou 62), Quando lanço a nota na entrada, Então ela sai no SPED nos registros D500 e
  D590, sem mexer em estoque. Hoje a conta pode entrar como despesa, sem os dados fiscais da nota, e o
  SPED não tem esse registro.
  - Pedido: P-072
  - Âncora: SPED da competência (`SpedIcmsIpiGeneratorService` gera só as notas emitidas) · `Purchase/Create.casos.md`
  - Fazer: Lançar a nota de comunicação com os dados fiscais e gerar D500/D590.
  - Hoje: miss | Não tem | A conta entra como despesa, sem os dados fiscais; o SPED não tem o registro
  - Comunicação visual: ok | Sim | empresa no regime normal
  - Clientes: Extreme, Safety
  - Etiquetas: valor, miss, cv, decisao
- `[BACKLOG]` **CT-e do frete entra no SPED** `[V0]` — Dado o CT-e (modelo 57) do frete de uma compra,
  Quando o lanço na entrada ligado à nota da compra, Então ele sai no SPED nos registros D100 e D190, e
  o valor do frete pode compor o custo dos itens da compra. Hoje o CT-e não é lançado e o SPED não tem
  o bloco D.
  - Pedido: P-073
  - Âncora: SPED da competência (`SpedIcmsIpiGeneratorService` gera só as notas emitidas) · `Purchase/Create.casos.md`
  - Fazer: Lançar o CT-e na entrada, ligado à compra, e gerar o bloco D.
  - Hoje: miss | Não tem | O CT-e não é lançado e o SPED não tem o bloco D
  - Comunicação visual: ok | Sim | frete de chapa e substrato
  - Clientes: Extreme, Safety
  - Etiquetas: valor, miss, cv, decisao
- `[BACKLOG]` **Entrada de nota de serviço** `[V0]` — Dado a NFS-e de um prestador com ISS retido,
  Quando a lanço como entrada de serviço, Então nasce a conta a pagar pelo valor líquido, o ISS retido
  fica registrado como imposto a recolher, nada entra no estoque e a nota aparece nas notas de entrada.
  As demais retenções (IR, PIS, COFINS, CSLL e INSS) seguem a mesma linha. Hoje não há entrada de nota
  de serviço; em que obrigação ela sai é decisão do contador.
  - Pedido: P-074
  - Âncora: `Purchase/Create.casos.md`
  - Fazer: Entrada de NFS-e recebida, com as retenções e a conta a pagar pelo líquido. Em que obrigação ela sai, o contador decide.
  - Hoje: miss | Não tem | Não há entrada de nota de serviço
  - Comunicação visual: ok | Sim | instalação e serviço terceirizados
  - Clientes: Techpress, ArtLaser
  - Etiquetas: valor, miss, cv, decisao
- `[BACKLOG]` **Notas de serviço tomado buscadas sozinhas** (comparação com concorrentes,
  2026-10-23) `[V0]` — Dado a empresa com a busca ligada, Quando um prestador emite uma NFS-e contra o
  CNPJ dela, Então a nota aparece numa lista de notas recebidas, o financeiro confere e aprova, e só
  então nasce a conta a pagar; cada imposto retido vira uma conta a pagar própria, com o vencimento
  dele. Nota recusada não gera nada. Fonte: Omie (monitoramento das NFS-e tomadas nas prefeituras e no
  Ambiente Nacional). Hoje as notas que chegam sozinhas são as NF-e da distribuição do DF-e; a NFS-e
  tomada não chega.
  - Pedido: P-096
  - Âncora: Omie (monitoramento nas prefeituras e no Ambiente Nacional) · `Purchase/Create.casos.md`
  - Fazer: Buscar as NFS-e tomadas, aprovar e gerar a conta a pagar e as dos impostos retidos.
  - Hoje: miss | Não tem | Chegam sozinhas só as NF-e da distribuição do DF-e
  - Comunicação visual: ok | Sim | instalação e serviço terceirizados
  - Etiquetas: valor, miss, cv, decisao

---

## Compra com e sem nota e NCM na origem (pedido de 2026-10-27)

> Casos sem id, como os de cima: entram quando tiverem teste. Pedido da PraMoto, trazido pelo
> suporte ([F]): ela compra com e sem nota, e os produtos que entram sem nota ficam sem NCM. A
> escolha de vender com ou sem nota continua na venda, como hoje; nenhum caso aqui faz a venda
> decidir pela origem da compra.

- `[BACKLOG]` **Compra nasce da nota do fornecedor** `[V0]` `[E0]` — Dado a NF-e do fornecedor
  baixada da SEFAZ para o CNPJ, ou o XML enviado, Quando importo a nota como compra, Então cada item
  fica ligado ao produto (o vínculo fica lembrado para a próxima nota do mesmo fornecedor) e a compra
  guarda a chave da nota. O estoque entra no recebimento; a conta a pagar nasce quando a compra é
  conferida contra a NF-e de entrada, com a divergência dentro da tolerância, e essa conferência
  manifesta a nota. É o fluxo de Compra dos modelos do [W] (Requisição → Pedido → Recebido →
  Conferido → Pago, em `prototipo-ui/cowork/Wagner/fluxos-modelos.casos.md`). Uma compra digitada à
  mão pode ser ligada depois a uma nota baixada. Âncora: US-COM-003 e a ADR de
  entrada por XML (`memory/decisions/proposals/2026-10-06-entrada-xml-compra.md`, aceita). Hoje as
  tabelas da entrada entraram (2026-10-27), o leitor do XML está no PR #10024 e a compra a partir da
  nota ainda não existe.
  - Pedido: P-100
  - Âncora: US-COM-003 · ADR de entrada por XML (aceita em 06/10)
  - Fazer: Seguir as fases da ADR: o leitor do XML, a compra a partir da nota com a dupla prova de valor e a aba Entradas.
  - Hoje: part | Em parte | As tabelas da entrada (o vínculo produto × fornecedor e a chave na compra) entraram em 27/10; o leitor do XML está no PR #10024; a compra a partir da nota ainda não existe
  - Comunicação visual: ok | Sim | toda compra com nota
  - Clientes: PraMoto
  - Etiquetas: valor, part, cv
- `[BACKLOG]` **Compra de pessoa física emite a nota de entrada** `[V0]` — Dado uma peça ou moto
  usada comprada de um particular, Quando lanço a compra, Então ela oferece "Emitir nota de entrada";
  emitida, a nota fica ligada à compra e o produto passa a ter entrada com nota. Compra de empresa
  sem nota fica marcada como sem nota: quem emite a nota é o fornecedor. **Depende do [W]:** no fluxo
  de Compra dele, a conta a pagar nasce em Conferido, depois de conferir a NF-e de entrada; a compra
  sem nota não tem o que conferir e ficaria parada em Recebido. Falta a regra para ela (por exemplo,
  pular a conferência e gerar a conta a pagar no recebimento). O CFOP e a natureza da nota
  de bem usado mudam por estado; o contador confirma. Hoje a nota de entrada que funciona é a da
  devolução de venda (`DevolucaoService`); o código antigo de nota de entrada a partir da compra
  (`NfeEntradaController`) não tem rota.
  - Pedido: P-101
  - Âncora: Montagem da nota de entrada da devolução (`DevolucaoService`)
  - Fazer: Emitir a nota de entrada a partir da compra, reaproveitando a montagem da devolução. O CFOP e a natureza da nota de bem usado, que mudam por estado, o contador confirma.
  - Hoje: miss | Não tem | A nota de entrada que funciona hoje é a da devolução de venda; existe um código antigo de nota de entrada a partir da compra (`NfeEntradaController`) sem rota
  - Comunicação visual: gray | Pouco | compra de particular é rara
  - Clientes: PraMoto
  - Etiquetas: valor, miss, decisao
- `[BACKLOG]` **Produto criado na compra pede o NCM** — Dado a empresa que emite nota, Quando
  cadastro um produto novo pela compra ou pelo cadastro rápido, Então o NCM aparece pedido, com uma
  sugestão tirada de produtos parecidos do catálogo; salvar sem NCM continua permitido, com o aviso.
  O Novo produto já avisa (`Produto/Create.casos.md`, UC-PCAD-14); o cadastro rápido e a compra não.
  - Pedido: P-105
  - Âncora: `UC-PCAD-14` (o Novo produto já avisa)
  - Fazer: Levar o aviso e a sugestão de NCM ao cadastro rápido e ao produto criado na compra, por onde nascem os produtos sem nota.
  - Hoje: part | Em parte | O Novo produto já avisa (`UC-PCAD-14`); o cadastro rápido e a compra não
  - Comunicação visual: ok | Sim | material cadastrado às pressas
  - Clientes: PraMoto
  - Etiquetas: part, cv

O saldo fiscal do produto e o aviso na emissão estão em `Produto/Index.casos.md` e
`Fiscal/Nfe.casos.md`, com a mesma data.

---

## Receber o pedido de compra em partes (US-COM-013)

> **Âncora:** US-COM-013 do [SPEC de Compras](../../../../memory/requisitos/Compras/SPEC.md), com a
> decisão da coordenação de 2026-10-26: o recebimento parcial vai pelo pedido de compra
> (`po_quantity_purchased`), sem coluna nova. ESTOQUE: regra mestre.

## UC-PURCRE-12 · O pedido de 10 recebe 4 e depois 6, e só o que chegou entra no estoque · `must` `[V0]` `[T0]`

- **Persona:** quem recebe a mercadoria e lança a compra (Larissa, biz=4).
- **Aceite:** Dado um pedido de compra de 10 unidades de um fornecedor · Quando a pessoa lança uma
  compra recebida de 4 ligada a esse pedido · Então o estoque sobe 4, o pedido fica Parcial e a
  lista de pedidos abertos do fornecedor mostra 6 a receber · E quando lança outra compra de 6 ·
  Então o estoque fica em 10, o pedido fica Concluído e sai da lista. Pedido de fornecedor de outra
  empresa nunca aparece na lista.
- **Teste:** [`RecebimentoParcialPedidoTest`](../../../../Modules/Compras/Tests/Feature/RecebimentoParcialPedidoTest.php)
  (lane compras-pest; estoque conferido por dois caminhos: saldo gravado e soma das compras recebidas).
- **Contrato:** SPEC US-COM-013 + regra mestre de estoque. A trava contra pedido de outra empresa no
  `store()` é a US-COM-029.
- **Status: 🧪 comportamento · na lane**

## UC-PURCRE-13 · Escolher o pedido traz as linhas com o que falta receber · `must`

- **Persona:** quem recebe a mercadoria e lança a compra (Larissa, biz=4).
- **Aceite:** Dado uma empresa que usa pedido de compra e um fornecedor escolhido com pedido aberto ·
  Quando a pessoa escolhe o pedido em "Receber de um pedido de compra" e clica em Trazer itens ·
  Então cada linha do pedido entra na compra com a quantidade que falta, o preço do pedido e a
  indicação "Pedido · já recebido · a receber" embaixo do nome; a quantidade pode ser ajustada para
  o que chegou, e acima do que falta a linha avisa "acima do pedido". O pedido trazido não aparece
  de novo na lista. Sem fornecedor, ou sem pedido aberto, o bloco não aparece.
- **Teste:** [`tests/purchase-receber-do-pedido.test.tsx`](../../../../tests/purchase-receber-do-pedido.test.tsx)
  (vitest; mutação `quantity: l.pedido` derruba o assert com `expected 10 to be 6`).
- **Contrato:** SPEC US-COM-013. Quem grava é o UC-PURCRE-12.
- **Status: 🧪 comportamento**

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
