---
id: modules-fisio-pages-fisio-agenda-index-casos
casos: Fisio · Agenda · /fisio/agenda
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: a agenda decide quem é atendido e quando. Sessão marcada em cima de outra, falta que não bloqueia quem falta sempre, ou paciente de outra clínica na semana são erro de operação ou vazamento Tier 0.
owner: wagner
last_run: "2026-11-04"
last_run_ci: "_pendente_ — FisioAgendaTelaTest entra na lane Verticais · MySQL; o veredito é da lane."
related_us: [US-FISIO-014, US-FISIO-015]
---

# Casos de Uso & Aceite — Fisio · Agenda (`/fisio/agenda`)

> **Âncora:** etapa 04 §3.2 (A1, A4, A5, A7, A10, A12) e o protótipo `Agenda.dc.html`. Os UCs derivam da etapa e do protótipo, não do `.tsx`.
>
> **Status:** ✅ passa · 🧪 teste cita o UC, sem veredito coletado · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FAG-01 | a agenda nasce com os tipos de atendimento, só na empresa que a abriu | should | `FisioAgendaTelaTest` |
| UC-FAG-02 | sessão marcada aparece na semana do profissional, sem o recado do paciente | must | `FisioAgendaTelaTest` |
| UC-FAG-03 | outra clínica não vê, não marca, não muda nem desmarca | must | `FisioAgendaTelaTest` |
| UC-FAG-04 | sem `fisio.editar` a agenda abre só para ler | must | `FisioAgendaTelaTest` |
| UC-FAG-05 | desmarcar aponta quantos da lista de espera servem na vaga | must | `FisioAgendaTelaTest` |

---

## UC-FAG-01 · Tipos de atendimento prontos na primeira vez · `should`
- **Persona:** fisioterapeuta abrindo a agenda pela primeira vez.
- **Aceite:** Dado uma clínica sem tipos · Quando abre `/fisio/agenda` · Então existem Avaliação (60 min), Sessão (50), Reavaliação (60) e Teleconsulta (30) para ela, e nenhuma outra clínica ganha tipo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAgendaTelaTest.php`
- **Status: 🧪**

## UC-FAG-02 · Marcar em três campos e ver na semana `[T0]` · `must`
- **Persona:** fisioterapeuta entre um atendimento e outro.
- **Aceite:** Dado paciente, tipo e horário livre · Quando marca · Então o atendimento aparece na semana como "aguardando", com o nome curto do paciente, e a leitura não traz o recado do paciente. Horário ocupado responde com o pedido de encaixe (regra do serviço, US-FISIO-014).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAgendaTelaTest.php`
- **Status: 🧪**

## UC-FAG-03 · Agenda não cruza clínicas `[T0]` · `must`
- **Aceite:** Dado um atendimento da 98 · Quando um usuário da 99 tenta mudar o status, desmarcar, marcar com o paciente da 98 ou pô-lo na lista de espera · Então tudo responde 404 e nada muda; a semana da 99 não mostra o atendimento.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAgendaTelaTest.php`
- **Status: 🧪**

## UC-FAG-04 · Só ler sem `fisio.editar` · `must`
- **Aceite:** Dado um usuário com `fisio.access` e sem `fisio.editar` · Quando abre a agenda · Então vê a semana; marcar e desmarcar respondem 403.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAgendaTelaTest.php`
- **Status: 🧪**

## UC-FAG-05 · Vaga liberada vai para a lista de espera · `must`
- **Persona:** recepção quando um paciente desmarca.
- **Aceite:** Dado um paciente na lista de espera que aceita manhã · Quando a sessão da manhã é desmarcada · Então a resposta diz que 1 paciente da lista de espera serve no horário, e a tela mostra a vaga liberada com esse número.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioAgendaTelaTest.php`
- **Status: 🧪**
