---
id: modules-producao-pages-producao-painel-index-charter
page: /producao/painel
component: Modules/Producao/Resources/js/Pages/Producao/Painel/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-21"
parent_module: Producao
related_adrs: [93, 358, 431]
alcance:
  rota: /producao/painel
  rota_nome: producao.painel.index
  permission: producao.access
  menu_hook: Modules/Producao/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: producao_module
tier: B
charter_version: 1
---

# Page Charter — /producao/painel (DRAFT · o que trava a produção hoje)

> Nasce na thread 06 do playbook de Produção (ADR 0431), junto com o Quadro. Padrão de Tela PT-04
> Dashboard. Casos: [`Index.casos.md`](Index.casos.md). Âncora visual: a vista `Painel` de
> `producao-cv-page.jsx` (classes `pcv-*` do bundle). Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Mostrar ao gestor, às 8h, o que trava a produção hoje e onde agir: indicadores que abrem o Quadro já
filtrado, um alerta por problema com uma ação, a fila de arte esperando o cliente, a carga das máquinas
e o que a produção fez por último.

## Goals — Features (faz)

- 4 indicadores: OS em produção, atrasadas ou vencendo, arte travando, refugo na semana — UC-PCV-01.
- Os três primeiros abrem o Quadro filtrado (`?so=atrasadas`, `?so=arte`) — UC-PCV-02.
- "Atrasadas ou vencendo" e "arte travando" saem dos mesmos cartões do Quadro: o número é um só — UC-PCV-03.
- Alertas só quando há problema (atraso, arte, máquina acima do dia), cada um com uma ação — UC-PCV-01.
- Fila de aprovação de arte com o que o cliente disse e quando, ajuste pedido primeiro — UC-PCV-04.
- "Acontecendo agora": os eventos da produção da empresa, sem os que falam de uma pessoa — UC-PCV-29.
- Carga das máquinas hoje: horas das etapas previstas para hoje ÷ horas do dia do posto.
- É a entrada do item Produção no menu (vista inicial do protótipo).
- Na fila de arte, "Reenviar link" (aguardando) e "Enviar nova prova" (ajuste pedido, com o link do arquivo
  novo) geram link novo e o anterior deixa de valer; com WhatsApp ligado o cliente recebe, senão o link aparece
  para mandar à mão — UC-PCART-07. Liberado por [W] em 2026-10-27 (antes era Non-Goal: o banco só guarda o hash).

## Non-Goals — Features (NÃO faz, nesta entrega)

- ❌ Refugo em R$ e o indicador levando ao Custo (a fórmula de custo e a tela de Custo são da thread 08).
- ❌ Gargalo com sugestão de reprogramação e "Ver na linha do tempo" (UC-PCV-09 · thread 07).
- ❌ Produtividade por setor (UC-PCV-27, LGPD) e aproveitamento de mídia agregado (UC-PCV-26).

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE).
- Uma consulta de cartões por abertura (prop deferida `painel`).

## Refs

- Playbook `06-painel-quadro.md` · ADR 0431 · UI-0013 · PT-04 Dashboard · US-PCP-017
