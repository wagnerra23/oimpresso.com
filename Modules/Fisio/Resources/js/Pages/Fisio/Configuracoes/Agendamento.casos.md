---
id: modules-fisio-pages-fisio-configuracoes-agendamento-casos
casos: Fisio · Configurações · Agendamento pelo app · /fisio/configuracoes/agendamento
irmaos: Agendamento.charter.md (lei) · Agendamento.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: estas regras decidem o que o paciente consegue marcar sozinho. App ligado sem querer, tipo liberado sem querer ou regra de uma clínica mudando a de outra viram agenda bagunçada ou vazamento Tier 0.
owner: wagner
last_run: "2026-11-04"
last_run_ci: "_pendente_ — FisioConfigAgendamentoTest entra na lane Verticais · MySQL; o veredito é da lane."
related_us: [US-FISIO-015]
---

# Casos de Uso & Aceite — Fisio · Configurações · Agendamento pelo app

> **Âncora:** etapa 04 §3.2 (A8, A9, A21) e o protótipo `ConfigAgendamentoOnline.dc.html`. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa · 🧪 teste cita o UC, sem veredito coletado · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FCA-01 | nasce desligado e sem tipo liberado | must | `FisioConfigAgendamentoTest` |
| UC-FCA-02 | salvar liga, guarda os horários e libera só os tipos marcados | must | `FisioConfigAgendamentoTest` |
| UC-FCA-03 | mudar prazo ou faltas sobe a versão da política | must | `FisioConfigAgendamentoTest` |
| UC-FCA-04 | sem `fisio.editar` não salva; uma clínica não muda a outra | must | `FisioConfigAgendamentoTest` |

---

## UC-FCA-01 · Desligado até a clínica decidir · `must`
- **Aceite:** Dado uma clínica que nunca configurou · Quando abre a tela · Então o app está desligado e nenhum tipo aparece para o paciente.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigAgendamentoTest.php`
- **Status: 🧪**

## UC-FCA-02 · O que o paciente vê no app · `must`
- **Aceite:** Dado a Sessão marcada como liberada e horários abertos na segunda e na quinta · Quando salva · Então o app fica ligado, os horários ficam guardados em ordem e só a Sessão fica liberada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigAgendamentoTest.php`
- **Status: 🧪**

## UC-FCA-03 · Política com versão · `must`
- **Persona:** clínica que muda o prazo de cancelamento de 24 h para 12 h.
- **Aceite:** Dado a política na versão N · Quando salva sem mudar prazo nem faltas · Então continua N; quando muda o prazo · Então vira N+1 (cada atendimento grava a versão em que foi marcado).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigAgendamentoTest.php`
- **Status: 🧪**

## UC-FCA-04 · Só quem edita, só a própria clínica `[T0]` · `must`
- **Aceite:** Dado um usuário só com `fisio.access` · Quando salva · Então 403 e nada muda. Dado um usuário da 99 enviando o id de um tipo da 98 · Quando salva · Então muda só a 99, e o tipo da 98 continua como estava.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigAgendamentoTest.php`
- **Status: 🧪**
