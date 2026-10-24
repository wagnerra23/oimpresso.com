---
id: modules-producao-pages-producao-linha-index-charter
page: /producao/linha
component: Modules/Producao/Resources/js/Pages/Producao/Linha/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-22"
parent_module: Producao
related_adrs: [93, 358, 431]
alcance:
  rota: /producao/linha
  rota_nome: producao.linha.index
  permission: producao.access
  menu_hook: Modules/Producao/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: producao_module
tier: B
charter_version: 1
---

# Page Charter — /producao/linha (DRAFT · linha do tempo por máquina, reprogramar, gargalo)

> Nasce na thread 07 do playbook de Produção (ADR 0431). Casos: [`Index.casos.md`](Index.casos.md).
> Âncora visual: a vista `Linha` de `producao-cv-page.jsx` (classes `pcv-*` do bundle), na versão ajustada às
> regras de Fluxos pelo #9763. Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar o dia de cada máquina — o que está programado, quanto da capacidade usa e o que trava — e deixar o PCP
reprogramar arrastando, com a sugestão do gargalo pronta para aplicar e desfazer.

## Goals — Features (faz)

- Uma linha por máquina com os blocos do dia, a carga "X de Y h" contra a capacidade do posto e o fim do turno da
  empresa marcado; bloco travado por arte ou por falta de material aparece tracejado; bloco que passa do fim do
  turno ou da entrega prometida tem borda de estouro — UC-PCV-08.
- "Hoje" e "Próximo dia útil": o dia seguinte pula folga e feriado do calendário de produção (UC-FLX-06) — UC-PCV-08.
- Gargalo: máquina acima da capacidade recebe uma sugestão — a menor etapa pendente que resolve vai para outra
  máquina do mesmo setor com folga, cujo responsável a opera — com "Aplicar" e, depois, "Desfazer" — UC-PCV-09.
- Reprogramar arrastando o bloco (15 em 15 min; outra linha = outra máquina do mesmo setor) ou com Alt+setas, só
  com `producao.etapa.mover`. Só a etapa pendente se move; recusa outro setor e horário ocupado (diz com qual OS);
  trocando para máquina com operadores cadastrados, pergunta quem faz (UC-FLX-08). O responsável recebe aviso
  no app — UC-PCV-30.
- O filtro do Quadro (busca, situação, setor, responsável), na URL e pela mesma regra `../_lib/filtro.ts` — UC-PCV-06.
- "Eventos das máquinas hoje", filtrável pela máquina clicada — UC-PCV-29.

## Non-Goals — Features (NÃO faz, nesta entrega)

- ❌ Abrir a ficha da OS ao clicar no bloco (a ficha é a thread 08).
- ❌ "Mesma mídia: dá para agrupar" do protótipo (encaixe de mídia é versão 2, D-ENCAIXE).
- ❌ Gargalo por IA ou por mais de uma etapa de uma vez (D5: regra simples na V1).

## Refs

- Playbook `07-linha.md` · ADR 0431 · UI-0013 · `Producao.casos.md` UC-PCV-08/09/30 · protótipo ajustado em #9763
