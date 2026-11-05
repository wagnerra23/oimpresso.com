---
id: requisitos-fisio-telas-agenda-paciente-casos
casos: Agenda do paciente no portal — marcar, minhas consultas, remarcar, cancelar, confirmar, Cheguei, lista de espera · rotas /fisio/p/{token}/agenda/*
irmaos: ../SPEC.md (US-FISIO-016) · ../etapas/04-f3-operacao.md §3.2 (A6, A7, A9, A19, A20, A21) · ../../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md (o portal que a abriga)
tecnica: Caso de uso = narrativa do paciente + critério de aceite verificável (Dado/Quando/Então)
por_que: o paciente marca sozinho em horário da clínica. Horário ocupado vendido duas vezes, regra da clínica ignorada, cancelamento depois do prazo sem passar pela fisio, ou consulta de outra pessoa visível pelo link são erro de operação ou vazamento Tier 0.
owner: wagner
last_run: "2026-11-04"
last_run_ci: "_pendente_ — FisioPortalAgendaTest entra na lane Verticais · MySQL; o veredito é da lane."
related_us: [US-FISIO-016]
---

# Casos de Uso & Aceite — Agenda do paciente no portal (US-FISIO-016)

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): a agenda do paciente mora **dentro** do
> portal (`Fisio/Public/Portal`), como as telas internas Hoje e Check-in; o contrato do portal
> (`Portal.casos.md`) é editado por outros lotes ao mesmo tempo, e a agenda tem rotas próprias.
>
> **Âncora:** etapa 04 §3.2 (A6, A7, A9, A19, A20, A21) e o canvas Prescreve Pro (`PacienteAgendar`,
> `PacienteConsultas`, `PacienteAgendaConfirmar`). Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa · 🧪 teste cita o UC, sem veredito coletado · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPA-01 | horários livres = abertos para o app menos os ocupados | must | `FisioPortalAgendaTest` |
| UC-FPA-02 | marcar grava pelo app com o recado; o mesmo horário não vende duas vezes | must | `FisioPortalAgendaTest` |
| UC-FPA-03 | app desligado, só pela clínica ou bloqueio por faltas impedem marcar | must | `FisioPortalAgendaTest` |
| UC-FPA-04 | remarcar e cancelar só antes do prazo da política | must | `FisioPortalAgendaTest` |
| UC-FPA-05 | confirmar presença e "Cheguei" a partir de 1 h antes | should | `FisioPortalAgendaTest` |
| UC-FPA-06 | "me avise se abrir vaga" entra na lista de espera; sair tira | should | `FisioPortalAgendaTest` |
| UC-FPA-07 | atendimento de outra pessoa é 404; sem PIN é 403 | must | `FisioPortalAgendaTest` |

---

## UC-FPA-01 · Só o que a clínica abriu · `must`
- **Aceite:** Dado a quarta aberta às 08:30, 10:00 e 14:00, e 08:30 já ocupada · Quando o paciente pede os horários · Então vê 10:00 e 14:00; num dia sem horário aberto, nada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**

## UC-FPA-02 · Marcar e o recado para a fisio `[T0]` · `must`
- **Aceite:** Dado um horário livre · Quando marca com recado · Então o atendimento é da clínica do link, com origem "app" e o recado; marcar o mesmo horário de novo responde 422.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**

## UC-FPA-03 · Regras da clínica valem no app · `must`
- **Aceite:** Dado o app desligado, ou "ninguém" pode marcar sozinho, ou o paciente bloqueado por faltas · Quando tenta marcar · Então 422 com o motivo (`desligado`, `so_pela_clinica`, `bloqueado_faltas`), e a lista de consultas diz por quê. "Só com pacote" não libera ninguém até o pacote existir (lote do Financeiro).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**

## UC-FPA-04 · Prazo da política · `must`
- **Aceite:** Dado uma consulta a mais de 24 h · Quando remarca · Então a antiga fica cancelada pelo paciente e a nova aponta para ela. Dado menos de 24 h · Quando tenta cancelar · Então 422 `fora_do_prazo` e nada muda (só a clínica decide, A8).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**

## UC-FPA-05 · Confirmo e Cheguei · `should`
- **Aceite:** Dado uma consulta agendada · Quando confirma · Então fica confirmada. "Cheguei" antes de 1 h do horário responde 422; dentro da janela, o status vira "chegou".
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**

## UC-FPA-06 · Me avise se abrir vaga · `should`
- **Aceite:** Dado a lista de espera ligada · Quando marca pedindo aviso · Então entra na lista de espera para antes da data marcada; "Sair" tira.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**

## UC-FPA-07 · Cada um só vê o seu `[T0]` · `must`
- **Aceite:** Dado a consulta de outro paciente da mesma clínica · Quando o paciente tenta cancelar pelo id · Então 404 e nada muda; ela não aparece na lista dele. Sem PIN na sessão, 403 `pin_pendente`.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPortalAgendaTest.php`
- **Status: 🧪**
