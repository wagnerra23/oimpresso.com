---
id: modules-fisio-pages-fisio-public-confirmacao-casos
casos: Fisio · Confirmar consulta · /fisio/consulta/{consulta}
irmaos: Confirmacao.charter.md (lei) · Confirmacao.tsx (tela) · Modules/Fisio/Jobs/EnviarConfirmacaoConsultaJob.php (envio)
tecnica: Caso de uso = narrativa do paciente + critério de aceite verificável (Dado/Quando/Então)
por_que: falta sem aviso custa a sessão da clínica e a vaga de quem está na lista de espera. A resposta tem de sair num toque, sem PIN, e não pode desmarcar fora do prazo nem expor dado de saúde.
owner: wagner
last_run: "2026-11-23"
last_run_ci: "_pendente_ — FisioConfirmacaoConsultaTest entra na lane Verticais (MySQL) neste PR."
related_us: [US-FISIO-017]
---

# Casos de Uso & Aceite — Fisio · Confirmar consulta (`/fisio/consulta/{consulta}`)

> **Âncora:** etapa 04 A6 ("confirmação por WhatsApp D-1 com Confirmo · Preciso remarcar · Cancelar; a resposta muda o status sozinha") e A8 (falta e cancelamento tardio só sugeridos, a profissional confirma). Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FCF-01 | uma mensagem por atendimento, com link assinado | must | `FisioConfirmacaoConsultaTest` |
| UC-FCF-02 | fora da janela, já confirmado ou sem consentimento: não envia | must | `FisioConfirmacaoConsultaTest` |
| UC-FCF-03 | 98 contra 99: cada empresa só os seus, pelo número dela | must | `FisioConfirmacaoConsultaTest` |
| UC-FCF-04 | abrir só mostra; link sem assinatura, adulterado ou sem pacote não abre | must | `FisioConfirmacaoConsultaTest` |
| UC-FCF-05 | "Confirmo" confirma pelo WhatsApp, sem efeito na 2ª vez | must | `FisioConfirmacaoConsultaTest` |
| UC-FCF-06 | "Não vou poder ir" desmarca só dentro do prazo | must | `FisioConfirmacaoConsultaTest` |

## UC-FCF-01 · Uma mensagem por atendimento `[T0]` · `must`

- **Dado** um atendimento `agendado` que começa entre 2 h e 24 h à frente, de paciente com consentimento de WhatsApp e celular, numa empresa com número ativo,
- **Quando** o envio de hora em hora roda (uma ou mais vezes),
- **Então** sai uma única mensagem por atendimento, pelo número da empresa, com dia, hora e um link assinado para aquele atendimento. Duas sessões do mesmo paciente no mesmo dia geram duas mensagens.
- **Status:** 🧪

## UC-FCF-02 · Quem não recebe · `must`

- **Dado** atendimento a menos de 2 h, a mais de 24 h, já confirmado, ou de paciente sem consentimento de WhatsApp,
- **Quando** o envio roda,
- **Então** nenhuma mensagem sai para eles (e o atendimento elegível ao lado sai, provando que o envio rodou).
- **Status:** 🧪

## UC-FCF-03 · Cada empresa só os seus `[T0]` · `must`

- **Dado** atendimentos na 98 e na 99 no mesmo horário,
- **Quando** o envio da 98 roda,
- **Então** só o atendimento da 98 sai, pelo número da 98.
- **Status:** 🧪

## UC-FCF-04 · Abrir o link `[T0]` · `must`

- **Dado** o link recebido,
- **Quando** o paciente (ou a prévia do WhatsApp) abre,
- **Então** a página mostra dia e hora e as ações, e o atendimento continua como estava. Link sem assinatura, com o número de outro atendimento ou de empresa sem o pacote não abre.
- **Status:** 🧪

## UC-FCF-05 · Confirmo · `must`

- **Dado** a página aberta de um atendimento `agendado`,
- **Quando** o paciente toca "Confirmo",
- **Então** o atendimento fica `confirmado` com canal `whatsapp`, e a página diz "Confirmado!". Tocar de novo não muda o horário da confirmação.
- **Status:** 🧪

## UC-FCF-06 · Não vou poder ir · `must`

- **Dado** a política da clínica de desmarcar até 24 h antes,
- **Quando** o paciente toca "Não vou poder ir",
- **Então** com mais de 24 h o atendimento é desmarcado pelo paciente; com menos, nada muda e a página manda falar com a clínica.
- **Status:** 🧪

## Fora do contrato por enquanto

- Escolher o novo horário na própria página ("Preciso remarcar" hoje só orienta).
- Segundo lembrete para quem não respondeu (A6).
- Antecedência por clínica (D-2 a 2 h): hoje é a mesma para todas, em `fisio.confirmacao`.
- Botões dentro da conversa do WhatsApp (precisa de modelo de mensagem aprovado pela Meta).
