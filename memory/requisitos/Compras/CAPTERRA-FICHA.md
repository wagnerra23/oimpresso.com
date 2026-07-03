# CAPTERRA-FICHA — Compras (capacidade)

> Ficha canônica de benchmark de **capacidade** do módulo Compras (compra/nota de entrada — `Modules/Compras` wrapper Inertia sobre `transactions` polimórfica).
> **Gerada:** 2026-07-03 · agente `capterra-senior` · Onda 2.1 do programa de ondas.
> **Persona primária:** Larissa @ ROTA LIVRE (`business_id=4`), vestuário Termas do Gravatal/SC, não-técnica, monitor 1280px, internet de loja instável. Entrada de compra por grade tam×cor (50+ modelos/entrega × 4 tam × 3-5 cores = 600-1000 SKUs/lote). 99% do volume do oimpresso novo.
> **Alvo de código:** `Modules/Compras/Http/Controllers/ComprasController.php` (~152 LOC) · `Modules/Compras/Services/ComprasService.php` (~327 LOC, wrapper fino sobre `TransactionUtil::getListPurchases`) · `resources/js/Pages/Compras/Index.tsx` (~849 LOC, cockpit Cowork F1) · `Drawer.tsx` (~608 LOC, 5 tabs + FSM stepper visual). CRUD real vive em `resources/js/Pages/Purchase/Create.tsx` (convergência C1). Import de XML DF-e + manifestação SEFAZ **já existem no `Modules/NfeBrasil`** (`DistribuicaoDfeService`/`ManifestacaoService`, testados); falta só a **ponte DF-e→compra** (US-COM-003 — `nfe_dfe_recebidos.transaction_id` + `ImportarDfeComoCompraService`).
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1) + [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal) + [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0).

> ⚠️ **Complementar, não substituto.** Já existe [`CAPTERRA-DESIGN-FICHA.md`](CAPTERRA-DESIGN-FICHA.md) (nota **67**, foco UX/design do protótipo Cowork F1) e o module-grade **59** (bucket Médio, subiu de 38 após a onda ESTABILIZAR). Esta ficha mede **CAPACIDADE** (features/automação/fiscal/recebimento/resiliência) vs os líderes de compras/procurement — eixo que nem a nota de design nem o module-grade medem. Ver §8 "O que a nota esconde".

---

## 1. Identidade do módulo

- **Nome interno:** `Modules/Compras` (nWidart) — caminho B híbrido: greenfield Controllers/Pages, REUSA `transactions` polimórfica + `TransactionUtil` + Observer Financeiro.
- **Domínio:** Compra / nota de entrada / pedido de compra (`type='purchase'`/`'purchase_order'`/`'purchase_return'` na tabela core UltimatePOS).
- **Função:** cockpit de listagem + 4 KPIs + drawer denso 5 tabs sobre as compras do business. Botão "+ Nova compra" e ações de CRUD **delegam** `/purchases/*` Inertia (convergência C1) — o `/compras` NÃO cria compra própria.
- **Estado lifecycle:** Wave 1-5 landadas (cockpit + drawer + Pest multi-tenant). Wave 4.5 (GradeMatrixInput) vive em `Purchase/Create.tsx`. Wave 6 (bridge XML DF-e) **pendente — maior risco, nunca construída**. **NÃO está em produção nem canary para NENHUM business** — `config/governance/module_clients.yaml` não tem entry `Compras` (D5=0/15; US-COM-010 `todo`).
- **Clientes diretos:** **nenhum em prod.** Larissa @ ROTA LIVRE biz=4 sinalizou dor real (DISCOVERY 2026-05-21: opera compra + entrada por grade) mas **nunca usou `/compras` em produção**. Wagner biz=1 usa `/purchases` legacy.
- **Diferencial-chave (potencial, não realizado):** multi-tenant Tier 0 real ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) + cockpit+drawer Cowork superando UX Bootstrap dos ERPs BR + grade tam×cor vestuário + (futuro) DF-e SEFAZ pull NSU nativo como substituto de OCR/AI capture. **Honestidade:** hoje o diferencial é de UI e de higiene Tier 0, não de capacidade de compras — o motor fiscal/recebimento que define o domínio ainda não existe.

## 2. Concorrentes-alvo

Pricing qualitativo (Tier 0: não commitar valores BRL — [proibicoes](../../proibicoes.md)). Global em US$ (referência pública).

| # | Concorrente | Tipo | Faixa | Lacuna que o oimpresso pode preencher | Fonte |
|---|---|---|---|---|---|
| 1 | **Bling** | ERP PME BR + notas de entrada | entrada baixa → sério | UI Bootstrap legado sem drawer/cockpit denso; **mas import XML NF-e + manifestação + vínculo-a-PO é canônico e completo** (a lacuna é NOSSA) | ajuda.bling.com.br |
| 2 | **Tiny (Olist)** | ERP PME BR | entrada → médio | Sem drawer/FSM visual; **matching automático XML→PO via campo `xPed` + por (fornecedor+descrição)** já resolvido | ajuda.olist.com |
| 3 | **Omie** | ERP BR + NF-e Agent | médio | UI mais pesada; **NF-e Agent polling SEFAZ 24h + AP automático atrelado ao recebimento** — referência-topo BR de automação de compra | ajuda.omie.com.br |
| 4 | **Conta Azul Pro** | ERP/financeiro PME BR | entrada → médio | Sem FSM visual nem grade tam×cor; recebimento NF-e + lançamento contábil em modal único "Larissa-grade" | contaazul.com |
| 5 | **Hiper** | ERP pequeno-varejo BR | entrada | **Fiscal amplo (import XML + manifestação) + integração estoque** direta; sem drawer denso | hiper.com.br |
| 6 | **Nex (Nextar)** | PDV/estoque pequeno-varejo BR | free → entrada | Entrada de nota simples; sem grade tam×cor, sem cockpit KPI | nextar.com.br |
| 7 | **Zoho Inventory** | Inventory PME global | US$ tiers | **PO workflow linear** (Draft→Open→Partially Received→Received→Billed→Closed) + purchase-receives partial; sem fiscal BR | zoho.com/inventory |
| 8 | **Cin7 Core (ex-DEAR)** | Mid-market inventory | US$ mid | **6 stages drag** (Draft→Ordered→Receiving→Received→Costed→Invoiced) + audit por campo; sem fiscal BR | cin7.com |
| 9 | **Lightspeed Retail (X/R)** | POS varejo global | US$ + por-caixa | **Partial receive canon** (Received Total vs Ordered Total, delivery linkada expansível, autosave check-in); sem fiscal BR | lightspeedhq.com |
| 10 | **Shopify (PO nativo, ex-Stocky)** | E-commerce + POS | US$ | Receive-partial "received vs not received" linha-a-linha + bulk; **sem fiscal BR (NF-e)** — desqualificado p/ loja BR | help.shopify.com |
| 11 | **Precoro** | P2P mid-market | US$499+/mês | **3-way match + AI OCR + e-invoicing + AP** (teto de capacidade); over-engineering pra PME loja | procuredesk.com |
| 12 | **Procurify** | P2P mid-market | quote | Budget control + PO + real-time spend visibility (teto); complexo demais pra Larissa | procurify.com |
| 13 | **Coupa / SAP Ariba** | Source-to-pay enterprise | 6 dígitos/ano | 3-way match + strategic sourcing + compliance global (**teto absoluto**); **desqualificado por over-engineering PME** — só referência de ceiling | spendflo.com |

## 3. Capacidades em produção (validadas)

> ⚠️ "Em produção" aqui = **existe no código e roda em dev/CI**, NÃO "em uso por cliente real" (D5=0). Rigor: separo o que REALMENTE funciona do que é protótipo/const/pendente.

```yaml
capacidades_reais_no_codigo:
  - us: US-COM-001
    nome: "Cockpit /compras: lista paginada + 4 KPIs (aberto/transito/mes/fornec) + filtros query-string"
    score: P0
    onde: "ComprasController::index + ComprasService::listarCompras/calcularKpis (Inertia::defer nas 4 props)"
    evidencia: "Index.tsx 849 LOC; verificado@fd96258"
    em_uso_prod: NAO   # não está em module_clients.yaml

  - us: US-COM-001
    nome: "Drawer detalhe 5 tabs (Resumo/Itens/Documentos/Pagamentos/Histórico) + timeline activitylog"
    score: P1
    onde: "Drawer.tsx 608 LOC + ComprasService::buscarDetalhe (eager-load contact/lines/payments)"
    em_uso_prod: NAO

  - us: US-COM-006
    nome: "Pest cross-tenant biz=1 vs biz=99 REAL (4 cenários: list, show-404, KPIs scope, filtro ?q= JOIN contacts)"
    score: P0
    onde: "Modules/Compras/Tests/Feature/MultiTenantTest.php (HTTP real, cria Business+Transaction+hits /compras)"
    evidencia: "verificado@176f9bc — teste NÃO é source-grep; skip-graceful se schema ausente"

  - us: US-COM-007
    nome: "business_id do auth() (não session) + abort_if + cross-check drift session≠auth (defense-in-depth)"
    score: P0
    onde: "ComprasController::index/show"

  - us: US-COM-009
    nome: "Hotfix R1: scope contacts.business_id no leftJoin de getListPurchases + guard SQL toSql()"
    score: P0
    onde: "app/Utils/TransactionUtil.php:~4916 + MultiTenantSqlGuardTest.php (->toSql() DB-agnostic, roda em sqlite)"

  - us: US-COM-008
    nome: "Throttle 60,1 no route group /compras + FormRequest ListarComprasRequest (whitelist sort/stage/per_page anti-SQLi/DOS)"
    score: P2
    onde: "Routes/web.php + Http/Requests/ListarComprasRequest.php"
    evidencia: "PARCIAL — falta Pest comportamental do 429 (só source-grep em GapsHardeningTest)"

  - us: US-COM-011
    nome: "OTel spans custom (compras.listarCompras/calcularKpis/buscarDetalhe) via OtelHelper::spanBiz"
    score: P3
    onde: "ComprasService (zero-cost path quando OTel off)"

  # ─── O QUE NÃO É CAPACIDADE REAL (protótipo/const/pendente) ───
  - us: US-COM-FSM
    nome: "FSM 6 estágios (rascunho→pedido→transito→recebido→conferido→pago)"
    score: P0
    onde: "APENAS const STAGES no Drawer.tsx:12 — UI-only, NÃO persistida"
    em_uso_prod: NAO
    alerta: "não é máquina de estado; status real = string mistura UltimatePOS legacy (received/ordered/pending)"

  - us: US-NFE-DFE
    nome: "Pull DF-e SEFAZ NSU + manifestação destinatário (substrato do import de compra)"
    score: P0
    onde: "Modules/NfeBrasil: DistribuicaoDfeService + BuscarDfesRecebidosJob + cron PuxarDfesRecebidos + ManifestacaoService/Controller + tabelas nfe_dfe_recebidos/itens/eventos/nsu_state"
    evidencia: "DistribuicaoDfeServiceTest + ManifestacaoServiceTest + ManifestacaoControllerTest — REAL, testado"
    alerta: "existe e roda — MAS vive no NfeBrasil, não no Compras; é o substrato, não a compra"

  - us: US-COM-003
    nome: "Bridge DF-e recebida → Compra (NfeDfeRecebido → Transaction type=purchase) — a última milha"
    score: P0
    onde: "NÃO EXISTE — nfe_dfe_recebidos sem transaction_id; nenhum ImportarDfeComoCompraService; comentários em Compras (ServiceProvider:13/InstallController:16/Routes:13) remetem a Wave 6"
    alerta: "o import fiscal está pronto (acima); falta converter em compra + matching de produto"

  - us: US-COM-005
    nome: "GradeMatrixInput tam×cor vestuário (unlock Larissa)"
    score: P1
    onde: "resources/js/Pages/Purchase/Create.tsx (NÃO /compras — convergência C1)"
    em_uso_prod: NAO   # aguarda smoke/canary
```

## 4. Dimensões de capacidade P0-P3 — comparativa

Legenda: ✅ pareia/supera líder · 🟡 parcial · ❌ ausente. Nota /10 por **mecanismo concreto** (não por nome do concorrente).

| ID | Capacidade | Peso | Líder do eixo (mecanismo SOTA) | oimpresso Compras hoje | Nota /10 |
|---|---|:-:|---|---|:-:|
| **C01 (P0)** | **Importar XML NF-e como compra + manifestação destinatário SEFAZ** | 4 | Bling/Omie/Tiny (import XML → grava estoque+conta; manifestação "confirmo operação"; polling SEFAZ por CNPJ) | 🟡 **pull + manifestação PRONTOS no `Modules/NfeBrasil`** (`DistribuicaoDfeService` puxa via SEFAZ NSU + `BuscarDfesRecebidosJob`/cron + `ManifestacaoService` testado + `nfe_dfe_recebidos`/`itens`/`eventos`); **falta só a última milha — a ponte→compra** (`nfe_dfe_recebidos` sem `transaction_id`, nenhum `ImportarDfeComoCompraService`; comentários no Compras remetem a "Wave 6") | **5** |
| **C02 (P0)** | **Matching automático XML→PO (fornecedor + produto)** | 4 | Tiny (campo `xPed` auto-vincula + por fornecedor/descrição→EAN); Bling (vincula item↔PO no import) | ❌ **ausente** — sem matching por CNPJ nem por EAN/xProd | **0** |
| **C03 (P0)** | **Recebimento parcial (qty recebida ≠ pedida)** | 4 | Lightspeed (Received vs Ordered Total, delivery linkada expansível, autosave check-in); Shopify (received vs not received linha-a-linha) | ❌ **ausente** — drawer só mostra estado inteiro; sem qty-recebida por linha | **1** |
| **C04 (P0)** | **Cálculo custo/total da compra correto — comprovado por teste** | 4 | ninguém *anuncia*; dever Tier 0 (regra-mestre valor/estoque) | 🟡 `buscarDetalhe` calcula `line_total = qty × price_inc_tax` no PHP; **ZERO teste que a compra persiste total/custo/estoque certo** — hardening tests são source-grep (§8) | **3** |
| **C05 (P0)** | **3-way match (PO ↔ Recebimento ↔ NF-e)** | 4 | Precoro/Coupa/Ariba (auto-match + tolerância + exceções); "essential 2026" P2P | ❌ **ausente** — nem PO-vs-receipt, nem receipt-vs-NFe | **0** |
| **C06 (P0)** | Isolamento multi-tenant (Tier 0) | 4 | — (concorrentes multi-empresa, sem Tier 0 rígido) | ✅ `business_id` do auth + abort_if + cross-check + Pest cross-tenant REAL biz=1/99 + guard SQL `toSql()` | **9** |
| **C07 (P1)** | Cálculo de estoque na entrada (baixa/movimentação) | 2 | Bling/Omie ("gravar estoque" no import); Zoho purchase-receives | 🟡 **preservado do Blade + endurecido** — `Purchase/Create.tsx` POSTa no mesmo `PurchaseController::store` → `ProductUtil::createOrUpdatePurchaseLines`+`updateProductQuantity` grava `variation_location_details.qty_available` por variação/local; guard Tier 0 `assertPurchaseVariationsOwnership` valida ownership ANTES de escrever (o Blade não tinha). Gap: **zero teste de invariante de estoque** + só entrada manual (o import DF-e ainda não alimenta — G-01) | **5** |
| **C08 (P1)** | Contas a pagar automático (Observer Financeiro) | 2 | Omie (AP atrelado ao recebimento); Precoro (AP pós-match) | 🟡 `TransactionObserver` Financeiro cria `fin_titulos` type=pagar quando `/purchases/store` roda — herdado, não do `/compras`; funciona mas não é capacidade própria | **6** |
| **C09 (P1)** | FSM de estágios persistida + auditável | 2 | Cin7 (6 stages drag + lock + audit por campo); Zoho (workflow linear com status) | ❌ **UI-only** — const `STAGES` no Drawer, mapeada sobre `transactions.status`; sem state machine, sem `sale_stage_history`, sem transição gateada | **2** |
| **C10 (P1)** | Grade tam×cor (entrada matricial vestuário) | 2 | Cin7/Lightspeed (matrix por atributo) | 🟡 **construído e ligado ponta-a-ponta** — rota `GET /purchases/grade-matrix` → `PurchaseController::gradeMatrix` monta layout 2D; `GradeMatrixInput`+`GradeProductCombobox` (US-COM-005) em `Purchase/Create.tsx` expandem cada célula em `variation_id`+qty+custo → mesmo POST `/purchases` → purchase_line + estoque por variação. Upgrade sobre o Blade (linha-a-linha → matricial). Gap: **fora do `/compras`** + zero teste + canary Larissa pendente | **7** |
| **C11 (P1)** | Supplier scorecard (OTIF / lead-time / defect / fill-rate) | 2 | LeanLinking/EvaluationsHub (OTIF≥95%, PPM, rolling 13-sem) | ❌ **ausente** — nenhuma métrica de fornecedor | **0** |
| **C12 (P1)** | Aprovação / workflow multi-nível de compra | 2 | Procurify/Precoro (budget control + aprovação por alçada) | ❌ **ausente** — sem alçada, sem approval chain | **1** |
| **C13 (P2)** | KPIs cockpit (a pagar / trânsito / mês / fornecedores) | 1 | HubSpot/Shopify Insights (highlights) | ✅ 4 KPIs agregados server-side + `Inertia::defer` + cores semânticas | **8** |
| **C14 (P2)** | Drawer detalhe denso (list-detail sem sair da lista) | 1 | Linear/Attio/Shopify (drawer canon 2026) | ✅ drawer 480px, 5 tabs, timeline activitylog, breakdown financeiro | **8** |
| **C15 (P2)** | Anti-N+1 / perf (defer + eager-load) | 1 | — (higiene) | ✅ `Inertia::defer` nas 4 props + eager-load em `buscarDetalhe`; **"N+1 nas rows" VERIFICADO falso-positivo (2026-07-03)** — `listarCompras` → `getListPurchases` traz `supplier_business_name`+`location_name` via JOIN (colunas flat), sem lazy-load por linha; `->with()` seria incorreto (SELECT sem FKs + `groupBy`). Travado por `ComprasListagemNPlusUmTest` (contagem de queries CONSTANTE, MySQL CT 100) | **8** |
| **C16 (P2)** | Autosave rascunho de compra (Larissa atende telefone) | 1 | Lightspeed (modal check-in autosave) | ❌ **ausente** — sem draft persistido; forms não modelados no `/compras` | **2** |
| **C17 (P2)** | LGPD / PII fornecedor redigida | 1 | — (dever regulatório BR 2026) | ❌ Drawer renderiza `tax_number`(CNPJ/CPF) + `mobile` + `email` **raw, sem PiiRedactor** (Drawer.tsx:266/275/281) | **2** |
| **C18 (P3)** | A11y (WCAG 2.1 AA) | 0.5 | Shopify Polaris | 🟡 cores contrastam; drawer sem `role=dialog`/focus-trap/`aria-label` no botão fechar (herdado do protótipo F1) | **5** |
| **C19 (P3)** | Atalhos teclado (`/` `N` `I` `↑↓` `Esc`) | 0.5 | Linear (Cmd+K + Esc + `/`) | 🟡 **declarados no footer mas sem handlers** — risco de expectativa frustrada | **4** |

## 5. Cálculo da nota ponderada

Pesos canônicos: **P0=4 · P1=2 · P2=1 · P3=0.5**.

```
P0 (peso 4): (C01 5 + C02 0 + C03 1 + C04 3 + C05 0 + C06 9) = 18 × 4 = 72
P1 (peso 2): (C07 5 + C08 6 + C09 2 + C10 7 + C11 0 + C12 1) = 21 × 2 = 42
P2 (peso 1): (C13 8 + C14 8 + C15 8 + C16 2 + C17 2)         = 28 × 1 = 28
P3 (peso 0.5):(C18 5 + C19 4)                                =  9 × 0.5=  4.5

Σ ponderado = 72 + 42 + 28 + 4.5 = 146.5

Máximo possível:
  P0: 6×10×4 = 240 · P1: 6×10×2 = 120 · P2: 5×10×1 = 50 · P3: 2×10×0.5 = 10  → 420

nota_capacidade = 146.5 / 420 × 100 = 34.9 → 34/100
```

```
NOTA CAPACIDADE oimpresso Compras: 34/100
Referência-topo BR (Omie/Hiper):        ~72/100  — import XML + manifestação + NF-e Agent + AP automático + estoque
Referência BR direta (Bling/Tiny):      ~68/100  — import XML + matching XML→PO (xPed) + vínculo-a-PO + AP
Teto mid-market inventory (Cin7/Zoho):  ~66/100  — PO workflow 6-stages + partial receive + costed (sem fiscal BR)
Teto procurement (Coupa/Ariba/Precoro): ~85/100  — 3-way match + AI OCR + e-invoicing — DESQUALIFICADO por over-engineering PME loja

Gap pro topo BR (Omie): -38 pts. Causa: dos 4 P0 que DEFINEM compra BR, três continuam ~0 — matching XML→PO (C02), recebimento parcial (C03), 3-way match (C05); e o import XML DF-e (C01) tem o pull+manifestação prontos no NfeBrasil mas NÃO fecha a compra (falta a ponte).
Onde Compras já ganha: multi-tenant Tier 0 real (C06=9), cockpit+drawer denso (C13/C14=8) — eixos de UI/higiene. Preserva do Blade a movimentação de estoque na entrada (C07=5, com guard Tier 0 novo) e faz a grade tam×cor matricial (C10=7, upgrade sobre o Blade linha-a-linha). O substrato fiscal (DF-e pull+manifestação) já existe no NfeBrasil — vantagem real, mas ainda não convertida em capacidade de compra.
```

**Leitura honesta:** a capacidade (34) fica **abaixo** do module-grade (59) e do design (67) — e isso é o ponto da onda. O module-grade mede governança/higiene (Tier 0, Pest, doc, sec); o design mede UX do protótipo; **nenhum dos dois mede se o módulo entrega valor de compras**. Quando você pergunta "o oimpresso importa uma NF-e de fornecedor, casa com o pedido, recebe parcial e concilia?", a resposta é "importa e manifesta (via NfeBrasil), mas não vira compra, não casa PO, não recebe parcial, não concilia". O substrato fiscal BR — a parte cara — **já está construída**; o que falta é a ponte + a mecânica de recebimento/conciliação. Por isso a capacidade sobe pouco (o import existe) mas continua baixa (o import não fecha o ciclo de compra).

## 6. Top gaps P0/P1 (pra subir a nota)

| # | Gap | Cap | Esforço | ROI (persona Larissa) | Sinal ADR 0105 | Concorrente que tem |
|---|---|---|---|---|---|---|
| **G-01** | **Ponte DF-e recebida → Compra** (US-COM-003): **reusa o que já existe no `Modules/NfeBrasil`** (`DistribuicaoDfeService` pull NSU + `nfe_dfe_recebidos` + `ManifestacaoService`) — só falta migration `nfe_dfe_recebidos.transaction_id` + `ImportarDfeComoCompraService` (DFe→Transaction type=purchase) + modal "Importar XML" listando DF-e pendentes | C01 | **M (~5-7h — não L; o import fiscal já está pronto)** | **P0** — elimina digitar 600-1000 SKUs; a parte cara já existe | ✅ execute (dor real DISCOVERY) | Bling, Tiny, Omie, Hiper |
| **G-02** | **Matching automático XML→produto** (por EAN + xProd; fallback manual) — o que faz o import valer a pena | C02 | M (~6h, depende G-01) | **P0** — sem isso o import ainda exige mapear item a item | ✅ execute | Tiny (`xPed`+descrição), Bling |
| **G-03** | **Teste E2E de cálculo custo/total/estoque da compra** — cria compra (grade + frete + desconto + imposto) → assert `final_total`/`purchase_line`/estoque persistidos. Fecha C04 e blinda Tier 0 valor/estoque | C04 | M (~6h) | **P0 crítico** — 1 célula de grade = 1 SKU × custo × qty MEXE EM ESTOQUE; sem teste = repete o incidente do Sells (§8) | ✅ execute (dever Tier 0) | ninguém *anuncia*, é dever |
| **G-04** | **Recebimento parcial** (qty recebida por linha ≠ pedida + trânsito residual + autosave check-in) | C03 | M-L (~1h frontend + backend) | **P1** — vestuário recebe parcial real (lote incompleto) | 🟡 sinal médio (uso PME varejo) | Lightspeed, Shopify, Zoho |
| **G-05** | **FSM de estágios PERSISTIDA** (`spatie/laravel-model-states` ou coluna `stage`) — parar de mentir "Recebido" na tela quando o banco diz `pending` | C09 | L (~8-12h, ADR 0143) | médio — remove drift UI↔banco; base pra 3-way match | 🟡 medir antes | Cin7, Zoho |
| **G-06** | **PiiRedactor no Drawer** (CNPJ/CPF + mobile + email fornecedor) + `module_clients.yaml` entry — LGPD 2026 + destrava D5 | C17 | S (~2.5h) | dever LGPD + sai do feature-theater | ✅ execute | dever regulatório |

## 7. Diferenciais oimpresso vs concorrentes

1. **Multi-tenant Tier 0 real** (`business_id` do auth + abort_if + cross-check drift + Pest cross-tenant REAL biz=1/99 + guard SQL `toSql()`) — concorrentes são multi-empresa mas sem isolamento auditável desse nível. **É o único eixo onde Compras já supera o mercado.**
2. **Cockpit + drawer denso Cowork** (4 KPIs defer + drawer 480px 5 tabs + timeline) — supera a UI Bootstrap legado de Bling/Omie/Tiny em list-detail e densidade (herança do design F1 nota 67).
3. **DF-e SEFAZ pull NSU como substituto nativo de OCR/AI capture — já construído.** O mundo anglo gasta US$2.36-3.00/invoice em AI-OCR pra extrair dados de PDF (95-99% accuracy); o Brasil tem o XML estruturado da NF-e, e o oimpresso **já o puxa e manifesta** via `Modules/NfeBrasil` (`DistribuicaoDfeService` + `ManifestacaoService`, testados — 100% accuracy, zero inferência). **Vantagem ESTRUTURAL e real** — mas ainda não converte em compra: vira diferencial de verdade quando a ponte G-01 (curta) plugar esse dado no `type=purchase`.
4. **Grade tam×cor vestuário** (`GradeMatrixInput` auto-detect 2D) — nicho que ERP BR horizontal não cobre; mas vive em `/purchases`, não `/compras`, e não foi validado com Larissa.
5. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 — vs Bootstrap/jQuery legado dos ERPs BR.

## 8. O que a nota esconde (leitura adversarial)

Como Compras é o módulo mais fraco do projeto, o ângulo desta onda é **o que é PIOR do que a nota 59 sugere, e o que é TEATRO**. Seis achados, todos com evidência:

1. **O module-grade 59 mede higiene, não valor de compras.** A nota 59 (subiu de 38 na onda ESTABILIZAR) vem das 9 dimensões `module-grade-v3`: multi-tenant, Pest, doc, arquitetura, cliente, perf, LGPD, sec, obs. **Nenhuma pergunta "o módulo importa NF-e? casa com PO? recebe parcial? concilia?"** — que é o que compra É. A capacidade real (esta ficha, **30**) é ~metade da nota de governança. A onda ESTABILIZAR fez o módulo *seguro e testado*, não *capaz*.

2. **A FSM é teatro puro — a tela mente.** Os "6 estágios" (`rascunho→pedido→transito→recebido→conferido→pago`) existem **só como `const STAGES` no `Drawer.tsx:12`**, renderizados sobre `transactions.status`. Não há state machine, não há transição gateada, não há histórico. O `status` real é a string legacy UltimatePOS (`received`/`ordered`/`pending`) — **o drawer pode mostrar "Recebido" com o banco em `pending`**. É um stepper visual, não uma máquina de estado. (Contraste: o Sells tem FSM canônico REAL, `sale_stage_history` append-only, ADR 0143 — Compras não.)

3. **O diferencial nº1 do domínio BR está 80% construído — e ocioso.** Correção honesta (Wagner apontou 2026-07-03): o import de XML DF-e **existe e é testado**, só que mora no `Modules/NfeBrasil`, não no Compras — `DistribuicaoDfeService` puxa via SEFAZ NSU, `BuscarDfesRecebidosJob`/cron popula `nfe_dfe_recebidos`, e `ManifestacaoService` faz a manifestação destinatário (prazo 180d NT 2014.002), tudo com Pest verde. **O que falta é a última milha:** `nfe_dfe_recebidos` **não tem `transaction_id`**, não há `ImportarDfeComoCompraService`, e todo comentário no Compras (`ServiceProvider:13`/`InstallController:16`/`Routes:13`) remete a "Wave 6". Ou seja: a parte cara (falar com a SEFAZ, guardar o XML, manifestar) está pronta; a compra nunca recebe esse dado. É pior teatro que "não existe" — é infra pronta que **não está plugada**. G-01 deixa de ser "construir o import" e vira "construir a ponte de um import que já funciona" (esforço bem menor). A ficha de design conta 32 capacidades e dá 67, mas trata a automação fiscal como "gap de integração, fora do escopo dela" — esta ficha é justamente esse escopo, e o gap ali é **de ligação, não de construção**.

4. **Módulo não está em prod nem canary pra ninguém — toda nota é teórica (feature theater).** `config/governance/module_clients.yaml` não tem `Compras` (D5=0/15; US-COM-010 ainda `todo`). Larissa *sinalizou* dor (DISCOVERY) mas **nunca abriu `/compras` em produção**. Wagner usa `/purchases` legacy. O risco R10 do audit ("toda nota fina é teórica") continua ALTO e não-mitigado. Um cockpit lindo que ninguém usa não é capacidade — é demo.

5. **Não há prova de que o cálculo de custo/total/estoque persiste certo — e os "testes de hardening" são tautológicos.** `buscarDetalhe` calcula `line_total = qty × purchase_price_inc_tax` em PHP (ComprasService.php:251), mas **nenhum teste submete uma compra e verifica que `final_total`/`purchase_lines`/estoque foram gravados corretos**. `GapsHardeningTest.php` e `GapsP1HardeningTest.php` são `file_get_contents` + `str_contains` no *source* — passam mesmo se a conta estiver errada (o **mesmo anti-padrão catalogado** em [proibicoes §5, 2026-06-05](../../proibicoes.md) que mordeu o Sells). Isso é Tier 0: **1 célula de grade tam×cor = 1 SKU × custo × qty, e entrada de compra MEXE EM ESTOQUE**. A regra-mestre valor/estoque exige dupla confirmação por teste — que aqui não existe. Nota: `MultiTenantTest` e `MultiTenantSqlGuardTest` **são reais** (bom) — o teatro é específico dos hardening/gaps tests.

6. **A razão de existir do módulo pra Larissa (GradeMatrixInput) nem vive no `/compras`.** Por convergência C1, o `GradeMatrixInput` foi pra `resources/js/Pages/Purchase/Create.tsx`. O DISCOVERY inteiro justificou o módulo pela dor da grade tam×cor da Larissa — e essa peça mora em outro lugar, aguardando smoke/canary. O `/compras` que leva a nota é o cockpit de leitura; o unlock real da persona está fora dele e não-validado.

**Síntese adversarial:** o module-grade 59 diz "seguro e documentado"; a capacidade diz "importa e manifesta a NF-e (via NfeBrasil) mas não a converte em compra, não casa PO, não recebe parcial, não concilia, mente o estágio na tela, e ninguém usa". A onda ESTABILIZAR resolveu o Tier 0 (necessário e bem-feito) e o substrato fiscal já estava construído — mas o módulo ainda **não faz compra**: faz uma tela sobre compras alheias, com o import da NF-e pronto ao lado e não-plugado. O caminho pra virar produto real passa por G-01→G-02→G-03 (ponte DF-e→compra + matching + teste de cálculo), não por polir o cockpit.

## 9. Anti-padrões / pegadinhas Tier 0 (Compras)

- ⛔ **Mexer em custo/total/estoque da compra** (`purchase_price`, `final_total`, `qty` da grade, movimentação de estoque na entrada) sem **dupla confirmação** (2 caminhos com números) + **tabela antes→depois** + aprovação humana — regra-mestre Tier 0 valor/estoque ([proibicoes](../../proibicoes.md)). Entrada de compra é write de estoque.
- ⛔ **Teste source-grep** (`file_get_contents`+`str_contains` no source, como os GapsHardening tests) declarado como cobertura de comportamento — tautológico, trava o desvio ([proibicoes §5](../../proibicoes.md)). Teste ancora em contrato (SPEC/ADR/caso), roda o código.
- ⛔ **Smoke em `business_id=4`** (ROTA LIVRE) — usar biz=1 ou biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)). Toda test data usa biz=1/99, nunca a Larissa real.
- ⛔ **Alterar `format_date`** de biz=4 — shift +3h preservado intencionalmente ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)); datas do drawer ("Mercadoria recebida · 08/05 11:42") respeitam.
- ⛔ **`Inertia::render` com prop cara sem `defer`** — já aplicado nas 4 props; manter (skill `inertia-defer-default`). Adicionar `->with(['contact','location'])` no `listarCompras().paginate()` (N+1).
- ⛔ **PII fornecedor raw** (CNPJ/CPF/mobile/email no `Drawer.tsx`) sem `PiiRedactor` — gap LGPD ATIVO (C17); mascarar por role antes de qualquer canary.
- ⛔ **Criar `Pages/Compras/Create.tsx` duplicado** — convergência C1: "+ Nova compra" delega `/purchases/create` via `router.visit` (não `<a href>`, não `window.location`). Só nasce Create próprio se Larissa reportar dor vertical que `Purchase/Create.tsx` não atende (review trigger US-COM-002).
- ⛔ **Job async sem `$businessId` no constructor** — o futuro `ImportarDfeComoCompraJob` (G-01) recebe business_id explícito; `session()` não vive na fila ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

## 10. Decisão / Nota / Recomendação

### Nota de capacidade
**34/100** — bem abaixo do topo BR (Omie/Hiper ~72, Bling/Tiny ~68) e do teto mid-market (Cin7/Zoho ~66). Honesto: Compras é **melhor que o mercado em isolamento Tier 0 e UI de cockpit** (C06=9, C13/C14=8), **preserva do Blade** a movimentação de estoque na entrada (C07=5) + grade tam×cor matricial (C10=7, upgrade), tem o **substrato fiscal pronto no NfeBrasil** (import DF-e + manifestação testados → C01=5), e é **vazio no resto do que É compra** — matching XML→PO (C02=0), recebimento parcial (C03=1), 3-way match (C05=0). O module-grade 59 e o design 67 escondem que o motor do domínio não fecha o ciclo.

### Causa principal do gap (1 frase)
**A onda ESTABILIZAR deixou o módulo seguro, testado e bonito, e o import fiscal (DF-e pull + manifestação) já existe no NfeBrasil — mas nada converte a NF-e recebida em compra, o pedido não é casado, o recebimento parcial não existe, e a FSM que a tela exibe é uma const visual, não uma máquina de estado.**

### Top 3 P0 pra fechar (executável)
1. **G-01 — Ponte DF-e recebida → Compra** (US-COM-003): **não é construir o import** (o `Modules/NfeBrasil` já puxa e manifesta) — é a última milha: `nfe_dfe_recebidos.transaction_id` + `ImportarDfeComoCompraService` + modal "Importar XML". Diferencial nº1 do domínio + unlock da Larissa (elimina digitar 600-1000 SKUs). Esforço **M** (o caro já está feito). **Comece por aqui.**
2. **G-03 — Teste E2E de cálculo custo/total/estoque**: rede de segurança Tier 0 antes de qualquer entrada de compra tocar estoque real — o incidente do Sells (R$ inflado ×100k) mostra o custo de não ter. Esforço M.
3. **G-02 — Matching automático XML→produto** (por EAN+xProd): o que faz o import (G-01) valer a pena em vez de exigir mapeamento manual item a item. Esforço M, depende de G-01.

### Referências
- [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md) (UX, nota 67) · [BRIEFING.md](BRIEFING.md) · [SPEC.md](SPEC.md) (US-COM-001..011) · [RUNBOOK-compras-index.md](RUNBOOK-compras-index.md)
- [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) (inventário + 15 gaps + roadmap 3 ondas) · [DISCOVERY-LARISSA-COMPRAS.md](DISCOVERY-LARISSA-COMPRAS.md)
- Session log: [2026-07-03-capterra-compras.md](../../sessions/2026-07-03-capterra-compras.md)
- Fontes externas 2026: Bling ([manifestar+importar](https://ajuda.bling.com.br/hc/pt-br/articles/360057118174), [vincular item↔PO](https://ajuda.bling.com.br/hc/pt-br/articles/21830391097367)) · Omie ([NF-e Agent auto](https://ajuda.omie.com.br/pt-BR/articles/1350609), [recebimento](https://ajuda.omie.com.br/pt-BR/articles/1419039)) · Tiny ([importar XML `xPed`](https://ajuda.olist.com/duvidas/como-importar-xml-manualmente)) · Lightspeed ([partial receive](https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534168876187)) · [3-way match P2P 2026](https://www.procuredesk.com/best-procurement-software-3-way-match/) · [AI AP automation 2026](https://beancount.io/blog/2026/05/11/accounts-payable-automation-2026-ai-invoice-capture-three-way-match-touchless-approvals-cut-costs-eliminate-duplicate-payments-guide) · [supplier scorecard KPIs](https://evaluationshub.com/supplier-scorecard-best-practices-kpis-weighting-cadence/) · [Reforma Tributária/NFS-e 2026](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-consumo/orientacoes-2026)

---

**Próxima revisão:** 2026-10-03 (trimestre) ou quando G-01 (bridge XML DF-e) fechar.
**Onda:** 2.1 (adversário concorrente Compras — programa de ondas).
