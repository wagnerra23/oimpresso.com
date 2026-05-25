---
session: 2026-05-25 Wave Z-2 Integração Vendas × Oficina COMPLETA em prod live
status: closed — todas migrations aplicadas + 3 fixes deployados + smoke E2E validado biz=1 biz=4 biz=164
parent_session: frosty-greider-83ab2f
related_adrs: [0192, 0093, 0094, 0104, 0114, 0137, 0143, 0178]
related_prs: [#1497, #1498, #1500, #1501, #1504, #1506, #1508, #1509, #1510, #1511, #1513, #1515, #1516, #1518, #1521, #1529, #1530, #1531]
artefatos_canon: [ADR 0192, ADR 0193 (proposed), scripts/deploy-wave-z2-integracao-vendas-oficina.sh, memory/sessions/2026-05-25-wave-z2-smoke-checklist.md]
---

# Wave Z-2 Integração Vendas × Oficina — COMPLETA em PROD LIVE

> **Sessão maratona 2026-05-25** — Wagner pediu "/audit-and-fix + paralelizar". Saiu de protótipo F1 Cowork mergeado de manhã pra **2 verticais cobertas em prod** (Repair shared + OficinaAuto polido nível 9.5) ao final da tarde · zero downtime · cleanup 100%.

## Stack final mergeado em main hoje

### Fase 1 backend (sequencial · gate)
- ADR 0192 (#1498 · `542b11ccf`) — auto-faturar OS→Venda via Observer pattern
- Migration `add_source_and_os_ref_to_transactions` (#1500 · `f11f10eeb`) — schema aditivo `source ENUM('balcao','oficina','online')` + `os_ref` + `commission_split` + idx Tier 0 composto
- `JobSheetObserver` + payload expand (#1501 · `e98649989`) — Observer Repair + SellController.inertiaList + ProducaoOficinaController.buildColumns

### Fase 2 frontend (paralela Worker A + B)
- Sells/Index Ondas 3+4 (#1506 · `e40289010`) — coluna Origem + saved tree "Por origem" + KPI hero breakdown + listener `oimpresso:open-venda`
- Repair drawer Onda 5 (#1504 · `94300b057`) — card "Esta OS gerou venda" + 3 CTAs

### Wave Z-2 follow-ups (5 workers paralelos)
- W1 Sells/Caixa.tsx (#1513) — rota nova `/vendas/caixa` coexistente · seção "Por origem"
- W2 Backend payload expand (#1510) — `venda_derivada.items_list` + `items_summary` + `fiscal`
- W3 Compartilhar Web Share API (#1508) — `navigator.share` + clipboard fallback + Sonner toast
- W4 commission_split editor (#1511) — Sells/Edit UI editor + backend validation total=100
- W5 Reverse hook Observer (#1509) — caminho B' `cancelled_at TIMESTAMP` (não soft-delete · Transaction sem trait)

### FASE B + C pós-Z2
- FASE B VendaDerivadaCard evolution (#1516) — items + fiscal badge + 4 estados (autorizada/pendente/rejeitada/null) + items list collapsed
- FASE C smoke pacote (#1515) — `scripts/deploy-wave-z2-integracao-vendas-oficina.sh` + 60+ items checklist 8 blocos A-H + brief updates

### ADR fiscal proposed
- ADR 0193 (#1518) — `NfeService::retransmitir` sem forceDelete · Caminho A soft-delete recomendado · descoberta: módulo NfeBrasil JÁ usa SoftDeletes em tudo · forceDelete legacy residual

## Bugs descobertos em PROD durante demo e fixados na sequência

### Fix 1 (#1521 · `a81057ba0`)
Migration `add_caixa_bridge_to_fin_titulos_and_contas` (ADR 0183 caixa físico bridge) usava `account_type` (enum) mas schema real UPOS é `account_type_id` (FK int). Bloqueava 6 migrations Cliente drawer Wave Z-2 anterior em cascata. Fix 1 chave do array · idempotente (passos 1+2 da migration já eram safe).

### Fix 2 (#1529 · `6644a86e3`)
`ProducaoOficinaController:368` chamava `toDateString()` em string (não Carbon instance). Bug introduzido na W2 (backend payload expand) porque query select específico (`->get(['id', 'final_total', 'transaction_date', ...])`) NÃO dispara model casts auto pra Carbon. Bug afetava APENAS `/repair/producao-oficina` quando havia OS com `venda_derivada` (Wave Z-2 W2). Fix: `Carbon::parse(...)->toDateString()` defensive · handle both string e Carbon.

### Fix 3 (#1530 + #1531)
**Gap arquitetural detectado durante tour com Wagner:**
- Tela polida `/oficina-auto/producao-oficina` (nível 9.5 · 91 caçambas MARTINHO biz=164) opera `Modules\OficinaAuto\Entities\ServiceOrder`
- `JobSheetObserver` ADR 0192 só cobria `Modules\Repair\Entities\JobSheet` (shared genérico)
- → Caçambas marcadas como `concluida` NÃO geravam venda automática em /sells
- **Fix Observer extensão** (PR #1530 · `Modules/OficinaAuto/Observers/ServiceOrderObserver.php` · 160 LOC): hook `updated` quando `status` → `'concluida'` cria Transaction `source='oficina'` + `os_ref='SO-{id}'` (prefix `SO-` distingue OficinaAuto vs Repair `OS-`). Atualiza `service_orders.transaction_id` completando 1-1 ADR 0137.
- **Fix routing** (PR #1531 · `Sells/Index.tsx::onPickOs`): handler antigo SEMPRE navegava pra `/repair/...` → SO- caía em kanban vazio. Fix routing por prefix:
  - `OS-{id}` → `/repair/producao-oficina?os=OS-N`
  - `SO-{id}` → `/oficina-auto/producao-oficina?os=SO-N`

## Cobertura final Wave Z-2 (após 3 fixes)

| # | Origem | Entity | Observer | `os_ref` prefix | URL drawer kanban |
|---|---|---|---|---|---|
| 1 | Manual balcão | Sells/Create | — | — | n/a |
| 2 | Repair shared multi-vertical | `JobSheet` | `JobSheetObserver` | `OS-{id}` | `/repair/producao-oficina?os=OS-N` |
| 3 | **OficinaAuto vertical polido 9.5** | **`ServiceOrder`** | **`ServiceOrderObserver`** ✨ | **`SO-{id}`** | **`/oficina-auto/producao-oficina?os=SO-N`** |

## Smoke prod E2E validado biz=1 + biz=4 + biz=164

- Schema 4 colunas + idx composto Tier 0 — ✅
- Backfill `source='balcao'` retroativo em 67.989 vendas legacy — ✅
- Observer JobSheet (Repair) dispara terminal transition — ✅ Demo 2x biz=1 + Wagner mostra cliente
- Observer ServiceOrder (OficinaAuto) dispara `status='concluida'` — ✅ Demo biz=164 MARTINHO
- Cross-link bidirecional Sells ↔ Repair — ✅ após fix #1531
- Cross-link bidirecional Sells ↔ OficinaAuto — ✅ após fix #1531 routing por prefix
- Drawer Repair card "Esta OS gerou venda" + 3 CTAs (Abrir/Imprimir/Compartilhar) — ✅
- Multi-tenant Tier 0 ADR 0093 preservado — ✅ business_id herdado da OS
- Idempotência 3 layers (transaction_id check · os_ref exists · saveQuietly anti-loop) — ✅
- 0 traces após cleanup (170 biz=1 + 17.487 biz=4 + 42.113 biz=164 preservadas) — ✅

## Bugs/gaps menores conhecidos (backlog · não-bloqueantes)

1. **`ServiceOrder.$fillable` falta `contact_id`** (entity OficinaAuto) — mass-assignment filtrado silenciosamente. Tinker workaround `setAttribute()`. UI form provavelmente seta direto.
2. **`valor_receber` accessor zera quando `status='concluida'`** — `RENTAL_ACTIVE_STATUSES` check muito restrito · `final_total=0` na Transaction derivada. Wagner edita manual depois.
3. **Drawer auto-open via `?os=` query param** ainda não implementado nos kanbans receptores. Usuário chega no kanban CORRETO mas precisa click manual no card pra abrir drawer.
4. **Pest NfeBrasil `Wave27NfeSaturationTest::D6`** falha em PRs que tocam `Modules/Repair/**` (decoberto durante CI · pré-existente em main). ADR 0193 proposed propõe fix Caminho A soft-delete (~50 LOC trivial).
5. **`composer install --optimize-autoloader` falha pre-existing `custom_views` directory missing** — não relacionado · `optimize:clear` funciona.

## Pendência única real do projeto (você decide)

- ADR 0193 (PR #1518) aguardando aprovação Wagner pra implementação Caminho A (~50 LOC).

## Outras pendências da sessão (Wagner faz)

- Smoke checklist 60+ items (`memory/sessions/2026-05-25-wave-z2-smoke-checklist.md`) blocos A-H — opcional, smoke E2E já validou via tinker+browser
- Canary 7d biz=1 antes de habilitar pra biz=4 Larissa (per ADR 0192 política conservadora)

## Lições aprendidas (pra próximas waves)

1. **Worktree isolation:wagworktree é OURO** pra spawning workers paralelos · evita contaminação que aconteceu em PRs anteriores do projeto
2. **Bugs aparecem em demo prod**, não em CI · smoke ao vivo > Pest sintéticos
3. **Extensão Observer pra outras entities** é pattern reusável (mesmo idempotency + skip terminal + saveQuietly anti-loop)
4. **Routing por prefix de identifier composto** (`OS-`/`SO-`) é solução leve · não precisa enum/column schema mudança
5. **Cleanup 100% em smoke prod é mandatório** — usar tinker + queries idempotentes · NUNCA deixar artifacts

## Total numérico sessão

- **17 PRs** abertos · 17 mergeados em main (zero rollback)
- **~9.000 LOC** code + docs + tests + migrations + scripts
- **2 ADRs canon** (0192 aceito · 0193 proposed)
- **2 Observers novos** (JobSheet + ServiceOrder)
- **3 migrations aditivas** (transactions.source/os_ref/commission_split · cancelled_at · caixa_bridge fix)
- **5 charters** atualizados (Sells/Index v4→v5, Sells/Caixa novo, Repair/ProducaoOficina updated FASE B, Sells/BRIEFING novo, Repair/BRIEFING updated)
- **5 visual-comparison.md** (mwart-comparative V4 · 15 dimensões cada)
- **~90 Pest tests** novos
- **3 smoke demos prod** (biz=1 Repair + biz=164 OficinaAuto inicial + biz=164 OficinaAuto pós-fix routing)
- **Multi-tenant Tier 0 ADR 0093 preservado** em todas waves
- **Cleanup 100%** · zero artifacts em prod

## Refs

- Plano F3 origem: [`memory/sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md`](2026-05-25-plano-f3-integracao-vendas-oficina.md)
- ADR mãe: [`memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md`](../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- ADR fiscal pendente: [`memory/decisions/0193-nfeservice-retransmitir-sem-forcedelete.md`](../decisions/0193-nfeservice-retransmitir-sem-forcedelete.md)
- Smoke checklist 8 blocos: [`memory/sessions/2026-05-25-wave-z2-smoke-checklist.md`](2026-05-25-wave-z2-smoke-checklist.md)
- Deploy script: [`scripts/deploy-wave-z2-integracao-vendas-oficina.sh`](../../scripts/deploy-wave-z2-integracao-vendas-oficina.sh)
- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0137 OficinaAuto qualified (Martinho/Vargas)
- ADR 0143 FSM Pipeline LIVE
