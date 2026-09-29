---
date: "2026-09-29"
time: "11:05 BRT"
slug: ponto-header-rolagem-horizontal
tldr: "Rolagem horizontal nas telas do Ponto depois do #8118 corrigida no #8152 (faixa de abas num Grid de 1 coluna minmax(0,1fr)). Mergeado, deployado no ffab91c4c, smoke em prod verde a 1280 e 1440 nas 3 telas. O bump de last_run das 6 telas já tinha sido feito no #8154."
prs: [8152]
decided_by: [W]
next_steps:
  - "Nada pendente desta thread."
---

# Ponto: rolagem horizontal do header de módulo

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- Handoff irmão do dia: [2026-09-29-1024 thread 17](2026-09-29-1024-ponto-thread-17-data-contract-fechada.md).

## O que aconteceu
Depois do #8118 (PontoAreaHeader + 12 abas), `/ponto/colaboradores` a 1280 tinha `main` 1020 × scrollWidth 1280.

Causa medida no DOM de prod: `main.main-body` é flex em coluna, e o wrapper `mx-auto max-w-7xl` da página não estica, fica com a largura do conteúdo (fit-content). O min-content do `PageHeaderTabs` com 12 abas é 1492px, e ele forçava o wrapper até 1280.

Opções medidas e descartadas:
- `min-width:0` no wrapper não resolve.
- `contain:inline-size` zera a rolagem, mas encolhe o header para 583px.

Conserto ([#8152](https://github.com/wagnerra23/oimpresso.com/pull/8152), no `PageHeaderTabs`, só com `scrollable`): a barra fica dentro de `<Grid cols={1} gap={0}>`, que é `repeat(1, minmax(0,1fr))`. O primeiro commit usava um grid solto, e o ratchet `Layout primitives` reprovou. O Ponto é o único consumidor de `scrollable`, e o conserto não toca `PontoAreaHeader` nem `PontoSubNav`, então o #8123 e o #8125 herdam sem conflito.

## Smoke em prod (bundle real, deploy `ffab91c4c`, run 36574792480)
Sonda: `main.clientWidth × main.scrollWidth`, com confirmação de que o wrapper `grid-cols-1` está no DOM servido.

| tela | 1280 | 1440 |
|---|---|---|
| Colaboradores | 1020×1280 → 1020×1020 | 1180×1280 → 1180×1180 |
| Aprovações | 1005×1280 → 1005×1005 | 1165×1280 → 1180×1180 |
| Configurações | 1005×1152 → 1005×1005 | 1165×1165 (já estava ok) |

As 12 abas seguem lá e a faixa rola dentro dela. O selo "Atualizado" fica dentro da tela. Controle negativo: tirar o grid do DOM volta 1020×1280.

## Persistência
- git: #8152 mergeado (`d6392e714`).
- Relatórios enviados à sessão gerente (cool-burnell), inclusive a correção sobre o G-6.
- BRIEFING: não aplicável (correção de layout, sem capacidade nova).

## Próximos passos
Nada pendente. O `last_run` das 6 telas do Ponto (G-6 herdado do #8118) já foi atualizado pelo #8154 às 13:23Z. Conferido: o casos-gate não acusa violação nova.

## Lições
- Fila de deploy: um deploy novo cancela o anterior que ainda estava na fila. O deploy do próprio merge foi cancelado, então é preciso esperar o primeiro deploy que *contém* o merge (checar com `merge-base --is-ancestor`), não o do merge commit.
- `gh run list` devolveu duas vezes um retrato velho (runs de 26/08 e 23/09). Uma leitura isolada não serve para decidir. É a LC-24, sem ocorrência nova registrada, porque a segunda leitura resolveu.
- Pendência que eu listei já tinha sido fechada por outra sessão. Antes de reportar pendência herdada, conferir `git log origin/main -- <arquivo>`.
