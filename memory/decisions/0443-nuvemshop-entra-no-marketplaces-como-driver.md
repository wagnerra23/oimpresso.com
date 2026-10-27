---
slug: 0443-nuvemshop-entra-no-marketplaces-como-driver
number: 443
title: "Nuvemshop entra no Modules/Marketplaces como segundo canal, não no Woocommerce"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-27"
module: null
quarter: 2026-Q4
tags: [marketplaces, nuvemshop, loja-virtual, oauth2, webhooks, lgpd, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0439-ativacao-modulo-marketplaces-mercado-livre-primeiro
  - 0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0117-multiplos-numeros-whatsapp-por-business
pii: false
review_triggers:
  - "Nuvemshop troca a versão da API (hoje 2025-03) e quebra mais de 3 chamadas do canal"
  - "Um cliente pede Loja Integrada, Tray ou Shopify antes de a Nuvemshop estar completa"
  - "O fluxo do Mercado Livre muda o contrato de mkt_orders ou do VirarVendaJob"
---

# ADR 0443 — Nuvemshop entra no `Modules/Marketplaces` como segundo canal

> **Ratificada por [W] em 2026-10-27:** "Ratifico a ADR 0443: a Nuvemshop entra no Marketplaces.
> Pode seguir com as fatias." Ficam abertas, para decisão do [W], o `customers/redact` (seção LGPD)
> e o antes → depois da fatia c (regra mestre de valor e estoque).

## Contexto

O levantamento de lacunas de 2026-10-25 lista "Loja virtual" como lacuna da frente Plataforma.
Hoje o oimpresso integra uma loja própria só pela WooCommerce (`Modules/Woocommerce`). A Nuvemshop
é a plataforma de loja virtual mais usada por pequena empresa no Brasil, e a integração com ela
falta. Em 2026-10-27 o [W] mandou destravar todas as lacunas
([ADR 0382](0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md): trabalho dirigido por
[W] não espera sinal de cliente).

A integração precisa de três coisas: sincronizar produto e estoque, receber o pedido da loja e
transformar o pedido pago em venda. Há dois lugares onde ela pode morar.

**`Modules/Woocommerce`** é o módulo herdado do UltimatePOS. Guarda **uma** loja por empresa em
colunas da tabela `business` (URL e chaves), recebe webhook por rotas próprias e cria a venda pelo
caminho do núcleo (`createSellTransaction` → linhas → baixa de estoque). Não tem tabela de contas,
nem log de webhook append-only, nem processo FSM próprio.

**`Modules/Marketplaces`** foi ativado pela [ADR 0439](0439-ativacao-modulo-marketplaces-mercado-livre-primeiro.md)
para o Mercado Livre e já tem, em `main`: catálogo de canais (`mkt_marketplaces`), contas por
empresa com credencial cifrada e mais de uma conta permitida (`mkt_account_credentials`),
anúncios ligados à variação (`mkt_listings`), pedidos (`mkt_orders`), log de webhook append-only
(`mkt_webhook_log`), o `VirarVendaJob` com o `PedidoParaVenda` (que confere o total por dois
caminhos antes de criar a venda), o local de venda escolhido na conta, o processo FSM
`venda_marketplace` (ADR 0143) e a tela de pedidos.

## Decisão

1. **A Nuvemshop entra no `Modules/Marketplaces`**, como segundo canal, com slug `nuvemshop` no
   catálogo `mkt_marketplaces`. Não entra no `Modules/Woocommerce`.
2. **Reaproveita o que o canal do Mercado Livre já tem**, sem tabela nova: a conta conectada vai
   em `mkt_account_credentials` (o `store_id` da loja em `seller_id_external`, o token cifrado), o
   pedido em `mkt_orders`, a notificação em `mkt_webhook_log`, o vínculo produto↔loja em
   `mkt_listings`, e o pedido pago segue para o mesmo `VirarVendaJob`. O que é do Mercado Livre
   dentro do `PedidoParaVenda` (formato dos itens, status "paid", origem da venda) passa a ser
   traduzido por canal antes de chegar ao serviço, que continua sendo um só.
3. **Tudo atrás de flag desligada** (`MARKETPLACES_NUVEMSHOP_ATIVO=false`) e com o canal inativo
   no catálogo. Ligar é ato do [W], depois de criar o aplicativo de parceiro.
4. **Ordem das fatias**, um PR cada: (a) catálogo + configuração + conectar a loja (OAuth);
   (b) receptor de notificação com verificação de assinatura + os três webhooks LGPD obrigatórios;
   (c) pedido pago vira venda (regra mestre de valor e estoque: antes→depois aprovado pelo [W]);
   (d) estoque do ERP para a loja; (e) produto do ERP para a loja e tela de vínculo.
5. **Estoque segue a D2 da ADR 0439:** primeiro o pedido vem da loja para o ERP; o estoque do ERP
   para a loja entra depois, atrás de flag por empresa.

### O que é diferente do Mercado Livre (lido da documentação pública, API versão 2025-03)

| Ponto | Mercado Livre | Nuvemshop |
|---|---|---|
| Token | expira em 6 h, renovado | **não expira**; só vale até a loja reinstalar ou remover o app |
| Assinatura do webhook | o ML não assina | **assina**: cabeçalho `x-linkedstore-hmac-sha256` = HMAC-SHA256 do corpo com o segredo do app. Sem assinatura válida, a notificação é recusada |
| Corpo da notificação | `resource` + `topic` | `{store_id, event, id}`; eventos `order/paid`, `order/cancelled`, `product/updated` etc. |
| Prazo de resposta | — | 3 s; até 16 novas tentativas em 48 h, então o receptor só grava e enfileira |
| LGPD | — | `store/redact`, `customers/redact` e `customers/data_request` são **obrigatórios** para o app |
| Pedido pago | `status = paid` | `payment_status = paid` |
| Chamada à API | `Authorization: Bearer` | `Authorization: Bearer` + `User-Agent` com nome e contato do app (sem ele, HTTP 400) |
| Limite | ~1500/min | 40 de balde, 2 por segundo por loja |

Sobre os webhooks LGPD: o `store/redact` apaga a credencial da loja e desconecta a conta. O
`customers/data_request` registra o pedido para a empresa atender.

**Ponto que é decisão do [W]:** o `customers/redact` pede para apagar dados do comprador. A
[lápide de 2026-07-27](../proibicoes.md) diz que num ERP não se apaga PII (o controle é por
permissão). A proposta não fere a lápide: não há varredura por prazo, e a venda, a nota e o valor
ficam intactos; sai só a cópia do comprador que o canal guardou (`mkt_orders.buyer_external` e o
bloco `customer` dentro de `raw_payload`), quando a loja pedir. Se o [W] preferir não apagar nada,
o receptor só registra o pedido e responde 200, e o app pode ser reprovado na revisão da Nuvemshop.

## Justificativa

- **Marketplaces já resolve o difícil.** Credencial por empresa e cifrada, mais de uma loja por
  empresa, log append-only, conferência do total por dois caminhos, local de venda por conta e
  processo FSM são exatamente o que a Nuvemshop precisa. Fazer no Woocommerce exigiria criar tudo
  isso de novo num módulo herdado, ou guardar a segunda loja em colunas da tabela `business`.
- **Uma tela e um relatório só** de pedidos de canal: o cliente vê Mercado Livre e Nuvemshop no
  mesmo lugar, e a Jana responde sobre os dois pela mesma tabela.
- **Custo de manter**: um segundo canal no mesmo módulo divide código (conta, pedido, venda); o
  que muda por canal fica isolado nas classes de cada um.

**Alternativa rejeitada — `Modules/Woocommerce`:** é por loja única em colunas da tabela
`business` e não tem log append-only nem FSM. Somar a Nuvemshop ali manteria dois caminhos de
"pedido vira venda" que já divergem (o do Marketplaces confere o total, o da Woo não).

**Alternativa rejeitada — módulo `Modules/Nuvemshop` próprio:** é a D1 da ADR 0439 recusada por
outro nome (um módulo por canal).

## Consequências

**Positivas:** a lacuna "Loja virtual" fecha sem tabela nova; o `PedidoParaVenda` passa a ter dois
canais e por isso fica genérico de fato.

**Negativas / trade-offs:** o `PedidoParaVenda` precisa de uma pequena refatoração para receber
itens já traduzidos (fatia c), que toca valor e estoque e exige a prova da regra mestre; o módulo
Marketplaces passa a responder pelos webhooks LGPD da Nuvemshop.

**O que depende de [W] fora do código:** criar o aplicativo de parceiro em partners.nuvemshop.com.br
(app_id, client_secret), com URL de retorno `/marketplaces/oauth/callback/nuvemshop` e as URLs dos
webhooks; guardar no Vaultwarden; escolher a empresa que testa primeiro; ligar a flag.

## Referências

- Documentação pública: tiendanube.github.io/api-documentation (authentication, intro, resources/webhook, resources/order)
- [ADR 0439](0439-ativacao-modulo-marketplaces-mercado-livre-primeiro.md) — D1 (um módulo, drivers por canal) e D2 (sentido único no início)
- `memory/requisitos/Marketplaces/SPEC.md`
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — `business_id` em tudo
