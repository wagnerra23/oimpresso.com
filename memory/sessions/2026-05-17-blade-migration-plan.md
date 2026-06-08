# Plano migracao Blade massiva -> MWART (W31-10)

> **Sessao:** 2026-05-17 (bulk-screen-review-r1 / W31-10)
> **Origem:** ROADMAP onda paralelizacao bulk-screen-review-r1 — pareado com geracao UI-CATALOG.md cross-projeto (33 modulos).
> **Decisao requerida Wagner:** aprovar lista T1 (Top 30 telas Blade prod-criticas) -> dispara `/migracao-blade-react` batch.
> **Cross-ref:**
> - [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canonico (unico caminho)
> - [ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) — Visual comparison gate F3
> - [ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — Cowork loop formalizado
> - [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE (relevante p/ Sells/Repair MWART)
> - Skill `migracao-blade-react` (Tier B auto-trigger)

---

## Estado atual (2026-05-17)

| Metrica | Valor | Observacao |
|---|---:|---|
| Pages tsx Inertia (sem _components) | **192** | telas reais usuario-visiveis |
| Pages tsx total (com helpers) | 274 | inclui `_components/`, `_lib/`, etc |
| Charters `.charter.md` | 86 | cobertura ~45% das telas Inertia reais |
| Blade `resources/views/**` (UltimatePOS core) | **648** | majoritariamente legacy POS |
| Blade `Modules/<X>/Resources/views/**` | **517** | distribuido 19 modulos |
| **Blade total restante** | **1.165** | meta MWART: reduzir 90%+ em 6 cycles |
| Cobertura MWART (Inertia % vs total) | ~14% | (192 / (192+1165)) |

### Top 15 modulos por Blade count

| Modulo / area | Blade count | Pages tsx ja MWART | Razao recomendada |
|---|---:|---:|---|
| **resources/views (UltimatePOS core)** | 648 | — | legacy POS — migrar gradual, area-por-area (top dirs abaixo) |
| **Modules/Accounting** | 91 | 0 | Wagner pediu prioridade Blade migration; **0 MWART** ainda |
| **Modules/Essentials** | 87 | 13 | mix legacy + novo; Documents/Holidays/Knowledge ja tsx, restante legacy |
| **Modules/Crm** | 68 | 0 | Wave 9-10 ja em prod via React custom (nao Inertia native); avaliar caminho |
| **Modules/Repair** | 52 | 11 | FSM canon — Inertia majoritario, restante legacy (DeviceModels, Index legacy) |
| **Modules/Superadmin** | 45 | — | painel admin Officeimpresso, separado runtime |
| **Modules/Cms** | 45 | 0 | landing/CMS — prioridade media |
| **Modules/Ponto** | 26 | 20 | Ponto v2 majoritariamente Inertia, restantes sao relatorios PDF/print |
| **Modules/Manufacturing** | 20 | 1 | producao — apenas 1 tsx, alto legacy |
| **Modules/Officeimpresso** | 18 | — | painel admin, prioridade baixa |
| **Modules/AssetManagement** | 17 | — | pouco usado |
| **Modules/Woocommerce** | 13 | — | integracao externa, prioridade baixa |
| **Modules/Jana** | 9 | 9 | quase 100% MWART; restante sao webhooks/emails |
| **Modules/ProductCatalogue** | 8 | — | catalog publico, avaliar caminho |
| **Modules/Spreadsheet** | 7 | — | export — pode ficar Blade indefinidamente (PDF/Excel) |

### Top 10 dirs `resources/views/**` (UltimatePOS core)

| Dir | Blade count | Justificativa migracao |
|---|---:|---|
| `report/` | 27 | relatorios — muitos sao print/PDF (manter Blade); avaliar dashboards interativos |
| `contact/` | 14 | Customer/Supplier CRUD — alta visibilidade prod biz=4 ROTA LIVRE |
| `transaction_payment/` | 11 | pagamento — alta visibilidade |
| `sale_pos/` | 10 | POS — ja sendo migrado por `Sells/` Inertia (paralelo) |
| `product/` | 10 | produto CRUD — `Produto/` Inertia ja existe (8 tsx); restante = forms avancados |
| `layouts/` | 8 | templates Blade base — manter ate cutover total Inertia |
| `account/` | 8 | contas bancarias — `Financeiro/ContasBancarias` ja tsx |
| `install/` | 7 | instalador — manter Blade (one-off) |
| `cash_register/` | 7 | caixa POS — substituido por Atendimento/CaixaUnificada (Inertia) |
| `sell/` | 5 | venda CRUD legacy — `Sells/` Inertia substitui |

---

## Estrategia recomendada (3-tier ramped)

### T1 — Esta sprint (prox 2 semanas)

**Alvo: 30 telas Blade prod-criticas** (alta visibilidade biz=1 + biz=4 ROTA LIVRE).

Criterio de selecao:
- Rota ativa em prod (usuario clica >10x/semana)
- Sem Inertia substituto ainda
- Charter design aprovado por Wagner ou criavel rapido
- Estimativa <2h/tela MWART completo (F1-F5)

**Lista T1 sugerida (Wagner aprovar):**

| # | Blade origem | Modulo / dir | Target tsx | Estimativa |
|---|---|---|---|---:|
| 1 | `contact/index.blade.php` | core | `Cliente/Index.tsx` (existe? regularizar) | 1h |
| 2 | `contact/create.blade.php` | core | `Cliente/Create.tsx` | 1.5h |
| 3 | `contact/edit.blade.php` | core | `Cliente/Edit.tsx` | 1.5h |
| 4 | `contact/show.blade.php` | core | `Cliente/Show.tsx` | 1h |
| 5 | `transaction_payment/index.blade.php` | core | `Financeiro/Pagamentos/Index.tsx` | 1.5h |
| 6 | `transaction_payment/show.blade.php` | core | `Financeiro/Pagamentos/Show.tsx` | 1h |
| 7-10 | `product/{index,create,edit,show}.blade.php` | core | `Produto/*` (4 telas, parcial existe) | 5h |
| 11-15 | `Modules/Repair/views/device_models/*` (5 telas) | Repair | `Repair/DeviceModels/{Index,Create,Edit,Show,Print}.tsx` | 6h |
| 16-20 | `Modules/Essentials/views/documents/*` (5 telas) | Essentials | `Essentials/Documents/*.tsx` (parcial existe) | 6h |
| 21-25 | `Modules/Accounting/views/journal_entry/*` (5 telas) | Accounting | `Accounting/Journal/*.tsx` (novo) | 8h |
| 26-30 | `Modules/Ponto/views/{escala_*,relatorio_*}` (5 telas) | Ponto | `Ponto/{Escalas,Relatorios}/*.tsx` (parcial existe) | 6h |

**Total T1:** ~40h trabalho codavel + IA-pair (~$60-150 IA + 1 dev humano 3-5 dias).

### T2 — Proxima sprint

**Alvo: 100 telas Blade modulos vendaveis** (Accounting, Crm, Essentials, Cms).

- Accounting: 91 -> Inertia native (Wagner priority confirmada)
- Crm: 68 -> decidir entre manter React custom ou migrar Inertia
- Essentials: 87-13=74 restantes
- Cms: 45

**Total T2:** ~150h (3-4 cycles paralelizado 4 agents).

### T3 — Backlog (proximos 6+ cycles)

**Alvo: 863 Blade restantes** — migracao background.

Inclui:
- UltimatePOS core (report, install, layouts) — alguns ficam Blade definitivo (print/PDF, instalador)
- Modules legacy pouco usados (Superadmin painel, Officeimpresso painel admin)
- Spreadsheet (export — Blade serve)

**Quanto fica Blade indefinidamente:**
- Relatorios print/PDF (~50 telas — Blade serve melhor que tsx + html2pdf)
- Instalador one-off (`install/*` — 7 telas)
- Emails (`emails/*` — nao conta como UI)
- Layouts base (`layouts/*` — vai sumir gradualmente)

Estimativa final pos T3: **~80-100 Blade definitivos** (de 1.165 hoje, -91%).

---

## Estimativa global

| Recurso | Estimativa |
|---|---|
| Custo IA (Claude Code Opus, 4 agents paralelos) | **$2-5/tela MWART** (mix F1-F5) |
| Custo IA total T1 (30 telas) | **$60-150** |
| Custo IA total T1+T2+T3 (~1.000 telas) | **$2.000-5.000** (spread 6 cycles) |
| Tempo IA-pair por tela | 30min-2h (depende complexidade — form simples vs FSM-integrated) |
| Tempo humano-limite (smoke biz=1, canary, monitor) | igual hoje — relogio do mundo real |
| Cycles previstos para 90% migracao | **6-8** (com 4 agents/sprint MWART) |

---

## Top 5 priorizacoes sugeridas Wagner aprovar

> Ordem de impacto×esforco. Wagner marca quais T1 entram esta sprint.

### 1. `contact/*` (4 telas) — biz=4 ROTA LIVRE alta visibilidade
- **Por que:** Larissa edita clientes diario; Blade legacy nao integra com Atendimento/CaixaUnificada
- **Esforco:** 5h (4 telas simples)
- **Beneficio:** unifica fluxo cliente -> venda -> atendimento

### 2. `transaction_payment/*` (2 telas) — financeiro alta visibilidade
- **Por que:** pagamentos sao tocados N×/dia; integrar com Financeiro/Unificado ja Inertia
- **Esforco:** 2.5h
- **Beneficio:** elimina ultimo gap UX Financeiro

### 3. `Modules/Repair/device_models/*` (5 telas) — completa FSM
- **Por que:** FSM Repair LIVE prod biz=1; DeviceModels e ultimo "buraco" Blade do modulo
- **Esforco:** 6h
- **Beneficio:** Repair 100% Inertia, encerra MWART do modulo

### 4. `Modules/Accounting/*` (91 telas) — Wagner priority Blade migration
- **Por que:** Wagner pediu explicitamente prioridade; modulo 100% Blade hoje
- **Esforco:** 150h+ (spread 2-3 cycles)
- **Beneficio:** desbloqueia oferta "Contabil" como add-on vendavel

### 5. `Modules/Essentials/{documents,knowledge,reminders}/*` (~30 telas) — completar
- **Por que:** parcialmente migrado (13 tsx); finalizar elimina inconsistencia UX
- **Esforco:** 30h
- **Beneficio:** Essentials 100% Inertia, simplifica manutencao

---

## Risco / atencao

- ⛔ **NUNCA migrar tela Blade sem charter aprovado** ([ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md)) — hook `block-mwart-violation.ps1` bloqueia
- ⛔ **Smoke biz=1 obrigatorio** ([ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md)) — nunca smoke biz=4 (cliente)
- ⛔ **F3 estado-da-arte** ([ADR 0107](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — visual comparison gate
- ⛔ **Wagner aprova SCREENSHOT** (nao tabela) na F5
- ⛔ **Modulos com FSM** (Sells, Repair) — passar pelo `ExecuteStageActionService` ([ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))

---

## Proxima acao

Wagner aprova lista T1 (Top 5 priorizadas acima ou seleciona subset) -> `/migracao-blade-react` batch 30 telas (4 agents paralelos, ate $150 IA).

Apos T1 mergeado + canary 7d + smoke real, abrir T2 planning.

---

## Anexo — UI-CATALOG.md cross-projeto

Pareado nesta sessao: **33 UI-CATALOG.md gerados** (1 por modulo Pages). Localizacao: `memory/requisitos/<Modulo>/UI-CATALOG.md`.

Cada catalog mostra:
- Telas tsx + charter + review status
- Blade count do modulo
- Prioridade MWART recomendada

Regenerador: `bash /tmp/generate_catalogs.sh` (em S4+ vira `php artisan ui:catalog-generate`).

---

**Gerado por W31-10 (bulk-screen-review-r1).** Areas isoladas, sem git ops. Parent consolida via PR.
