---
id: modules-crm-pages-crm-nps-index-charter
page: /crm/nps
component: Modules/Crm/Resources/js/Pages/Crm/Nps/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-nps-captura-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-27"
parent_module: Crm
related_adrs: [93, 438]
related_us: [US-CRM-097]
alcance:
  rota: /crm/nps
  rota_nome: crm.nps.index
  permission: crm.nps.ver
  menu_hook: Modules/Crm/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: crm_module
tier: B
charter_version: 1
---

# Page Charter — /crm/nps (DRAFT · painel NPS)

> **Status:** draft. Lacuna P2 "NPS pós-venda" do levantamento de concorrência de 2026-10-25, destravada
> por [W] em 2026-10-27. Padrão de Tela **PT-02** (indicadores em cima, lista embaixo). Forma: quadro B do
> protótipo `prototipo-ui/cowork/Wagner/crm-nps-captura-page.jsx` (ADR 0438). Casos:
> [`Index.casos.md`](Index.casos.md) · SPEC: US-CRM-097.

## Mission

Mostrar ao dono e ao comercial o que os clientes responderam na pesquisa depois da venda ou da OS entregue,
com os detratores na frente para alguém ligar para eles.

## Goals — Features (faz)

- Período de 7, 30 ou 90 dias (padrão 30).
- NPS (−100 a +100), respostas sobre enviadas, distribuição promotores/neutros/detratores.
- Lista de até 100 respostas: nota, cliente, venda ou OS de origem, comentário, quando respondeu; detratores primeiro.
- PT-BR em todo texto.

## Non-Goals — Features (NÃO faz)

- ❌ Responder ao cliente ou abrir atendimento pela tela — o retorno ao detrator é por fora nesta versão.
- ❌ Enviar ou reenviar pesquisa manualmente — o envio é do `crm:nps-enviar`.
- ❌ Mostrar pesquisa de outra empresa.

## UX Targets

- Cabe em 1280px; a tabela rola na horizontal dentro do cartão, a página não.

## Automation Anti-hooks

- Sem `crm.nps.ver` = 403 e o item "NPS" não aparece no menu do CRM.
