---
id: resources-js-pages-notificationtemplate-index-charter
page: /notification-templates
component: resources/js/Pages/NotificationTemplate/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/notificacoes-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-09"
parent_module: NotificationTemplate
related_adrs: [93, 104, 358]
related_runbook: memory/requisitos/NotificationTemplate/RUNBOOK-modelos-notificacao.md
tier: B
charter_version: 1
---

# Modelos de notificação — charter draft

## Missão e escopo
Configurar o que a empresa comunica por evento e canal. O primeiro recorte F3 selecionou e consultou modelos reais; Editar modelos abriu o formulário existente por navegação HTML, preservando gravação em lote. O editor React, tags, prévia e ações de envio ficaram na US-NOTIF-004. A flag useV2NotificationTemplates permaneceu OFF; não foi declarado cutover.

## Dados e regras
Três grupos dinâmicos do controller: general_notifications, customer_notifications e supplier_notifications. name/extra_tags e nove campos foram preservados. Permissão send_notification; isolamento por business_id. send_ledger ofereceu só e-mail. Modelos de módulos não foram reduzidos a uma lista fixa. Campos salvos foram mostrados como texto, sem executar HTML. Auto_send* e canais vazios foram sinalizados, sem declarar UC-NOT-18/25 provados.

## Fonte visual e estados
Fonte aprovada: notificacoes-page.jsx e .css; rail de 236px com painel, empilhados no móvel. PageHeader, AppShellV2 e tokens do DS foram reutilizados. A terceira coluna de prévia do pacote ficou para US-NOTIF-004. Estados deste recorte: Deferred/loading, busca sem resultado, ausência de modelos, canal vazio, seleção e canal indisponível. Atalhos / e Esc; listener removido no cleanup e foco de campos preservado.

## Casos e limites
Casos integrais do pacote ficaram em Index.casos.md; critérios do editor não foram encurtados para promover prova parcial. Cinco testes React mediram seleção/busca/atalhos/canais. Backend foi medido no CT 100; pixels, contraste e smoke de navegador ficaram pendentes.
