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
- Filtro por texto, "Atrasadas ou vencendo", "Travadas por arte", setor e responsável (no navegador) — parte do UC-PCV-06.
- Abre já filtrado pela URL (`?so=atrasadas|arte`, `?agrupar=maquina`), que é como o Painel chega aqui — UC-PCV-02.
- O cartão mostra a arte "Aguardando cliente" ou "Ajuste pedido", da mesma fonte da fila do Painel — UC-PCV-04.
- "Alocar" no cartão da etapa pendente, só com `producao.etapa.mover` — UC-FLX-10 (US-PCP-029).
- Marcar OS da CV com peça medida e "Simular bobina" em lote, com cadastro da largura útil quando falta e o
  desenho das faixas; "Aceitar e gerar romaneios" só com o papel `producao.liberar` — UC-FLX-13 (US-PCP-024).

## Non-Goals — Features (NÃO faz, nesta entrega)

- ❌ Mover etapa à mão arrastando o cartão (UC-PCV-07: falta fechar o apontamento aberto no backend).
- ❌ Painel de eventos ao lado do quadro (os eventos estão no Painel, `/producao/painel`).
- ❌ Aviso de conflito ao alocar (UC-FLX-10: o backend ainda não detecta sobreposição no posto).
- ❌ Filtro que persiste entre Quadro e Linha (UC-PCV-06 completo depende da thread 07).

## Refs

- Playbook `06-painel-quadro.md` · ADR 0431 · UI-0013 · `CasosDoPrototipo.php` UC-FLX-10/13
