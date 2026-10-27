---
id: modules-crm-pages-crm-nps-responder-charter
page: /nps/{token}
component: Modules/Crm/Resources/js/Pages/Crm/Nps/Responder.tsx
related_prototype: prototipo-ui/cowork/Wagner/prototipos/crm-nps-e-captura.html
owner: wagner
status: draft
last_validated: "2026-10-27"
parent_module: Crm
related_adrs: [93, 438]
related_us: [US-CRM-097]
alcance:
  rota: /nps/{token}
  rota_nome: crm.nps.responder
  permission: publica
  menu_hook: n/a (página pública, aberta pelo link do WhatsApp)
  pacote: crm_module
tier: B
charter_version: 1
---

# Page Charter — /nps/{token} (DRAFT · resposta pública do NPS)

> **Status:** draft. Lacuna P2 "NPS pós-venda" do levantamento de concorrência de 2026-10-25, destravada
> por [W] em 2026-10-27. Página pública, **sem AppShellV2**. Forma: quadro A do protótipo
> `prototipo-ui/cowork/Wagner/prototipos/crm-nps-e-captura.html` (ADR 0438). Casos:
> [`Responder.casos.md`](Responder.casos.md) · SPEC: US-CRM-097.

## Mission

Deixar o cliente dar uma nota de 0 a 10 em um toque, no celular, pelo link que chegou no WhatsApp depois
da venda ou da OS entregue.

## Goals — Features (faz)

- Pergunta com o nome da empresa, 11 botões (0 a 10) de 44px, comentário opcional de até 1.000 caracteres.
- Enviar grava a nota; depois disso a página mostra a nota que valeu e o agradecimento.
- PT-BR em todo texto.

## Non-Goals — Features (NÃO faz)

- ❌ Mostrar cliente, venda, OS ou valor — a página só conhece o nome da empresa (o link pode ser repassado).
- ❌ Trocar a nota depois de enviada — a primeira resposta vale.
- ❌ Login ou cadastro.

## UX Targets

- Cabe em 360px sem rolagem lateral; botões de nota ≥ 44px de altura.

## Automation Anti-hooks

- Flag `CRM_NPS_ENABLED` desligada ou token desconhecido = 404, a mesma resposta (não revela se o token existe).
