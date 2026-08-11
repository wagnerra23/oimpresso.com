---
id: resources-js-pages-compras-index-casos
casos: Cockpit de Compras · /compras
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo de quem-vê-o-quê e o contrato de filtro não mudam quando o cockpit ganhar abas novas.
owner: wagner
last_run: "2026-08-11"
last_run_ci: "UC-CMP-06/07 reconciliados em 2026-08-11 ([W]: 'pague a dívida, e deixe requerido'); veredito segue da lane PHP / Pest (Compras · MySQL)"
---

# Casos de Uso & Aceite — Cockpit de Compras (`/compras`)

> **Âncora:** os UC derivam dos CU do
> [SDD §6.1](../../../../memory/requisitos/Compras/SDD-tela-cockpit-compras-v1.0.md) — `CU-COM-01`,
> `CU-COM-02`, `CU-COM-03`, `CU-COM-04`, `CU-COM-05`, `CU-COM-06`, `CU-COM-07` e `CU-COM-08` —
> **nunca do `Index.tsx`**: teste derivado do código é tautológico e trava o desvio em vez de
> pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Por que este arquivo nasce agora:** completa o trio da tela (o charter existe desde 2026-05-21;
> `casos.md` faltava — `npm run screen:files -- Compras/Index` acusava `✗ .casos.md`). É o chip **S1**
> da Onda 1 do [passo 5](../../../../memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md),
> e fecha 4 das 5 lacunas que `node scripts/governance/requisitos-status.mjs Compras` nomeava
> (US-COM-006/007/009/011 marcadas `done` **sem contrato**).
>
> ⚖️ **FORÇA DO VEREDITO — leia antes de confiar no status.** A lane
> `PHP / Pest (Compras · MySQL)` (`.github/workflows/compras-pest.yml`) é **ADVISORY**: ela não está
> em [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json)
> (as lanes Pest **required** são Financeiro, NfeBrasil e Unit). **Reprova visível, não bloqueia merge.**
> O que bloqueia merge aqui é o `Casos-coverage · ratchet` (G-1 trio + G-2 UC↔teste), que é required.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
> ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora (SDD §6) | Teste | Status |
|----|-------------|------|-----------------|-------|--------|
| UC-CMP-01 | Listagem não mostra compra de outro business | must `[T0]` | `CU-COM-02` item 1 | `MultiTenantTest` | 🧪 |
| UC-CMP-02 | Detalhe cross-tenant responde 404, não 403 | must `[T0]` | `CU-COM-02` item 2 · `CU-COM-03` item 3 | `MultiTenantTest` | 🧪 |
| UC-CMP-03 | KPIs agregam só o próprio business | must `[T0]` | `CU-COM-02` item 3 | `MultiTenantTest` | 🧪 |
| UC-CMP-04 | Busca `?q=` não casa fornecedor de outro business | must `[T0]` | `CU-COM-02` item 4 | `MultiTenantTest` · `MultiTenantSqlGuardTest` | 🧪 |
| UC-CMP-05 | Sem `compras.view` → 403; com ela, componente `Compras/Index` | must | `CU-COM-01` itens 1-2 · `CU-COM-07` item 2 | `ComprasIndexTest` | 🧪 |
| UC-CMP-06 | Estar numa aba de estágio não derruba a busca | must | `CU-COM-04` item 1 | `ComprasContratoFiltrosTest` | 🧪 **vermelho esperado** |
| UC-CMP-07 | Todo `sort` que o Service ordena é aceito pelo Request | must | `CU-COM-04` item 2 | `ComprasContratoFiltrosTest` | 🧪 **vermelho esperado** |
| UC-CMP-08 | Cockpit respeita as localizações permitidas | must `[reg]` | `CU-COM-05` item 1 | `ComprasContratoFiltrosTest` | 🧪 **vermelho esperado** |
| UC-CMP-09 | Entrada da compra grava valor e move estoque | must `[V0]` | `CU-COM-08` itens 1-3 | `PurchaseCalculoValorEstoqueE2ETest` | 🧪 (roda só no **nightly**, não no PR) |

> 🧪 **Nenhum status aqui é afirmação de verde.** Este PR não executou teste algum (CT 100/CI —
> [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). "Vermelho
> esperado" é **predição declarada**, derivada de leitura de código; o veredito vem da lane
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-15).

---

## UC-CMP-01 · Listagem não mostra compra de outro business · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — o cockpit **nunca** pode mostrar uma linha de outro tenant.
- **Aceite:** Dado uma compra criada no business 99 e uma no business 1 · Quando o usuário do
  business 1 pede `GET /compras` com partial reload `only:['rows']` · Então **nenhum** `ref_no`
  criado no business 99 aparece entre as linhas.
- **Teste:** [`MultiTenantTest`](../../../../Modules/Compras/Tests/Feature/MultiTenantTest.php) —
  *"cenario 1: GET /compras user biz=1 NAO vê compras criadas em biz=99"*.
- **Contrato:** `CU-COM-02` item 1 do SDD · `R-COM-002` do [SPEC](../../../../memory/requisitos/Compras/SPEC.md) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o cockpit deriva a lista de `TransactionUtil::getListPurchases`, um util
  de **6k+ linhas compartilhado com Sells e Expenses**. Qualquer refactor lá pode soltar o
  `WHERE transactions.business_id` sem que ninguém do Compras perceba.
- **Status: 🧪** — o teste existe e cita o UC; veredito pendente da lane `PHP / Pest (Compras · MySQL)` (advisory).

---

## UC-CMP-02 · Detalhe cross-tenant responde 404, não 403 · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — responder 403 **confirma que o recurso existe**; 404 não conta nada.
- **Aceite:** Dado uma compra do business 99 · Quando o usuário do business 1 pede
  `GET /compras/{id}/detalhe` · Então recebe **404**. E, como controle positivo, o mesmo endpoint
  para uma compra **do próprio business** responde **200**.
- **Teste:** [`MultiTenantTest`](../../../../Modules/Compras/Tests/Feature/MultiTenantTest.php) —
  *"cenario 2"* + *"cenario 2b (sanity — controle positivo)"*.
- **Contrato:** `CU-COM-02` item 2 e `CU-COM-03` item 3 do SDD · aceite #2 de **US-COM-007**
  (*"`abort_if(...404)` em show/edit/update/destroy"*).
- **Regressão que defende:** o padrão natural do Laravel é 403 em falha de autorização. `ComprasController@show`
  escolhe 404 **de propósito** (comentário `Defense-in-depth: 404 (não 403)`). Sem o controle positivo,
  um `abort(404)` incondicional passaria — por isso o par 404/200 é o contrato, não o 404 sozinho.
- **Status: 🧪** — teste existe e cita o UC; veredito pendente da lane (advisory).

---

## UC-CMP-03 · KPIs agregam só o próprio business · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — o KPI é um número agregado: um vazamento aqui é **silencioso**
  (não aparece linha estranha, só o total fica errado).
- **Aceite:** Dado compras nos businesses 1 e 99 · Quando o usuário do business 1 resolve a prop
  deferida `kpis` · Então os contadores (`aberto`, `transito`, `mes`, `fornec`) **não** contam
  nenhuma transação do business 99.
- **Teste:** [`MultiTenantTest`](../../../../Modules/Compras/Tests/Feature/MultiTenantTest.php) —
  *"cenario 3: props.kpis defer do user biz=1 NAO conta compras de biz=99"*.
- **Contrato:** `CU-COM-02` item 3 do SDD · `R-COM-003` do SPEC.
- **Regressão que defende:** `calcularKpis` monta 4 agregações independentes (`count`, `count`, `sum`,
  `distinct count`). Basta **uma** delas perder o `where('business_id')` — e a listagem continuaria
  correta, escondendo o defeito.
- **Status: 🧪** — teste existe e cita o UC; veredito pendente da lane (advisory).

---

## UC-CMP-04 · Busca `?q=` não casa fornecedor de outro business · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — este é o **risco R1** do
  [`AUDIT-SENIOR-2026-05-25`](../../../../memory/requisitos/Compras/AUDIT-SENIOR-2026-05-25.md),
  classificado de blast radius máximo.
- **Aceite:** Dado um contato (fornecedor) que existe **só** no business 99 · Quando o usuário do
  business 1 busca por parte do nome dele (`?q=`) · Então nenhuma linha do business 99 volta — porque
  o `leftJoin('contacts')` carrega o escopo `contacts.business_id` **dentro da closure do join**, não
  só no `WHERE` externo.
- **Teste:** [`MultiTenantTest`](../../../../Modules/Compras/Tests/Feature/MultiTenantTest.php)
  *"cenario 4"* (comportamental, HTTP) + [`MultiTenantSqlGuardTest`](../../../../Modules/Compras/Tests/Feature/MultiTenantSqlGuardTest.php)
  *"US-COM-009 cenario 5"* (invariante de SQL, que cobre também Sells e Expenses).
- **Contrato:** `CU-COM-02` item 4 do SDD · **US-COM-009** (o hotfix que fechou o R1).
- **Regressão que defende:** o `WHERE transactions.business_id` **não protege o JOIN**. Antes do
  hotfix, um `?q=` malicioso casava `contacts.supplier_business_name` de outro tenant. O par
  comportamental+SQL existe porque o guard de SQL sozinho é estrutural e o HTTP sozinho pode passar
  por falta de dado.
- **Status: 🧪** — testes existem e citam o UC; veredito pendente da lane (advisory).

---

## UC-CMP-05 · Sem `compras.view` → 403; com ela, componente `Compras/Index` · `must`

- **Persona:** qualquer operador — a porta de entrada da tela.
- **Aceite:** Dado um usuário do business 1 · Quando ele **tem** `compras.view`, `GET /compras`
  responde **200** e o componente Inertia é `Compras/Index` (nunca Blade); quando **não tem**,
  responde **403**. E a prop `permissions.create` reflete `purchase.create` — **não** `compras.create`
  (convergência C1): quem não pode criar compra não vê o botão.
- **Teste:** [`ComprasIndexTest`](../../../../Modules/Compras/Tests/Feature/ComprasIndexTest.php) —
  `test_rota_compras_responde_200_com_permission`, `test_index_renderiza_inertia_component_compras_index`,
  `test_sem_permission_compras_view_retorna_403`, `test_c1_prop_permissions_create_resolve_via_purchase_create`,
  `test_c1_user_sem_purchase_create_recebe_permissions_create_false`.
- **Contrato:** `CU-COM-01` itens 1-2 e `CU-COM-07` item 2 do SDD · `R-COM-001` do SPEC ·
  ADR proposta [`compras-purchase-convergencia-c1`](../../../../memory/decisions/proposals/compras-purchase-convergencia-c1.md).
- **Regressão que defende:** a autorização mora no `authorize()` de um **FormRequest**, não no
  Controller — é fácil alguém trocar o type-hint por `Request` num refactor e a tela abrir pra todo mundo.
- **Status: 🧪** — testes existem e citam o UC; veredito pendente da lane (advisory).

---

## UC-CMP-06 · Estar numa aba de estágio não derruba a busca · `must`

- **Persona:** Larissa / ROTA LIVRE — clica na aba *"A pagar"*, digita o nome do fornecedor e dá Enter.
  É o gesto mais natural da tela.
- **Aceite:** Dado que o cockpit oferece as abas *Todas / A pagar / Rascunhos / Em trânsito* · Quando
  a tela navega com o `stage` correspondente à aba ativa · Então `/compras` **responde 200** — a
  listagem não pode ser derrubada pela validação do próprio módulo. **E a whitelist continua fechada:**
  um `stage` arbitrário (`'; DROP'`) segue rejeitado.
- **Teste:** [`ComprasContratoFiltrosTest`](../../../../Modules/Compras/Tests/Feature/ComprasContratoFiltrosTest.php) —
  *"UC-CMP-06 · aba de estágio emitida pela tela não pode derrubar a listagem"*.
- **Contrato:** `CU-COM-04` item 1 do SDD · **US-COM-008** (o `ListarComprasRequest` é o entregável
  dela) · `R-COM-004` do SPEC (*"Filtros: query string `?q=...&stage=...`"*) · §Goals do charter
  (*"Filtros locais: all / abertas / rascunhos / em trânsito"*).
- **Regressão que defende:** a whitelist do `ListarComprasRequest` (`all,received,ordered,pending,draft`)
  e os ids das abas (`all,abertas,rascunhos,transito`) são **dois vocabulários diferentes no mesmo
  módulo**. Hoje nada liga um ao outro — nem tipo, nem teste. Qualquer aba nova reabre o mesmo buraco.
- ⚠️ **O assert aceita as duas correções — de propósito.** O contrato é *"a UI não emite valor que o
  contrato rejeita"*, e congelar um dos caminhos no assert seria travar a implementação, não o
  comportamento ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) Fase 2.2).
- ✅ **Caminho decidido (técnico, ancorado — não é escolha de produto):** **a aba emite o status core**
  (`ordered`/`pending`/`draft`/`received`), *não* se alarga a whitelist. Razão: o
  [dicionário de domínio](../../../../memory/dominio/compras.md) define o ciclo canônico
  `ordered` → `pending` → `received`, e ele é a **fonte única** do gate `dominio:check` (required,
  [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4).
  Alargar a whitelist para `abertas|rascunhos|transito` **oficializaria um segundo vocabulário
  dentro do mesmo módulo** — exatamente a doença que o dicionário cura (a erradicação de `locacao`
  na OficinaAuto, [proibições §5](../../../../memory/proibicoes.md) 2026-06-09, é o precedente).
  O rótulo visível para a Larissa continua *"A pagar"*/*"Rascunhos"*/*"Em trânsito"*: muda o **valor
  emitido**, não a palavra na tela.
- 🔧 **Implementado em 2026-08-11** ([W]: *"pague a dívida, e deixe requerido"* — a correção passou a
  caber). **O caminho literal decidido acima não fecha, e isto foi MEDIDO, não julgado:**
  duas das quatro abas não têm status core equivalente —
  **"A pagar"** é `dueAmount = final_total − amount_paid` (grandeza de **pagamento**, calculada de
  duas colunas de dinheiro — não existe em `transactions.status`), e **"Em trânsito"** agrupa
  **dois** status (`transito` OU `pedido`) enquanto o Service filtra com `where` de valor único.
  Fazer a aba "emitir o status core" exigiria inventar semântica de domínio para "A pagar" —
  decisão de produto, não de implementação.
  **O que foi feito preserva o princípio que a decisão protegia** (um só vocabulário na fronteira):
  o defeito real era `Index.tsx:256`, onde a **busca** mandava `stage: localFilter` — rótulo de
  exibição no lugar do filtro de servidor. Passou a mandar `filters.stage`, alinhando com as outras
  três chamadas do arquivo (154/196/211). A fronteira carrega só o vocabulário CORE; as abas seguem
  filtrando client-side, e o rótulo visível para a Larissa não muda.
  **A whitelist NÃO foi alargada** — o controle-negativo anti-SQLi segue intacto.
- **Status: 🧪 vermelho esperado** — **predição**, derivada de leitura. Veredito vem da lane.

---

## UC-CMP-07 · Todo `sort` que o Service ordena é aceito pelo Request · `must`

- **Persona:** Larissa / ROTA LIVRE — clica no cabeçalho *"Estágio"* ou *"A pagar"* pra ordenar.
- **Aceite:** Dado que `ComprasService::SORT_MAP` declara o conjunto de colunas que o módulo **sabe**
  ordenar com segurança · Quando qualquer uma dessas colunas chega como `sort` em `/compras` · Então
  a listagem responde **200**. Os dois contratos do mesmo módulo não podem divergir: ou o Request
  aceita o que o Service mapeia, ou o Service para de mapear o que o Request rejeita.
- **Teste:** [`ComprasContratoFiltrosTest`](../../../../Modules/Compras/Tests/Feature/ComprasContratoFiltrosTest.php) —
  *"UC-CMP-07 · todo sort do SORT_MAP é aceito pelo ListarComprasRequest"* (o conjunto é lido do
  `SORT_MAP` por reflexão — **não** de uma lista copiada à mão, que drifaria) + o controle negativo
  *"sort fora do SORT_MAP continua rejeitado"*.
- **Contrato:** `CU-COM-04` itens 2-3 do SDD · **US-COM-008** (a whitelist anti-SQLi é o entregável).
- **Regressão que defende:** o `SORT_MAP` existe **justamente** como defesa anti-SQLi — é ele que
  decide qual coluna SQL real entra no `orderBy`. Manter uma segunda lista no FormRequest, escrita à
  mão e menor, transforma a defesa em bug de usabilidade e garante drift a cada coluna nova.
- ⚠️ **Não é "abrir a whitelist".** O controle negativo faz parte do UC: valor **fora** do `SORT_MAP`
  continua rejeitado. A superfície permitida não cresce — ela passa a ter **um** dono em vez de dois.
- **Status: 🧪 vermelho esperado** — **predição**. Veredito vem da lane.

---

## UC-CMP-08 · Cockpit respeita as localizações permitidas · `must` `[reg]`

- **Persona:** operador restrito a uma loja (P3 do SDD §2) — ⬜ **persona ainda não validada por [W]**;
  ver o aviso no SDD §2.
- **Aceite:** Dado um usuário **sem** `access_all_locations`, com permissão `location.{A}` mas **não**
  `location.{B}`, no mesmo business · Quando abre `/compras` · Então **nenhuma** compra da loja B
  aparece na listagem — exatamente como `/purchases` se comporta nos **dois** caminhos (Blade e React).
- **Teste:** [`ComprasContratoFiltrosTest`](../../../../Modules/Compras/Tests/Feature/ComprasContratoFiltrosTest.php) —
  *"UC-CMP-08 · compra de local não permitido não aparece no cockpit"*.
- **Contrato:** `CU-COM-05` item 1 do SDD. **Paridade contra a fonte 3** (Blade AdminLTE):
  `PurchaseController@index` aplica `whereIn('transactions.location_id', $permitted_locations)` **no
  ramo AJAX que a Blade consome** e **no ramo `indexInertia`**. O `ComprasService` não aplica escopo
  algum além do `business_id` — varredura contada em SDD §5.4.1 (4 chamadores de `getListPurchases`,
  3 escopam, 1 não).
- **Regressão que defende:** é **perda de escopo na migração**, o vetor que o
  [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) existe pra pegar: documentar só o
  React atual carimbaria a ausência como se fosse o correto. Não é vazamento cross-tenant — o
  `business_id` segue escopado (UC-CMP-01 prova) — é vazamento **intra-tenant** entre lojas.
- ⚠️ **Se [W] decidir que Compras não tem escopo por localização**, isto vira **Non-Goal explícito no
  charter** (só [W] preenche) e o UC é retirado. O que não pode é a ausência ficar **silenciosa**.
- **Status: 🧪 vermelho esperado** — **predição**. Veredito vem da lane.

---

## UC-CMP-09 · Entrada da compra grava valor e move estoque · `must` `[V0]`

- **Persona:** Larissa / ROTA LIVRE — o KPI *"Volume do mês"* e a coluna *"Total"* do cockpit só
  valem se o `final_total` gravado estiver certo; e o estoque que ela vende depois vem desta entrada.
- **Aceite:** Dado uma compra com grade 2×2, frete, desconto percentual e imposto · Quando é
  submetida em `POST /purchases` (biz=1) · Então `final_total` confere **por dois caminhos**
  (recomputo à mão **e** soma das linhas), as `purchase_lines` batem `qty × custo` por `variation_id`,
  e `variation_location_details.qty_available` muda exatamente pelo delta comprado — com blindagem
  contra o `num_uf` ×100.
- **Teste:** [`PurchaseCalculoValorEstoqueE2ETest`](../../../../Modules/Compras/Tests/Feature/PurchaseCalculoValorEstoqueE2ETest.php) —
  *"POST /purchases (grade+frete+desconto%+imposto) persiste VALOR, LINHAS e ESTOQUE corretos"*.
- **Contrato:** `CU-COM-08` itens 1-3 do SDD · **US-COM-011** (o teste **é** o entregável da US) ·
  [REGRA MESTRE valor/estoque](../../../../memory/proibicoes.md) Tier 0 · [ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md) (biz=1).
- **Regressão que defende:** o incidente de 2026-06-05 — `Util::num_uf` interpretando o ponto
  decimal de um total fracionado como separador de milhar, inflando 16 vendas ×100k. A entrada de
  compra tem a mesma superfície (desconto %, frete, imposto) e **mexe em estoque** além de valor.
- ⚖️ **Força do veredito — três portas diferentes, três respostas** (a distinção que a classe LC-08
  exige que se declare; medido 2026-07-27):
  - **roda em algum lugar?** ✅ sim — `phpunit.xml:57` registra `./Modules/Compras/Tests/Feature`
    (o diretório inteiro) e `scripts/tests/shards-plan.mjs` enumera subdiretórios de `Modules` como
    shards ⇒ **cai no full-suite nightly do CT 100**.
  - **roda no PR?** ❌ **não** — o step `Run Pest` do [`compras-pest.yml`](../../../../.github/workflows/compras-pest.yml)
    é allowlist arquivo-a-arquivo e **este arquivo não está lá**; `git grep PurchaseCalculoValorEstoqueE2E -- .github/`
    devolve **zero**.
  - **bloqueia merge?** ❌ não (a própria lane é advisory).
  → **Gap pra [W]:** o entregável de uma US `[V0]` Tier 0 não é exercitado por PR nenhum. Adicioná-lo
  à allowlist é decisão dele — este chip **não** mexeu, porque adicionar um E2E pesado de status
  desconhecido junto com os failing-first do UC-CMP-06/07/08 misturaria dois sinais na mesma lane.
- **Status: 🧪** — teste existe e cita o UC; veredito vem do nightly, não deste PR.

---

## Por que `CU-COM-06` e `CU-COM-09` do SDD ficaram **sem UC**

Não é esquecimento — é o critério de parada ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) Fase 2.2):

- **`CU-COM-06` (menu Ações)** — o contrato existe em 2 fontes (a Blade e o `AcoesDropdown`), mas
  exercitá-lo exige **e2e de browser**, e o Compras **não tem spec Playwright** (`npm run screen:files -- Compras/Index`
  resolve o e2e só pelo `PixelBaselineTest`, que é regressão visual, não comportamento). UC com id sem
  teste que o cite é **órfão**, e o `casos-gate` G-2 (required) bloqueia o merge de quem for atendê-lo.
  Fica como `[BACKLOG]` até existir a casa de teste.
- **`CU-COM-09` (importar DF-e)** — **não há código**: o botão está `disabled` e o
  `ImportarDfeComoCompraService` não existe (US-COM-003). Caso sem implementação vira UC órfão, e
  *"UC não é canal de pedido"* ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16). O
  contrato nasce **junto** com a implementação.

---

## `[BACKLOG]` — achados sem contrato em 2 fontes (não viram UC agora)

> Regra dura: comportamento com contrato em **≥2 fontes** vira UC com id; contrato em 1 fonte só, ou
> achado sem âncora, vira bullet sem id. UC com id sem teste é **órfão**, e o `casos-gate` G-2
> (required) **bloqueia o merge de quem for atendê-lo** — 7 UC ancorados valem mais que 30 órfãos
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

- `[BACKLOG]` Cockpit ignora `view_own_purchase` (o legado restringe a `created_by = eu` quando o
  usuário tem `view_own_purchase` sem `purchase.view`). A premissa — alguém com `compras.view` **e**
  `view_own_purchase` **sem** `purchase.view` — não está estabelecida em documento nenhum do módulo.
  Precisa de [W] antes de virar UC. SDD §5.4.1 · D3.
- `[BACKLOG]` `AcoesDropdown` recebe `visibility` com defaults `{canEdit:true, canDelete:true, canRefund:true}`
  e o `Index.tsx` **não passa** as `permissions` que o backend já resolve — o menu mostra Editar/Excluir
  para quem não pode. SDD D4.
- `[BACKLOG]` Três ações da Blade não existem no cockpit: **baixar documento**, **ver documento
  (imagem)** e **adicionar pagamento**. SDD `CU-COM-06` item 1 · D5.
- `[BACKLOG]` Filtro de aba é client-side sobre a **página corrente**: "A pagar" mostra as abertas
  daquela página, e o rodapé (soma da página) diverge do `SummaryFooter` (soma do servidor) na mesma
  tela. O charter já declara como dívida consciente. SDD §5.4.2 · D6.
- `[BACKLOG]` Estágios `conferido` e `pago` são **inalcançáveis** — nenhum valor de
  `transactions.status` mapeia pra eles. É o sintoma da FSM não-persistida: **US-COM-014**. SDD §5.4.5 · D7.
- `[BACKLOG]` Os 4 botões de export (CSV/Excel/PDF/Imprimir) chamam o mesmo handler, que ignora o
  formato e **não propaga o filtro corrente** — exporta a lista inteira da Blade. SDD §5.4.6 · D8.
- `[BACKLOG]` A Blade oferece 5 filtros de servidor (local · fornecedor · status · status de pagamento ·
  período); o cockpit oferece 2 (`q`, `stage`). Some sem Non-Goal ⇒ candidato a regressão de paridade,
  mas o charter chama filtro server-side de "Wave 7" — **decisão de escopo, precisa de [W]**. SDD D9.
- `[BACKLOG]` `throttle:60,1` está declarado na rota mas o **429 nunca foi provado comportamentalmente**
  (o teste atual é source-grep). **US-COM-008** admite o diferimento. SDD D11.
- `[BACKLOG]` Drawer sem `role="dialog"`, focus-trap e handler `Esc` — **US-COM-020**. SDD D12.

## ⚠️ Divergências que precisam de [W] (não corrigidas aqui — são INTENÇÃO)

- **Non-Goal do charter vs. drawer que existe:** o charter diz *"❌ NÃO renderiza DrawerView 5 tabs —
  backend `show()` ainda não existe"*, mas o `show()` existe (rota `compras.show`) e o `Drawer.tsx`
  renderiza as 5 abas. Non-Goal é intenção; o agente é **proibido** de editar. SDD §5.4.3 · D13.
- **Anti-hook do charter vs. bridges deliberados:** o charter proíbe `window.open`/`window.location.href`
  para `/purchases/*`; Impressão, Rótulos e Reembolso usam isso **de propósito** (são Blade-only, e o
  próprio cabeçalho do `AcoesDropdown` documenta a escolha). SDD `CU-COM-07` item 4 · D14.
- **Fonte 4 (Delphi) ausente:** Compras não tem `ANTI-REGRESSAO-*.md`. A paridade deste trio é medida
  contra a **Blade viva**, não contra o Office Comercial. Nada de contrato legado Delphi foi inventado.
  SDD §0.1 · D15.
