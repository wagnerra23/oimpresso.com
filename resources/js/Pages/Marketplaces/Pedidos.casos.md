---
casos: Marketplaces/Pedidos — carimbado do Padrão de Tela
irmaos: Pedidos.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Marketplaces/Pedidos

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. Os UCs são citados pelo Pest de contrato `MarketplacesPedidosTelaContratoTest`.

---

## UC-PEDIDO-00 · Chego na tela pelo menu, sem digitar URL
- **Persona:** Larissa — abre o sistema e encontra a tela pelo sidebar.
- **Aceite:** Dado usuário com a permission `marketplaces.access` · Quando abre o sistema ·
  Então o item "Marketplaces" existe no sidebar e leva a `/marketplaces/pedidos`; sem o pacote, o item some.
- **Teste:** `Modules/Marketplaces/Tests/Feature/MarketplacesPedidosTelaContratoTest.php`.
- **Regressão que defende:** a tela responder 200 e ninguém alcançar — `modifyAdminMenu()`
  no-op passa por todo gate de código, porque um método vazio é sintaticamente perfeito.
- **Status: ⬜**

---

## UC-PEDIDO-01 · Vejo os pedidos da minha empresa e quais já viraram venda
- **Persona:** dona da loja que vende no Mercado Livre (US-MKT-008).
- **Aceite:** Dado pedidos do marketplace na empresa 98 e na 99 · Quando a usuária da 98 abre `/marketplaces/pedidos` ·
  Então a lista traz só os pedidos da 98, com número, situação, total e o id da venda quando já virou venda.
- **Teste:** `Modules/Marketplaces/Tests/Feature/MarketplacesPedidosTelaContratoTest.php`.
- **Regressão que defende:** pedido de outra empresa aparecer na lista (ADR 0093).
- **Status: ⬜**

---

## UC-PEDIDO-02 · Filtro os pedidos que ainda não viraram venda
- **Aceite:** Dado um pedido com venda e outro sem · Quando filtro "Ainda sem venda" · Então só o sem venda aparece; com "Com venda", só o outro.
- **Teste:** `Modules/Marketplaces/Tests/Feature/MarketplacesPedidosTelaContratoTest.php`.
- **Status: ⬜**

---

## UC-PEDIDO-03 · Escolho o local de venda da conta conectada
- **Persona:** quem tem `marketplaces.account.connect` (decisão [W] 2026-10-26: "escolhido ao conectar a conta").
- **Aceite:** Dado uma conta da empresa sem local · Quando escolho um local da empresa · Então a conta passa a ter esse local;
  local de outra empresa é recusado (422) e conta de outra empresa não é encontrada (404); sem a permissão de conectar, 403.
- **Teste:** `Modules/Marketplaces/Tests/Feature/MarketplacesPedidosTelaContratoTest.php`.
- **Regressão que defende:** venda de marketplace cair em local de outra empresa.
- **Status: ⬜**

---

## UC-PEDIDO-04 · Sem o pacote, a tela não abre
- **Aceite:** Dado a empresa sem o pacote `marketplaces_module` · Quando abro `/marketplaces/pedidos` · Então 403.
- **Teste:** `Modules/Marketplaces/Tests/Feature/MarketplacesPedidosTelaContratoTest.php`.
- **Status: ⬜**

---

## UC-PEDIDO-05 · Conecto a loja Nuvemshop da minha empresa
- **Persona:** dona da loja que vende na própria loja Nuvemshop e quer os pedidos aqui (ADR 0443, US-MKT-026).
- **Aceite:** Dado a integração ligada (`MARKETPLACES_NUVEMSHOP_ATIVO`) e usuário com `marketplaces.account.connect` · Quando conecto a loja ·
  Então volto da autorização da Nuvemshop com a loja gravada na **minha** empresa (98, nunca na 99), com o token cifrado e sem vencimento;
  reconectar a mesma loja não duplica; retorno com `state` de outra sessão não grava nada; com a integração desligada, nada sai do ERP.
- **Teste:** `Modules/Marketplaces/Tests/Feature/MarketplacesOAuthNuvemshopContratoTest.php` (todos os `it('UC-PEDIDO-05 · …')`).
- **Regressão que defende:** loja de um cliente ligada à empresa de outro por um link de retorno forjado (ADR 0093); a conta
  Nuvemshop cair na renovação de token do Mercado Livre e virar "token expirado" sem ter vencido.
- **Status: ⬜** (roda só no CI; CT 100 fora do ar)

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** abrir o pedido num painel lateral com itens, envio e histórico do FSM (US-MKT-008, drawer `OrderSheet`).

## Trilha do tempo
- 2026-10-26 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-10-27 · UC-PEDIDO-05 (conectar a loja Nuvemshop, ADR 0443 fatia a). Fonte do caso: ADR 0443 + SPEC US-MKT-026.
- 2026-10-28 · `last_run` 2026-10-26 → 2026-10-27: a tela não mudou desde que nasceu (#9916, 2026-10-27, mesmo commit do trio); o G-6 acusava só a data do carimbo, anterior ao commit. UC-PEDIDO-01..04 relidos contra o `.tsx`.
