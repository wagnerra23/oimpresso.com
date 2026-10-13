---
id: resources-js-pages-notificationtemplate-index-charter
page: /notification-templates
component: resources/js/Pages/NotificationTemplate/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/notificacoes-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: NotificationTemplate
related_adrs: [93, 104, 358]
related_runbook: memory/requisitos/NotificationTemplate/RUNBOOK-modelos-notificacao.md
related_us: [US-NOTIF-003, US-NOTIF-004]
tier: B
charter_version: 1
---

# Modelos de notificação — charter draft

## Missão e escopo
Configurar o que a empresa comunica por evento e canal. O primeiro recorte F3 selecionou e consultou modelos reais. O segundo (13/10) tornou o painel editável: texto/HTML por canal, CC/BCC, tags no cursor, automáticos e teste de envio, gravando só os modelos alterados num único POST do mesmo template_data. O editor clássico segue em ?legacy=1. Modo Visual, prévia e restaurar padrão ficaram pendentes na US-NOTIF-004. A flag useV2NotificationTemplates permaneceu OFF; não foi declarado cutover. Em 13/10 [W] mandou ligar: o default da flag virou ON para todas as empresas (rollback = OFF no GrowthBook); o smoke em produção é a prova pendente.

## Dados e regras
Três grupos dinâmicos do controller: general_notifications, customer_notifications e supplier_notifications. name/extra_tags e nove campos foram preservados. Permissão send_notification; isolamento por business_id. send_ledger ofereceu só e-mail. Modelos de módulos não foram reduzidos a uma lista fixa. O corpo HTML é editado como texto em textarea, sem renderizar nem executar HTML. Automáticos só em new_sale e payment_reminder, como na Blade; os demais saem 0. O teste de envio usa a versão gravada e só o canal e-mail, que é o único que a rota envia. Auto_send* e canais vazios foram sinalizados, sem declarar UC-NOT-18/25 provados.

## Fonte visual e estados
Arquétipo aprovado: configuração com rail e painel, sem DataTable. Fonte aprovada: notificacoes-page.jsx e .css; rail de 236px com painel, empilhados no móvel. PageHeader, AppShellV2 e tokens do DS foram reutilizados. A terceira coluna de prévia do pacote ficou para US-NOTIF-004. Estados deste recorte: Deferred/loading, busca sem resultado, ausência de modelos, canal vazio, seleção e canal indisponível. Atalhos / e Esc; listener removido no cleanup e foco de campos preservado.

## Casos e limites
Casos integrais do pacote ficaram em Index.casos.md; critérios do editor não foram encurtados para promover prova parcial. Cinco testes React mediram seleção/busca/atalhos/canais. Backend foi medido no CT 100; pixels, contraste e smoke de navegador ficaram pendentes.
