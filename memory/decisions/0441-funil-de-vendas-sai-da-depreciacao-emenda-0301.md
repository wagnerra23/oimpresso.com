---
slug: 0441-funil-de-vendas-sai-da-depreciacao-emenda-0301
number: 441
title: "O funil de vendas (negócios em kanban) sai da depreciação do pipeline CRM"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-26"
module: crm
quarter: 2026-Q4
tags: [crm, funil, deals, kanban, depreciação, emenda]
supersedes: []
supersedes_partially:
  - 0301-separar-cliente-deprecar-crm-pipeline
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0301-separar-cliente-deprecar-crm-pipeline
pii: false
review_triggers:
  - "90 dias depois de ligado sem nenhuma empresa com negócio cadastrado no funil (a lacuna não era real para os clientes)"
  - "o funil passar a depender de leads, propostas ou campanhas do pipeline antigo (o recorte desta emenda deixou de valer)"
---

# ADR 0441 — O funil de vendas (negócios em kanban) sai da depreciação do pipeline CRM

> **Status:** proposto. Decisão de [W] em 2026-10-26 ao destravar o item "funil de vendas em kanban" do levantamento de lacunas de 2026-10-25 (frente CRM e WhatsApp), sabendo que ele esbarrava na ADR 0301. O merge deste PR é a ratificação.

## Contexto

A [ADR 0301](0301-separar-cliente-deprecar-crm-pipeline.md) separou o cadastro de Cliente do pipeline CRM e colocou o pipeline inteiro em depreciação — leads, funil, propostas, campanhas, follow-ups e negócios —, porque "não faz sentido pro negócio gráfica/vestuário" (silenciado em 2026-06-08).

O levantamento de concorrência de 2026-10-25 apontou o funil de vendas em kanban como lacuna P1: os ERPs brasileiros que concorrem com o oimpresso (Bling, Tiny, Omie, Conta Azul) e os CRMs que os clientes usam ao lado (RD Station, Pipedrive, Agendor) mostram a carteira de negócios por etapa, e o oimpresso já tem o motor pronto e testado — `Modules/Crm/Services/DealPipelineService.php` (mover etapa com histórico, resumo por etapa, previsão ponderada) sobre a tabela `crm_deals` —, sem tela.

## Decisão

1. **Sai da depreciação só o funil de negócios:** a entidade `Deal` (`crm_deals`), o `DealPipelineService` e uma tela nova de kanban. A tabela `crm_deals` passa de TARGET a KEEP no [plano de depreciação](../requisitos/Crm/DEPRECATION-PLAN-pipeline.md).
2. **O resto do pipeline continua em depreciação**, sem mudança: leads (`contacts.type='lead'`), propostas, campanhas por e-mail/SMS, follow-ups e o resto de `crm_*`. O funil não passa a depender de nenhum deles: o negócio aponta para um **cliente do cadastro** (`contact_id`) e a proposta (`proposal_id`) fica opcional e sem uso na tela.
3. **A tela nasce desligada por empresa** pelo caminho canônico de módulo/permissão (Camadas 1 e 3 de `proibicoes.md` §Multi-tenant): permissão Spatie própria, sem hardcode de `business_id`.
4. Etapas fixas (`Deal::STAGES`) nesta primeira versão; etapas por empresa ficam para quando houver sinal de cliente (ADR 0105).

## Consequências

- (+) A lacuna de funil fecha com o código que já existia, sem ressuscitar o pipeline inteiro.
- (+) O plano de depreciação fica mais estreito e continua reversível.
- (−) Um pedaço do `Modules/Crm` que ia sair passa a ter dono e teste; a etapa E5 do plano (DROP) não pode mais incluir `crm_deals`.
- (⚠️) Se ninguém usar o funil em 90 dias, a decisão volta para revisão (review_trigger).

## Alternativas consideradas

1. **Manter a depreciação e não fazer o funil** — rejeitada por [W] em 2026-10-26.
2. **Reabrir o pipeline CRM inteiro** — rejeitada: leads/propostas/campanhas continuam sem sinal de cliente e já têm caminho de remoção; o funil não precisa deles.
3. **Construir o funil sobre as vendas (orçamento → venda) em vez de `crm_deals`** — adiada: a FSM de vendas (ADR 0143) já mostra o andamento do pedido; o funil cobre a fase anterior, de negócio ainda sem orçamento.

## Refs

- Levantamento de lacunas de 2026-10-25 (frente CRM e WhatsApp, item P1 "Funil de vendas em kanban").
- [ADR 0301](0301-separar-cliente-deprecar-crm-pipeline.md) · [DEPRECATION-PLAN-pipeline](../requisitos/Crm/DEPRECATION-PLAN-pipeline.md) · `Modules/Crm/Services/DealPipelineService.php` · `Modules/Crm/Tests/Feature/Wave27DealPipelineTest.php`
