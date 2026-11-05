---
id: modules-fisio-pages-fisio-agenda-index-charter
page: /fisio/agenda
component: Modules/Fisio/Resources/js/Pages/Fisio/Agenda/Index.tsx
related_prototype: "prototipo-ui/cowork/Wagner/prototipos/fisio/Agenda.dc.html"
owner: wagner
status: draft
last_validated: "2026-11-04"
parent_module: Fisio
related_adrs: [93, 358, 437, 438]
alcance:
  rota: /fisio/agenda
  rota_nome: fisio.agenda.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/agenda (DRAFT · agenda da clínica)

> Nasce na US-FISIO-015 (lote 3 do canvas Prescreve Pro). Regras: US-FISIO-014 e etapa 04 §3.2 (A1–A13). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar ao fisioterapeuta a semana dele, quem confirmou, quem faltou e quem está esperando vaga, e marcar uma sessão em três campos.

## Goals — Features (faz)

- Semana do profissional (seg a sáb, 8h às 18h), com a cor do status: confirmado, aguardando, teleconsulta, falta — UC-FAG-02.
- Resumo do dia: sessões, confirmadas, faltas no mês — UC-FAG-02.
- Vaga liberada nas últimas 24 h com quantos da lista de espera servem nela; lista de espera com a preferência em texto — UC-FAG-05.
- Nova sessão em 3 campos (paciente, tipo, dia e hora); conflito oferece encaixe — UC-FAG-02.
- Bloquear horário (almoço, curso, férias).
- Só a empresa da sessão; escrita só com `fisio.editar` — UC-FAG-03, UC-FAG-04.

## Non-Goals — Features (NÃO faz)

- ❌ Cobrar falta ou cancelamento tardio: a tela e o servidor só marcam; cobrança é do lote do Financeiro e é sempre confirmada pelo profissional (etapa 04 A8).
- ❌ Mostrar o recado ou a nota do paciente na grade (A20: ficam na ficha do atendimento).

## Refs

- Protótipo: `prototipo-ui/cowork/Wagner/prototipos/fisio/Agenda.dc.html`
- Backend: `Modules/Fisio/Http/Controllers/AgendaController.php` · `Modules/Fisio/Services/Agenda/AgendaDaSemana.php` · `Modules/Fisio/Services/Agenda/AgendaService.php`
