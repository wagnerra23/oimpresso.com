---
id: resources-js-pages-purchase-index-casos
casos: Listagem de Compras · /purchases
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o dual-path Blade×React e o escopo por tenant são duráveis — não mudam quando a lista ganhar coluna ou filtro novo.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "nasce com dívida de prova DECLARADA — os testes que citam estes UC são ESTRUTURAIS (grep no fonte), não exercitam request. Ver §Dívida de prova."
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

## ⚠️ Dívida de prova — o que os testes desta tela **não** provam

Este arquivo nasce com um alerta, não com um selo. Medição em `origin/main` (2026-09-04):

| teste | requests HTTP | asserts de presença | o que de fato prova |
|---|---:|---:|---|
| [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) | **0** | **55** | que certas *strings* existem em `Index.tsx` e em `PurchaseController.php` |

`IndexPageTest` lê os arquivos-fonte e casa texto (`file_get_contents` + `toContain`). Ele pega a
**remoção** de um trecho — o que não é nada — mas **não exercita** request, não monta tenant e não
valida resposta. É a classe [LC-11](../../../../memory/LICOES_CODE.md) (presence-gate: gate que mede
PRESENÇA em vez de COMPORTAMENTO), que o ledger alarma com 11 ocorrências.

**Consequência honesta:** nenhum UC abaixo recebe `Status: ✅`. Todos carregam **⚠️ 🧪 estrutural** —
o teste existe, cita o UC e satisfaz o G-2, mas a defesa é de forma, não de comportamento.
Converter isso em prova real é trabalho próprio, fora do escopo deste PR (chip aberto).

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURIDX-01 | SPA recebe React; acesso direto recebe Blade | must | RUNBOOK §1 · charter Mission | `IndexPageTest` | ⚠️ 🧪 estrutural |
| UC-PURIDX-02 | Lista nunca sai do `business_id` da sessão | must `[T0]` | RUNBOOK §5 · charter Non-Goal 4 | `IndexPageTest` | ⚠️ 🧪 estrutural |
| UC-PURIDX-03 | Lista respeita `permitted_locations` | must `[T0]` | RUNBOOK §3 · charter Goals | `IndexPageTest` | ⚠️ 🧪 estrutural |
| UC-PURIDX-04 | Ação "Etiquetas" existe no React (paridade Blade) | must `[reg]` | RUNBOOK §2 (regressão datada) | `IndexPageTest` | ⚠️ 🧪 estrutural |
| UC-PURIDX-05 | Rota Blade abre por `window.open`, nunca `router.visit` | must | RUNBOOK §3 · §5 | `IndexPageTest` | ⚠️ 🧪 estrutural |
| UC-PURIDX-06 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | RUNBOOK §5 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `IndexPageTest` | ⚠️ 🧪 estrutural |

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
- **Status: ⚠️ 🧪 estrutural** — as três asserções são casamento de texto no fonte do controller;
  nenhuma emite request. Provam que o código não foi *apagado*, não que o roteamento funciona.

---

## UC-PURIDX-02 · Lista nunca sai do `business_id` da sessão · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — uma linha de outro tenant na lista de compras é vazamento
  de dado comercial (fornecedor, custo, volume).
- **Aceite:** Dado compras nos negócios 1 e 98 · Quando o usuário do negócio 1 abre `/purchases`
  · Então a query sai de `TransactionUtil::getListPurchases($business_id)` e **nenhuma** compra do
  negócio 98 aparece. E `indexInertia` **não** usa `withoutGlobalScopes` sem o comentário
  `SUPERADMIN` que o canon exige.
- **Teste:** [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) — *"Controller
  indexInertia PRESERVA business_id scope (Tier 0 IRREVOGÁVEL — ADR 0093)"* · *"Controller
  indexInertia NÃO usa withoutGlobalScopes sem comentário SUPERADMIN"*.
- **Contrato:** RUNBOOK §5 · charter §Non-Goals item 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 IRREVOGÁVEL).
- **Regressão que defende:** o model `Transaction` **não tem global scope** — o isolamento aqui é
  manual, escrito em cada query. Foi exatamente essa ausência que produziu o IDOR de escrita
  corrigido em `PurchaseController@update`
  ([`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php)).
  O que falhou uma vez no `update` pode falhar no `index`.
- **Status: ⚠️ 🧪 estrutural** — o assert casa texto no fonte. **Não existe** teste que crie dois
  tenants e prove a ausência da linha alheia nesta tela.

---

## UC-PURIDX-03 · Lista respeita `permitted_locations` · `must` `[T0]`

- **Persona:** operador com acesso a uma filial só não pode enxergar a compra de outra filial.
- **Aceite:** Dado um usuário cujas `permitted_locations` cobrem apenas a filial A · Quando abre
  `/purchases` · Então a lista traz só compras da filial A, e os filtros condicionais (status,
  fornecedor, situação de pagamento, período) são aplicados **depois** do recorte de filial, nunca
  no lugar dele.
- **Teste:** [`IndexPageTest`](../../../../tests/Feature/Purchase/IndexPageTest.php) — *"Controller
  indexInertia PRESERVA permitted_locations filter"* · *"PRESERVA filtros condicionais"*.
- **Contrato:** RUNBOOK §3 (*"Lista vazia → conferir `getListPurchases` + `permitted_locations`"*) ·
  charter §Goals.
- **Regressão que defende:** `permitted_locations` e os filtros de UI moram na mesma cadeia de
  condicionais. Um refactor que reordene ou unifique essa cadeia pode transformar um recorte de
  **segurança** num filtro de **conveniência** — e a tela continua parecendo certa para quem tem
  acesso a todas as filiais, que é quem costuma revisar.
- **Status: ⚠️ 🧪 estrutural** — casamento de texto no fonte; sem request, sem usuário com filial
  restrita.

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
- **Status: ⚠️ 🧪 estrutural** — e aqui a dívida dói mais: **nenhum assert cita `labels/show`,
  `purchase_id=` ou `Barcode`**. A regressão que o RUNBOOK §2 documenta em prosa **não tem hoje um
  teste que a impeça de voltar**. Registrado em `[BACKLOG]` abaixo com o teste devido nomeado.

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
- **Status: ⚠️ 🧪 estrutural** — e a ligação entre os asserts citados e este contrato é **indireta**.
  Este UC está mais perto de `[BACKLOG]` do que de coberto; fica com id porque o contrato existe em
  2 fontes canon (RUNBOOK §3 e §5) e a citação satisfaz o G-2 — mas o `Status` não mente sobre isso.

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
- **Status: ⚠️ 🧪 estrutural** — **exceção honesta:** este é o único UC da tela cuja natureza é de
  fato *estrutural*. Ausência de literal no arquivo **é** o contrato; aqui o presence-gate é o
  instrumento certo, não um substituto de teste de comportamento.

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
2. **A tela não tem teste de comportamento.** Não é achado de estilo: `IndexPageTest` tem 55 asserts
   e 0 requests. Enquanto isso durar, o `Status` de todos os UC (menos o 06) segue
   `⚠️ estrutural`, e o verde da lane **não** significa que a listagem isola tenant.
