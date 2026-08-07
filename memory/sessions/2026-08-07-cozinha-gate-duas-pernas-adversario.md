---
date: "2026-08-07"
topic: "Cozinha — gate reativado nas duas pernas (endpoint + menu), com adversário read-only antes do merge"
authors: ["C", "W"]
prs: [5388]
us: ["US-GOV-059"]
outcomes:
  - "Gate de sell.view ativo em KitchenController::index() E na linha do menu Cozinha (espelho, evita classe A)"
  - "Duas premissas do pedido refutadas por medição: o precedente do ModifierSets era do MENU, e o menu da Cozinha não declarava predicado algum"
  - "Adversário read-only derrubou 4 pontos meus antes do merge, incluindo uma RAZÃO ERRADA já escrita no canon"
  - "Resíduo declarado e NÃO fechado: refreshOrdersList sem gate (vaza além da cozinha) e markAsCooked como mutação por GET"
---

# Cozinha — gate nas duas pernas, e o que o adversário derrubou

**PR:** [#5388](https://github.com/wagnerra23/oimpresso.com/pull/5388) (squash `433291bd`) · **US:** US-GOV-059 · **Deploy:** automático, `success` · **Smoke:** real em prod, autenticado.

## O pedido e o que a medição fez com ele

Pedido: reativar o gate comentado em `KitchenController::index()`, **espelhando o predicado que o link do sidebar já exige**, e registrar na US-GOV-059.

Duas premissas do brief não sobreviveram:

1. **O precedente citado não existe como descrito.** O brief dizia que `ModifierSetsController::index()` passou a gatear com `product.view || product.create`. Não passou — esse predicado é da **linha do menu** (`AdminSidebarMenu.php:906` em `main`); aquele `index()` segue **sem gate**, o que o próprio SPEC já registrava como *"achado adjacente, não corrigido"*.
2. **O menu da Cozinha não declarava predicado nenhum** (`in_array('kitchen', $enabled_modules)`, e nada mais). Logo **não havia o que espelhar**: espelhar ao pé da letra deixaria o buraco aberto, e **qualquer** permissão no endpoint o tornaria mais estrito que o menu — a **classe A** que a US existe pra matar.

Retrato medido das 5 telas da pasta:

| Tela | Menu | Gate do `index()` | Casam? |
|---|---|---|---|
| Mesas | módulo + `access_tables` | `access_tables` | ✅ |
| Reservas | módulo + (`crud_all_bookings` \|\| `crud_own_bookings`) | o mesmo OR | ✅ |
| Modificadores | módulo + (`product.view` \|\| `product.create`) | **nenhum** | ❌ |
| **Cozinha** | **só o módulo** | **nenhum** (comentado) | — |
| Pedidos | **só o módulo** | **nenhum** (comentado) | — |

Também: o brief dizia *"os irmãos gateiam o `index()`"* — vale para **2 de 4** (`OrderController` tem o gate comentado igual).

Por isso o gate saiu nas **duas pernas** com o mesmo `sell.view`: endpoint **e** linha do menu.

## O adversário mudou o resultado

[W] pediu adversário read-only antes do PR. Ele **confirmou** 4 coisas (a correção de premissa, o retrato 5/5, a inexistência de outra superfície de menu apontando pra Cozinha, e que nenhum teste existente quebra) e **derrubou** 4 — todas re-medidas por mim antes de aplicar:

| # | O que caiu | Correção |
|---|---|---|
| 1 | **Razão errada em canon** — justifiquei não gatear o `refreshOrdersList` com *"arrasta o `OrderController`"*. O método **já ramifica** em `$orders_for`. | A razão real: gatear só o ramo `kitchen` **não fecharia nada**, porque com `orders_for` ausente o `$filter` fica vazio e `getAllOrders` devolve **todas** as vendas `final` não-servidas do business. Virou **errata** no SPEC, não apagamento. |
| 2 | `markAsCooked` é **mutação por `GET`**, e o botão vem das partials que o `refreshOrdersList` serve sem gate | Nomeado como **o maior dos três resíduos**, não um menor |
| 3 | Faltava o **custo** que torna o fork decidível | `sell.view` é over-grant: o único papel de serviço canônico (`Waiter#5`) tem só `dashboard.data`; `sell.view` abre a listagem inteira de Vendas |
| 4 | *"Decisão [W] pendente"* em **presente** dentro de canon | Virou fato datado. Idem *"desde o upstream"* → inferência declarada (o squash do #2413 apagou a linhagem) |

Ele também pesou a decisão [W] de 2026-08-07 sobre `restaurant.*`: **não é reabertura** (o sujeito daquela é o `@can` do `modifier_sets`, nomeado 3× no texto), mas é **extensão de escopo na mesma família** — por isso a aprovação virou condição de merge, não formalidade.

## Smoke real (R1) — o que provou e o que não provou

Os `curl` deslogados davam **302 e não provavam nada**: o `auth` dispara antes de o middleware montar o menu. Só a sessão autenticada exercita o código mudado.

| Verificação | Resultado |
|---|---|
| App de pé | `/` e `/login` → **200** |
| Sidebar renderiza (o middleware roda em **toda** requisição) | ✅ completo, **zero erro de console** |
| Item do menu para admin | ✅ presente no grupo PRODUÇÃO |
| **Rota mudada, autenticado** | ✅ **200**, renderiza a listagem |
| Regressão adjacente (`tables`, `bookings`, `orders`, `home`) | ✅ inalteradas |

⚠️ **Não provado:** a perna *"quem não tem `sell.view` toma 403"*. [W] é admin e o `Gate::before` faz `can()` retornar `true` — provar exigiria um usuário sem a permissão, que é escrita em produção.

## Achados de campo, nenhum causado por este PR

- **`kitchen` está habilitado em biz=1** (junto de `booking`, `service_staff`, `modifiers`). A feature está **ligada**, não só "existe" — contradiz levemente a leitura de "não está em uso".
- **A tela do Restaurante está em espanhol** (*"Todas las órdenes"*, *"No se encontraron pedidos"*, *"Refrescar"*) e o label do menu é **"Cocina"**: o `lang` pt-BR do módulo nunca foi traduzido e cai no fallback. Aparece pro cliente no dia em que a feature entrar em uso.
- Alerta **"Certificado vencido"** (NFe) ativo no topo do sidebar — sem relação com este PR.

## Resíduo declarado (NÃO fechado)

`refreshOrdersList` e `refreshLineOrdersList` seguem **sem gate** (e o primeiro, com `orders_for` ausente, vaza além da cozinha); `markAsCooked` segue **mutação por `GET`** sem gate, com o botão vindo daquelas partials. Fechar arrasta a decisão da tela Pedidos junto — próximo escopo.

**Sem teste e sem lane:** nenhuma lane roda `tests/Feature/Restaurant`. O `ci.yml` roda a lista curada `.github/ci-sqlite-pest.list` (**149** alvos) — wirar custaria **uma linha nessa lista**, não uma lane nova. Não criei o arquivo: sem a linha, seria cobertura de mentira.

## Erros meus, e a defesa que funcionou

Três instâncias de classes já catalogadas (contadores incrementados em [`LICOES_CODE.md`](../LICOES_CODE.md)):

- **LC-08 (56→57)** — a razão errada do item 1, escrita por leitura sem percorrer o util. Near-miss irmão: quase afirmei que o `Infra Contract` era *required* porque um regex bateu em **prosa** do `required-checks-baseline.json`.
- **LC-10 (3→4)** — o marcador em presente.
- **LC-16 (2→3)** — reescrevendo o SPEC, casei um parágrafo sem incluir o blockquote seguinte e **dupliquei** texto.

A defesa que pegou tudo foi a mesma, duas vezes: **teste de identidade**. Na duplicata, `grep` de conferência logo após editar. No conflito de merge da cauda da US-GOV-059 contra o #5384, resolvi por **range de linha** e provei os dois blocos (65 e 109 linhas) **byte-idênticos** no resultado — zero marcador, 1 ocorrência de cada cabeçalho. Range + identidade > casamento textual.

## Recibos

- `php -l` nos 2 arquivos — PHP 8.4.22 no CT 100.
- `permission-drift.mjs --json` (detector desta própria US, que **lê** o arquivo mudado): 24 órfãs, `sell.view` **não** entre elas.
- `anchor-drift` nos **3** modos que o job roda, `doneness-lint`, schema do SPEC no modo **por arquivo** (o modo `--glob` dava verde medindo **zero** arquivo — verde por não-execução; bite-test provou que o validador morde), `sdd-scorecard --ratchet` — todos rc=0.
- CI do PR: **100 SUCCESS · 2 SKIPPED · 0 falhas**.
