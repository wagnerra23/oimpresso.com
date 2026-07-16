---
page: /ponto
component: resources/js/Pages/Ponto/Dashboard/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-006]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/DashboardController@index` (rota `ponto.dashboard`, permissão `ponto.access`). Visão geral ao vivo do ponto eletrônico do dia.

---

## Mission
É a home do módulo de ponto: dá ao gestor uma visão ao vivo do dia — quem está presente, atrasos/faltas, HE do mês, aprovações pendentes — com gráfico dos últimos 7 dias, feed de atividade (marcações), inbox de alertas e atalho para aprovações. Serve de painel de comando e ponto de entrada para as demais telas.

---

## Goals — Features (faz)
- 6 KPIs: colaboradores ativos, presentes agora, atrasos hoje, faltas hoje, HE do mês, aprovações pendentes.
- Faixa de presença ao vivo (`PresenceStrip`).
- Gráfico de barras dos últimos 7 dias (trabalhado + HE, canvas simples sem lib).
- Feed de atividade do dia (marcações recentes).
- Inbox de alertas com ação/severidade.
- Bloco de aprovações pendentes com link pra fila.
- Atalho "Bater ponto".

---

## Non-Goals — Features (NÃO faz)
- ❌ Não bate ponto aqui — o botão navega pro fluxo de marcação.
- ❌ Não aprova/rejeita intercorrências — só lista; decisão é em `/ponto/aprovacoes`.
- ❌ Não agrega dados de outro business — tudo escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não edita marcações — marcações são append-only (Portaria MTP 671/2021).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Todas as props (exceto `server_time`) vêm via `Inertia::defer` (closures lazy — aggregates/joins/sum).
- Polling ao vivo a cada 30s: `router.reload({ only: ['kpis','presenca_agora','atividade_recente','alertas','server_time'] })`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não muta nada — dashboard é read-only; o polling só recarrega props de leitura.
- ❌ Não dispara alerta/notificação externa a partir da tela.
- ❌ Não persiste estado do polling entre navegações (limpa o interval no unmount).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Validar custo do polling 30s com defer (evitar N queries repetidas)
