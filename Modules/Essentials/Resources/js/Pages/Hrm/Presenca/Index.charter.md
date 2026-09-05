---
id: resources-js-pages-hrm-presenca-index-charter
page: /hrm/attendance
component: Modules/Essentials/Resources/js/Pages/Hrm/Presenca/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-09-04"
parent_module: Essentials
related_adrs: [93, 104, 114, 264]
tier: B
charter_version: 1
---

# Page Charter — /hrm/attendance · Presença (HRM) (DRAFT)

> **Status:** draft. A `.tsx` **ainda não existe** — este charter aterrissa no PR-1 do
> [`PEDIDO-CL-hrm.md`](../../../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)
> (onda HRM-O5) e a Page vem no PR-9. **Bloqueado por D1 e D3** do HRM-O0 — sem elas o
> comportamento da tela não está decidido, então este charter descreve o backend de hoje,
> não o alvo.
>
> Backend: `AttendanceController` (7 endpoints) + `EssentialsUtil::clockin/clockout/
> getTotalWorkDuration`, rotas em [`Modules/Essentials/Routes/web.php`](../../../../../Routes/web.php).
>
> **Sobre `related_prototype`:** o build F1 é `hrm-extras.jsx` (`Presenca`) em
> [`prototipo-ui/cowork/`](../../../../../../../prototipo-ui/cowork/hrm-extras.jsx), mas o hub
> `hrm-page.jsx` se declara porte do `nav_hrm.blade` (lápide §5 2026-08-28 — não promover porte
> reverso a âncora de design). A âncora declarada é o Padrão de Tela.

---

## Mission
Registro de entrada/saída pela web do próprio colaborador, lista de marcações por período e
colaborador, espelho do mês cruzando presença × licença × feriado × folga, resumo por turno e
por data, importação de planilha.

---

## Goals — Features (faz)
- Clock-in/clock-out do próprio colaborador pela web.
- Lista de marcações filtrada por período e colaborador.
- Espelho do mês (uma coluna por dia) e resumos por turno e por data.
- Importação de planilha de marcações.

---

## Non-Goals — Features (NÃO faz)
- ❌ **Não é ponto legal.** A Portaria MTP 671/2021 é o módulo **Ponto WR2** — esta tela é
  apontamento operacional (quem está no balcão agora). Ver **D1**.
- ❌ Não trata intercorrência nem fecha banco de horas.
- ❌ Não emite espelho fiscal.
- ❌ Não valida biometria nem REP.

---

## Regras de domínio
- **P1** Marcar exige a permissão de função `essentials.allow_users_for_attendance_from_web` —
  a configuração antiga "permitir usuários" saiu das settings.
- **P2** `essentials_settings.is_location_required` ligado recusa a marcação sem localização do
  navegador.
- **P3** Turno **fixo** aplica tolerância (`grace_before/after_checkin`, `..._checkout`); turno
  **flexível** ignora — não há escala para comparar.
- **P4** Turno com `is_allowed_auto_clockout` fecha a marcação em aberto no `auto_clockout_time`
  (comando `AutoClockOutUser`).
- **P5** Entrada e saída não podem cair dentro de outra marcação do mesmo colaborador
  (`validateClockInClockOut`) — a validação existe no formulário, **não** no import (A7).
- **P6** Duração de marcação aberta é contada até agora; a folha usa
  `getTotalWorkDuration('hour', ...)`.
- **P7** "Ausente" do resumo por data = total de colaboradores − quem marcou; licença e feriado
  **contam como ausência** hoje (ver **D3**).
- **P8** Editar/excluir marcação exige `essentials.crud_all_attendance`; ver a própria exige
  `essentials.view_own_attendance`.
- **P9** Visibilidade é limitada pelas localidades permitidas do usuário (join em
  `model_has_permissions`).

---

## Achados
- **A7** O import de presença não reusa `validateClockInClockOut` linha a linha, faz rollback
  total em vez de relatório de linhas recusadas, e usa `ini_set('max_execution_time', 0)` em vez
  de fila (PR-6).

---

## Anti-hooks
- ⛔ Não tratar esta tela como espelho de ponto legal — quem responde pela Portaria 671/2021 é
  o módulo Ponto WR2 (D1 decide o dono da jornada).
- ⛔ Não escrever UC sobre bloqueio de marcação em período de licença antes de **D3**: hoje
  conviver é possível, e inventar o comportamento no charter seria anti-padrão que parece canon.

---

## Pendências antes de `status: live`
1. **D1** (dono da jornada: HRM × Ponto WR2) · 2. **D3** (licença bloqueia marcação?)
3. PR-6 (import) · 4. PR-7 (conflito licença × presença) · 5. PR-9 cria a `Index.tsx`.
