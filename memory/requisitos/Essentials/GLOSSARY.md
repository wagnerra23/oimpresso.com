# Glossário · Essentials

## Attendance
Registro de entrada/saída de colaborador — versão leve do Ponto WR2. Essentials tem feature `view_own_attendance` mas não é substituto do módulo PontoWr2 (sem conformidade Portaria 671).

## Clock in / Clock out
Botões de bater ponto direto na interface (sem REP físico). Gera `ip_address`, `clock_in_note`, `clock_in_location`.

## Document (HRM)
Arquivo vinculado a um colaborador ou cargo (contrato, exame admissional, foto de documento). Storage em `storage/app/essentials/documents/`.

## Knowledge Base
Sistema de base de conhecimento interno. Cada artigo tem categoria, tags, visibilidade (público ou por role).

## Leave
Ausência formal — folga, férias, atestado. Tem `leave_type` associado e passa por aprovação de gestor (`approve_leave`).

## Leave Type
Categoria de ausência. Cadastrado em `essentials_leave_types` com configs tipo "desconta salário?", "desconta do saldo?", "precisa de atestado?".

## Message (HRM)
Mensagem interna entre colaboradores — tipo email simplificado. Tem `user_id`, `to_user_id`, `subject`, `body`, `read_at`.

## Payroll
Folha de pagamento calculada com base em attendance + leaves + bonus do mês. Essentials gera relatório exportável; fechamento financeiro vai pra Accounting.

## Reminder
Lembrete agendado (task com data). Pode ser pessoal ou associado a um cliente/contato (CRM).

## To-do
Tarefa simples — title + description + deadline + assignees. Tem sub-resources: comments, attachments.
