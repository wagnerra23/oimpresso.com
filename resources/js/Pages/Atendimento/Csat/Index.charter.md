---
page: /atendimento/csat
component: resources/js/Pages/Atendimento/Csat/Index.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Whatsapp
related_us: [US-WA-CSAT]
related_adrs: [114, 101, 93, 135, 142]
tier: B
charter_version: 1
---

# Page Charter — /atendimento/csat (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Whatsapp/Http/Controllers/Admin/CsatController@index` (rota `atendimento.csat.index`, permissão `whatsapp.access`). Dashboard Cockpit V2 da pesquisa de satisfação pós-atendimento (CSAT 1-5).

---

## Mission
Dar ao gestor de atendimento uma leitura rápida da satisfação do cliente após a resolução de conversas. A pesquisa 1-5 é enviada automaticamente quando o atendente marca a conversa como resolvida, e a nota é parseada do próximo inbound do cliente. A tela consolida score médio, volume enviado/respondido, taxa de resposta, distribuição de notas e as últimas respostas.

---

## Goals — Features (faz)
- Grid de 4 KPIs (deferred): score médio (1-5, com tom por faixa), pesquisas enviadas, respondidas e taxa de resposta.
- Distribuição de notas (deferred): barras horizontais por nota de 5 a 1 com contagem.
- Tabela das últimas respostas (deferred, até 20): nota em estrelas, cliente (link pro thread da conversa no Inbox), canal, atendente que resolveu, comentário e data de resposta.
- Filtro de range (7/30/90 dias) via `Select`, com partial reload só de `range`, `kpis`, `distribution` e `recent`.
- Empty state quando ainda não há respostas.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não envia nem reenvia a pesquisa CSAT por esta tela (o envio é automático via `CsatDispatcher` ao resolver a conversa) — inferência pendente de Wagner.
- ❌ Não permite editar/anular uma nota de CSAT respondida — inferência pendente de Wagner.
- ❌ Não exporta CSV/relatório nem drill-down por atendente/canal aqui — inferência pendente de Wagner.
- ❌ Não mostra CSAT de outro `business_id` — todas as queries filtram `business_id` (global scope `HasBusinessScope`, Tier 0 ADR 0093).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- `kpis`, `distribution` e `recent` carregados via `Inertia::defer` no controller — queries pesadas (counts, avg, eager-loads) pulam execução quando o partial reload não pede (skill `inertia-defer-default`, D-14).
- Range whitelisted no backend (7/30/90, default 30) — defesa contra injection.
- Nota de satisfação capturada automaticamente do próximo inbound do cliente após a conversa ser marcada resolvida (fora desta tela).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh — só recarrega ao trocar o range.
- ❌ Não muta dados: é dashboard somente leitura (nenhum POST/PUT/DELETE nesta tela).
- ❌ Não dispara pesquisa CSAT ao abrir a tela.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se o link do cliente pro Inbox (`/atendimento/inbox?thread=`) é o destino canônico
