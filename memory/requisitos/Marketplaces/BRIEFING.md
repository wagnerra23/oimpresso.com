---
id: requisitos-marketplaces-briefing
module: Marketplaces
status: em-construcao
status_nota: "Ativado por decisão [W] em 2026-10-26 (ADR 0439). Entregue até aqui: esqueleto + schema (001), catálogo (002) e OAuth do ML sem tela (003)."
updated_at: "2026-10-26"
---

# BRIEFING — Marketplaces

## O que é

Integração do ERP com marketplaces brasileiros: o pedido feito no marketplace vira venda no
oimpresso, com NF-e e rastreio, e o estoque e os anúncios ficam sincronizados. Serve a qualquer
vertical (não é módulo de vertical). Separado do `Woocommerce`, que é loja própria.

## Estado

- **Ativado em 2026-10-26** pela [ADR 0439](../../decisions/0439-ativacao-modulo-marketplaces-mercado-livre-primeiro.md)
  (decisão [W], pela ADR 0382). Até então era wish à espera de sinal de cliente.
- **Mercado Livre primeiro.** Shopee depois do fluxo do ML completo; Amazon e Magalu mais tarde.
- **Código:** `Modules/Marketplaces` com instalação 1-clique, pacote `marketplaces_module` (desligado
  por padrão), as 5 tabelas do primeiro pedido (US-MKT-001), o catálogo (US-MKT-002) e a conexão
  da conta do Mercado Livre por OAuth, sem tela ainda (US-MKT-003, `/marketplaces/contas/mercado-livre/conectar`).
  As notificações do ML chegam em `/webhook/marketplaces/mercado-livre` e o pedido é gravado em
  `mkt_orders`, ainda sem virar venda (US-MKT-006).
  Próximas US em [SPEC.md](SPEC.md) §11.2: pedido vira venda → tela de pedidos → NF-e → rastreio.

## O que depende de [W]

- Criar o aplicativo em developers.mercadolivre.com.br (client_id, client_secret, URLs de retorno
  e de notificação) e guardar no Vaultwarden.
- Conferir com o contador o mapeamento de CFOP (SPEC §5) antes de ligar a NF-e automática.
- Indicar a empresa que testa primeiro em produção.
- Cadastrar no app do ML a URL de notificação `https://oimpresso.com/webhook/marketplaces/mercado-livre`, tópico `orders_v2`.

## Regras que não mudam

- Credencial por `business_id`, cifrada; uma empresa pode ter mais de uma conta (ADR 0117).
- Webhook só com assinatura válida, gravado append-only; todo job leva `business_id`.
- Mudança de etapa da venda só pelo `ExecuteStageActionService` (ADR 0143).
- Dados do comprador em log e PR só em hash.
