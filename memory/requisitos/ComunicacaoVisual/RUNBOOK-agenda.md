---
last_validated: "2026-10-21"
slug: runbook-comunicacao-visual-agenda
title: "RUNBOOK — /comunicacao-visual/agenda (Agenda de instalação)"
type: runbook
module: ComunicacaoVisual
page: /comunicacao-visual/agenda
component: resources/js/Pages/ComunicacaoVisual/Agenda.tsx
status: rascunho
updated_at: 2026-10-21
version: 0.1
owner: W
---

# RUNBOOK — /comunicacao-visual/agenda (Agenda de instalação)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para tela
> **nova**, sem Blade anterior (não há o que migrar: o backend nasceu como API em US-COMVIS-007).
> Charter: [`Agenda.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Agenda.charter.md) · casos:
> [`Agenda.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Agenda.casos.md) · SPEC: US-COMVIS-007.

## 1. Estrutura

- Rota `GET /comunicacao-visual/agenda?semana=AAAA-MM-DD` → `AgendaController@index` →
  `Inertia::render('ComunicacaoVisual/Agenda')`. Só lê.
- Agendar é o `POST /comunicacao-visual/api/os/{os}/instalacao` que já existia
  (`InstalacaoController@agendar`). A tela não recalcula conflito: mostra a recusa 422 da API.
- `instalacoes` é `Inertia::defer` (query da semana); `equipe` e `os` são eager porque o formulário e as
  linhas da grade precisam delas no primeiro paint.

## 2. Âncoras de contrato

| âncora | elemento |
|---|---|
| `cabecalho` | `<div>` em volta do `PageHeader` |
| `semana` | navegação semana anterior / hoje / próxima |
| `agenda` | `<Card>` da grade semana × equipe |
| `agendar` | `<Card>` do formulário (ou do aviso de permissão) |
| `bloqueio` | alerta "Sem janela — não agendado" com a mensagem da API |

## 3. Âncora de design

Widget "Agenda de instalação" de `prototipo-ui/cowork/Wagner/producao-cv-campo.jsx`: cada item mostra hora,
OS, equipe, duração, veículo e selo de estado. A tela reorganiza os mesmos itens numa grade (linha = pessoa,
coluna = dia), porque o pedido da US é "agenda calendário (semanal)".

## 4. Verificação

- Pest: `Modules/ComunicacaoVisual/Tests/Feature/AgendaTelaContratoTest.php` (tenant 98 × 99, MySQL; no CT 100).
- Smoke: abrir `/comunicacao-visual/agenda`, agendar duas instalações com a mesma pessoa no mesmo horário e ver
  o alerta "Sem janela" com o nome e o horário.
