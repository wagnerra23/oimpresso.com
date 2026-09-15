# Presença (HRM) — charter

- **tela:** `/hrm/attendance` (lista, espelho do mês, por turno, por data, clock-in/out, importar)
- **related_prototype:** PT-01
- **build F1:** `hrm-extras.jsx` (`Presenca`)
- **fonte:** `AttendanceController`, `EssentialsUtil::clockin/clockout/getTotalWorkDuration`, `attendance/index.blade.php` (29 KB)
- **status:** draft · aguarda [W] em D1 e D3

## O que a tela faz
Registro de entrada/saída pela web do próprio colaborador, lista de marcações com período e colaborador, espelho do mês cruzando presença × licença × feriado × folga, resumo por turno e por data, importação de planilha.

## O que NÃO faz
Não é ponto legal (Portaria MTP 671/2021 = módulo **Ponto WR2**), não trata intercorrência, não fecha banco de horas, não emite espelho fiscal, não valida biometria nem REP.

## Regras
- **P1** Marcar exige a permissão de função `essentials.allow_users_for_attendance_from_web` — a configuração antiga "permitir usuários" saiu das settings.
- **P2** `essentials_settings.is_location_required` ligado recusa a marcação sem localização do navegador ("você precisa habilitar a localização").
- **P3** Turno **fixo** aplica tolerância (`grace_before/after_checkin`, `..._checkout`); turno **flexível** ignora — não há escala para comparar.
- **P4** Turno com `is_allowed_auto_clockout` fecha a marcação em aberto no `auto_clockout_time` (comando `AutoClockOutUser`).
- **P5** Entrada e saída não podem cair dentro de outra marcação do mesmo colaborador (`validateClockInClockOut`) — a validação existe no formulário, **não** no import (A7).
- **P6** Duração de marcação aberta é contada até agora; a folha usa `getTotalWorkDuration('hour', ...)`.
- **P7** "Ausente" do resumo por data = total de colaboradores − quem marcou; licença e feriado **contam como ausência** (D3).
- **P8** Editar/excluir marcação exige `essentials.crud_all_attendance`; ver a própria exige `essentials.view_own_attendance`.
- **P9** Visibilidade é limitada pelas localidades permitidas do usuário (join em `model_has_permissions`).

## Pendências antes de status: live
D1 (dono da jornada) · D3 (licença bloqueia marcação) · PR-6 (import) · PR-7 (conflito).
