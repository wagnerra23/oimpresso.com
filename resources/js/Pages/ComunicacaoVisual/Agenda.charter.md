---
id: resources-js-pages-comunicacao-visual-agenda-charter
page: /comunicacao-visual/agenda
component: resources/js/Pages/ComunicacaoVisual/Agenda.tsx
runbook: memory/requisitos/ComunicacaoVisual/RUNBOOK-agenda.md
related_us: [US-COMVIS-007]
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-campo.jsx
status: draft
---

# Charter — Pages/ComunicacaoVisual/Agenda.tsx

## Persona-alvo

Coordenador de instalação da gráfica de comunicação visual. Monitor 1280px. Precisa ver quem da equipe está
livre na semana e agendar a próxima fachada sem mandar duas equipes ao mesmo tempo para o mesmo carro.

## Objetivo desta página

1. **Semana × equipe** — uma linha por pessoa da empresa, uma coluna por dia (segunda a domingo); cada
   instalação aparece no dia de início, na linha de cada pessoa da equipe, com horário, OS, veículo e estado.
2. **Agendar** — escolher a OS, o dia, início e fim, a equipe e o veículo, e enviar pela API
   `POST /comunicacao-visual/api/os/{os}/instalacao`.
3. **Bloqueio visível** — quando a API recusa por falta de janela, o alerta "Sem janela — não agendado" mostra
   com quem e quando o horário conflita, e nada é gravado.

Contrato comportamental: [`Agenda.casos.md`](Agenda.casos.md).

## Fora desta versão

Ainda não estão na tela (não assuma que estão): arrastar instalação entre dias; checklist de EPI, foto,
assinatura e comprovante (outras partes da US-COMVIS-007); aviso ao cliente por WhatsApp (PR separado).
A regra de conflito mora no backend (`AgendaInstalacao`); a tela só mostra a recusa.
