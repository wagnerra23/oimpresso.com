---
id: resources-js-pages-purchase-edit-casos
casos: Editar Compra · /purchases/{id}/edit
irmaos: Edit.charter.md (lei) · Edit.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: os três gates de escrita (tenant, janela temporal, devolução existente) são duráveis — não mudam quando o formulário ganhar campo novo.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "🔴 NENHUM teste de tests/Feature/Purchase/ roda em lane alguma — 8 arquivos órfãos de CI; o do IDOR soma quarentena por cima. Ver §Dívida de prova."
---

# Casos de Uso & Aceite — Editar Compra (`/purchases/{id}/edit`)

> **Âncora:** os UC derivam do
> [`RUNBOOK-purchase-edit.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-edit.md)
> (§3 multi-tenant Tier 0 · §6 validação · §7 POST) cruzado com o [`Edit.charter.md`](Edit.charter.md)
> (§Regras invariantes R-PUR-001 · R-PUR-005 · R-PUR-006 · R-PUR-007) — **nunca do `Edit.tsx`**
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> Esta é a tela de **escrita sobre dado que já existe**: ela não cria, ela altera valor gravado. Os
> UC abaixo são quase todos gates de recusa — o que a tela **não** pode deixar acontecer.

---

## 🔴 Dívida de prova — **nenhum** teste desta tela roda em lane alguma

> ⚠️ **Correção da v1 deste arquivo (2026-09-05), registrada e não apagada.** A v1 marcava os
> `Wave2*` como *"✅ sim — executa"* e tratava a quarentena do `UpdateCrossTenantIdorTest` como o
> problema. **Estava errado nos dois pontos:** eu medi a perna do **skip** e **não medi a perna da
> lane**. Quem pegou foi o gate `uc-lane-coverage` (advisory) do CI, reprovando os 7 UC desta tela
> com *"existe e NENHUMA lane roda"*. O gate estava certo.

Medição em `origin/main` (2026-09-05), **três pernas**, todas contadas:

| perna | resultado |
|---|---|
| workflows que citam `tests/Feature/Purchase` (`git grep -c -- .github/`) | **0** |
| linhas `Purchase` em `.github/ci-sqlite-pest.list` (542 linhas) | **0** |
| arquivos de teste em `tests/Feature/Purchase/` | **8** |

| teste | requests | presença | executa? |
|---|---:|---:|---|
| [`Wave2EditInertiaTest`](../../../../tests/Feature/Purchase/Wave2EditInertiaTest.php) | 0 | 15 | 🔴 não — sem lane |
| [`Wave2EditBaselineTest`](../../../../tests/Feature/Purchase/Wave2EditBaselineTest.php) | 0 | 5 | 🔴 não — sem lane |
| [`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php) | 0 | 2 | 🔴 não — **sem lane E em quarentena** (dupla) |

`UpdateCrossTenantIdorTest` documenta, no próprio cabeçalho, um **IDOR de escrita cross-tenant em
dinheiro que existiu de verdade**: `PurchaseController@update` fazia a busca sem `where business_id`
e — como o model `Transaction` **não tem global scope** — um usuário do negócio A alterava o
lançamento financeiro do negócio B. O fix foi escopar a busca; o teste prova o fix em três cenários
(cross-tenant não resolve · o dado da vítima permanece intacto · same-tenant continua funcionando).

**São duas camadas de falha, e a de cima é a que ninguém tinha nomeado:**

1. **Sem lane** — nenhum dos 8 arquivos de `tests/Feature/Purchase/` é invocado por lane alguma.
   Nas palavras do próprio gate: *"teste fora de toda lane é 'verde impossível': existe, pode estar
   vermelho há meses, e nenhum PR o acorda."*
2. **Quarentena** — sobre isso, o `UpdateCrossTenantIdorTest` ainda chama `markTestSkipped` fora do
   sqlite (`beforeEach` **global**, linha 27), e a suíte real roda em MySQL. Mesmo ganhando lane,
   pularia.

Skip sai **exit 0** ([LC-13](../../../../memory/LICOES_CODE.md): `0 failed` nunca prova execução).
**A regressão mais cara já vivida por esta controller está hoje sem defesa ativa** — e a razão
principal não é a quarentena, é a ausência de lane.

**Por que o conserto não está neste PR:** o gate diz, com todas as letras, *"conserto NÃO é mexer na
allowlist por conta própria — por que ela existe (custo de CI? teste instável escondido?) é decisão
[W]"*. Concordo: pôr 8 arquivos numa lane muda custo de CI e pode acordar vermelhos antigos.

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PUREDT-01 | SPA recebe React; Blade legacy preservado | must | RUNBOOK §11 · charter §Reuso | `Wave2EditInertiaTest` · `Wave2EditBaselineTest` | ⚠️ 🧪 estrutural |
| UC-PUREDT-02 | `update()` nunca alcança transação de outro tenant | must `[T0]` `[V0]` | RUNBOOK §3 · charter R-PUR-001 | `UpdateCrossTenantIdorTest` | 🔴 quarentena |
| UC-PUREDT-03 | Edição fora da janela `transaction_edit_days` é recusada | must | RUNBOOK §3 · charter R-PUR-005 | `Wave2EditBaselineTest` | ⚠️ 🧪 estrutural |
| UC-PUREDT-04 | Compra com devolução já criada não pode ser editada | must `[V0]` | RUNBOOK §3 · charter R-PUR-006 | `Wave2EditBaselineTest` | ⚠️ 🧪 estrutural |
| UC-PUREDT-05 | Sem `purchase.update` não se abre nem se salva | must | RUNBOOK §3 · charter R-PUR-007 | `Wave2EditBaselineTest` | ⚠️ 🧪 estrutural |
| UC-PUREDT-06 | O formulário chega pré-populado com a compra, tipada | should | RUNBOOK §4 · §9 | `Wave2EditInertiaTest` | ⚠️ 🧪 estrutural |
| UC-PUREDT-07 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | RUNBOOK §3 | `Wave2EditInertiaTest` | 🧪 estrutural (correto) |

---

## UC-PUREDT-01 · SPA recebe React; Blade legacy preservado · `must`

- **Persona:** Maiara/Felipe corrigem a compra pelo Cockpit; o Blade atende acesso direto.
- **Aceite:** Dado `PurchaseController@edit` · Quando o request traz o header Inertia ou `?v=2`
  · Então renderiza a Page `Purchase/Edit`. E, como **controle negativo**, o GET normal continua
  devolvendo a view Blade legacy.
- **Teste:** [`Wave2EditInertiaTest`](../../../../tests/Feature/Purchase/Wave2EditInertiaTest.php) —
  *"Controller edit() tem dual path (Inertia atrás de ?v=2 OU header)"*;
  [`Wave2EditBaselineTest`](../../../../tests/Feature/Purchase/Wave2EditBaselineTest.php) —
  *"Blade legacy edit.blade.php existe"* · *"Controller edit() PRESERVA return view(purchase.edit)"*.
- **Contrato:** RUNBOOK §11 (F5 CUTOVER = dual path) · [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md).
- **Regressão que defende:** o `update()` é compartilhado. Limpar o Blade "porque migrou" quebra
  quem entra pela URL direta.
- **Status: 🔴 sem lane** — casamento de texto no fonte; nenhum request emitido.

---

## UC-PUREDT-02 · `update()` nunca alcança transação de outro tenant · `must` `[T0]` `[V0]`

- **Persona:** Wagner / WR2 SC — este é o caso que **já aconteceu**. Não é hipótese.
- **Aceite:** Dado uma transação do negócio 98 com `final_total` gravado · Quando o usuário do
  negócio 1 submete `POST /purchases/{id}` para aquele id · Então a busca escopada por `business_id`
  **não resolve** (`ModelNotFoundException` → 404) **e** o `final_total` da vítima permanece
  **exatamente** o que era. E, como **controle positivo**, o mesmo usuário atualiza normalmente a
  transação do próprio negócio — sem esse par, um `abort` incondicional passaria no teste.
- **Teste:** [`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php)
  — *"cross-tenant: usuário biz=1 NÃO resolve Transaction de biz=99"* · *"o lançamento financeiro da
  vítima permanece INTACTO"* · *"same-tenant: usuário biz=1 resolve E atualiza o PRÓPRIO Transaction"*
  · *"Controller@update scopa a busca por business_id (sem findOrFail nu)"*.
- **Contrato:** RUNBOOK §3 (*"`business_id` validado em sessão"*) · charter R-PUR-001 (Tier 0
  IRREVOGÁVEL) · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  [proibicoes §REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE](../../../../memory/proibicoes.md).
- **Regressão que defende:** o defeito original — busca sem `where business_id` num model **sem
  global scope** — é invisível em review porque a linha *parece* idiomática (`findOrFail($id)`). O
  teste guarda inclusive a forma: exige o `where` e proíbe o `findOrFail` nu voltar.
- **Status: 🔴 sem lane + quarentena** — **este é o UC mais grave do trio inteiro.** `[T0]` **e** `[V0]`,
  regressão **já materializada**, teste escrito e correto, e **não executa**. Nomeado com destaque
  no `[BACKLOG]` e no chip de saída.

---

## UC-PUREDT-03 · Edição fora da janela `transaction_edit_days` é recusada · `must`

- **Persona:** Maiara corrige uma compra recente; compra antiga é fato consumado (estoque já girou,
  financeiro já fechou).
- **Aceite:** Dado uma compra mais antiga que `transaction_edit_days` do negócio · Quando o operador
  tenta abrir a edição · Então o gate `canBeEdited` recusa — a tela não abre e o `update` não aceita.
- **Teste:** [`Wave2EditBaselineTest`](../../../../tests/Feature/Purchase/Wave2EditBaselineTest.php)
  — *"Controller edit() PRESERVA permission purchase.update + canBeEdited time-gate"*.
- **Contrato:** RUNBOOK §3 · charter R-PUR-005.
- **Regressão que defende:** editar compra antiga reescreve custo de item cujo estoque **já saiu** —
  a margem de vendas passadas muda retroativamente, e ninguém liga uma coisa à outra.
- **Status: 🔴 sem lane** — o assert prova que a chamada ao gate **existe no fonte**; não monta
  compra fora da janela nem observa a recusa.

---

## UC-PUREDT-04 · Compra com devolução já criada não pode ser editada · `must` `[V0]`

- **Persona:** Maiara — a devolução referencia as linhas da compra; mexer na base quebra o vínculo.
- **Aceite:** Dado uma compra que já tem devolução · Quando o operador tenta editar · Então o gate
  `isReturnExist` bloqueia, e nem a tela abre nem o `update` grava.
- **Teste:** [`Wave2EditBaselineTest`](../../../../tests/Feature/Purchase/Wave2EditBaselineTest.php)
  — *"Controller edit() PRESERVA isReturnExist bloqueio"*.
- **Contrato:** RUNBOOK §3 · charter R-PUR-006.
- **Regressão que defende:** alterar quantidade de uma compra devolvida produz **estoque negativo ou
  fantasma** sem erro nenhum na hora — o rombo aparece no inventário. Território da
  [Regra Mestre de valor e estoque](../../../../memory/proibicoes.md).
- **Status: 🔴 sem lane** — casamento de texto; a devolução não é criada nem o bloqueio observado.

---

## UC-PUREDT-05 · Sem `purchase.update` não se abre nem se salva · `must`

- **Persona:** operador de conferência não pode alterar compra.
- **Aceite:** Dado um usuário sem `purchase.update` · Quando pede `GET /purchases/{id}/edit` ou
  submete o `update` · Então é recusado nos **dois** pontos — a ausência do botão na tela é
  conveniência, não segurança.
- **Teste:** [`Wave2EditBaselineTest`](../../../../tests/Feature/Purchase/Wave2EditBaselineTest.php)
  — *"Controller edit() PRESERVA permission purchase.update + canBeEdited time-gate"*.
- **Contrato:** RUNBOOK §3 · charter R-PUR-007.
- **Regressão que defende:** o `PurchaseController@show` **já teve a linha de permissão comentada**
  (o `ShowPageTest` guarda isso com *"permission re-adicionada (não comentada)"*). O mesmo `//` numa
  linha de autorização é a mudança mais barata de escrever e a mais cara de descobrir.
- **Status: 🔴 sem lane** — prova que a chamada existe no fonte; nenhum usuário sem permissão é
  montado. E o `update` **não** tem assert próprio de permissão — só o `edit`. Registrado no `[BACKLOG]`.

---

## UC-PUREDT-06 · O formulário chega pré-populado com a compra, tipada · `should`

- **Persona:** Maiara abre a edição e vê os dados atuais, não um formulário vazio.
- **Aceite:** Dado uma compra existente · Quando a Page monta · Então o form vem pré-populado a
  partir da prop `purchase`, e as `purchase_lines` são serializadas com tipos explícitos — sem
  vazamento de `any` na fronteira controller→Page.
- **Teste:** [`Wave2EditInertiaTest`](../../../../tests/Feature/Purchase/Wave2EditInertiaTest.php) —
  *"Page pré-popula useForm com purchase prop"* · *"Page declara interface PurchaseEditPageProps +
  PurchaseEditPayload"* · *"Controller editInertia serializa purchase_lines com tipos seguros (sem
  any leak)"* · *"Page submete POST /purchases/{id} (method spoofing PUT)"*.
- **Contrato:** RUNBOOK §4 (tabela de props) · §9 (*"pré-população via prop purchase"*).
- **Regressão que defende:** um campo que **não** chega pré-populado é submetido vazio e **apaga** o
  valor gravado — o pior tipo de perda de dado, porque parece uma edição legítima do operador.
- **Status: 🔴 sem lane** — `should`, não `must`: aqui a asserção estrutural cobre uma parte
  honesta do contrato (a interface tipada declarada no arquivo).

---

## UC-PUREDT-07 · A Page não decide tenant — `business_id` vem das props · `must` `[T0]`

- **Persona:** Wagner — o front nunca é a autoridade sobre de quem é o dado.
- **Aceite:** Dado `Edit.tsx` · Quando o arquivo é lido · Então **não** existe `business_id`
  hardcoded; `editInertia` recebe o `business_id` da sessão e o tipa como inteiro.
- **Teste:** [`Wave2EditInertiaTest`](../../../../tests/Feature/Purchase/Wave2EditInertiaTest.php) —
  *"Page NÃO tem business_id hardcoded"* · *"Controller editInertia PRESERVA business_id Tier 0 +
  tipa int"*.
- **Contrato:** RUNBOOK §3 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** um `business_id` fixo parece constante de config em review e só se
  revela no segundo tenant.
- **Status: 🔴 sem lane** — o contrato aqui *é* a ausência de um literal no arquivo, então o
  presence-gate seria o instrumento certo. Só que ele tambem nao roda: instrumento certo, nunca acionado.

---

## `[BACKLOG]` — achados sem contrato em 2 fontes, ou sem teste que os defenda

- `[BACKLOG]` 🔴 **Tirar `UpdateCrossTenantIdorTest` da quarentena é o item mais valioso do módulo
  inteiro.** É a defesa de um IDOR de escrita **real e já corrigido**, hoje inativa. Ao consertar,
  medir **as duas pernas** — inclusão (a lane roda o arquivo?) e subtração (allowlist/quarentena o
  exclui?) — e provar pelo **contador de assertions**, nunca pelo nome no log
  ([proibicoes §5](../../../../memory/proibicoes.md) 2026-08-02 + emenda 2026-08-12). O motivo do
  skip está escrito no arquivo: *"schema sintético manual incompatível com MySQL persistente"* — ou
  seja, o conserto é de **fixture**, não de asserção.
- `[BACKLOG]` 🔴 **`Wave2EditInertiaTest` tem um assert que falharia — e ninguém sabe, porque ele
  também não roda.** O teste *"RUNBOOK + visual-comparison existem"* faz `file_exists()` em
  `memory/requisitos/Inventory/RUNBOOK-purchase-edit.md` e
  `memory/requisitos/Inventory/purchase-edit-visual-comparison.md`. **Os dois paths não existem** —
  os arquivos reais estão em
  [`memory/requisitos/Compras/_telas/`](../../../../memory/requisitos/Compras/_telas/). O diretório
  `Inventory/` existe (tem `BRIEFING.md` e `SPEC.md`), o que torna o ponteiro plausível o bastante
  para atravessar review. E `Wave2Edit` **não aparece** em `.github/ci-sqlite-pest.list`, então o
  vermelho nunca apareceu. Ao consertar: corrigir o path **e** pôr o arquivo numa lane — só o path
  é progresso falso, porque o assert continua não rodando.
- `[BACKLOG]` **O `update()` não tem assert próprio de permissão.** UC-PUREDT-05 cobre o `edit()`; o
  `update()` só é guardado pelo scope de tenant (que está em quarentena). Um teste que submeta o
  `update` sem `purchase.update` não existe.
- `[BACKLOG]` **O charter do Edit não tem §Non-Goals nem §Anti-hooks.** Índice e Show têm; este
  charter salta direto de §Regras invariantes para §Reuso. Como Non-Goals e Anti-hooks são
  preenchidos **só por [W]** (a skill `charter-write` é proibida de inferir), isso não se conserta
  aqui — é pedido, não trabalho.
- `[BACKLOG]` **Recebimento parcial** — US-COM-013 no [SPEC](../../../../memory/requisitos/Compras/SPEC.md),
  ainda não implementado. Caso sem implementação vira UC órfão; o contrato nasce junto com o código.

---

## ⚠️ Divergências que precisam de [W] (não corrigidas aqui — são INTENÇÃO)

1. **Um `[T0]` `[V0]` com regressão já materializada está sem defesa ativa.** UC-PUREDT-02 não é
   risco teórico: o IDOR **existiu**, foi corrigido, e o teste que impede o retorno não roda desde a
   quarentena da Onda 2. Isso é achado a decidir, não conserto silencioso — e o conserto é de
   fixture, o que o torna barato.
2. **O charter está `status: draft`** e sem Non-Goals/Anti-hooks. Este `casos.md` derivou das
   §Regras invariantes (R-PUR-001/005/006/007), que existem — mas a lei da tela está incompleta até
   [W] preencher as duas seções.
