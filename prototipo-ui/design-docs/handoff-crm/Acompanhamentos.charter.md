---
id: resources-js-pages-crm-acompanhamentos-charter
page: /crm/follow-ups
component: resources/js/Pages/Crm/Acompanhamentos.tsx
related_prototype: prototipo-ui/cowork/crm-blade.jsx, prototipo-ui/cowork/crm-blade-forms.jsx
owner: wagner
status: draft
last_validated: "2026-08-24"
parent_module: Crm
related_adrs: [93, 264, 286]
tier: A
charter_version: 1
related_blade: Modules/Crm/Resources/views/schedule/*.blade.php, schedule_log/*.blade.php
related_controller: Modules/Crm/Http/Controllers/ScheduleController.php, ScheduleLogController.php
---

# Page Charter — /crm/follow-ups (DRAFT)

> **Status:** draft — o Blade tem duas DataTables na mesma tela (acompanhamentos e recorrentes), 7 filtros, modal de criar/editar, modal de log e **duas telas cheias** de criação em lote (antecipado e recorrente). É a tela mais pesada do módulo.

## Mission

A agenda de contatos do comercial e da cobrança: o que foi combinado com quem, quando, por qual canal — e o registro do que aconteceu em cada tentativa.

## Goals

- Lista com os 7 filtros do Blade: contato, atribuído, status, tipo, período, acompanhamento por, categoria
- Rodapé que conta por status e por tipo **o recorte filtrado** (o `footerCallback` do Blade)
- Aba de recorrentes: regra (`follow_up_by` + `recursion_days`) que gera acompanhamento sozinha
- Criar avulso (modal), editar, e registrar log por tentativa (assunto, tipo, janela, descrição, status resultante)
- Criação em lote: **antecipado** (por status de pagamento com faturas, por dias sem pedido, ou por nome) e **recorrente**
- Título e descrição aceitam as tags do módulo (`{invoice_no}`, `{due_amount}`, `{days}`, `{contact_name}`)
- Notificação opcional (SMS/e-mail) com antecedência em minutos/horas

## Non-Goals

- ❌ Não é inbox: conversa de WhatsApp/e-mail vive em Atendimento — aqui fica o compromisso, não a thread
- ❌ Não cobra: gerar acompanhamento de cobrança não baixa título nem manda boleto
- ❌ Não agenda produção (isso é OP) nem visita técnica de OS
- ❌ Lote não cria acompanhamento sem lista revisável — a prévia é obrigatória

## UX Targets

- 1280px sem scroll horizontal nas colunas essenciais (contato, início, status, tipo, atribuído)
- Lote: "Próximo" mostra a prévia com remoção linha a linha antes de salvar
- Log abre sobre a tela, sem perder o filtro da lista

## Automation Anti-hooks

- ❌ Recorrente nunca dispara notificação retroativa ao ser criada
- ❌ Lote não salva com prévia vazia (o Blade avisa "não há nenhum cliente")
- ❌ Nunca lista acompanhamento de outro `business_id`; sem `crm.access_all_schedule`, só os próprios

## Refs

- Blade: `schedule/{index,create,edit,show,create_advance_follow_up,create_recursive_follow_up}`, `schedule/partial/*`, `schedule_log/*`
- Controller: `ScheduleController::{index,store,getInvoicesForFollowUp,getFollowUpGroups}`, `ScheduleLogController`
- Permissões: `crm.access_all_schedule` / `crm.access_own_schedule`
