---
sessao: "_saida-R1"
thread: "R1 · conferir as 11 ondas do EXPORT-FORJA (2026-09-03) contra o main"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 7e4a6ebfda
---
# _saida-R1

## Resultado

As 11 ondas do `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` **já fecharam no `main` em 2026-09-02/03**.
Nenhuma está "não iniciada". Cada uma tem um PR de entrega e um PR de fechamento escrito contra o
alvo do export. A numeração do export não é a do `PARIDADE §11`: a lista do Trabalho, por exemplo,
é Onda 3 aqui e Onda 4 lá. Por isso a coluna "entrega" cita o PR pelo número, não pela onda.

| onda | seção | estado | entrega | fechamento contra o export |
|---|---|---|---|---|
| 1 | shell/header + topnav | entregue | #6553 · #6563 | #6682 (badge de pendências em toda tela do hub) |
| 2 | Trabalho · chrome | parcial (19 de 20) | #6582 | #6694 declara 18 de 20; depois #6691 entregou o botão "Papéis" (abre `ForjaRunbook.tsx`). Falta "Perguntar" (`forja-ia`), superfície sem receptor |
| 3 | Trabalho · lista | entregue (11 de 13, ausências declaradas) | #6582 · #6669 | #6693 |
| 4 | Trabalho · quadro | entregue | #6616 | #6695 (7 colunas em vez de 6 é decisão [W] de 2026-08-11, não bug) |
| 5 | Trabalho · gantt | parcial | #6624 (moldura) · #6644 (smoke) | #6692: o corpo `.fj-g-*` × `@svar-ui/react-gantt` é decisão [W] em aberto |
| 6 | Aprovações | entregue | #6571 | #6678 (tipografia ao token do ramp) |
| 7 | Saúde | entregue | #6572 | #6679 (colisão UC-FORJA-15 → 18) |
| 8 | MCP + Handoffs | entregue | #6575 | #6684 (`design-diff --compare` = 0 DIVERGE em prod) |
| 9 | Changelog | entregue | #6591 | #6675 (tipografia/gap) |
| 10 | Integrador | entregue | #6620 | #6676 (tipografia/gap + TabBar do DS) |
| 11 | Triagem | não construída, por decisão [W] | #6617 (revogação de 7 das 8 telas de `/project-mgmt`) | #6683: `FjTriagemView` é órfã no protótipo e a Triagem não cabe em Aprovações |

## Como foi medido

1. `git log --since=2026-09-03 origin/main -- <arquivo-âncora>` para cada arquivo do §1 do export.
2. `git log --grep=forja` filtrado por `onda|export|paridade` na mesma janela.
3. O corpo de cada PR de fechamento foi lido (`git log -1 --format=%B`), não só o título.

Não reexecutei `design-diff --compare` em nenhuma onda. Os vereditos "0 DIVERGE" e "N de M" acima
são os registrados nos PRs de fechamento, com a data deles.

## O que isto muda nas outras threads

- Nenhuma thread deste playbook refaz layout de onda: o trabalho que sobra é contrato (casos, UC, alvo, scorecard).
- Continuam com [W]: o corpo do Gantt (Onda 5), o alvo de toque (81 de 118 botões < 24×24, §2 do export) e o receptor das 8 superfícies sem dono (`issue-drawer`, `cmdk`, `notifs`, `novo-issue`, `runbook`, `handoff`, `ia`, `rag`). O `runbook` ganhou receptor parcial pelo #6691.
