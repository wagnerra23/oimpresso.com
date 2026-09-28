---
thread: "25 · gap.md — Relatórios"
dono: "[CL]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main e4289e688 (2026-09-28)
prefixo_tocado: memory/requisitos/Ponto/relatorios-index-gap.md
veredito: "entregue — 1 gap.md; D-REL-FLUXO dá razão ao vivo, D-REL-FILA dá razão ao protótipo"
---
# _saida-25 · gap.md — Relatórios

## Achados

- **Filtros globais no topo já existem no vivo** (`Index.tsx:146-178`, `type="month"`) — é o que `D-REL-FLUXO` decidiu. Por R2, o wizard e o campo Formato que mente saem do **protótipo**.
- **Agrupamento por categoria já existe no vivo** (`Index.tsx:110-120`) — "PARAR SE" cumprido, catch-up do protótipo.
- **O vivo desabilita "Em breve"** (`:224-236`); `D-REL-FILA` manda manter o clique e registrar, com `business_id` e a copy "pedido registrado". Pedido de backend (hoje `abort(501)` em `RelatorioController.php:102`).
- Faltam no vivo: nota de topo com Portaria 671/2021 Anexo I, flag de marcações anuladas (onde ela mora após o wizard sair é **pendente [W]**) e o rodapé legal.

## Como foi medido

- Protótipo relido no build importado no #8067 (`ponto-telas.jsx`, 1.062 linhas). As linhas que a thread citava eram de um build anterior e **não** foram reaproveitadas.
- Lado vivo lido no `.tsx` e no Controller desta base, com linha. Nenhum `.tsx` destas telas tem `data-contract` (`grep -n data-contract` = 0 nas 1 telas), então toda âncora é linha-only até a thread 17.
- O caso vem do charter e do protótipo, nunca do código (§5 2026-06-05). O código só confirma o que o vivo faz.

## Fora do prefixo, declarado

- `memory/requisitos/Ponto/relatorios-index.map.json` — derivados por `scripts/design/gerar-map.mjs` (esqueleto no scratch, depois cópia) com as âncoras medidas preenchidas. `design-code-map-check.mjs --check --strict` rc=0.

## O que não fiz

- Nenhuma mudança em `resources/js/Pages/**` (`nao_toca`). Os pedidos ficam nos gap.md para as threads 17, 27 e 28.
