---
id: modules-producao-pages-producao-quadro-index-charter
page: /producao/quadro
component: Modules/Producao/Resources/js/Pages/Producao/Quadro/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-21"
parent_module: Producao
related_adrs: [93, 358, 431]
alcance:
  rota: /producao/quadro
  rota_nome: producao.quadro.index
  permission: producao.access
  menu_hook: Modules/Producao/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: producao_module
tier: B
charter_version: 1
---

# Page Charter — /producao/quadro (DRAFT · quadro por setor ou máquina, alocador, simulador de bobina)

> Nasce na thread 06 do playbook de Produção (ADR 0431). Casos: [`Index.casos.md`](Index.casos.md).
> Âncora visual: a vista `Quadro` de `producao-cv-page.jsx` (classes `pcv-*` do bundle). Non-Goals e
> Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar cada OS uma vez, na coluna do setor (ou da máquina) da etapa atual, e dar ao gestor os dois botões
que o backend já tinha: trocar quem e quando começa a etapa (UC-FLX-10) e juntar as OS da comunicação visual
na bobina (UC-FLX-13).

## Goals — Features (faz)

- Quadro por setor ou por máquina; etapa atual = a em andamento, senão a próxima pendente — UC-PCV-05.
- Filtro por texto, "Atrasadas ou vencendo", "Travadas por arte", setor e responsável, guardado na URL; a regra
  é `Pages/Producao/_lib/filtro.ts`, a mesma que a Linha do tempo vai usar — UC-PCV-06.
- Mover a OS para outro setor pelo menu ⋮ ("Mover para …") ou arrastando o cartão, no modo "Por setor", só com
  `producao.etapa.mover`. Só a coluna muda; o evento "Moveu para X (à mão)" fica no histórico; a coluna manual some
  quando o operador inicia ou conclui uma etapa — UC-PCV-07.
- Abre já filtrado pela URL (`?so=atrasadas|arte`, `?agrupar=maquina`), que é como o Painel chega aqui — UC-PCV-02.
- O cartão mostra a arte "Aguardando cliente" ou "Ajuste pedido", da mesma fonte da fila do Painel — UC-PCV-04.
- "Alocar" no cartão da etapa pendente, só com `producao.etapa.mover` — UC-FLX-10 (US-PCP-029).
- Marcar OS da CV com peça medida e "Simular bobina" em lote, com cadastro da largura útil quando falta e o
  desenho das faixas; "Aceitar e gerar romaneios" só com o papel `producao.liberar` — UC-FLX-13 (US-PCP-024).

## Non-Goals — Features (NÃO faz, nesta entrega)

- ❌ Mover concluir, abrir ou fechar etapa ou apontamento (o protótipo só troca a coluna; ver UC-PCV-07 no casos).
- ❌ Painel de eventos ao lado do quadro (os eventos estão no Painel, `/producao/painel`).
- ❌ Aviso de conflito ao alocar (UC-FLX-10: o backend ainda não detecta sobreposição no posto).
- ❌ A metade "na Linha" do UC-PCV-06: a Linha do tempo é a thread 07, que importa o mesmo `_lib/filtro.ts`.

## Refs

- Playbook `06-painel-quadro.md` · ADR 0431 · UI-0013 · `CasosDoPrototipo.php` UC-FLX-10/13
