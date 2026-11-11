---
id: reference-dominio-estagio
name: Domínio — Estágio (máquina de estados)
description: Por que venda e ordem de serviço mudam de fase por um gateway único, com efeitos isolados e histórico append-only — e por que o UPDATE direto é bloqueado de propósito.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-11-05"
nav_group: dominio
nav_order: 40
lente: [operar, construir]
related: [reference-dominio-venda, reference-dominio-os, reference-fluxo-cancelamento]
---

# Domínio — Estágio (máquina de estados)

> Os estágios, as ações e quem pode executá-las **não são enum de coluna** — são dados, por
> business. Dono: [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) +
> [`app/Domain/Fsm/`](../../app/Domain/Fsm). As etapas de cada empresa vêm do **modelo de fluxo**
> que ela usa ([ADR 0435](../decisions/0435-fluxos-por-modelo-porte-atividade.md), proposta) e
> se veem na tela **Sistema › Fluxos e regras** (`/fluxos`). Este documento não lista etapas.

## De onde vêm as etapas: o modelo da empresa

Cada empresa usa um **modelo de fluxo**, escolhido em Sistema › Fluxos e regras. O modelo monta
um processo por assunto — venda, produção, compra, entrega, financeiro — e nem todo modelo tem
todos. Quem ainda não escolheu fica no modelo **legado**, que é o processo `venda_com_producao`
de antes do catálogo; ele continua funcionando como sempre funcionou.

Três consequências que mudam a leitura do resto desta página:

- **A produção é processo próprio** nos modelos: a OS por item da venda anda na Produção, não em
  etapas dentro da venda ([ADR 0436](../decisions/0436-os-por-item-producao-dona-da-os-do-pedido.md),
  proposta).
- **Pago não é etapa obrigatória.** A venda vai de Faturada para Entregue; "pago" é a situação do
  título no Financeiro. A etapa `paid` continua existindo só para quem já está nela
  ([ADR 0434](../decisions/0434-fsm-pedido-retomar-pagamento-fora-das-etapas.md), item 2).
- **Trocar de modelo não move documento.** Venda, OS ou compra que já está num processo termina
  nele (ADR 0435, item 8).

A lista de modelos, processos e etapas mora no catálogo
[`app/Domain/Fsm/Modelos/`](../../app/Domain/Fsm/Modelos), exportado do protótipo
`prototipo-ui/cowork/Wagner/fluxos-data.jsx` e `fluxos-processos.jsx` e conferido por
[`CatalogoModelosFluxoTest`](../../tests/Unit/Fsm/CatalogoModelosFluxoTest.php). Para saber as
etapas de um processo, abra a aba Processos da tela; não copie a lista para outro documento.

## Regra da etapa: "ao entrar em X, faz Y"

Nos modelos, a etapa pode carregar regras no formato **ao entrar em <etapa> → efeito**, cada uma
ligada a um caso de uso (UC-FLX). A regra executa um efeito que já existe no código ou fica
**planejada**: gravada como dado, sem executar nada, até alguém provar que o efeito existente
serve ao caso. Regra planejada nunca muda valor nem estoque (ADR 0435, item 4). A aba Casos de
uso da tela e a página `/documentacao/casos-de-uso` mostram o estado de cada uma.

Algumas regras são escolhas da empresa na aba Fluxos da empresa: margem mínima do orçamento,
reservar material na venda, requisição pelo estoque mínimo e o custo do insumo na compra.

## A regra que explica o resto

**Toda mudança de fase passa por um gateway único.** Não existe "mudar o estágio no banco" nem
"o controller ajusta e salva": o model tem uma trava que **bloqueia o `UPDATE` direto**, e a
transição só acontece pelo serviço que a autoriza.

Isso é deliberado, e o motivo é auditoria: se houvesse um segundo caminho, existiria mudança de
estado que o histórico não vê. Com um caminho só, **o histórico é completo por construção** — e
ele é append-only: registro de transição não se edita nem se apaga.

## Efeito não mora no controller

O que acontece *junto* com a transição — reservar estoque, consumir, liberar, cancelar em
cascata — vive em **peças isoladas** que a máquina aciona. Nenhuma delas está embutida no
código da tela ou do controller.

Ganho prático: dá para saber o que uma ação faz lendo a lista de efeitos, em vez de caçar
`if` espalhado. E dá para mudar o efeito sem tocar em quem o dispara.

## Permissão é por ação e por papel

Quem pode fazer o quê é **dado, não código** — cadastrado por business. Ação marcada como
crítica **sem papel cadastrado** não executa: falha fechada, de propósito. É mais seguro
recusar do que deixar passar por omissão de cadastro.

## O que isso muda no dia a dia

| pergunta | resposta |
|---|---|
| *"a venda sumiu do painel"* | não sumiu — mudou de fase; o painel filtra por estágio |
| *"por que não consigo avançar?"* | a ação exige papel que o seu usuário não tem, ou não existe transição daqui pra lá |
| *"quem mudou isso?"* | o histórico sabe — e não pode ter sido por fora |
| *"posso corrigir direto no banco?"* | **não.** A trava recusa, e é a única defesa que garante o histórico |

## Duas armadilhas catalogadas

Ao mexer aqui, duas coisas já quebraram antes e estão registradas nas proibições: **atributo
dinâmico em model** com nome que não é coluna real (o Eloquent tenta persistir e o `UPDATE`
falha), e **registrar observer dentro do boot do trait** (o Laravel detecta recursão). Ambas
com o padrão correto ao lado, em [`memory/proibicoes.md`](../proibicoes.md) (§ *FSM Pipeline
Canônico*).

Vale ler antes, não depois: as duas custaram hotfix em produção.
