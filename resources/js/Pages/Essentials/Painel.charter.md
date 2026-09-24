---
page: /hrm/dashboard
component: resources/js/Pages/Essentials/Painel.tsx
owner: wagner
status: draft
parent_module: Essentials
related_prototype: prototipo-ui/cowork/Wagner/hrm-page.jsx
related_adrs: [104, 93, 358, 14]
runbook: RUNBOOK-painel.md
alcance:
  rota: /hrm/dashboard
  rota_nome: hrmDashboard
  permission: (nenhuma — só auth + pacote, como a Blade que substitui)
  menu_hook: Modules/Essentials/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: essentials_module
tier: B
charter_version: 1
---

# Page Charter — Essentials/Painel (DRAFT · carimbado do PT-04)

> Nascida do **PT-04 Dashboard** via `criar-tela.mjs`. Thread 06 do playbook hrm.
> F1: [`RUNBOOK-painel.md`](../../../../memory/requisitos/Essentials/RUNBOOK-painel.md) ·
> contrato de teste: [`Painel.casos.md`](Painel.casos.md). Substitui `dashboard/hrm_dashboard.blade.php`.

## Mission

Abrir o HRM mostrando, a quem entra, as próprias licenças, faixas de meta e os próximos
feriados — e, ao administrador, quantos colaboradores há e em que setores.

## Goals — Features (faz)

- KPIs clicáveis que levam à tela dona (Colaboradores → `/users`, Licenças → `/hrm/leave`, Presença → `/ponto`).
- Lista as licenças **aprovadas** do usuário que tocam o próximo mês.
- Lista as faixas de meta de venda **gravadas** do usuário (valor inicial, final, percentual).
- Lista os feriados do próximo mês, respeitando as localidades permitidas do usuário.
- Ao administrador: total de colaboradores e contagem por setor.
- PT-BR em todo label e mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ **Não conta presença.** Desde a D1 a jornada é do Ponto (ADR 0014, emenda 2026-09-05):
  `essentials_attendances` não alimenta número nenhum aqui — o card aponta para `/ponto`.
- ❌ **Não apura o realizado de vendas nem comissão.** É caminho de valor; a Metas o excluiu
  pela mesma razão (`Metas.charter.md`, Non-Goals). Só as faixas gravadas aparecem.
- ❌ **Não mostra folha nem custo por setor.** Folha está bloqueada até ADR própria (D2).
- ❌ **Não inventa número.** Card sem agregado no controller mostra `—` e um link.
- ❌ **Não escreve nada** e não cria rota, permission nem migration.

## Automation Anti-hooks

- ❌ Nenhum agente pode encher um card com query nova "só para o painel": o dado vem dos
  agregados que `DashboardController@hrmDashboard` já calculava (playbook 06 §B).
- ❌ Nenhum agente pode remover o filtro `business_id` de usuários, setores, licenças ou
  feriados. Provado por `HrmPainelTest` (UC-PAINEL-02).
- ❌ Nenhum agente pode usar `biz=4` (ROTA LIVRE) em teste, fixture ou smoke desta tela.

## UX Targets

- Cabe em 1280px sem scroll horizontal.
- O conteúdo chega por `Inertia::defer` — o cabeçalho aparece antes dos cards.

## Refs

- Padrão de Tela: PT-04 Dashboard (KpiGrid + KpiCard) · Constituição UI v2: UI-0013
