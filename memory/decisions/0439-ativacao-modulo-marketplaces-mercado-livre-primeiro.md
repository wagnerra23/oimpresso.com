---
slug: 0439-ativacao-modulo-marketplaces-mercado-livre-primeiro
number: 439
title: "Ativação do módulo Marketplaces por decisão [W]: um módulo com drivers, Mercado Livre primeiro"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-26"
module: null
quarter: 2026-Q4
tags: [marketplaces, mercado-livre, oauth2, webhooks, nfe, fsm, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0117-multiplos-numeros-whatsapp-por-business
  - 0121-oimpresso-modular-especializado-por-vertical
pii: false
review_triggers:
  - "Mercado Livre muda mais de 5 endpoints do driver ao mesmo tempo"
  - "Primeiro cliente pede Shopee ou Amazon antes de o fluxo do ML estar completo"
---

# ADR 0439 — Ativação do módulo Marketplaces: um módulo com drivers, Mercado Livre primeiro

## Contexto

O `SPEC.md` de Marketplaces (25 US, `US-MKT-001..025`) e o rascunho
`proposals/drafts/marketplaces-modulo-cross-vertical.md` (decisões D1–D8) existem desde 2026-05,
mas o módulo nunca foi construído: o SPEC condicionava a construção a um sinal de cliente
(§11.1) e a uma "ADR de ativação" (§11.4).

Em 2026-10-25 o levantamento de lacunas frente à concorrência pôs a integração com marketplaces
como **o P0 da frente Plataforma** e [W] pediu que ela fosse executada. Pela
[ADR 0382](0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md), trabalho dirigido por
[W] não espera sinal: a §11.1 está satisfeita pela decisão dele. Esta ADR é a "ADR de ativação"
que a §11.4 pede, e fecha as decisões D1–D8 que o rascunho deixou abertas.

## Decisão

1. **O módulo é ativado.** `memory/requisitos/Marketplaces/SPEC.md` passa de "aguarda sinal" para
   em construção. As US seguem a ordem do SPEC §11.2 (o mínimo para um pedido real ponta a ponta):
   001 schema → 002 catálogo → 003 OAuth → 006 webhook → 007 pedido vira venda → 008 tela de
   pedidos → 009 NF-e → 010 rastreio; depois 005/011/012 (anúncio e estoque).
2. **Mercado Livre é o primeiro marketplace.** Shopee (US-MKT-021) vem depois que o fluxo do ML
   estiver completo, reaproveitando o mesmo driver; Amazon e Magalu ficam para depois.
3. **D1 — um módulo `Modules/Marketplaces`** com uma interface `MarketplaceDriver` e um driver
   por marketplace (`MercadoLivreDriver` primeiro). Tabelas `mkt_*` compartilhadas.
4. **D2 — sincronização em sentido único no início:** pedidos, NF-e e rastreio vêm do
   marketplace para o ERP. Estoque do ERP para o marketplace (US-MKT-011) entra depois, atrás de
   flag por empresa e com debounce.
5. **D3 — Full e estoque próprio** modelados desde o schema (`mkt_orders.shipping_type`); os CFOP
   de remessa e retorno do Full ficam para a fase do anúncio em lote.
6. **D4 — anúncio criado manualmente** pela tela; cadastro em lote depois (US-MKT-013).
7. **D5 — CFOP escolhido automaticamente** pelo mapeamento do SPEC §5, com troca manual por
   pedido registrada em auditoria. **A NF-e automática só é ligada depois que [W] e o contador
   conferirem o mapeamento** (flag desligada até lá).
8. **D6 — dois saldos a receber:** o valor que o Mercado Pago ainda vai liberar fica separado do
   recebível confirmado, com data prevista = `money_release_date`, e não entra em "vencidos".
9. **D7 — reputação:** só leitura e alerta nas primeiras fases; nenhuma ação automática.
10. **D8 — preço por anúncio:** `mkt_listings.price_marketplace` é a fonte, com regra de markup
    opcional (`mkt_pricing_rules`).

Regras que não mudam: credencial por `business_id`, cifrada (`Crypt`), mais de uma conta por
empresa permitida (padrão da ADR 0117); webhook só é aceito com assinatura válida e é gravado
append-only; todo job carrega `business_id` no construtor; mudança de etapa da venda só pelo
`ExecuteStageActionService` (ADR 0143); dados do comprador vão para log e PR só em hash.

## Justificativa

O rascunho já tinha comparado as alternativas (não construir e indicar Tiny/Bling; um módulo
por marketplace; só ML) e recomendado as mesmas opções; esta ADR as adota sem mudança, exceto
pela ordem, que vem do SPEC §11.2. Começar só pelo ML limita o custo de manter driver de API que
muda com frequência (risco 2 do SPEC §10) ao marketplace de maior volume.

Reabrir quando um cliente precisar de outro marketplace antes de o fluxo do ML terminar, ou
quando a API do ML exigir reescrita grande do driver.

## Consequências

**Positivas:** o P0 da frente Plataforma tem ordem e escopo fechados; cada US vira um PR pequeno.

**Negativas / Trade-offs:** custo de manutenção recorrente do driver ML; a NF-e automática fica
parada até a conferência do CFOP; até a US-MKT-011 o estoque do anúncio é ajustado à mão.

**O que depende de [W] fora do código:** criar o aplicativo em developers.mercadolivre.com.br
(client_id, client_secret, URL de retorno e de notificação) e guardá-lo no Vaultwarden; conferir
o mapeamento de CFOP com o contador; dizer qual empresa testa primeiro em produção.

## Referências

- `memory/requisitos/Marketplaces/SPEC.md` (§3 schema, §5 CFOP, §11 ativação)
- `memory/decisions/proposals/drafts/marketplaces-modulo-cross-vertical.md` (D1–D8, alternativas)
- ADR 0382 — trava de sinal não vale para trabalho dirigido por [W]
- ADR 0093 — isolamento por `business_id`
- ADR 0143 — pipeline FSM de vendas
