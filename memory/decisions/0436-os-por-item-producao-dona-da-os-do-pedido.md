---
slug: 0436-os-por-item-producao-dona-da-os-do-pedido
number: 436
title: "OS por item: a OS que nasce do pedido mora no Modules/Producao e anda no motor FSM; o roteiro anda no PCP"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-16"
module: pcp
tags: [fsm, producao, pcp, vendas, multi-tenant]
supersedes: []
supersedes_partially: [0431-pcp-sai-do-desejo-modules-producao]
superseded_by: []
related:
  - 0129-state-machine-canonica-fsm-rbac
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0431-pcp-sai-do-desejo-modules-producao
  - 0435-fluxos-por-modelo-porte-atividade
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "um módulo vertical (CV, Repair, Oficina) precisar que a OS do pedido carregue dados técnicos próprios"
  - "o motor FSM passar a representar etapas em paralelo"
---

# ADR 0436 — OS por item

## Contexto

A [ADR 0435](0435-fluxos-por-modelo-porte-atividade.md) separou a venda da produção e deixou para a
fase 2 a OS por item: no protótipo do [W] (`fluxos-processos.jsx`, processo `producao`), "Cliente
aprova" cria uma OS para cada item que gera OS, cada OS tem o próprio roteiro e anda pelas situações
Aguardando liberação → Na fila → Em execução ⇄ Pausada, Retrabalho, Concluída, Cancelada. A tela de
criar venda do protótipo já tinha "Gera OS?" por item.

Medido no `main` em 16/10/2026:

- `cv_ordens_producao` (`Modules/ComunicacaoVisual`) tem formato de um item, mas só testes a criam,
  e as colunas são de CV (substrato, m², instalação).
- Nada cria `pcp_programacoes`; `pcp_roteiros` existe por família, sem ligação com produto.
- O motor FSM guarda uma etapa por documento e não representa duas trilhas andando ao mesmo tempo.

O desenho e as quatro decisões abaixo foram aprovados pelo [W] em 16/10/2026 no PR #9517
(protótipo + plano).

## Decisão

1. **A OS por item é uma entidade do `Modules/Producao`**: `pcp_ordens`, uma linha por item da
   venda que gera OS (única por empresa + linha da venda), com `business_id`, venda, linha, produto,
   quantidade, roteiro e `current_stage_id`. Isso emenda o item 5 da
   [ADR 0431](0431-pcp-sai-do-desejo-modules-producao.md) ("CV, Repair e Oficina seguem donos da
   OS") **só para a OS que nasce do pedido nos modelos de fluxo**: as OS próprias de Repair e
   Oficina continuam onde estão, e a `OrdemProducao` da CV não é apagada (os dados técnicos da CV
   podem virar extensão 1:1 depois).
2. **O motor guarda a situação; o PCP guarda o roteiro.** O processo `pcp_ordem_item` tem os 7
   estados e as 9 transições do protótipo, igual para todos os modelos. As etapas do roteiro (com as
   trilhas em paralelo e a junta) viram linhas de `pcp_programacoes` com `os_tipo = 'pcp_ordem'`.
   O processo de produção que o "aplicar modelo" monta com as etapas do modelo passa a ser o
   roteiro padrão do modelo (PR próprio, depois do #9514).
3. **O roteiro vem do produto** (`pcp_produto_roteiro`, por empresa, sem mexer em `products`); sem
   roteiro no produto, valem as etapas de produção do modelo da empresa.
4. **Gera OS o item cujo produto tem roteiro ligado**, ou, sem roteiro, o produto que não é serviço
   numa empresa com modelo de produção. Serviço e pronta-entrega não geram.

## Consequências

- Tier 0: `pcp_ordens` e `pcp_produto_roteiro` têm `business_id` com índice, FK e escopo; o motor já
  recusa documento de uma empresa na etapa de outra.
- `sale_stage_history.transaction_id` passa a receber também o id da OS por item (o motor grava a
  chave do documento), como já acontece com a OS da Oficina. Leitor que lista o histórico da venda
  por esse campo precisa filtrar pelo processo — tratado no PR que cria as OS.
- As programações ganham `bloco`, `trilha`, `ordem`, `nome`, `junta` e `roteiro_etapa_id`, e o
  posto passa a ser opcional (etapa de arte não tem máquina).
- Parcelas de sinal e saldo, prazo pela fila e alocador ficam fora desta fase.
