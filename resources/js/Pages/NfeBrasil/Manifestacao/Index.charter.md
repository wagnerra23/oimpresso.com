---
page: /nfe-brasil/manifestacao
component: resources/js/Pages/NfeBrasil/Manifestacao/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-10"
parent_module: NfeBrasil
related_adrs: [29, 39, 93, 94, 116]
related_us: [US-NFE-052, US-NFE-061]
tier: A
charter_version: 1
---

# Page Charter — /nfe-brasil/manifestacao

> **Status:** live em 2026-05-10. Charter criado por skill `charter-write` disparado pela auditoria de completude (US-NFE-061 P0). Esta tela já tinha `manifestacao-visual-comparison.md` aprovado (2026-05-09) + `RUNBOOK-manifestacao.md` — charter formaliza no padrão Page Charter. Non-Goals + Anti-hooks aprovados por Wagner em 2026-05-10.

---

## Mission

Manifestar (cienciar / confirmar / desconhecer / não realizada) **NFes recebidas** que o destinatário precisa responder — única tela onde o usuário vê DFes pendentes pelo prazo legal SEFAZ (180 dias), aplica eventos individuais ou em bulk, e dispara sync NSU sob demanda. Origem: ADR 0116 (caso Gold) — cliente recebe NFe de fornecedor e tem obrigação fiscal de manifestar.

---

## Goals — Features (faz)

- AppShellV2 + Head `Manifestação · NF-e Brasil`
- Layout list+detail Cockpit V2 (ADR 0039): lista esquerda (DFes) + foco direito (LinkedItens / LinkedFornecedor / LinkedHistorico)
- Atalhos teclado canônicos Cockpit: `J/K` navega lista, `C` confirma, `D` desconhece, `R` não realizada, `/` foca busca
- KPIs no topo: pendentes / vencendo em 7d / confirmadas no mês (queries scoped por `business_id`)
- 5 status visuais distintos via `STATUS_LABEL` + `STATUS_BADGE` (pendente=amber, confirmada=emerald, desconhecida=slate, não_realizada=orange, ciencia=slate)
- PrazoBadge tri-nível: vencido (red), ≤7d (red), ≤30d (amber), >30d (mute)
- Filtros persistentes: status (default `pendente`), busca livre por CNPJ/nome/chave_44
- Persistência localStorage `oimpresso.nfebrasil.manifestacao.filter` (DESIGN.md §12)
- Confirmações destrutivas: cienciar/confirmar (1 confirm), desconhecer/não-realizada (confirm + prompt justificativa ≥15 chars NT 2014.002)
- Bulk-confirmar via checkbox + endpoint `/bulk/confirmar`
- Sync NSU manual via `/sync-now` (botão exposto quando `permissions.canManage`)
- LinkedApps lazy-fetch JSON: `/{id}/itens`, `/{id}/eventos`
- Permissões granular: `view` (index + JSON) vs `manage` (mutações) — controla via `permissions.canManage` no payload + `abort_unless($this->canView(), 403)` no Controller
- Multi-tenant Tier 0: `NfeDfeRecebido` usa `HasBusinessScope` (ADR 0093) + cross-tenant guard explícito nos POSTs (defesa em profundidade)
- Job background `BuscarDfesRecebidosJob` consome NSU SEFAZ → cria/atualiza `NfeDfeRecebido` (sync periódico + manual)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test.

- ❌ Importar XML manualmente (sync vem só de NSU SEFAZ — ADR 0116)
- ❌ Editar/anular evento já registrado SEFAZ (eventos manifestação são append-only por lei)
- ❌ Manifestar fora do prazo legal sem aviso (180d SEFAZ — alerta visual obrigatório)
- ❌ Aprovar XAPI (XML auxiliar) ao confirmar (escopo é só evento 210/220/22*)
- ❌ Disparar emissão de NFe inversa ao desconhecer (canon = só registra evento, não cria documento)
- ❌ Sync agendado fora do schedule canônico (Schedule fechado, sync manual só via `/sync-now`)
- ❌ Notificar fornecedor automaticamente ao desconhecer (canon = só registra evento; relacionamento humano)

---

## UX Targets

- p95 first-paint < 1500ms (lista paginada 50 + KPIs + nsuState)
- Aplicar evento (cienciar/confirmar/desconhecer/não-realizada) < 2000ms (síncrono SEFAZ)
- Sync NSU manual < 4000ms (async via Job, retorno imediato + flash)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (canon Cockpit V2 list+detail densidade alta)
- Atalhos teclado respondem < 100ms
- Tipografia canon ADR 0110: header 24px, badge 12px, prazo `font-mono tabular-nums`
- Cores semânticas Cockpit V2: emerald (confirmada), amber (pendente), red (vencido/<7d), slate (neutro/concluído), orange (não-realizada)
- Foco visual sincroniza com seleção (J/K muda foco no centro)
- Auto-scroll mantém DFe focado em viewport
- Persistência filter sobrevive reload (`localStorage`)

---

## UX Anti-patterns

- ❌ Modal pra detalhe DFe (canon = expandir LinkedApps no painel direito; modal só pra confirmar destrutivos)
- ❌ Bulk-action sem confirmação (canon = confirm explícito por batch + revisão antes)
- ❌ Justificativa < 15 chars (NT 2014.002 SEFAZ exige; UI valida e bloqueia)
- ❌ Auto-confirmar via atalho sem confirm visual (canon = `window.confirm` antes de POST)
- ❌ Cor crua `bg-(green|red|amber)-N` (canon = `bg-emerald-50` / `bg-red-50` / `bg-amber-50` ADR 0110)
- ❌ Reload full após mutação (canon = `preserveScroll: true` + `preserveState: true`)
- ❌ Mostrar DFe de outro tenant na lista (multi-tenant Tier 0)
- ❌ Atalho J/K em foco de input/textarea (handler já checa `e.target instanceof HTMLInputElement`)
- ❌ Toast genérico "OK" sem indicar evento aplicado (canon = mensagem específica do evento)

---

## Automation Hooks

- `GET /nfe-brasil/manifestacao` → `ManifestacaoController::index` (Inertia render com itens paginated + kpis + nsuState + permissions)
- `POST /nfe-brasil/manifestacao/{id}/cienciar` → evento 210 SEFAZ
- `POST /nfe-brasil/manifestacao/{id}/confirmar` → **evento 220 SEFAZ** (confirmação operação)
- `POST /nfe-brasil/manifestacao/{id}/desconhecer` → evento 220 com justificativa (NT 2014.002)
- `POST /nfe-brasil/manifestacao/{id}/nao-realizada` → evento 220 com justificativa
- `POST /nfe-brasil/manifestacao/bulk/confirmar` → batch evento 220
- `POST /nfe-brasil/manifestacao/sync-now` → dispatch `BuscarDfesRecebidosJob` (consome NSU SEFAZ → cria/atualiza DFes pendentes)
- `GET /nfe-brasil/manifestacao/{id}/itens` → JSON pra LinkedItens lazy-fetch
- `GET /nfe-brasil/manifestacao/{id}/eventos` → JSON pra LinkedHistorico lazy-fetch
- Job: `BuscarDfesRecebidosJob` rodado em schedule + on-demand
- Service: `ManifestacaoService::aplicarEvento(id, type)` orquestra build XML + sign cert A1 + POST SEFAZ + parse retorno + persist `NfeDfeEvento`
- Multi-tenant: `HasBusinessScope` no `NfeDfeRecebido` + `NfeDfeEvento` + `NfeDfeNsuState`; cross-tenant guard explícito (`where('business_id', $businessId)`) nos POSTs
- Audit: mutações (cienciar/confirmar/desconhecer/naoRealizada/bulkConfirmar/syncNow) precisam `activity('nfe.manifestacao')->log()` — implementação via US-NFE-062 P1

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir (read da lista é puro)
- ❌ Não dispara emails ao manifestar (canon = relacionamento fornecedor é humano)
- ❌ Não escreve no banco no render inicial (só nos POSTs e no Job)
- ❌ Não chama SEFAZ no render (só nos POSTs de evento + sync-now)
- ❌ Não acessa DFe de outro `business_id` (multi-tenant Tier 0 + cross-tenant guard explícito)
- ❌ Não permite manifestar evento sem cert A1 ativo (validação anterior em ManifestacaoService)
- ❌ Não permite manifestação dupla (idempotente — segundo POST do mesmo evento retorna no-op)
- ❌ Não escreve `nfe_dfe_eventos` sem retorno SEFAZ válido (tudo em transação)
- ❌ Não dispara Job de emissão de NFe inversa (escopo é só registrar evento manifestação)
- ❌ Não loga PII (CNPJ emitente sai limpo; conteúdo XML não vai em log plain text)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// Modules/NfeBrasil/Tests/Charters/ManifestacaoCharterTest.php

it('renders under 1500ms p95 with paginated DFes + KPIs')
it('does not emit emails on render, manifestation or sync')
it('does not call SEFAZ on render (only on POST events + sync-now)')
it('does not write to DB on render (only on POSTs and Job)')
it('isolates DFes by business_id (cross-tenant 404 + guard)')
it('blocks event manifestation when no cert A1 active')
it('rejects justificativa shorter than 15 chars (NT 2014.002)')
it('idempotent: second POST same event returns no-op')
it('renders at 1280px without horizontal scroll')
it('responds to J/K keyboard within 100ms')
it('persists filter to localStorage oimpresso.nfebrasil.manifestacao.filter')
it('does not log PII (CNPJ + XML stripped from any Log entry)')
it('logs activity on each manifestation event with business_id')
```

---

## Comparáveis canônicos

> Já documentado em `memory/requisitos/NfeBrasil/manifestacao-visual-comparison.md` (15 dimensões, approved 2026-05-09).

- **Cockpit V2 list+detail** (canon visual ADR 0110) — atalhos J/K + foco lateral
- **Linear Inbox** — densidade da lista esquerda + KPIs no topo
- **MailApp** — bulk-action via checkbox + confirmação batch
- **Excluir:** AdminLTE legacy DataTables (anti-pattern Wagner — "feio" madrugada 07-mai)

---

## Refs

- [US-NFE-052](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — Manifestação Destinatário UI
- [ADR 0116](../../../../../memory/decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md) — caso Gold (origem)
- [ADR 0029](../../../../../memory/decisions/0029-padrao-inertia-react-ultimatepos.md) — Inertia + UltimatePOS
- [ADR 0039](../../../../../memory/decisions/0039-ui-chat-cockpit-padrao.md) — Cockpit pattern (list+detail base)
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0110](../../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit V2 canon
- [RUNBOOK-manifestacao.md](../../../../../memory/requisitos/NfeBrasil/RUNBOOK-manifestacao.md) — playbook operacional
- [manifestacao-visual-comparison.md](../../../../../memory/requisitos/NfeBrasil/manifestacao-visual-comparison.md) — comparativo visual approved 2026-05-09

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | [CC] charter-write skill + [W] | Draft criado por US-NFE-061 (auditoria de completude module-completeness-audit). Tela já tinha visual-comparison + RUNBOOK aprovados; charter formaliza no padrão. Wagner aprovou Non-Goals + Anti-hooks no mesmo dia → status:live. |
