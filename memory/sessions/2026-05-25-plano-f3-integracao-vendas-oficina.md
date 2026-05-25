---
session: 2026-05-25 plano F3 — Integração Vendas × Oficina (A1 KB-9.75)
status: aprovado · Wagner respondeu 4 decisões 2026-05-25 ~14:15
parent_prs: [#1493 (código F1 protótipo · b2fcabbf2), #1495 (docs SYNC_LOG · 11484114d), #1497 (este plano)]
related_adrs: [0093, 0094, 0104, 0107, 0114, 0143, 0178]
charter_refs: [resources/js/Pages/Sells/Index.charter.md (v4), resources/js/Pages/Repair/ProducaoOficina/Index.charter.md]
---

# Plano F3 — Integração Vendas × Oficina (A1 do método KB-9.75)

> **Objetivo:** traduzir os 5 pontos de costura cross-source dos protótipos Cowork (`prototipo-ui/vendas-page.jsx` + `oficina-page.jsx` + `vendas-extras.jsx`) pra Inertia/React real, sem reescrever os módulos Sells/Repair existentes. OS entregue (stage=pronto) vira automaticamente venda derivada com `source: "oficina"` + `os_ref: "OS-NNNN"`.

> **Não é:** rewrite completo do `Sells/Index.tsx` (1551 LOC já live v4) ou `Repair/ProducaoOficina/Index.tsx` (607 LOC). É **adição cirúrgica** de 5 pontos de costura preservando contrato existente.

---

## Pré-flight done (skill preflight-modulo Tier 0)

| Checklist | Estado |
|---|---|
| Sells/Index.charter.md v4 (last_validated 2026-05-21) | ✅ lido — payload `/sells-list-json` retorna 14 fields derivados; localStorage `oimpresso.sells.*`; tabs Visão ADR 0178 |
| Repair/ProducaoOficina/Index.charter.md (status rascunho) | ✅ lido — kanban genérico shared multi-vertical; `business.repair_settings` JSON; drawer com OS detalhes |
| Sells/Index.tsx atual (1551 LOC) | ✅ identificado — `.sells-cowork` wrapper Cowork verbatim |
| Repair/ProducaoOficina/Index.tsx atual (607 LOC) | ✅ identificado — JobSheet query real + mock fallback |
| memory/requisitos/Repair/BRIEFING.md | ✅ lido — kanban shared multi-vertical, FSM Pipeline ADR 0143 live |
| Observer precedentes | ✅ `Modules/Financeiro/Observers/TransactionObserver.php` + `app/Domain/Fsm/Observers/TransactionFsmObserver.php` (FSM-aware) |
| Schema transactions atual | ✅ sem `source`/`os_ref` ainda — migration aditiva nova |
| Sells/Caixa.tsx | ❌ **NÃO EXISTE** — legacy é `CashRegisterController` Blade. Cowork protótipo `VendasCaixaPage` exigirá charter novo + PR separado |
| ADR auto-faturar OS→Venda | ❌ **NÃO EXISTE** — precisa criar nova ADR pré-Backend (decisões Wagner 2026-05-25: auto-faturar / split / OS sem nota / Felipe filtro) |

---

## Escopo (5 pontos de costura + backend)

### Frontend (Inertia/React)

1. **`Sells/Index.tsx` — coluna Origem** (entre "Atendido por" e "Pipeline")
   - Pill colorida `<VdSource source={row.source} osRef={row.os_ref} />` (Balcão · Oficina · Online)
   - Link `↗ #OS-NNNN` clicável quando `source === 'oficina'`
   - Linha de oficina: stripe azul sutil border-left (`.os-row[data-source="oficina"]`)
2. **`Sells/Index.tsx` — saved tree "Por origem ▾"** dropdown Visões
   - Branch expansível com filhos Balcão/Oficina/Online + contadores
   - Persiste seleção em `localStorage['oimpresso.sells.visao_origem']`
3. **`Sells/Index.tsx` — KPI hero breakdown** quando `Foco === 'faturamento'`
   - "Faturado hoje · todas origens" header
   - Breakdown line: `● Balcão R$ X · ● Oficina R$ Y · ● Online R$ Z`
4. **`Repair/ProducaoOficina/Index.tsx` drawer** — card "Esta OS gerou venda #V-NNNN"
   - Condicional: `stage === 'pronto'` AND `os.venda_derivada !== null`
   - Card highlight verde com breakdown peças/serviço + fiscal + 3 atalhos (Abrir #V-NNNN / Imprimir recibo / Compartilhar)
   - Botão "Abrir" dispatch `window.CustomEvent('oimpresso:open-venda', { detail: { venda_id } })` → Sells/Index listener abre drawer SaleSheet
5. **`Sells/Caixa.tsx`** ← **PR SEPARADO** (charter novo, fora desta integração)
   - Substitui legacy `CashRegisterController` Blade
   - Seção "Por origem" com barras de progresso por source + refs cross-módulo
   - **Decisão pendente:** entra nesta integração OU em wave separada?

### Backend

6. **Migration aditiva** — `database/migrations/YYYY_MM_DD_add_source_and_os_ref_to_transactions.php`
   ```sql
   ALTER TABLE transactions
     ADD COLUMN source ENUM('balcao','oficina','online') NULL DEFAULT 'balcao',
     ADD COLUMN os_ref VARCHAR(20) NULL,
     ADD INDEX idx_transactions_source (business_id, source, transaction_date);
   ```
   - Default `'balcao'` retroativo (vendas legacy = balcão)
   - `business_id` mantém global scope (Tier 0)
   - `idx_transactions_source` composto pra KPI breakdown query
7. **`Modules/Repair/Observers/JobSheetObserver.php`** — pattern `Modules/Financeiro/Observers/TransactionObserver.php`
   - Hook `updated` → quando `current_stage_id` transiciona pra status `is_completed_status === true` (canonical "Pronto/Entregue"), cria `Transaction` derivada com `source = 'oficina'`, `os_ref = "OS-{job_sheet_id}"`, business_id herdado
   - Idempotente: se `Transaction::where('os_ref', "OS-{id}")->exists()` skip (não duplica)
   - Decisões Wagner: split comissão mecânico/balcão (campo `commission_split JSON`) · OS sem nota vira venda mesmo (`fiscal: {}` vazio) · Felipe filtro pré-aplicado é UI-only (não toca backend)
8. **`SellController::getSellsListJson()`** — adicionar `source` + `os_ref` ao payload + label derivado
9. **`ProducaoOficinaController::index()`** — adicionar `venda_derivada` lookup por `os_ref` no drawer payload

---

## Decomposição em ondas (PRs atômicos ≤300 LOC cada · skill commit-discipline)

| # | Onda | Escopo | LOC est | Dependências |
|---|---|---|---|---|
| **0** | **ADR auto-faturar OS→Venda** | `memory/decisions/0NNN-auto-faturar-os-venda-observer.md` (Nygard) registrando 4 decisões Wagner + Observer pattern + idempotência + LGPD note | ~120 LOC docs | nenhuma |
| **1** | **Backend schema** | Migration `add_source_and_os_ref_to_transactions` + cast no Model `App\Transaction` + 2 Pest GUARDs (default 'balcao' / idx existe) | ~60 LOC code + ~80 LOC tests | Onda 0 ADR aceita |
| **2** | **Backend Observer + payload** | `JobSheetObserver@updated` + register em `Modules/Repair/Providers/RepairServiceProvider.php` + `SellController::getSellsListJson()` adiciona source/os_ref/label + `ProducaoOficinaController::index()` adiciona venda_derivada + 4 Pest tests (Observer fires once · idempotente · multi-tenant scope · OS sem nota cria sem fiscal) | ~180 LOC code + ~150 LOC tests | Onda 1 |
| **3** | **Frontend Sells/Index coluna Origem** | `VdSource` component + coluna na tabela + stripe `.os-row[data-source]` + tokens CSS source no `.sells-cowork` (extrai do `vendas.css` Cowork) + 2 Pest browser tests (pill renderiza · link OS dispara navegação) | ~120 LOC code + ~80 LOC tests | Onda 2 |
| **4** | **Frontend Sells/Index saved tree + KPI breakdown** | Tree branch `origem` expansível no dropdown Visões + listener `oimpresso:open-venda` + KPI hero breakdown condicional `Foco=Faturamento` + persist localStorage `oimpresso.sells.visao_origem` | ~140 LOC code + ~60 LOC tests | Onda 3 |
| **5** | **Frontend Repair/ProducaoOficina drawer card** | `.ofc-venda-card` quando `stage='pronto'` + dispatch `oimpresso:open-venda` + 3 atalhos + breakdown peças/serviço/fiscal | ~150 LOC code + ~50 LOC tests | Onda 4 |
| **6** | **Sells/Caixa.tsx (charter novo + PR separado)** | Charter `Sells/Caixa.charter.md` + componente `VendasCaixaPage` + seção "Por origem" + controller route nova `GET /vendas/caixa` (preserva legacy `/cash-register/*`) | ~250 LOC code + ~100 LOC tests + charter | independente — **PERGUNTA Wagner: entra agora ou wave separada?** |

**Total:** 5 PRs principais (Ondas 0-5) + Onda 6 opcional · ~1.000 LOC code · ~520 LOC tests · ~6-10h IA-pair com margem 2x ([ADR 0106 recalibração](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

---

## Multi-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) — guardrails

- ✅ Migration adiciona índice composto `(business_id, source, transaction_date)` — KPI breakdown query sempre scopado
- ✅ Observer JobSheet herda `business_id` da OS pra criar Transaction derivada (nunca cross-tenant)
- ✅ Idempotência via `where('os_ref', $ref)->where('business_id', $businessId)` — não duplica nem cross-tenant
- ✅ Endpoint `/sells-list-json` mantém global scope existente · novo field `source` não fura tenancy
- ✅ Endpoint `/repair/producao-oficina` mantém `business_id` scope existente · `venda_derivada` lookup também scopado
- ✅ Pest test obrigatório `MultiTenantIntegrationVendasOficinaTest` — verificar que OS biz=1 cria Transaction biz=1 (não biz=2)

---

## Permissions (skill multi-tenant-patterns)

- Frontend Sells/Index coluna Origem renderiza sempre — gate de visibilidade é em `direct_sell.view` existente
- Repair drawer card "Esta OS gerou venda" só aparece pra usuário com permissão `direct_sell.view` (lookup já scopado)
- Backend Observer roda independente de permission (system-level, dispara em response a state change)
- Felipe filtro pré-aplicado: UI-only via `localStorage['oimpresso.sells.visao_origem']='oficina'` se `user.profile_default === 'mecanico'` — sem ACL hard

---

## ADRs necessárias (Onda 0)

1. **ADR nova — Auto-faturar OS→Venda via Observer** (status: proposed)
   - Contexto: integração Sells × Repair cross-source · método KB-9.75 A1
   - Decisão: `JobSheetObserver@updated` cria `Transaction` derivada quando stage transiciona pra `is_completed_status=true`. Idempotente por `os_ref`. Multi-tenant scoped.
   - Decisões Wagner 2026-05-25: auto-faturar sem click manual / split comissão JSON / OS sem nota cria venda mesmo (`fiscal: {}` vazio) / Felipe filtro UI-only
   - Consequências: backlog: comissão UI editor (futuro) · NFC-e/NFS-e dispatch async não acopla (mantém atual)
   - Status: proposed (aprovação Wagner pré-Onda 1)

---

## Decisões Wagner (aprovadas 2026-05-25 ~14:15)

1. **Onda 6 (`Sells/Caixa.tsx`):** **wave separada.** Sai do escopo desta integração. PR atômico ≤300 LOC mantido. Backlog item: charter `Sells/Caixa.charter.md` + componente novo + substituição de `CashRegisterController` Blade legacy.
2. **Observer trigger FSM:** **`entregue_completo`** (terminal · ADR 0143). Conservador — venda registra quando cliente buscou OS e dinheiro entrou (não em `pronto_para_retirar` que pode ter cliente desistindo).
3. **`commission_split` JSON shape:** **`{ mecanico_id: int, mecanico_pct: float, balcao_id: int|null, balcao_pct: float }`** total=100 validado server-side. Balcão NULL quando só mecânico (mecanico_pct=100). Cobre 95% dos casos. Estrutura array flex `[{user_id, role, pct}]` fica pro futuro se sinal aparecer.
4. **Branch protection:** **review manual a cada onda.** Wagner aprova + mergeia cada PR no GitHub. 5 PRs principais (Ondas 0-5). Mais seguro pra prod.

(Decisões 2 e 5 não perguntadas explicitamente — defaults: PR-ADR auditável · sem `--admin` exceto plan PR #1497.)

---

## Execução paralela aprovada (Wagner 2026-05-25 ~14:15)

```
FASE 1 (serial · backend gate):
  Onda 0 ADR → Wagner merge → Onda 1 Migration → Wagner merge → Onda 2 Observer+payload → Wagner merge

FASE 2 (2 sub-agents paralelos via coordenador-paralelo):
  ┌─ Worker A (sequencial dentro): Onda 3 (coluna Origem) → Wagner merge → Onda 4 (tree + KPI + listener) → Wagner merge
  └─ Worker B (isolado):          Onda 5 (Repair/ProducaoOficina drawer card) → Wagner merge

Onda 6 (Sells/Caixa.tsx): wave separada futura — fora deste plano.
```

Worker A sequencial porque tocam mesmo arquivo `Sells/Index.tsx` (merge conflict garantido se paralelo).
Worker B independente — `Repair/ProducaoOficina/Index.tsx` é arquivo disjunto.
Ganho estimado: Fase 2 fecha em max(A_total, B) em vez de A+B → ~40% redução.

---

## Próximo passo

✅ Plano aprovado. **Iniciando Onda 0 (ADR auto-faturar OS→Venda)** em paralelo com merge do plan PR #1497.

Cada onda seguirá mwart-process 5 fases (PLAN → BACKEND BASELINE → FRONTEND INCREMENTAL → QA → CUTOVER) com visual-comparison.md por tela tocada (skill mwart-comparative V4) + Pest GUARDs antes de Edit em prod.
