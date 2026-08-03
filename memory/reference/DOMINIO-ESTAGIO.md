---
id: reference-dominio-estagio
name: Domínio — Estágio (máquina de estados)
description: Por que venda e ordem de serviço mudam de fase por um gateway único, com efeitos isolados e histórico append-only — e por que o UPDATE direto é bloqueado de propósito.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: dominio
nav_order: 40
lente: [operar, construir]
related: [reference-dominio-venda, reference-dominio-os, reference-fluxo-cancelamento]
---

# Domínio — Estágio (máquina de estados)

> Os estágios, as ações e quem pode executá-las **não são enum de coluna** — são dados, por
> business. Dono: [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) +
> [`app/Domain/Fsm/`](../../app/Domain/Fsm).

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
