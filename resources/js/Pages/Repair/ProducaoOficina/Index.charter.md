---
page: /repair/producao-oficina
component: resources/js/Pages/Repair/ProducaoOficina/Index.tsx
owner: wagner
status: draft
status_detail: rascunho
last_validated: "2026-05-10"
parent_module: Repair
related_adrs: [94, 101, 114, 121, 93, 192]
related_prototype: prototipo-ui/prototipos/producao-oficina/F1.html
tier: A
---

# Page Charter — /repair/producao-oficina

> **Status:** F3 implementação inicial baseada em [F1 aprovado por Wagner em 2026-05-09](../../../../prototipo-ui/prototipos/producao-oficina/F1.html). Greenfield — sem tela Blade legacy.
> **Vocabulário shared (refactor 2026-05-10 US-REPA-002 Caminho A — [ADR 0121 §P8](../../../../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)):** kanban opera em vocabulário **genérico** consumível por qualquer vertical (Vestuario / ComunicacaoVisual / OficinaAuto). `code/item/usage_meter/executor/slot/area` em vez de termos específicos automotivos. Labels e slot_config vêm de `business.repair_settings` JSON.
> Query real `JobSheet` (US-REPAIR-PROD-2) com fallback gracioso pra mock data se biz não tem `repair_statuses` configurado.

---

## Mission (1 frase)

Visão de produção em **kanban de 5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto) pra operadores enxergarem o fluxo do dia em monitor 1280px **sem precisar abrir cada OS** — funciona pra qualquer vertical (assistência-técnica / oficina automotiva / gráfica / vestuário).

---

## Goals — Features (faz)

- Kanban 5 colunas com cards de OS (`code` identificador + `item` descrição + `brand` marca + `usage_meter`+`usage_unit` + `executor` responsável + `slot`/`area` localização física)
- Drawer lateral com detalhes da OS clicada (sintoma + fotos + peças + linha do tempo + banner aprovação cliente)
- Filtros funcionais via `slot_config` dinâmico (default: Box B1-B4 + Elevador E1-E2; outras verticais customizam via `business.repair_settings.slots`) — chips clicáveis no header, atualiza grid client-side via `useMemo` (US-REPAIR-PROD-3)
- `label_overrides` permite verticais sobrescreverem labels (ex: `executor → "Designer"` em ComVisual; `slot → "Bancada"` em vestuário)
- Contador "X de Y OS" quando filtro ativo + botão "Limpar filtros"
- Destaque visual nas OS `pending_approval = true` (banner laranja)
- **Drag-and-drop entre colunas** (US-REPAIR-PROD-4) — HTML5 nativo + optimistic update + POST `/move` que persiste no backend via mapping reverso (coluna → primeiro `repair_status_id` do bucket alvo)
- Cabe em monitor 1280px sem scroll horizontal (Larissa quirk crítico — aplicado a outros clientes 1280px também)
- AppShellV2 + topnav Repair (`KanbanSquare` icon)
- **Multi-tenant Tier 0** ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) preservado: queries `JobSheet` scopadas por `business_id` global scope; endpoint `/move` valida `business_id` antes de mutação
- **Onda 5 — Integração Vendas × Oficina** ([ADR 0192](../../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)): drawer renderiza card `Esta OS gerou a venda #V-NNNN` quando OS está na coluna `pronto` (= FSM `entregue_completo`) AND tem `venda_derivada` (Transaction `source='oficina'` criada pelo `JobSheetObserver`). Card mostra total + data + 3 atalhos:
  - **Abrir #V-NNNN** → dispatch `window.CustomEvent('oimpresso:open-venda', { detail: { venda_id } })` — listener em `Sells/Index.tsx` (Worker A Onda 4) abre drawer SaleSheet (loose coupling)
  - **Imprimir recibo** → `window.open('/sells/{venda_id}/print', '_blank')` (rota Blade legacy preservada)
  - **Compartilhar** → Web Share API nativa (mobile/PWA share-sheet) com fallback `navigator.clipboard.writeText()` + toast Sonner (desktop). Payload `Venda #V-NNNN · R$ XX,XX · DD/MM/YYYY` + URL `/sells/{id}`. `AbortError` (user cancelou) tratado silenciosamente. (Onda 5 follow-up Worker 3 · 2026-05-25)
- **FASE B — VendaDerivadaCard evolution** (Wave Z-2 backend W2 `2f6f10fc8` · 2026-05-25): card mostra **breakdown** Peças vs Serviços (grid 2-col responsive · empilha em mobile) + linha Subtotal + Desconto (se > 0 · rose) + Impostos (se > 0 · slate); **badge fiscal** NF-e com 4 estados (`autorizada` verde + link DANFE clicável `window.open(/danfe/{id}, '_blank')` · `pendente` amber · `rejeitada` rose · `null` slate sutil "Sem nota fiscal" pra OS informal); **lista items expandível** disclosure pattern (collapsed por default · `▸ Ver N itens da venda` → `▾ Ocultar` · max 10 visíveis · "+ N adicionais" sumário). Prefix textual "Peça" / "Serviço" (skill `pageheader-canon` · ZERO emoji em UI). Empty states tolerantes preservam Onda 5 backward compat: `items_list` ausente/`[]` não renderiza breakdown nem disclosure; `fiscal: null` renderiza só badge "Sem nota fiscal".
- Vocabulário shared multi-vertical preservado ([ADR 0121 §P8](../../../../../memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)) — `venda_derivada` é cross-vertical (OficinaAuto, ComunicacaoVisual, Vestuario)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test futuro (Non-Goal violado = CI quebra).

- ❌ Vocabulário automotivo hardcoded (`placa/vehicle/km/mecanico/box/elevador`) — refactor US-REPA-002 baniu. CI guard `repair-shared-vocab.yml` falha se voltar.
- ❌ CRUD de OS (vai pra `/repair/job-sheet`)
- ❌ Notificações push de mudança de status
- ❌ Atribuição de `executor` via UI (vai pra JobSheet)
- ❌ Aprovação cliente *via* esta tela (botão "Reenviar" no drawer só dispara WhatsApp template — sem UI de aprovar/recusar aqui)
- ❌ Edição inline em qualquer card
- ❌ Tela específica per-vertical (Vestuario/ComVisual/OficinaAuto NÃO tem `/vestuario/producao-oficina` — todas usam `/repair/producao-oficina` parametrizado por `business.repair_settings`)
- ❌ **Onda 5 / FASE B placeholder TODOs (charter aceita explícito):**
  - ~~Botão "Compartilhar" no card da venda derivada **sem ação por ora**~~ → **entregue Onda 5 follow-up Worker 3 (2026-05-25)** via Web Share API nativa + clipboard fallback + toast Sonner
  - ~~Breakdown peças/serviço no card~~ → **entregue FASE B (2026-05-25)** após Wave Z-2 backend W2 (`2f6f10fc8`) expandir payload com `items_list` + `items_summary` (eager-load `sell_lines.product` anti-N+1)
  - ~~Badges fiscais NF-e/NFS-e no card~~ → **entregue FASE B (2026-05-25)** apenas para NF-e (modelo 55/65). NFS-e segue Non-Goal — feature `NfeBrasil` não emite NFS-e ainda; quando emitir, expandir badge.
  - Edição inline da venda derivada (drawer só lê · CRUD vai pra `/sells/{id}/edit`)
  - Botão "Ver no Caixa do dia" (Sells/Caixa.tsx é Onda 6 wave separada · fora deste plano)
  - Items list ilimitada — capamos em 10 visíveis + sumário "+ N adicionais" pra não estourar densidade do drawer (480px)
  - Drilldown per-item (clicar num item → abrir detalhe) — fora do escopo; drawer Sells/SaleSheet já cobre

---

## UX Targets

- p95 first-paint < 600ms (mock data inline OU query real cached; props vêm prontas do Controller)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal — kanban grid `grid-cols-5` calculado pra caber
- Drawer overlay direita 480px com `transition` suave
- AppShellV2 layout (sidebar 260 + main 1fr; sem LinkedApps painel)
- Empty state em cada coluna sem cards ("Nenhuma OS")
- Density: cards compactos (3-4 visíveis por coluna sem scroll)
- `code` em fonte mono pra identificação rápida (mantém legibilidade pra placas/notas/SKUs)

---

## UX Anti-patterns

- ❌ Modal de qualquer tipo (drawer é o único container)
- ❌ Loading skeleton (props inline, sem async)
- ❌ Toast/snackbar
- ❌ Cores berrantes (DNA Cockpit V2 conservador — slate neutro + accent laranja só pra `pending_approval`)
- ❌ Animações decorativas (transitions só em hover/drawer)

---

## Automation Hooks

- Endpoint `/repair/producao-oficina` chama `ProducaoOficinaController::index`
- `loadRepairSettings($businessId)` lê `business.repair_settings` JSON (default fallback: Box+Elevador)
- Mock data inline (até US-REPAIR-PROD-2 amadurece) — query real virá com filtros backend e paginação por coluna
- Multi-tenant: queries scopadas por `business_id` global scope ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Sem cache de página (mock é estático; query real terá cache 30s por business)

---

## Configuração per-vertical (`business.repair_settings` JSON)

```json
{
  "slots": [
    {"key": "slot", "label": "Box",      "options": ["B1", "B2", "B3", "B4"]},
    {"key": "area", "label": "Elevador", "options": ["E1", "E2"]}
  ],
  "labels": {
    "executor": "Mecânico",
    "code": "Placa",
    "item": "Veículo"
  }
}
```

Vertical decide o que faz sentido. Vestuário pode ter `slots: [{key: "rack", label: "Arara", options: ["A1"..."A20"]}]`. Comunicação Visual: `slots: [{key: "machine", label: "Máquina", options: ["Plotter1", "ACM2", "Lona-frente"]}]`. OficinaAuto reusa default Box+Elevador.

---

## Backlog (US futuras)

- ✅ **US-REPAIR-PROD-2** — query real `JobSheet` com fallback gracioso pra mock se biz não tem `repair_statuses` configurado. Heurística sort_order quartil pra mapear status arbitrários do business pras 5 colunas fixas. Prop `data_source: 'live' | 'mock'` indica origem.
- ✅ **US-REPAIR-PROD-3** — filtros funcionais via slot_config dinâmico (entregue)
- ✅ **US-REPAIR-PROD-4** — drag-and-drop entre colunas via HTML5 nativo + optimistic update + POST `/producao-oficina/{id}/move`
- ✅ **US-REPAIR-PROD-5** — Pest GUARD entregue (`Modules/Repair/Tests/Feature/ProducaoOficinaTest.php`)
- ✅ **US-REPA-002** — Caminho A refactor vocabulário shared (este charter atualizado)
- ⏳ **US-REPA-003** — CI workflow `repair-shared-vocab.yml` que falha se `placa|vehicle|km|mecanico|elevador|box` voltar em `Modules/Repair/**` ou `resources/js/Pages/Repair/**`
- ⏳ **US-REPA-004** — Vestuario/ComVisual/OficinaAuto seeders `RepairSettingsSeeder` populando `business.repair_settings` per-vertical
- ✅ **US-REPA-INT-VND-5** — Onda 5 Integração Vendas × Oficina A1 KB-9.75 ([ADR 0192](../../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)): drawer card "Esta OS gerou venda #V-NNNN" + 3 CTAs (Abrir dispatch / Imprimir recibo / Compartilhar TODO). Adição cirúrgica preservando kanban + drag-drop + filtros + demais drawer sections.
- ✅ **US-REPA-INT-VND-B** — FASE B VendaDerivadaCard evolution (Wave Z-2 backend `2f6f10fc8`): breakdown peças/serviço + badge fiscal NF-e (autorizada/pendente/rejeitada/null) + lista items expandível com cap 10 + "+ N adicionais". Empty states tolerantes (items vazio + fiscal null). Tokens `.ofc-venda-grid` / `.ofc-vc` / `.ofc-fb-*` Cowork preservados verbatim. Pest GUARDs file-content pattern: `ProducaoOficinaFaseBVendaDerivadaCardTest.php`.
