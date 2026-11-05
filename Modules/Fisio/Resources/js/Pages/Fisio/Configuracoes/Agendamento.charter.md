---
id: modules-fisio-pages-fisio-configuracoes-agendamento-charter
page: /fisio/configuracoes/agendamento
component: Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Agendamento.tsx
related_prototype: "prototipo-ui/cowork/Wagner/prototipos/fisio/ConfigAgendamentoOnline.dc.html"
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 358, 437, 438]
alcance:
  rota: /fisio/configuracoes/agendamento
  rota_nome: fisio.configuracoes.agendamento
  permission: fisio.access
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/configuracoes/agendamento (DRAFT · agendamento pelo app)

> Nasce na US-FISIO-015 (lote 3). Regras: etapa 04 §3.2 A9 e A21. Casos: [`Agendamento.casos.md`](Agendamento.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Decidir o que o paciente pode marcar, remarcar e cancelar sozinho pelo app da clínica.

## Goals — Features (faz)

- Ligar e desligar o agendamento pelo app; nasce desligado — UC-FCA-01.
- Quem pode agendar sozinho (com pacote, todos em tratamento, ninguém) e quais tipos aparecem no app — UC-FCA-02.
- Horários abertos para o app por dia da semana; o resto da agenda fica com a recepção — UC-FCA-02.
- Regras: antecedência mínima, prazo para remarcar ou cancelar, dias à frente, faltas antes de bloquear, lista de espera, "Cheguei". Mudar o prazo ou as faltas sobe a versão da política — UC-FCA-03.
- Só a empresa da sessão; salvar só com `fisio.editar` — UC-FCA-04.

## Non-Goals — Features (NÃO faz)

- ❌ Promoção, venda extra no checkout ou nota pública (ADR 0437 §13.7, texto do protótipo).
- ❌ Sinal por Pix editável aqui enquanto a cobrança do Fisio não existir (lote do Financeiro).

## Refs

- Protótipo: `prototipo-ui/cowork/Wagner/prototipos/fisio/ConfigAgendamentoOnline.dc.html`
- Backend: `Modules/Fisio/Http/Controllers/ConfigAgendamentoController.php` · `Modules/Fisio/Entities/BookingSettings.php`
