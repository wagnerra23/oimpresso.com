---
id: resources-js-pages-purchase-create-casos
casos: Nova Compra · /purchases/create
irmaos: Create.charter.md (lei) · Create.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o contrato da grade (1 célula = 1 variation_id, 1 POST único) e o ownership das variations são duráveis — não mudam quando a tela ganhar campo novo.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "cobertura MISTA — o parser da grade tem prova real que EXECUTA (6 casos); os dois cross-tenant sintéticos estão em quarentena e não executam. Ver §Dívida de prova."
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

## Dívida de prova — cobertura **mista**, medida por bloco

Medição em `origin/main` (2026-09-04). Esta tela é a **melhor coberta** das quatro, e a única com
prova de comportamento executando — mas o recorte importa:

| teste / bloco | requests | presença | executa? |
|---|---:|---:|---|
| [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php) — 6 casos do parser (`GradeLayoutBuilder`, lógica pura) | — | — | ✅ **sim — comportamento real** |
| idem — 6 casos estruturais (rota registrada · scope no controller · wiring da Page) | — | 19 | ✅ sim — casamento de texto |
| idem — `describe('Tier 0 cross-tenant (synthetic sqlite)')`, 2 casos | 2 | — | 🔴 **NÃO** — pula |
| [`Wave2CreateInertiaTest`](../../../../tests/Feature/Purchase/Wave2CreateInertiaTest.php) | 0 | 35 | ✅ sim — casamento de texto |
| [`Wave2CreateBaselineTest`](../../../../tests/Feature/Purchase/Wave2CreateBaselineTest.php) | 1 | 9 | ✅ sim — guarda o Blade legacy |

**O que executa e prova de verdade:** os 6 casos do parser de layout da grade — 2D por barra, 2D por
hífen, 1 eixo, produto único, combinação ambígua e caso misto. São `GradeLayoutBuilder` puro,
driver-agnostic, e sustentam o UC-PURCRE-04 com `Status: 🧪 comportamento`.

**O que NÃO executa:** o bloco `describe('Tier 0 cross-tenant (synthetic sqlite)')` chama
`markTestSkipped` quando o driver **não** é sqlite, e a suíte real roda em **MySQL**
([proibicoes §Ambiente](../../../../memory/proibicoes.md)). As duas pernas foram conferidas — o
arquivo também **não** está em `.github/ci-sqlite-pest.list` (542 linhas, zero ocorrências de
`Purchase`), então a lane sqlite tampouco o executa. Skip sai **exit 0**
([LC-13](../../../../memory/LICOES_CODE.md): `0 failed` nunca prova execução).

**Consequência:** os UC 02 e 03 — ambos `[T0]`, e o 03 também `[V0]` — mantêm defesa **estrutural**
(o assert que confere o `firstOrFail` escopado no fonte roda), mas a prova de **comportamento**
cross-tenant está inativa.

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURCRE-01 | SPA recebe React; Blade legacy preservado | must | RUNBOOK §3 | `Wave2CreateInertiaTest` · `Wave2CreateBaselineTest` | ⚠️ 🧪 estrutural |
| UC-PURCRE-02 | Endpoint da grade recusa produto de outro tenant | must `[T0]` | RUNBOOK §10 · charter R-PUR-001 | `PurchaseGradeMatrixTest` | ⚠️ 🧪 estrutural (comportamento em quarentena) |
| UC-PURCRE-03 | `store()` recusa `variation_id` forjado de outro tenant | must `[T0]` `[V0]` | RUNBOOK §10 · charter R-PUR-001 | `PurchaseGradeMatrixTest` | ⚠️ 🧪 estrutural (comportamento em quarentena) |
| UC-PURCRE-04 | A grade nunca abre vazia — degrada 2D → 1 eixo → single | must | RUNBOOK §5 · charter Non-Goal 2 | `PurchaseGradeMatrixTest` | 🧪 **comportamento** (veredito pendente da lane) |
| UC-PURCRE-05 | 1 célula = 1 `variation_id`, num POST único | must `[V0]` | RUNBOOK §4 · charter Goals | `Wave2CreateInertiaTest` | ⚠️ 🧪 estrutural |
| UC-PURCRE-06 | Dropdown de filiais respeita `permitted_locations` | must `[T0]` | RUNBOOK §10 · charter R-PUR-002 | `Wave2CreateBaselineTest` | ⚠️ 🧪 estrutural |
| UC-PURCRE-07 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | RUNBOOK §10 | `Wave2CreateInertiaTest` | 🧪 estrutural (correto) |

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
- **Status: ⚠️ 🧪 estrutural** — o `Baseline` emite 1 request, mas as asserções de gate são
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
  (estrutural, **executa**) · *"usuário biz=1 NÃO resolve produto de biz=99"* e *"same-tenant biz=1
  resolve o próprio produto e monta a grade 2D"* (comportamento, **em quarentena**).
- **Contrato:** RUNBOOK §10 (invariante ✅ explícito) · charter R-PUR-001 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** `gradeMatrix` é endpoint **novo** (US-COM-005) e o model `Transaction`
  desta controller já teve IDOR real. Endpoint auxiliar chamado por `fetch` é o lugar clássico onde
  o scope é esquecido: ele não aparece na tela, aparece na aba de rede.
- **Status: ⚠️ 🧪 estrutural (comportamento em quarentena)** — o assert que confere o `firstOrFail`
  escopado no fonte **roda**; o par 404/200 que provaria o comportamento **não**.

---

## UC-PURCRE-03 · `store()` recusa `variation_id` forjado de outro tenant · `must` `[T0]` `[V0]`

- **Persona:** Wagner — este é o caminho de **escrita**: aceitar um `variation_id` alheio grava
  `purchase_lines` e, com status `received`, **move estoque** de produto que não é do tenant.
- **Aceite:** Dado um POST para `/purchases` cujo array `purchases[]` traz `variation_id` de produto
  do negócio 98 · Quando o usuário do negócio 1 submete · Então o `store()` **recusa** — a validação
  de ownership roda antes de `createOrUpdatePurchaseLines`, e nenhuma linha é gravada.
- **Teste:** [`PurchaseGradeMatrixTest`](../../../../tests/Feature/Purchase/PurchaseGradeMatrixTest.php)
  — *"store() valida ownership Tier 0 das variations (anti payload forjado cross-tenant)"*
  (estrutural, **executa**).
- **Contrato:** RUNBOOK §10 (invariante ✅) · charter R-PUR-001 ·
  [proibicoes §REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE](../../../../memory/proibicoes.md).
- **Regressão que defende:** a grade transformou a tela num emissor de **N linhas por submit**. Antes,
  forjar payload rendia uma linha; agora rende a matriz inteira. E o efeito não é só leitura: é
  estoque movido e valor gravado — o território da Regra Mestre, onde o canon exige dupla prova.
- **Status: ⚠️ 🧪 estrutural (comportamento em quarentena)** — o assert confere no fonte que a
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
- **Status: 🧪 comportamento (veredito pendente da lane)** — **o único UC das quatro telas cuja
  defesa é comportamento real e executa.** Os 6 casos exercitam o `GradeLayoutBuilder` de verdade
  (lógica pura, driver-agnostic), incluindo os dois caminhos de degradação que mais importam:
  ambíguo e misto.
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
- **Status: ⚠️ 🧪 estrutural** — os asserts provam que o modo grade está *plugado* no arquivo; **não**
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
- **Status: ⚠️ 🧪 estrutural** — casamento de texto; nenhum usuário com filial restrita é montado.

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
- **Status: 🧪 estrutural (correto)** — **sem ⚠️.** Ausência de literal *é* o contrato.

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
