---
id: modules-crm-pages-crm-funil-index-charter
page: /crm/funil
component: Modules/Crm/Resources/js/Pages/Crm/Funil/Index.tsx
related_prototype: n/a (herda PT-05 Kanban; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-10-26"
parent_module: Crm
related_adrs: [93, 441]
alcance:
  rota: /crm/funil
  rota_nome: crm.funil.index
  permission: crm.funil.ver
  menu_hook: Modules/Crm/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: crm_module
tier: B
charter_version: 1
---

# Page Charter — /crm/funil (DRAFT · funil de vendas em kanban)

> **Status:** draft. Nasce da lacuna P1 "funil de vendas em kanban" do levantamento de concorrência de
> 2026-10-25, destravada por [W] em 2026-10-26 via [ADR 0441](../../../../../../../memory/decisions/0441-funil-de-vendas-sai-da-depreciacao-emenda-0301.md)
> (o funil de negócios sai da depreciação do pipeline CRM). Padrão de Tela **PT-05 Kanban**. Casos:
> [`Index.casos.md`](Index.casos.md) · SPEC: US-CRM-092. Vai a `live` com o screenshot aprovado por [W].

## Mission

Mostrar ao time comercial **onde está cada negócio em andamento** — de Lead a Ganho ou Perdido —, quanto
vale cada etapa e quanto deve fechar no mês, e deixar mover o negócio de etapa arrastando o card.

## Goals — Features (faz)

- 6 colunas fixas (`Deal::STAGES`): Lead, Qualificado, Proposta, Negociação, Ganho, Perdido — com contagem e soma por coluna.
- 4 indicadores: em aberto (valor e quantidade), ganhos, taxa de conversão (ganhos entre os fechados) e previsão do mês (valor × chance da etapa).
- Arrastar o card muda a etapa (dnd-kit, mouse e toque, teclado); Ganho/Perdido pedem confirmação, porque depois o negócio não muda mais.
- Clique no card abre o painel do negócio: valor, cliente, responsável, botões de etapa e link para o cadastro do cliente.
- "Novo negócio" (só com `crm.funil.editar`): título, cliente do cadastro, valor (entrada pt-BR canon) e fechamento previsto; nasce em Lead.
- PT-BR em todo label, placeholder e mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Etapas por empresa — fixas nesta versão (ADR 0441 §4).
- ❌ Leads, propostas, campanhas e follow-ups do pipeline antigo — seguem em depreciação (ADR 0301); o negócio aponta para o cliente do cadastro.
- ❌ Reabrir negócio ganho ou perdido.

## UX Targets

- Cabe em 1280px: o quadro rola na horizontal dentro da área (colunas de 220px no mínimo), a página não.
- Card com alvo de toque ≥ 44px.

## Refs

- Padrão de Tela: [PT-05 Kanban](../../../../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-05-Kanban.md) (`Components/shared/KanbanDndProvider`).
- Protótipo: não há tela de funil no lugar fixo `prototipo-ui/cowork/Wagner/`; a tela segue o PT-05 Kanban. O `crm-page.jsx` do shell antigo do Felipe serviu só de referência de leitura (fora do lugar fixo, não é âncora).
- Constituição UI v2: UI-0013.
