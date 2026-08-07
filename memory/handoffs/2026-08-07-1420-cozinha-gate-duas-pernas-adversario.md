---
date: "2026-08-07"
time: "14:20"
slug: "cozinha-gate-duas-pernas-adversario"
tldr: "Gate da Cozinha reativado nas duas pernas (endpoint + menu) e mergeado; duas premissas do pedido caíram na medição, um adversário read-only derrubou mais quatro coisas minhas antes do merge — inclusive uma razão errada já escrita no canon — e o resíduo que de fato veda o dado segue aberto."
decided_by: ["W"]
cycle: null
prs: [5388]
us: ["US-GOV-059"]
related_adrs: ["0093-multi-tenant-isolation-tier-0"]
next_steps:
  - "Decidir o resíduo: refreshOrdersList/refreshLineOrdersList sem gate e markAsCooked como mutação por GET — fechar arrasta a tela Pedidos (OrderController) junto"
  - "Decidir se sell.view fica ou vira access_tables|| — o custo está medido no SPEC (Waiter#5 tem só dashboard.data)"
  - "OrderController::index() tem o gate comentado igual ao da Cozinha — mesma classe, não tocado"
  - "lang pt-BR do módulo restaurant nunca traduzido: a tela aparece em espanhol e o menu diz 'Cocina'"
---

# Cozinha — gate nas duas pernas, adversário antes do merge

**[#5388](https://github.com/wagnerra23/oimpresso.com/pull/5388) MERGED** (squash `433291bd`) · deploy automático `success` · CI **100 SUCCESS · 2 SKIPPED · 0 falhas** · smoke real autenticado feito.

## O que mudou

`KitchenController::index()` tinha o gate **comentado** e servia os **pedidos da cozinha** — transações, não nomes de catálogo — a qualquer usuário autenticado do business. `business_id` intacto (Tier 0 não violado); faltava RBAC **dentro** do tenant. Gate `sell.view` ativado **no endpoint e na linha do menu**.

## Duas premissas do pedido caíram na medição

1. **O precedente citado não existe como descrito.** O brief dizia que `ModifierSetsController::index()` gateia com `product.view || product.create`. **Não gateia** — esse predicado é da **linha do menu** (`AdminSidebarMenu.php:906`); aquele `index()` segue sem gate, o que o próprio SPEC já registrava como *"achado adjacente, não corrigido"*.
2. **O menu da Cozinha não declarava predicado nenhum.** Logo **não havia o que espelhar** — e qualquer permissão no endpoint o tornaria mais estrito que o menu, que é a **classe A** (link visível → 403) que a US existe pra matar. Por isso o gate saiu **nas duas pernas**.

Retrato medido: Mesas e Reservas espelham (menu = gate); Modificadores tem menu mais estrito que o controller; Cozinha e Pedidos não tinham nem um nem outro. O brief dizia *"os irmãos gateiam o `index()`"* — vale para **2 de 4**.

## O adversário mudou o resultado (pedido de [W] antes do PR)

**Confirmou 4** (correção de premissa · retrato 5/5 · nenhuma outra superfície de menu aponta pra Cozinha · nenhum teste existente quebra) e **derrubou 4**, todas re-medidas por mim antes de aplicar:

- **Razão ERRADA já escrita no canon** — justifiquei não gatear o `refreshOrdersList` com *"arrasta o `OrderController`"*. O método **já ramifica** em `$orders_for`. A razão real: gatear só o ramo `kitchen` **não fecharia nada**, porque com `orders_for` ausente o `$filter` fica vazio e `getAllOrders` devolve **todas** as vendas `final` não-servidas do business. Virou **errata** no SPEC, não apagamento.
- **`markAsCooked` é mutação por `GET`** (`routes/web.php:797`) sem gate, e o botão vem das partials que o `refreshOrdersList` serve sem gate ⇒ **é o maior dos três resíduos**, não um menor.
- **Faltava o custo que torna o fork decidível:** `sell.view` é **over-grant** — o único papel de serviço canônico (`Waiter#5`, `DummyBusinessSeeder.php:1396-1401`) tem `syncPermissions(['dashboard.data'])` e nada mais, enquanto `sell.view` abre a listagem inteira de Vendas.
- ***"Decisão [W] pendente"* em PRESENTE** dentro de canon ⇒ vira falsa no minuto em que [W] decide. Datado. Idem *"desde o upstream"*: o squash do #2413 apagou a linhagem, `git log -S` não prova ⇒ inferência declarada.

Sobre a decisão [W] de 2026-08-07 na mesma US: **não é reabertura** (o sujeito dela é o `@can` do `modifier_sets`, nomeado 3×), mas é **extensão de escopo na mesma família** — por isso a aprovação foi condição de merge.

## ⚠️ O que NÃO foi fechado

O PR fecha a **porta**, não o **dado**. Seguem abertos: `refreshOrdersList` (que com `orders_for` ausente vaza **além** da cozinha), `refreshLineOrdersList`, e `markAsCooked` **por GET**. Fechar arrasta a decisão da tela Pedidos junto — outro escopo, decisão [W].

**Sem teste e sem lane:** nenhuma lane roda `tests/Feature/Restaurant`. O `ci.yml` roda a lista curada `.github/ci-sqlite-pest.list` (**149** alvos) — wirar custaria **uma linha nessa lista**, não uma lane nova; não criei o arquivo porque sem a linha seria cobertura de mentira.

## Smoke real — e o que ele NÃO provou

Os `curl` deslogados davam **302 e não provavam nada** (o `auth` dispara antes de o middleware montar o menu). Autenticado: app de pé, **sidebar renderiza completo com zero erro de console** (o middleware roda em toda requisição), item de menu presente pro admin, **rota mudada → 200**, e `tables`/`bookings`/`orders`/`home` inalteradas.

⚠️ **Não provado:** a perna *"quem não tem `sell.view` toma 403"* — [W] é admin e o `Gate::before` faz `can()` retornar `true`; provar exigiria usuário sem a permissão, que é escrita em produção.

## Achados de campo (nenhum causado por este PR)

- **`kitchen` está habilitado em biz=1** (com `booking`, `service_staff`, `modifiers`) — a feature está **ligada**, não só "existe".
- **A tela do Restaurante está em espanhol** (*"Todas las órdenes"*, *"No se encontraron pedidos"*) e o menu diz **"Cocina"**: o `lang` pt-BR do módulo nunca foi traduzido.
- Alerta **"Certificado vencido"** (NFe) ativo no sidebar.

## Erros meus (ledger incrementado)

**LC-08 56→57** (a razão errada, derivada de leitura sem percorrer o util; near-miss irmão: quase afirmei que o `Infra Contract` era *required* porque um regex bateu em **prosa** do `required-checks-baseline.json`) · **LC-10 3→4** (marcador em presente) · **LC-16 2→3** (casei um parágrafo sem incluir o blockquote seguinte e **dupliquei** texto no SPEC).

A defesa que pegou tudo foi **teste de identidade**, duas vezes: `grep` de conferência logo após a edição, e — no conflito de merge da cauda da US-GOV-059 contra o [#5384](https://github.com/wagnerra23/oimpresso.com/pull/5384) — resolução por **range de linha** provando os dois blocos (65 e 109 linhas) **byte-idênticos**, zero marcador, 1 ocorrência de cada cabeçalho. O `memory-schema` também mordeu em mim (session log sem `topic`).

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `my-work` → **8 tasks**, todas em `REVIEW` (US-TR-309/310/305/306 · US-PG-008 · US-PROD-027 · US-INFRA-023/048). Nenhuma tocada por esta sessão.
- `decisions-search "permissões órfãs gate menu classe A US-GOV-059"` → 4 ADRs (0086, 0059, 0298, 0279); **nenhuma conflita** e **nenhuma ADR nova foi necessária** — o trabalho é incremento de US existente, não decisão arquitetural.
