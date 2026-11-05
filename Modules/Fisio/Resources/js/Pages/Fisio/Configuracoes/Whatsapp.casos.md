---
id: modules-fisio-pages-fisio-configuracoes-whatsapp-casos
casos: Fisio · Configurações · WhatsApp · /fisio/configuracoes/whatsapp
irmaos: Whatsapp.charter.md (lei) · Whatsapp.tsx (tela)
tecnica: Caso de uso = narrativa da clínica + critério de aceite verificável (Dado/Quando/Então)
por_que: a escolha do canal decide se o paciente recebe lembrete e por qual número. Escolher o QR sem saber do risco de bloqueio para os lembretes de todos os pacientes; a escolha de uma clínica mudando o envio de outra é vazamento Tier 0.
owner: wagner
last_run: "2026-11-05"
last_run_ci: "_pendente_ — FisioConfigWhatsappTest entra na lane Verticais · MySQL neste PR; o veredito é da lane."
related_us: [US-FISIO-009]
---

# Casos de Uso & Aceite — Fisio · Configurações · WhatsApp

> **Âncora:** SPEC US-FISIO-009 (canal escolhido pela clínica, `whatsmeow` exige aceite de risco registrado) + ADR 0437 §13.1 + protótipo `ConfigWhatsApp.dc.html`. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (veredito no manifesto) · 🧪 teste cita o UC, sem veredito coletado · ⬜ não verificado · ❌ quebrou. **O veredito é da lane, nunca desta leitura** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FCW-01 | tela mostra a escolha e o número do módulo | should | `FisioConfigWhatsappTest` |
| UC-FCW-02 | "Pelo meu WhatsApp" desliga o envio automático | must | `FisioConfigWhatsappTest` |
| UC-FCW-03 | QR só com o aceite do risco, gravado | must | `FisioConfigWhatsappTest` |
| UC-FCW-04 | "oficial" não envia por número conectado por QR | must | `FisioConfigWhatsappTest` |
| UC-FCW-05 | só `fisio.editar` salva; limites no servidor | must | `FisioConfigWhatsappTest` |
| UC-FCW-06 | escolha de uma clínica não muda outra | must | `FisioConfigWhatsappTest` |

---

## UC-FCW-01 · A clínica vê o canal e o número · `should`
- **Persona:** dona da clínica abrindo Configurações.
- **Aceite:** Dado um número de saída no módulo Whatsapp · Quando abre a tela · Então vê a escolha gravada (ou nenhuma), se o número é oficial, só o final dele, e se o Fisio está enviando automático agora.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigWhatsappTest.php`
- **Status: 🧪**

## UC-FCW-02 · Manual de verdade · `must`
- **Aceite:** Dado um número oficial conectado · Quando a clínica escolhe "Pelo meu WhatsApp" e salva · Então nenhum envio do Fisio usa o número (o botão "Enviar" devolve `wa.me`) e o teto por hora e o silêncio gravados passam a valer no agendamento.
- **Regressão que defende:** clínica que pediu envio manual recebendo mensagem automática.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigWhatsappTest.php`
- **Status: 🧪**

## UC-FCW-03 · QR só com o risco aceito `[T0]` · `must`
- **Aceite:** Dado um número conectado por QR · Quando a clínica escolhe QR sem marcar o aviso · Então nada é gravado e a tela pede o aceite. Marcando, grava o aceite com o usuário, a data e a versão do termo, e o envio automático liga.
- **Regressão que defende:** canal não oficial ligado sem a clínica saber que pode perder o número.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigWhatsappTest.php`
- **Status: 🧪**

## UC-FCW-04 · Oficial é oficial · `must`
- **Aceite:** Dado que a clínica escolheu "oficial" · Quando o número do módulo é conectado por QR · Então o Fisio não envia automático (vai para `wa.me`) até o número ser `meta_cloud`.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigWhatsappTest.php`
- **Status: 🧪**

## UC-FCW-05 · Quem pode mudar · `must`
- **Aceite:** Sem `fisio.editar` o salvar responde 403. Canal fora da lista ou teto fora de 5..60 é recusado e nada é gravado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigWhatsappTest.php`
- **Status: 🧪**

## UC-FCW-06 · Cada clínica com a sua escolha `[T0]` · `must`
- **Aceite:** Dadas as empresas 98 e 99 com número conectado · Quando a 98 escolhe envio manual · Então a 99 continua enviando automático e não tem escolha gravada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioConfigWhatsappTest.php`
- **Status: 🧪**
