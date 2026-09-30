---
casos: Patrimonio/Bens — listagem de bens do patrimônio (MWART, ADR 0104)
irmaos: Bens.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-bens.md (F1 PLAN)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Patrimonio/Bens

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2 (ADR 0264): UC declarado sem teste citando o id = órfão.
> Teste que os defende: [`Modules/AssetManagement/Tests/Feature/BensContratoTest.php`](../../../../Modules/AssetManagement/Tests/Feature/BensContratoTest.php).

> **Por que só três UC com id.** A onda 1 entrega a listagem migrada — e cada UC aqui tem
> teste que roda. Declarar de uma vez os cenários do protótipo (`patrimonio-page.jsx`)
> criaria órfãos e quebraria o G-2, porque o teste que os defende ainda não existe: os
> recortes de garantia/manutenção pedem predicado SQL novo, o total somado esbarra na REGRA
> MESTRE de valor, e as ações em lote não têm endpoint. Eles ficam no `[BACKLOG]` abaixo —
> prosa honesta, sem id — e **viram UC na onda que traz o teste**.

> **Onde os testes rodaram.** Duas execuções independentes, e a segunda é a que se
> re-verifica sozinha:
>
> · **CI — lane `assetmanagement-pest.yml` (MySQL), run 34597014205, 2026-09-11.**
>   Suíte do módulo: **105 passed · 348 assertions · 0 skipped**. Este arquivo:
>   **4 passed · 23 assertions** — o mesmo número da execução manual abaixo, reproduzido
>   por outra máquina, o que é o ponto de ter as duas.
>
> · **CT 100** (`oimpresso-staging`, MySQL real), 2026-09-08 — nunca local
>   ([`proibicoes.md §Ambiente`](../../../../memory/proibicoes.md)). Suíte do arquivo:
>   **4 passed · 23 assertions**; módulo inteiro: **76 passed · 254 assertions**, 0 falhas.
>
> **Por que a linha do CI foi acrescentada.** Até 2026-09-11 estes três UC tinham por único
> lastro o run MANUAL de CT 100 — e a lane que os cobria no CI, `Pest AssetManagement`
> (`modules-pest.yml`), ficava **verde pulando exatamente eles**: ela roda
> `DB_CONNECTION=sqlite` sem `migrate`, e os quatro casos saíam como `WARN … SQLite-incompatível`
> num rodapé `40 skipped, 58 passed`. Skip sai exit 0, então o verde não provava nada
> (LC-13). Recibo de execução à mão não se re-verifica sozinho; agora há quem o re-verifique
> a cada PR que toque o módulo ou a tela.

---

## UC-BENS-01 · A listagem mostra o patrimônio da MINHA empresa, e só dela

- **Persona:** quem administra o patrimônio — precisa confiar que a lista é da casa dele.
- **Aceite:** Dado dois businesses com bens cadastrados · Quando o usuário do business A abre
  `/asset/assets` com `asset.view` · Então a listagem traz o bem de A e **nenhum** bem de B.
- **Teste:** `BensContratoTest.php` — **dois** `it()` citando `UC-BENS-01`:
  1. o cenário direto (usuário de A não vê o bem de B);
  2. **o espelho** — usuário de B, na mesma tela e com a mesma busca, **vê** o bem de B e não
     o de A. O espelho existe porque, sozinho, o `not->toContain` do primeiro também passaria
     se o bem de B simplesmente não existisse. É o substituto do bite-test por mutação:
     desligar o `where('assets.business_id')` provaria o mesmo, mas seria remover uma proteção
     Tier 0 pra ver o alarme tocar.
- **Regressão que defende:** vazamento cross-tenant na listagem do patrimônio
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
- **Nota de método:** a busca do cenário (`q=BENS-CTR`) casa o código dos **dois** fixtures de
  propósito — eles disputam a mesma página, então o isolamento é a única explicação possível
  para o resultado. Sem esse recorte o teste era não-determinista: o CT 100 é base persistente
  e já tinha 82 assets no tenant 98, então com `paginate(25)` o fixture caía fora da primeira
  página e o teste falhava por paginação, não por regra. Medido, não suposto.
- **Status: 🧪** — os dois cenários passam no CI (lane `assetmanagement-pest`, run 34597014205, 2026-09-11) e no CT 100 (run 2026-09-08, seed 1788886934).

---

## UC-BENS-02 · A tela de Bens é Inertia, no endereço que o [W] decidiu

- **Persona:** o próprio time — a migração precisa ser verificável, não afirmada.
- **Aceite:** Dado um usuário com `asset.view` e o módulo assinado · Quando abre
  `/asset/assets` · Então recebe **200** e a página Inertia é o componente
  **`Patrimonio/Bens`**, com as props `filtros`, `opcoes` e `permissoes`.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-02`, com `assertInertia`.
- **Regressão que defende:** a URL **não muda** na migração (`assets.index`, `GET
  /asset/assets`), então status 200 sozinho não distingue "virou Inertia" de "continua
  Blade". O que distingue é o componente — e o assert do Inertia **verifica que o arquivo do
  componente existe**: ele falhou de verdade na primeira execução, quando o `.tsx` ainda não
  estava na árvore do CT 100 (`Inertia page component file [Patrimonio/Bens] does not exist`).
  Defende também o endereço da [ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md):
  mover a tela pra outra pasta quebra este teste.
- **Status: 🧪** — passa no CI (lane `assetmanagement-pest`, run 34597014205, 2026-09-11) e no CT 100 (run 2026-09-08).

---

## UC-BENS-03 · A busca recorta no servidor, não na página que já chegou

- **Persona:** quem procura um bem específico numa lista que não cabe numa tela.
- **Aceite:** Dado dois bens no mesmo business · Quando o usuário busca por um termo que casa
  só um deles (`?q=Fiorino`) · Então a página devolvida traz **o que casa** e **não traz** o
  que não casa.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-03`.
- **Regressão que defende:** busca implementada no cliente sobre a página corrente — que
  parece funcionar com poucos registros e mente com muitos, porque só enxerga as 25 linhas
  que já chegaram. O `toContain` vem junto do `not->toContain` pelo mesmo motivo: uma busca
  que devolvesse ZERO linha satisfaria a negativa sozinha e passaria pelo motivo errado.
- **Status: 🧪** — passa no CI (lane `assetmanagement-pest`, run 34597014205, 2026-09-11) e no CT 100 (run 2026-09-08).

---

## UC-BENS-04 · Nenhuma ação da lista leva a uma página em branco

- **Persona:** quem opera o patrimônio — clica num ícone esperando um formulário.
- **Aceite:** Dado a lista com um bem e **todas** as permissões ligadas (criar, editar, excluir,
  manutenção) · Quando a tela renderiza — cheia ou vazia · Então **nenhum** `href` aponta pra
  `create`/`edit` de bem, alocação ou manutenção; e o **excluir** (que funciona, via
  `router.delete`) continua na linha.
- **Teste:** `tests/js/patrimonio-sem-link-para-modal.test.tsx` — 2 `it()` citando `UC-BENS-04`.
- **Regressão que defende:** afordância falsa. MEDIDO em prod (biz=1, 2026-09-23): os endpoints `create`/`edit` do módulo só respondem sob `request()->ajax()` — numa navegação direta devolveram **200 com 0 bytes** (fragmento de modal jQuery, sem `@extends`). Até esta data a tela tinha 5 links
  pra lá: "Novo ativo", o CTA do vazio, alocar, manutenção e editar. O controle positivo (a linha
  renderizou + o excluir está lá) impede o caso de passar porque a tabela nem apareceu.
  **Bite-test (provado 2026-09-23):** com o `Bens.tsx` anterior, os 2 casos caem listando os hrefs.
- **Status: 🧪** — verde no vitest local pós-conserto; lane de CI a confirmar no PR.

---

## UC-BENS-05 · Cadastrar bem pelo drawer grava valor e quantidade exatamente como digitados

- **Persona:** quem cadastra o patrimônio — digita "1.234,56" e espera ver gravado exatamente
  esse valor, não cem vezes mais nem cem vezes menos.
- **Aceite:** Dado o drawer "Adicionar recurso" com nome, categoria, local, data, valor
  `1.234,56` e quantidade `2` · Quando o usuário clica "Cadastrar bem" · Então o POST leva
  `unit_price=1234,56`, `quantity=2` e a data no formato do negócio, e o banco grava
  `unit_price = 1234.5600`, `quantity = 2.0000`, no business da sessão, com código gerado pelo
  servidor e a garantia (início + N meses) quando informada. Form incompleto não posta.
  Empresa **sem nenhuma categoria de ativo** (caso real de biz=1, medido em 2026-09-23): o drawer
  diz "Nenhuma categoria de ativo cadastrada" e aponta `/taxonomies?type=asset`, em vez de
  travar o cadastro sem explicação.
- **Teste (dupla prova — REGRA MESTRE de valor/estoque):**
  - caminho 1 — `tests/js/patrimonio-cadastro-bem.test.tsx`: as strings exatas do payload,
    e o drawer real postando o que o serializador monta;
  - caminho 2 — `BensContratoTest.php`, `it()` citando `UC-BENS-05`: posta as MESMAS strings
    no `store()` real (tenant 98) e lê o banco, incluindo o caso `1234567,8` (milhar).
- **Regressão que defende:** separador de milhar lido como decimal (ou o contrário) — o
  incidente ROTA LIVRE de 2026-06-05 (valor ×100k por float cru lido pelo `num_uf`). E data
  ISO crua no `uf_date`, que lança e vira "algo deu errado".
- **Status: 🧪** — caminho 1 verde no vitest local (8/8); caminho 2 roda na lane
  `assetmanagement-pest` no CI.

---

## UC-BENS-06 · "Garantia crítica" recorta o conjunto no servidor, e a pílula conta o conjunto

- **Persona:** quem administra o patrimônio e quer ver, de uma vez, o que está sem cobertura
  ou prestes a ficar — antes de o conserto sair integral do caixa.
- **Aceite:** Dado, no mesmo business, um bem com garantia **vencida**, um com garantia
  **vencendo em 10 dias**, um com garantia **vigente por um ano** e um **sem registro** de
  garantia · Quando o usuário abre `/asset/assets?recorte=garantia` · Então a lista traz os dois
  primeiros, **não** traz o vigente e **não** traz o sem registro (ele é "sem garantia", não
  "vencida"); e a contagem `recortes_contagem.garantia` vem do servidor, sobre o conjunto.
  **Vale a garantia MAIS RECENTE** do bem, a que termina por último ([W] 2026-09-30): um bem com
  a garantia velha vencida e a renovação vigente **não** entra.
  Recorte fora da whitelist (`?recorte=qualquer`) é tratado como "todos".
- **Tier 0 (ADR 0093):** um bem de **outro** business com garantia vencida não entra na lista
  nem na contagem — `asset_warranties` não tem `business_id`, o recorte filtra por join.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-06` (recorte + controle negativo
  + tenant cruzado + contagem).
- **Regressão que defende:** criticidade derivada no cliente de `dias_restantes` (proibida pelo
  charter) e contagem feita sobre as 25 linhas da página.
- **Status: 🧪** — passa no CT 100 (MySQL real, 2026-09-29, worktree isolado no commit do PR):
  este `it()` **1 passed · 20 assertions**; suíte do módulo **114 passed · 409 assertions**.
  **Bite-test por mutação** (mesmo dia, arquivo restaurado por hash): sem aplicar o recorte →
  cai; sem o filtro de business no join **e** na consulta externa da contagem → cai com
  `3 ≠ 2` (o bem do adversário somou). Tirar só o filtro do join **sobrevive** — é mutante
  equivalente: a consulta externa já restringe ao business, e o do join fica como segunda
  defesa. Lane `assetmanagement-pest` a confirmar no PR.

---

## UC-BENS-07 · "Em manutenção" recorta o conjunto no servidor pela manutenção em aberto

- **Persona:** quem administra o patrimônio e precisa ver o que está fora de operação agora.
- **Aceite:** Dado, no mesmo business, um bem com manutenção `new`, um com `in_progress`, um só
  com manutenção **concluída**, um com status **vazio** e um **sem** manutenção · Quando o usuário
  abre `/asset/assets?recorte=manutencao` · Então a lista traz os dois primeiros e **não** traz os
  outros três; e `recortes_contagem.manutencao` soma só os dois, sobre o conjunto.
- **Regra:** "em aberto" é a lista fechada `new`/`in_progress` de
  `AssetMaintenanceService::contarAbertas()` — a mesma da pílula da aba Manutenções e do selo
  "N em manutenção" da linha. Status desconhecido fica de fora (erra pro lado visível).
- **Tier 0 (ADR 0093):** nem o bem de outro business nem uma manutenção **registrada no business
  de outro** entram — `asset_maintenances` tem `business_id` e o recorte o filtra.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-07` (recorte + controle negativo +
  dois vetores de tenant + contagem por delta).
- **Status: 🧪** — ver o recibo do PR.

## UC-BENS-08 · Editar o próprio bem não alcança a garantia de outra empresa

- **Persona:** quem edita um bem do próprio business (hoje pela rota `PUT /asset/assets/{id}`).
- **Aceite:** Dado um bem do business 98 com uma garantia e um bem do business 99 com outra ·
  Quando o usuário do 98 salva o próprio bem mandando em `edit_warranty` o id da sua garantia
  **e** o id da garantia do 99 · Então a garantia dele muda, e a do 99 **não muda nem é apagada**.
- **Tier 0 (ADR 0093):** `asset_warranties` não tem `business_id`, e o id vem do request. A
  garantia é amarrada ao bem já escopado (`asset_id`) antes de qualquer escrita; id que não é
  deste bem é ignorado.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-08` (controle positivo: a garantia
  do próprio bem muda no mesmo request, prova de que o caminho de `edit_warranty` executou).
- **Status: 🧪** — ver o recibo do PR.

## UC-BENS-09 · Editar o bem pelo drawer grava valor e quantidade como digitados e não apaga garantia

- **Persona:** quem administra o patrimônio e precisa corrigir um bem já cadastrado (valor,
  quantidade, local, garantia).
- **Aceite:** Dado um bem do próprio business com **duas** garantias · Quando o usuário abre
  `/asset/assets/{id}/edit`, muda (ou não) valor e quantidade e salva · Então o banco grava o valor e a
  quantidade digitados em pt-BR sem ler milhar como decimal, o código do bem não muda, "sem
  depreciação" continua NULL, e **as duas garantias seguem gravadas** com os valores que tinham.
- **Tier 0 (ADR 0093):** `/asset/assets/{id}/edit` de um bem de outro business devolve **404**.
- **REGRA MESTRE (valor/estoque):** dois caminhos — `tests/js/patrimonio-cadastro-bem.test.tsx`
  fixa as strings do envio; `BensContratoTest.php` posta as mesmas strings e lê o banco.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-09` (update + edit/create/404) e o
  vitest citando `UC-BENS-09`.
- **Status: 🧪** — ver o recibo do PR.

---

## UC-BENS-10 · O drawer do bem mostra cada devolução da alocação, e só as desta empresa

- **Persona:** quem administra o patrimônio e precisa saber quem devolveu o quê, quando e por
  quê — numa alocação que voltou em partes.
- **Fonte:** decisão [W] 2026-09-30 (`prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/_saida-16b.md`):
  o histórico de devoluções vai para o drawer do **bem**, aba Alocações, como no protótipo
  (`patrimonio-page.jsx` `BemDrawer`, :714-735). O protótipo modela 1 revogação por alocação; o
  nosso modelo é **1 : N** (`_saida-16.md`), e o drawer lista as N.
- **Aceite:** Dado um bem do business 98 com uma alocação de 2 un. e **duas** devoluções parciais
  de 0,5 · Quando o usuário do 98 clica na linha (`?bem=ID`) e abre a aba Alocações · Então vê a
  alocação com as duas devoluções (código, quantidade, data, autor, motivo) e "1 de 2 un." devolvidas.
  Uma devolução gravada no **business 99** apontando para essa alocação **não** aparece nem soma;
  uma alocação do 99 sobre o mesmo `asset_id` **não** aparece; `?bem=` com id de bem do 99 devolve
  "não encontrado".
- **Somente leitura:** o drawer não tem excluir devolução, revogar, alocar nem editar.
- **Teste (dois caminhos):**
  - dado — `BensContratoTest.php`, `it()` citando `UC-BENS-10` (tenant 98 × 99, ADR 0358);
  - tela — `tests/js/patrimonio-detalhe-bem.test.tsx`, 3 `it()` citando `UC-BENS-10` (lista N,
    só leitura, não encontrado). Bite-test por mutação (render 1 : 1 com `slice(0, 1)`): 2 casos caem.
- **Status: 🧪** — vitest verde local (3/3); Pest roda na lane `assetmanagement-pest` — ver o recibo do PR.

---

## UC-BENS-12 · "Enviar pra manutenção" pela linha do bem abre o drawer de manutenção

- **Persona:** quem vê um bem com defeito na lista e quer registrar o envio sem procurar a
  tela de Manutenções.
- **Aceite:** Dado um bem na lista e um usuário com permissão de manutenção
  (`asset.view_all_maintenance` ou `asset.view_own_maintenance` — a mesma que o `create()` de
  manutenção exige) · Quando ele clica em "Enviar pra manutenção" na linha · Então a tela vai
  para `/asset/asset-maintenance/create?asset_id={id do bem}`, que abre o drawer com o bem já
  escolhido (thread 19, UC-MANU-06). Sem a permissão, o botão não aparece. É botão com
  `router.get`, não `<a href>` (UC-BENS-04 segue valendo).
- **Teste:** `tests/js/patrimonio-bens-manutencao.test.tsx` — dois `it()` citando `UC-BENS-12`,
  com o "Editar" da mesma linha como controle de que a linha renderizou.
- **Fora:** o botão no rodapé do drawer de detalhe do bem (protótipo `patrimonio-page.jsx:656`)
  e o envio em lote (`:395`). Não escreve valor nem quantidade.
- **Status: ⬜** — a lane de CI do PR é o primeiro run.

## UC-BENS-11 · Excluir o bem leva as garantias dele junto

- **Persona:** quem exclui um bem cadastrado por engano e não espera deixar resto no banco.
- **Aceite:** Dado um bem com duas garantias e um bem de outro business com uma garantia ·
  Quando o usuário exclui o primeiro pela lista · Então o bem **e as duas garantias** saem, e a
  garantia do outro business fica intacta. Para o que já ficou para trás, o comando
  `assetmanagement:garantias-orfas` lista as garantias sem bem e só apaga com `--apply`; nunca
  toca garantia de bem que existe.
- **Por quê:** `asset_warranties` pertence inteira ao bem e não tem `business_id`. Até 2026-09-30
  o `AssetService::remover()` apagava o bem e a mídia e deixava a garantia órfã — medido em
  produção: 1 garantia de 2026-09-23 cujo bem foi excluído 22s depois de criado.
- **Teste:** `Modules/AssetManagement/Tests/Feature/GarantiasOrfasContratoTest.php` — dois `it()`
  citando `UC-BENS-11` (a exclusão pela rota, e o comando em dry-run e com `--apply`).
- **Status: 🧪** — ver o recibo do PR.

---

## Dívida declarada — `Alocado` não é número auditado

⚠️ Não é UC porque **não é comportamento que esta onda defende** — é defeito herdado que ela
documenta em vez de esconder. As agregações `allocated_qty` (join `AT`) e `revoked_qty`
(subconsulta `AR`) do `AssetController::index()` **não filtram por `business_id`**. Gêmeo
catalogado em `_saida-01.md §9(a)`, com thread dona.

**Medido no CT 100 em 2026-09-08** (leitura pura, as duas formas da agregação lado a lado):
**20 de 128** assets divergem — o padrão é `revogado: 4 → 0`, ou seja, a coluna "Alocado"
mostra 4 unidades **a menos** do que deveria, por contar revogação de outro tenant.
A pré-condição do vazamento (`asset_id` com transação de outro business) ocorre em **20**
assets nessa base.

**Ressalva de honestidade:** essa base é o staging do CT 100, e as linhas divergentes vêm de
fixtures acumulados dos testes cross-tenant (`AST-CRS-TNT01`), não de dado de cliente. A
medição prova que **a query vaza quando a pré-condição existe**; ela não afirma nada sobre
produção, que não foi medida.

Corrigir aqui esbarraria na REGRA MESTRE (mexer em quantidade exige prova por dois caminhos +
antes→depois + [W]) e em 1 PR = 1 intent. O que esta onda fez foi dar **um dono só** à
expressão (`AssetController::baseAssetsQuery`), lida pelos dois ramos.

---

## [BACKLOG] — vira UC na onda que trouxer o teste

- [BACKLOG] O rodapé soma o valor total do recorte, com a prova dupla que a REGRA MESTRE exige.
- [BACKLOG] Seleção em lote exporta a seleção e manda os selecionados pra manutenção.
- [BACKLOG] O usuário escolhe as colunas visíveis e a densidade, e a escolha sobrevive ao reload.
- [BACKLOG] Alocar a partir da linha, em drawer — escrita de QUANTIDADE: REGRA MESTRE Tier 0.
  (Mandar pra manutenção a partir da linha saiu daqui em 2026-09-30: virou UC-BENS-12.)
- [BACKLOG] "Enviar pra manutenção" no rodapé do drawer de detalhe do bem, como no protótipo
  (`patrimonio-page.jsx:656`) — o destino já existe (UC-BENS-12).
- [BACKLOG] `permitted_locations()` restringe a listagem, e nenhum parâmetro de query a afrouxa.
  (Hoje o código faz isso — aplica a restrição **antes** dos filtros do usuário —, mas nenhum
  teste defende; virou visível quando o fixture sem `access_all_locations` zerou a lista.)
