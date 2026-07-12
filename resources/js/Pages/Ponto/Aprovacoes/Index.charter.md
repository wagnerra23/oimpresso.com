---
page: /ponto/aprovacoes
component: resources/js/Pages/Ponto/Aprovacoes/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-001]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/aprovacoes (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/AprovacaoController@index` (rota `ponto.aprovacoes.index`, permissão `ponto.access`). Fila de intercorrências de ponto aguardando decisão do RH/gestor.

---

## Mission
O RH/gestor decide aqui as intercorrências de ponto submetidas pelos colaboradores (atestado, esquecimento de marcação, hora extra autorizada, etc.). A tela concentra a fila por estado, permite aprovar/rejeitar individualmente (com motivo) ou aprovar pendentes em lote, e sinaliza quais decisões impactam a apuração CLT.

---

## Goals — Features (faz)
- Lista paginada (20/pág) de intercorrências filtráveis por estado, tipo e prioridade.
- KPIs por estado (Pendente/Aprovada/Rejeitada/Aplicada/Rascunho/Cancelada) — clique no card filtra a lista.
- Aprovar intercorrência pendente via diálogo de confirmação (`POST /ponto/aprovacoes/{id}/aprovar`).
- Rejeitar com motivo obrigatório (mín. 5 chars) (`POST /ponto/aprovacoes/{id}/rejeitar`).
- Aprovação em lote das pendentes selecionadas (`POST /ponto/aprovacoes/lote`).
- Alerta visual quando a intercorrência `impacta_apuracao` (ajusta minutos trabalhados).
- Link "Ver" pro detalhe da intercorrência (`/ponto/intercorrencias/{id}`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não cria/edita intercorrências — isso é do módulo Intercorrências (`ponto.intercorrencias.*`).
- ❌ Não expõe dados de outro business — lista escopada por `business_id` (Tier 0 multi-tenant).
- ❌ Não altera marcações de ponto diretamente — marcações são append-only (Portaria MTP 671/2021); a aprovação só muda o estado da intercorrência.
- ❌ Não faz edição em massa de campos (só decisão aprovar/rejeitar em lote).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- `aprovacoes` e `contagens` vêm via `Inertia::defer` (closures lazy — paginate + selectRaw só rodam quando solicitados).
- Filtros/paginação usam partial reload (`only: ['aprovacoes','filtros']`) — `contagens`/`tipos` não re-executam.
- Toast de sucesso/erro em cada ação.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling — a fila não se atualiza sozinha.
- ❌ Não muta estado em requisição GET — aprovar/rejeitar/lote são POST explícitos com confirmação.
- ❌ Não notifica o colaborador sem opt-in — a notificação de rejeição depende da política LGPD do backend.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar RBAC: quem além do gestor pode aprovar em lote
