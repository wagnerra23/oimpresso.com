---
slug: 0014-essentials-pontowr2-integracao
number: 14
title: "Integração PontoWR2 × Essentials (HRM)"
type: adr
status: aceito
authority: reference
lifecycle: ativo
decided_at: "2026-04-21"
decided_by: [E]
module: pontowr2
quarter: 2026-Q2
---

# ADR 0014 — Integração PontoWR2 × Essentials (HRM)

**Data:** 2026-04-21
**Status:** Aceita
**Autora:** Eliana (WR2 Sistemas) — levantamento sessão 10

---

## Contexto

O módulo **Essentials** é o HRM nativo do UltimatePOS. O **PontoWR2** é o módulo de ponto eletrônico da WR2. Ambos lidam com jornada de trabalho, mas têm escopos complementares. Este ADR documenta a relação entre eles e as regras de não-duplicação de entidades.

---

## Entidades do Essentials (relevantes para PontoWR2)

| Entidade Essentials | Propósito | Relação com PontoWR2 |
|---------------------|-----------|----------------------|
| `Shift` | Define turno de trabalho (horário início/fim, dias da semana) | **PontoWR2 lê** Shift para calcular tolerâncias CLT |
| `EssentialsUserShift` | Associa usuário a um Shift | PontoWR2 usa para identificar escala do colaborador |
| `EssentialsAttendance` | Marcação de presença simples (check-in/out manual) | PontoWR2 **não duplica** — usa suas próprias `Marcacao` (REP-P) |
| `EssentialsLeave` | Afastamentos aprovados (férias, licença) | PontoWR2 respeita afastamentos como `Intercorrencia` tipo ausência |
| `EssentialsHoliday` | Feriados cadastrados | PontoWR2 usa para cálculo de HE em feriado (Art. 73 CLT) |
| `PayrollGroup` / `PayrollGroupTransaction` | Folha de pagamento | PontoWR2 alimenta com horas apuradas |

---

## Decisão

### 1. PontoWR2 NÃO duplica entidades do Essentials
- **Shift** do Essentials é a fonte de verdade para horário contratual
- PontoWR2 usa `escala_atual_id` no `Colaborador` para referenciar `Shift`
- PontoWR2 cria sua própria tabela `ponto_escalas` apenas para configurações específicas de ponto (tolerâncias, intervalos mínimos CLT) — campos que Essentials não tem

### 2. Separação de responsabilidades
| Responsabilidade | Módulo |
|------------------|--------|
| Definir horário contratual | Essentials (Shift) |
| Registrar batidas de REP-P/AFD | PontoWR2 (Marcacao — append-only) |
| Calcular HE, atrasos, banco de horas | PontoWR2 (ApuracaoService) |
| Aprovar/rejeitar afastamentos | Essentials (EssentialsLeave) |
| Gerar folha de pagamento | Essentials (Payroll) — alimentado por PontoWR2 |
| Feriados | Essentials (EssentialsHoliday) — lido pelo PontoWR2 |

### 3. Fluxo de integração
```
Essentials.Shift
    └─→ PontoWr2.Colaborador.escala_atual_id (FK para Shift)
    └─→ PontoWr2.ApuracaoService.calcular() usa horário do Shift

PontoWr2.ApuracaoService (resultado mensal)
    └─→ horas_extras → Essentials.PayrollGroupTransaction (lançamento)
    └─→ banco_horas_saldo → PontoWr2.BancoHoras (ledger próprio)

Essentials.EssentialsHoliday
    └─→ PontoWr2.ApuracaoService.isFeriado() para cálculo HE 100%

Essentials.EssentialsLeave (aprovado)
    └─→ PontoWr2.Intercorrencia (criada automaticamente via observer)
```

---

## Consequências

- **Positivo:** Evita dados duplicados. Essentials continua sendo a fonte de RH; PontoWR2 é especialista em conformidade legal de ponto.
- **Positivo:** Usuários do Essentials que não precisam de ponto REP-P continuam funcionando sem o PontoWR2.
- **Risco:** Se Essentials.Shift mudar estrutura em versão futura do UltimatePOS, PontoWR2 pode quebrar. Mitigação: observer no Shift que invalida cache de escalas.
- **Pendente:** Implementar observer EssentialsLeave → Intercorrencia (sessão futura).

---

> **Referências:** ADR 0004 (bridge colaborador), ADR 0007 (banco horas ledger), Portaria MTP 671/2021

---

## Emenda 2026-09-05 / 2026-09-24 — a presença web do HRM cede ao Ponto ([W])

> Emenda datada, registrada pela thread 09 do playbook SINCRONIZAR Hrm
> (`prototipo-ui/cowork/Wagner/cowork-inbox/hrm/playbook/09-presenca-sai.md`). O texto acima
> fica como estava: ele é o fato de 2026-04-21. O que muda daqui pra frente está aqui.

**D1 (decidida por [W] em 2026-09-05):** o `Modules/Ponto` é o **dono único da jornada**. A
presença web do Essentials (`essentials_attendances`: botão de entrada/saída no cabeçalho, tela
`/hrm/attendance`, importação de planilha, API `clock-in`/`clock-out` do Connector) deixa de
existir como caminho de registro.

Isso contradiz uma consequência da versão de 2026-04-21: *"usuários do Essentials que não precisam
de ponto REP-P continuam funcionando sem o PontoWR2"* **deixa de valer** para a presença.

**D3 (decidida por [W] em 2026-09-05):** licença aprovada bloqueia a marcação e sai da conta de
ausência. O guard nasce **no Ponto**, e "bloquear" é impedir a criação da marcação, nunca apagar
depois (`ponto_marcacoes` é append-only, Portaria MTP 671/2021). Fica como pedido ao dono do Ponto;
não é executado por esta emenda.

**Folha:** passa a ler o Ponto (`ponto_apuracao_dia`, `ponto_banco_horas`), não
`essentials_attendances`. A execução disso é a D2 (projeto com ADR própria).

**As 5 chaves de presença das Configurações do HRM se aposentam ([W] em 2026-09-24).** São
`grace_before_checkin`, `grace_after_checkin`, `grace_before_checkout`, `grace_after_checkout` e
`is_location_required`. Elas **não migram** para uma configuração do Ponto, porque o Ponto já as
cobre por lei:
- a tolerância é a do Art. 58 §1º da CLT, 5 minutos por marcação e 10 por dia
  (`Modules/Ponto/Config/config.php`, bloco `clt`). Uma janela por negócio acima disso seria ilegal;
- a localização é obrigatória na marcação por celular (REP-P, Portaria 671 Anexo I §10,
  `StoreMarcacaoRequest`). Não é opção.

### Rotas do HRM → destino no Ponto

| rota do HRM (antes) | agora |
|---|---|
| `GET /hrm/attendance` e `/hrm/attendance/{…}` (resource inteiro) | 301 → `/ponto/espelho` |
| `POST /hrm/import-attendance` | 301 → `/ponto/importacoes` |
| `POST /hrm/clock-in-clock-out` | 301 → `/ponto` |
| `POST /hrm/validate-clock-in-clock-out` | 301 → `/ponto` |
| `GET /hrm/get-attendance-by-shift` | 301 → `/ponto/espelho` |
| `GET /hrm/get-attendance-by-date` | 301 → `/ponto/espelho` |
| `GET /hrm/get-attendance-row/{user_id}` | 301 → `/ponto/espelho` |
| `GET /hrm/user-attendance-summary` | 301 → `/ponto/espelho` |
| API Connector `GET get-attendance/{user_id}` · `POST clock-in` · `POST clock-out` | 410 JSON, apontando para o Ponto |

Junto, no mesmo PR:
- desagendado o cron `pos:autoClockOutUser`. Jornada congelada com o cron vivo fecharia marcação que
  ninguém mais abre;
- removido o botão de entrada/saída do cabeçalho;
- removido o ponteiro `essentials_user_model` do config do Ponto, que apontava para uma entidade do
  lado que cede.

O código do `AttendanceController` e o dado de `essentials_attendances` **não** são apagados aqui:
migrar esse dado para o Ponto é outro PR, com dupla prova (regra de VALOR em `proibicoes.md`).

---

## Emenda 2026-09-29 — dono por papel: escala é do Ponto (D4), feriado é do HRM e lido pelo Ponto (D5) ([W])

> Emenda datada, registrada pela thread 12 do playbook SINCRONIZAR Hrm
> (`prototipo-ui/cowork/Wagner/cowork-inbox/hrm/playbook/12-dono-por-papel.md`). O texto de
> 2026-04-21 e a emenda de 2026-09-05 / 2026-09-24 ficam como estão.

**Regra ([W] 2026-09-29), extensão da D1:** o Ponto é dono da **jornada**; o HRM é dono do
**cadastro de pessoas** e da **aprovação**.

**D4 — escala / horário contratual é do Ponto.** A linha *"Shift do Essentials é a fonte de
verdade para horário contratual"* (§Decisão 1, 2026-04-21) **não vale mais**, e a do fluxo
(`Essentials.Shift → PontoWr2.Colaborador.escala_atual_id`) também não. A fonte é
`ponto_escalas`. O código já era assim — a ADR é que estava errada:
`escala_atual_id` referencia `ponto_escalas` (migration
`Modules/Ponto/Database/Migrations/2026_04_18_000003_*.php:42`, `Colaborador.php:102`), e o Ponto
não lê `Shift`.

Consequência: o cadastro de turno do HRM (`/hrm/shift` resource e `/hrm/shift/assign-users`) vira
**301 → `/ponto/escalas`**. O `ShiftController`, a entidade `Shift` e o dado de
`essentials_shifts` / `essentials_user_shifts` **não** são apagados aqui; migrar ou apagar o dado é
outro PR, com dupla prova (regra de VALOR em `proibicoes.md`).

Medido em produção em 2026-09-29, antes do 301 (SELECT somente leitura): **1** business com turno
cadastrado (3 turnos, 7 vínculos de colaborador, última alteração em **2023-02-01**), e **0**
registros em `ponto_escalas` em qualquer business. Nenhum cálculo do Ponto depende desses turnos,
porque o Ponto não os lê.

**D5 — feriado é cadastro do HRM, lido pelo Ponto.** O cadastro fica no HRM
(`EssentialsHoliday`, tela `Essentials/Holidays/Index`); a apuração do Ponto deve lê-lo para
HE 100% em feriado (Art. 73 da CLT) e para não contar falta. **Hoje o Ponto não lê**: 0 ocorrências
de `EssentialsHoliday` em `Modules/Ponto` (medido na base da thread). A frase de 2026-04-21
*"PontoWR2 usa para cálculo de HE em feriado"* descrevia a intenção, não o código. Fica como
pedido ao dono do Ponto; não é executado por esta emenda.

### Tema → dono → quem consome

| tema | dono | quem consome | estado em 2026-09-29 |
|---|---|---|---|
| Presença / marcação | Ponto | — | feito (emenda 2026-09-05 / 2026-09-24) |
| Escala / horário contratual (D4) | Ponto (`ponto_escalas`) | — | `/hrm/shift` → 301 `/ponto/escalas` |
| Feriados (D5) | HRM (`EssentialsHoliday`) | Ponto (apuração, Art. 73 CLT) | o Ponto ainda não lê — pedido aberto |
| Licença (aprovação) | HRM | Ponto abona o dia | feito (`ApuracaoService`, `LicencaAbonaDiaContratoTest`) |
| Licença bloqueia marcação (D3) | Ponto | — | pedido aberto |
| Departamentos / Cargos | HRM | — | cadastro do core, abas no HRM |
| Folha | projeto Folha (ADR própria) | lê horas do Ponto | bloqueada até o motor |
