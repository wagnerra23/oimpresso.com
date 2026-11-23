---
id: modules-fisio-pages-fisio-public-confirmacao-charter
page: /fisio/consulta/{consulta}
component: Modules/Fisio/Resources/js/Pages/Fisio/Public/Confirmacao.tsx
related_prototype: "prototipo-ui/cowork/Wagner/prototipos/fisio/PacienteConfirmarLink.dc.html"
related_us: [US-FISIO-017]
owner: wagner
status: draft
last_validated: "2026-11-23"
parent_module: Fisio
related_adrs: [93, 437]
alcance:
  rota: /fisio/consulta/{consulta}
  rota_nome: fisio.confirmacao.show
  permission: n/a (pública, sem login e sem PIN — link assinado de um atendimento)
  pacote: fisio_module
tier: B
charter_version: 2
---

# Page Charter — /fisio/consulta/{consulta} (DRAFT · confirmar consulta)

> Nasce na US-FISIO-017 (etapa 04 A6). Casos: [`Confirmacao.casos.md`](Confirmacao.casos.md).
> Protótipo: `PacienteConfirmarLink.dc.html` (desenhado em 2026-11-23, depois da tela; comparação medida em `memory/requisitos/Fisio/Public-Confirmacao-visual-comparison.md`). O protótipo irmão `PacienteAgendaConfirmar.dc.html` desenha a resposta dentro da conversa do WhatsApp, com botões; aqui a resposta é por link, porque funciona em todos os canais da clínica (oficial, QR e envio manual). Os botões dentro da conversa ficam para quando houver modelo de mensagem aprovado pela Meta.

## Mission

O paciente recebe a mensagem da clínica na véspera e responde com um toque se vai, se não vai ou se precisa remarcar, sem digitar PIN.

## Goals — Features (faz)

- Mostrar clínica, profissional, dia e hora da sessão — UC-FCF-04.
- "Confirmo": o atendimento passa a confirmado, com canal `whatsapp`; confirmar de novo não muda nada — UC-FCF-05.
- "Não vou poder ir": desmarca pelo paciente dentro do prazo da política da clínica; fora do prazo não desmarca e pede para falar com a clínica — UC-FCF-06.
- "Preciso remarcar": orienta a remarcar em "Minhas consultas" no link do programa (que pede PIN) ou responder a mensagem. Escolher o horário nesta página é o próximo passo.
- A mensagem sai uma vez por atendimento, entre 2 h e 24 h antes, só para quem deu consentimento de WhatsApp, pelo número da clínica — UC-FCF-01 a UC-FCF-03.

## Non-Goals — Features (NÃO faz)

- _A preencher por [W]._

## Anti-hooks

- ⛔ Abrir o link (GET) nunca muda o atendimento: o WhatsApp abre links sozinho para montar a prévia (UC-FCF-04).
- ⛔ Fora do prazo da política a página não desmarca: falta e cancelamento tardio são decididos pela profissional (etapa 04 A8, UC-FCF-06).
- ⛔ Nada clínico na página nem na mensagem: só clínica, profissional, dia e hora (ADR 0437 §5).
- ⛔ O atendimento vem do id assinado; o link de um atendimento não abre outro (UC-FCF-04).

## Refs

- ADR 0437 · `memory/requisitos/Fisio/etapas/04-f3-operacao.md` (A6, A8) · `Modules/Fisio/Services/Agenda/ConfirmacaoDaConsulta.php` · `Modules/Fisio/Jobs/EnviarConfirmacaoConsultaJob.php`
