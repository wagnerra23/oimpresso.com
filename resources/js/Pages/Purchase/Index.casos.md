---
id: resources-js-pages-purchase-index-casos
casos: Listagem de Compras · /purchases
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o dual-path Blade×React e o escopo por tenant são duráveis — não mudam quando a lista ganhar coluna ou filtro novo.
owner: wagner
last_run: "2026-09-05"
last_run_ci: "🟡 A lane purchase-pest.yml nasceu e os 2 UC [T0] (02/03) têm contrato de COMPORTAMENTO em PurchaseIndexTenantContratoTest — 4 passed, 11 assertions, run no CT 100 e verde no CI, mordida verificada por mutação. Os UC 01/04/05/06 seguem 🔴 sem lane: apontam pro IndexPageTest, que é presence-gate e não roda. Ver §Dívida de prova."
---

# Casos de Uso & Aceite — Listagem de Compras (`/purchases`)

> **Âncora:** os UC derivam do
> [`RUNBOOK-purchase-index.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-index.md)
> (§1 dual-path · §2 paridade de ações · §5 invariantes Tier 0) cruzado com o
> [`Index.charter.md`](Index.charter.md) (Goals/Non-Goals/Anti-hooks) — **nunca do `Index.tsx`**:
> teste derivado do código é tautológico e trava o desvio em vez de pegá-lo
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Por que nasce agora:** fecha o trio da tela. O `screen-coverage` acusava `Purchase · 4 telas ·
> 4 charter · 0 casos`; o charter existe desde 2026-07-11 e o contrato executável faltava.
> **Não existe SDD do Purchase** — a âncora canon disponível é o RUNBOOK de tela (doc canon
> obrigatório por [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)),
> e isso fica declarado aqui em vez de disfarçado.

---

## 🟡 Dívida de prova — a lane nasceu; 2 dos 6 UC já têm defesa ativa

> ✅ **Atualização de 2026-09-05 — a errata abaixo continua verdadeira para o dia em que foi escrita,
> e fica inteira.** O que mudou desde então: a lane
> [`purchase-pest.yml`](../../../../.github/workflows/purchase-pest.yml) **existe** e roda em CI, e
> os dois UC `[T0]` desta tela ganharam contrato de comportamento em
> [`PurchaseIndexTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseIndexTenantContratoTest.php)
> — as **duas** camadas de falha que a errata nomeia, fechadas para o UC-02 e o UC-03.
> Os UC **01, 04, 05 e 06** seguem `🔴 sem lane`: os 8 arquivos antigos ficam fora da allowlist
> porque têm 6 vermelhos reais (ver §Divergências), e pôr um teste vermelho numa lane é trocar
> "invisível" por "ruído que se aprende a ignorar".
>
> **O título desta seção mudou de 🔴 para 🟡 por isso — não por otimismo.** `na lane` subiu de 533
> para 535 no `uc-lane-coverage`, e os UC-02/03 saíram da lista de órfãos dele.

> ⚠️ **Correção da v1 deste arquivo (2026-09-05), registrada e não apagada.** A v1 dava a execução
> do `IndexPageTest` como certa e classificava a dívida como sendo **só** presence-gate: a tabela
> perguntava *"o que de fato prova"* — pergunta que já pressupõe que o teste roda —, o texto
> concluía *"o teste existe, cita o UC e satisfaz o G-2, mas a defesa é de forma, não de
> comportamento"*, e o §Divergências chegava a falar em *"o verde da lane"*, que **não existe**.
> **O presence-gate é real — mas não era o problema principal, e a premissa de que o teste roda era
> falsa.** Eu medi a perna do **assert** (o que ele prova) e **não medi a perna da LANE** (se alguém
> o invoca). Quem pegou foi o gate `uc-lane-coverage` do CI, reprovando os 6 UC desta
> tela com *"existe e NENHUMA lane roda"*. O gate estava certo; eu estava errado.

Medição em `origin/main` (2026-09-05), **três pernas**, todas contadas:

| perna | resultado |
|---|---|
| workflows que citam `tests/Feature/Purchase` (`git grep -c -- .github/`) | **0** |
| linhas `Purchase` em `.github/ci-sqlite-pest.list` (542 linhas) | **0** |
| arquivos de teste em `tests/Feature/Purchase/` | **8** |

**Os 8 arquivos de teste do Purchase são órfãos de CI.** Nenhuma lane os executa — nem a MySQL por
módulo, nem a sqlite curada do `ci.yml`. Logo o `IndexPageTest` **também não roda**:

| teste | requests HTTP | asserts de presença | executa? |
|---|---:|---:|---|
| [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) | **0** | **55** | 🔴 **não — sem lane** |
| [`PurchaseIndexTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseIndexTenantContratoTest.php) | **4** | 0 | ✅ **sim — `purchase-pest.yml`** |

**Duas camadas de falha, e a de cima é a que a v1 não tinha nomeado:**

1. **Sem lane** — o teste nunca é invocado. Nas palavras do próprio gate: *"teste fora de toda lane
   é 'verde impossível': existe, pode estar vermelho há meses, e nenhum PR o acorda."*
2. **Presence-gate** — mesmo ganhando lane, o que ele prova é que certas *strings* existem em
   `Index.tsx` e em `PurchaseController.php`. Ele lê os arquivos-fonte e casa texto
   (`file_get_contents` + `toContain`): pega a **remoção** de um trecho, mas não exercita request,
   não monta tenant e não valida resposta. É a classe
   [LC-11](../../../../memory/LICOES_CODE.md), que o ledger alarma com 11 ocorrências.

**Consequência (revista em 2026-09-05):** os UC **02 e 03** têm defesa ativa — comportamental **e**
executada. Os UC **01, 04, 05 e 06** seguem sem nenhuma das duas: continuam apontando para o
`IndexPageTest`, que é presence-gate e não roda.

**Por que o conserto dos outros 4 não está aqui:** o gate diz, com todas as letras, *"conserto NÃO é
mexer na allowlist por conta própria — por que ela existe (custo de CI? teste instável escondido?) é
decisão [W]"*. Concordo, e o motivo é concreto: rodada à mão no CT 100 (2026-09-04), a pasta
`tests/Feature/Purchase/` devolve **6 failed · 6 skipped · 90 passed (214 assertions)** — 5 dos
vermelhos cobram artefato que não existe e 1 é falso-positivo de presence-gate. Pôr esses 8 arquivos
na lane trocaria "invisível" por "vermelho permanente", que é o gate-de-teatro ao contrário. A lane
nasceu com allowlist justamente para admitir só o que está comprovadamente verde e crescer a partir
daí — o caminho é converter, um UC por vez, não ligar tudo de uma vez.

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURIDX-01 | SPA recebe React; acesso direto recebe Blade | must | RUNBOOK §1 · charter Mission | `IndexPageTest` | 🔴 sem lane |
| UC-PURIDX-02 | Lista nunca sai do `business_id` da sessão | must `[T0]` | RUNBOOK §5 · charter Non-Goal 4 | `PurchaseIndexTenantContratoTest` | ✅ comportamento · na lane |
| UC-PURIDX-03 | Lista respeita `permitted_locations` | must `[T0]` | RUNBOOK §3 · charter Goals | `PurchaseIndexTenantContratoTest` | ✅ comportamento · na lane |
| UC-PURIDX-04 | Ação "Etiquetas" existe no React (paridade Blade) | must `[reg]` | RUNBOOK §2 (regressão datada) | `IndexPageTest` | 🔴 sem lane (regressão já vivida, hoje sem defesa) |
| UC-PURIDX-05 | Rota Blade abre por `window.open`, nunca `router.visit` | must | RUNBOOK §3 · §5 | `IndexPageTest` | 🔴 sem lane |
| UC-PURIDX-06 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | RUNBOOK §5 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `IndexPageTest` | 🔴 sem lane |

---

## UC-PURIDX-01 · SPA recebe React; acesso direto recebe Blade · `must`

- **Persona:** Maiara/Felipe navegam pelo Cockpit; o cliente enxerga sempre o path React.
- **Aceite:** Dado `PurchaseController@index` · Quando o request traz o header Inertia **ou** `?v=2`
  · Então renderiza a Page `Purchase/Index` via Inertia. E, como **controle negativo**, um GET normal
  sem header **continua** caindo na view Blade legacy, e o request AJAX continua devolvendo o JSON
  Datatables.
- **Teste:** [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) — *"Controller
  index() tem dual path"* · *"Controller PRESERVA path Blade legacy"* · *"Controller PRESERVA path
  AJAX DataTables legacy (Yajra)"*.
- **Contrato:** RUNBOOK §1 (tabela de decisão do dual-path) · [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md).
- **Regressão que defende:** o F5 CUTOVER do MWART é humano e ainda não aconteceu nesta tela. Um
  refactor que "limpe" o Blade legacy mata o acesso direto fora do SPA — e o sintoma só aparece
  para quem abre a URL na mão, que é justamente quem não reporta.
- **Status: 🔴 sem lane** — as três asserções são casamento de texto no fonte do controller e
  nenhuma emite request; provariam que o código não foi *apagado*, não que o roteamento funciona.
  E nem a isso chegam: nenhuma lane as executa.

---

## UC-PURIDX-02 · Lista nunca sai do `business_id` da sessão · `must` `[T0]`

- **Persona:** operador do próprio negócio — uma linha de outro tenant na lista de compras é
  vazamento de dado comercial (fornecedor, custo, volume).
- **Aceite:** Dado uma compra no tenant **98** e outra num negócio distinto · Quando o usuário do
  **98** abre `/purchases` · Então a lista traz a compra própria (controle positivo) e **nenhuma**
  compra do outro negócio. E `indexInertia` **não** usa `withoutGlobalScopes` sem o comentário
  `SUPERADMIN` que o canon exige.
  > **Corrigido em 2026-09-05:** o aceite dizia *"negócios 1 e 98 · usuário do negócio 1"*. O 1 é a
  > WR2 Sistemas, empresa REAL, e no CT 100 a base é clone de prod que não se limpa entre runs —
  > semear compra ali escreve no espelho da empresa de verdade. O tenant de teste é o **98**,
  > fictício ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md),
  > que supersede a 0101). O adversário é **descoberto em runtime**, não fixado: qualquer business
  > != 98 que tenha `business_location` — sem location a compra alheia não apareceria nem havendo
  > vazamento (o INNER join a derrubaria), e seria verde por vácuo.
- **Teste:** [`PurchaseIndexTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseIndexTenantContratoTest.php)
  — *"UC-PURIDX-02 (A · controle positivo) a compra do PROPRIO business aparece na lista"* ·
  *"UC-PURIDX-02 (B · contrato T0) a compra de OUTRO business NAO aparece na lista"*.
  Os asserts de [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) **permanecem
  no arquivo**, mas seguem sem lane — pegam a remoção literal do trecho, que é uma defesa a menos,
  não a mesma.
- **Contrato:** RUNBOOK §5 · charter §Non-Goals item 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL).
- **Regressão que defende:** o model `Transaction` **não tem global scope** — o isolamento aqui é
  manual, escrito em cada query. Foi exatamente essa ausência que produziu o IDOR de escrita
  corrigido em `PurchaseController@update`
  ([`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php)).
  O que falhou uma vez no `update` pode falhar no `index`.
- **Status: ✅ comportamento · na lane** (2026-09-05) — dois tenants montados, request Inertia
  emitido, payload lido, e a lane `purchase-pest.yml` o executa em CI. **Morde, provado por
  mutação** no CT 100: removidos os **dois** escopos de business de `getListPurchases`
  (`transactions.business_id` **e** `BS.business_id` do INNER join) ⇒ `1 failed`, exatamente no
  assert do vazamento, com os 3 controles positivos ainda verdes.
  > **Nota que só o bite-test revelou:** derrubar **só** o `where('transactions.business_id')`
  > **não** avermelha — o INNER join em `business_locations` escopado por business já derruba a
  > linha alheia sozinho. São **duas defesas independentes**, e o teste defende o comportamento
  > observável, então só cai quando as duas caem. Isso é defesa em profundidade funcionando; a
  > redação anterior deste UC sugeria uma proteção só.

---

## UC-PURIDX-03 · Lista respeita `permitted_locations` · `must` `[T0]`

- **Persona:** operador com acesso a uma filial só não pode enxergar a compra de outra filial.
- **Aceite:** Dado um usuário cujas `permitted_locations` cobrem apenas a filial A · Quando abre
  `/purchases` · Então a lista traz só compras da filial A, e os filtros condicionais (status,
  fornecedor, situação de pagamento, período) são aplicados **depois** do recorte de filial, nunca
  no lugar dele.
- **Teste:** [`PurchaseIndexTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseIndexTenantContratoTest.php)
  — *"UC-PURIDX-03 (A · controle positivo) com acesso a todas as filiais, as DUAS aparecem"* ·
  *"UC-PURIDX-03 (B · contrato T0) com UMA filial permitida, a compra da OUTRA some"*.
  Os asserts de presença de [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php)
  **permanecem no arquivo**, mas seguem sem lane.
- **Contrato:** RUNBOOK §3 (*"Lista vazia → conferir `getListPurchases` + `permitted_locations`"*) ·
  charter §Goals.
- **Regressão que defende:** `permitted_locations` e os filtros de UI moram na mesma cadeia de
  condicionais. Um refactor que reordene ou unifique essa cadeia pode transformar um recorte de
  **segurança** num filtro de **conveniência** — e a tela continua parecendo certa para quem tem
  acesso a todas as filiais, que é quem costuma revisar.
- **Status: ✅ comportamento · na lane** (2026-09-05) — duas filiais criadas no tenant 98, um
  usuário com `access_all_locations` (controle positivo: as duas compras aparecem) e o mesmo
  usuário com a permissão revogada e só `location.{A}` concedida (contrato: a compra da filial B
  some). **Morde:** removido o bloco `if ($permitted_locations != 'all')` de `indexInertia` ⇒
  `1 failed`, no assert da filial proibida, com os outros 3 verdes.

---

## UC-PURIDX-04 · Ação "Etiquetas" existe no React (paridade Blade) · `must` `[reg]`

- **Persona:** **Larissa @ ROTA LIVRE (biz=4)** — recebe a mercadoria e precisa imprimir a etiqueta
  de código de barras das peças que acabou de lançar.
- **Aceite:** Dado uma compra na lista · Quando o operador abre as ações da linha · Então existe a
  ação **Etiquetas**, incondicional (não depende de permissão), apontando para
  `/labels/show?purchase_id={id}` — e abrindo em nova aba, **não** por navegação Inertia.
- **Teste:** [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) — *"Page respeita
  permissions (view/create/update/delete renderizam condicionalmente)"* (cobre a vizinhança das
  ações inline).
- **Contrato:** RUNBOOK §2 (tabela de paridade Blade × React, com o histórico datado).
- **Regressão que defende:** **esta regressão já aconteceu.** A ação existia no Blade
  (incondicional) e não foi portada na migração React; Larissa reportou por WhatsApp em 2026-06-17 —
  *"cadastrei umas peças e não tem opção de imprimir as etiquetas das compras"*. É o caso mais caro
  desta tela porque o dual-path **esconde a falta**: quem confere pelo Blade vê a ação e conclui que
  está tudo certo.
- **Status: 🔴 sem lane** — e aqui a dívida dói mais, agora em dobro: **nenhum assert cita
  `labels/show`, `purchase_id=` ou `Barcode`**, e o teste citado **não roda em lane alguma**. A
  regressão que o RUNBOOK §2 documenta em prosa — e que Larissa já viveu — **não tem hoje um teste
  que a impeça de voltar, nem um que fosse acordado se tivesse**. Registrado em `[BACKLOG]` abaixo
  com o teste devido nomeado.

---

## UC-PURIDX-05 · Rota Blade abre por `window.open`, nunca `router.visit` · `must`

- **Persona:** qualquer operador — o sintoma é a tela inteira morrer com um erro técnico.
- **Aceite:** Dado uma ação que aponta para rota **Blade** (`/labels/show`, `/purchases/print/…`)
  · Quando o operador aciona a ação · Então a navegação sai por `window.open` / `window.location` e
  **não** por `router.visit`, que exigiria uma resposta Inertia válida.
- **Teste:** [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) — *"Page importa
  AppShellV2 (Persistent Layout — ADR 0094)"* + *"Controller importa Inertia"* (contexto do contrato
  Inertia da tela).
- **Contrato:** RUNBOOK §3 (sintoma *"All Inertia requests must receive a valid Inertia response"*)
  · RUNBOOK §5 (invariante explícito).
- **Regressão que defende:** o erro não é um 500 no servidor — é o SPA quebrando no cliente com uma
  mensagem que **não nomeia a ação culpada**. Custa uma sessão de investigação por ocorrência.
- **Status: 🔴 sem lane** — e a ligação entre os asserts citados e este contrato é **indireta**.
  Este UC está mais perto de `[BACKLOG]` do que de coberto; fica com id porque o contrato existe em
  2 fontes canon (RUNBOOK §3 e §5) e a citação satisfaz o G-2 — mas o G-2 mede **citação**, não
  execução, e o `Status` não mente sobre isso.

---

## UC-PURIDX-06 · A Page não decide tenant — `business_id` vem das props · `must` `[T0]`

- **Persona:** Wagner — o front nunca pode ser a autoridade sobre de quem é o dado.
- **Aceite:** Dado `Index.tsx` · Quando o arquivo é lido · Então **não** existe `business_id`
  hardcoded no componente; o recorte de tenant é resolvido no controller e chega pronto nas props.
- **Teste:** [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) — *"Page NÃO tem
  business_id hardcoded (deve vir das props via Controller — Tier 0)"*.
- **Contrato:** RUNBOOK §5 (proíbe `business_id` hardcoded na Page) · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** um `business_id` fixo numa Page passa despercebido em review (parece
  constante de config) e cria um vazamento que só aparece no segundo tenant.
- **Status: 🔴 sem lane** — este é o único UC da tela cuja natureza é de fato *estrutural*: ausência
  de literal no arquivo **é** o contrato, então aqui o presence-gate **seria** o instrumento certo,
  não um substituto de teste de comportamento. Só que ele também não roda — instrumento certo,
  nunca acionado. A v1 chamava isto de *"exceção honesta"* e tirava o `⚠️`; a exceção valia para o
  eixo do presence-gate, e some no eixo da lane.

---

## `[BACKLOG]` — achados sem teste que os defenda (não viram UC agora)

> Regra dura: UC com id **sem teste que o cite** é órfão, e o `casos-gate` G-2 (required) bloqueia
> o merge de quem for atendê-lo. 6 UC ancorados valem mais que 15 órfãos
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

- `[BACKLOG]` **A regressão da Etiqueta não tem defesa.** Nenhum assert do repo cita `labels/show`,
  `purchase_id=` ou `Barcode` no contexto de `Purchase/Index.tsx`. O RUNBOOK §2 documenta a
  regressão de 2026-06-17 em prosa, e prosa não impede o retorno. O teste devido é de
  **comportamento** (montar a lista, abrir as ações, achar o link) — Pest Browser, casa de teste que
  esta tela não tem (`Purchase` está com `0 de 4` E2E no `screen-coverage`).
- `[BACKLOG]` **Ações ainda só no Blade.** RUNBOOK §2 marca `⚠️` para pagamento, devolução, mudança
  de status e e-mail — existem no dropdown Blade e não foram portadas para o React. É gap
  **conhecido e aceito**, não defeito; vira UC quando a paridade for decidida (é escopo, decisão [W]).
- `[BACKLOG]` **Teto rígido de 200 linhas sem paginação server-side.** O charter §Non-Goals declara
  o teto como *"inferência pendente de Wagner"* — premissa não estabelecida por [W] não vira
  contrato executável ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16: UC não é canal de pedido).
- `[BACKLOG]` **`view_own_purchase`.** `indexInertia` preserva o filtro de ownership (o teste cita),
  mas o comportamento esperado quando o usuário tem `view_own_purchase` **sem** `purchase.view` não
  está estabelecido em documento nenhum. Mesmo achado já registrado no
  [`Compras/Index.casos.md`](../Compras/Index.casos.md) — precisa de [W] antes de virar UC.

---

## ⚠️ Divergências que precisam de [W] (não corrigidas aqui — são INTENÇÃO)

1. **O charter está `status: draft`** e diz que [W] aprova Non-Goals + Anti-hooks antes de virar
   `live`. Este `casos.md` deriva desses Non-Goals: se [W] mudar algum, os UC 02 e 03 mudam junto —
   o trio inteiro fica pendente da mesma aprovação.
2. **A tela não tinha teste de comportamento — e o que tem não roda.** `IndexPageTest` tem 55
   asserts e 0 requests, e **nenhuma lane o executa**. **Parcialmente fechado em 2026-09-05:** a
   lane [`purchase-pest.yml`](../../../../.github/workflows/purchase-pest.yml) existe e os UC 02 e
   03 têm defesa comportamental executada. Os UC **01, 04, 05 e 06** seguem `🔴 sem lane` — para
   eles a frase acima continua inteira, inclusive *"não existe verde da lane a interpretar"*.
3. **Os 6 vermelhos que a ausência de lane escondia** (CT 100, 2026-09-04; reais em `origin/main`).
   São o motivo concreto de os 8 arquivos antigos ficarem fora da allowlist — e cada um é intent
   próprio:
   - **5 cobram artefato que não existe.** `memory/requisitos/Purchase/` contém **só**
     `BRIEFING.md` — sem `RUNBOOK-*.md`, sem `*-visual-comparison.md`.
     ⚠️ **Consequência que passa despercebida:** a âncora deste arquivo aponta para
     `memory/requisitos/`**`Compras`**`/_telas/RUNBOOK-purchase-index.md` — outro módulo — enquanto
     o hook `block-mwart-violation` exige `memory/requisitos/<Mod>/RUNBOOK-<tela>.md` para editar
     `Pages/Purchase/*.tsx`. Os dois caminhos não podem estar certos ao mesmo tempo; qual vale é
     decisão [W].
   - **1 é falso-positivo de presence-gate, e é o mais instrutivo.** `ShowPageTest` exige
     `not->toContain('Barcode')` sobre `Show.tsx`, e o que casa é o **comentário da linha 9** que
     documenta o próprio bug-fix do 500. O `Show.casos.md` marcava esse UC como
     `🧪 estrutural (correto)` — sem `⚠️` — sob o argumento de que "a ausência do literal *é* o
     contrato". Medido: não é. O instrumento não distingue *renderizar* barcode de *falar sobre*
     barcode. Ver §Divergências do `Show.casos.md`, onde as 3 saídas possíveis estão escritas.
4. **`UpdateCrossTenantIdorTest` não roda em lugar nenhum** — `markTestSkipped` fora de sqlite, e
   nenhuma lane sqlite o inclui. Além disso não exercita o controller: replica o padrão
   `Transaction::where('business_id',…)` inline e asserta sobre o Eloquent. O IDOR que ele nomeia
   está fechado no código, mas a prova disso é estrutural.
