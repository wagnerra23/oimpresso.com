# Pedido ao Design — Financeiro antes de Clientes

> **De:** Claude Code `[CL]` → **Para:** Cowork `[CC]` (o Claude do `claude.ai/design`) · **Data:** 2026-09-23
> **Plano que isto serve:** [`RUNBOOK-paridade-ondas.md` §12](../../requisitos/Financeiro/RUNBOOK-paridade-ondas.md)
> (o manual de ondas do Financeiro é o único plano do módulo; não há documento paralelo).
> Append-only: a resposta vem num arquivo novo.

## O que mudou

[W] decidiu começar a troca de layout pelo **Financeiro**, não por Clientes: Clientes é a única
família já em uso por cliente, e ninguém usa o Financeiro ainda. O pedido de Clientes
([`CODE_NOTES.prompt-cowork-onda2-clientes-2026-09-23.md`](CODE_NOTES.prompt-cowork-onda2-clientes-2026-09-23.md))
continua valendo, mas fica atrás na fila.

Primeiras telas: **DRE** e depois **Fluxo de caixa** (as duas em `financeiro-telas-extras.jsx`), uma
por onda. Comparação na empresa 1, período **julho de 2026** (o último mês com lançamento).

## Divisão de trabalho

- **Forma** (layout, cor, tipografia, rótulo, estado visual) → o protótipo manda ([ADR UI-0029](../../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)). É com você.
- **Comportamento e valores** (o que a tela calcula e mostra) → manda o teste verde. O protótipo não
  cria nem remove número; toda onda prova que os valores exibidos ficaram idênticos.

## O que precisamos do lado do Design

| # | Tela | Ponto | Pedido |
|---|---|---|---|
| 1 | Fluxo | ~~4 KPIs no código × 1 medido em 2026-09-08~~ — **resolvido pelo Code na FIN-0a (2026-09-23)**: com o DS carregado o protótipo mostra os 4; a medição antiga rodou sem o DS | Nada a fazer |
| 2 | Fluxo | O subtítulo do gráfico diz "barras = movimento líquido do dia" (`:126`), mas as barras desenham o **saldo**; o `moveBar` (`:145`) é calculado e nunca desenhado | Corrigir o subtítulo, ou desenhar as barras de movimento de fato |
| 3 | DRE | ~~Seletor Mês/Trimestre/Ano/12m~~ — **medido na FIN-0a**: com o DS carregado o protótipo já mostra os 4, com 3 desabilitados, igual à produção | Nada a fazer |
| 4 | ProvaViva | O charter aponta um HTML de `legado/financeiro-prova-viva/` removido no #7445 | Dizer se existe desenho atual para essa tela, ou se ela segue só o Padrão de Tela |
| 5 | PlanoContas, Relatórios | Há `TelaPContas` e `financeiro-relatorios.jsx` no protótipo, mas nenhum charter os declara como âncora | Dizer se são o desenho oficial dessas telas (a decisão final de ancorar é [W], tela a tela) |

Os dados de demonstração do protótipo ficam lá; o Code **não** porta valor nenhum do protótipo.

## Como devolver

Ajuste no projeto Cowork e **regenere o bundle ao fim do ciclo** (rotina obrigatória, decisão [W]
2026-09-06). O Code baixa pela rota do painel (`node scripts/design/protocolo.config.mjs`) e mede
com `design-diff.mjs`. Status do lado do Code: [`HANDOFF.md`](HANDOFF.md).
