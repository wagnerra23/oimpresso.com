# Licenças (HRM) — charter

- **tela:** `/hrm/leave` (índice + criar + trocar situação + tipos de licença)
- **related_prototype:** PT-01 (lista + drawer PT-02)
- **build F1:** `hrm-page.jsx` (`Licencas`), `hrm-forms.jsx` (`FormLicenca`)
- **fonte:** `EssentialsLeaveController`, `EssentialsLeaveTypeController`, `LeaveRequestService`, `leave/index.blade.php`, `leave_type/index.blade.php`
- **status:** draft · aguarda [W] em D3 (HRM-O0)

## O que a tela faz
Fila de pedidos de licença do negócio, com filtro por colaborador, situação e tipo; aprovar/cancelar em linha ou em lote; drawer com o pedido, o saldo do tipo, conflitos no período e o histórico; aba de saldo por tipo; aba de cadastro de tipos.

## O que a tela NÃO faz
Não calcula férias proporcionais, não gera aviso de férias, não integra com folha (dias de licença não descontam automaticamente), não substitui atestado (o anexo vive em Documentos), não tem aprovação em dois níveis.

## Regras de domínio
- **R1** Situação é uma de três: `pending` · `approved` · `cancelled` (`LeaveRequestService::statusMap`). Não existe "rejeitada" separada de "cancelada".
- **R2** Referência é gerada no servidor com o prefixo de `essentials_settings.leave_ref_no_prefix` (`setAndGetReferenceCount('leave')`) — a UI nunca deixa digitar.
- **R3** Quem tem `essentials.crud_all_leave` vê e cria para qualquer colaborador; quem só tem `essentials.crud_own_leave` vê e cria **apenas o próprio** (filtro é do controller, não da UI).
- **R4** Trocar situação exige `essentials.approve_leave`; a troca notifica o colaborador (`LeaveStatusNotification`) e grava no activitylog.
- **R5** Criar notifica **todos os administradores** do negócio (`NewLeaveNotification`), um e-mail por licença criada — criar para 7 pessoas dispara 7 notificações por admin.
- **R6** Dias do período = `diffInDays + 1` (inclusivo nas duas pontas).
- **R7** Excluir licença exige `essentials.crud_all_leave` e é **hard delete**.
- **R8** Tipo de licença tem limite (`max_leave_count`) e intervalo (`leave_count_interval` = `year`|`month`) — **informativos hoje** (ver A3).
- **R9** `update()` do controller está vazio: licença criada **não pode ser editada**, só ter a situação trocada. A UI não oferece editar.
- **R10** `show()` e `edit()` retornam `essentials::show`/`essentials::edit`, views que não existem → 500. As rotas do resource precisam sair (HRM-O8).
- **R11** O resumo por colaborador (`getUserLeaveSummary`) usa `is_admin` para escolher o `user_id`: gerente com `approve_leave` que não seja admin vê o **próprio** resumo, não o do filtro.

## Achados (viram teste vermelho)
- **A2** `store()` não tem FormRequest: fim antes do início, tipo de outro tenant e motivo vazio passam.
- **A3** `max_leave_count` nunca é aplicado — nem no pedido, nem na aprovação.
- **A4** `EssentialsLeaveTypeController::destroy` vazio: rota responde sem apagar.
- **A1** A tradução PT do módulo chama licença de "Sair" e a lista de "Todas as folhas".

## Non-goals / anti-hooks
Sem aprovação hierárquica, sem cota por colaborador (a cota é por tipo), sem calendário arrastável, sem export PDF nesta onda, sem edição de licença (R9 é lei até [W] mudar).

## Pendências antes de status: live
1. D3 (licença bloqueia marcação?) · 2. PR-2/PR-3 no verde · 3. screenshot [W2] com o vocabulário do PR-8 aplicado.
