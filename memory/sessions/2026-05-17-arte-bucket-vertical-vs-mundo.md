# Estado-da-arte: bucket vertical_client_facing oimpresso vs Top 5 mundo 2026

> **Sessão**: 2026-05-17 — agent `estado-da-arte`
> **Escopo**: 5 módulos bucket vertical_client_facing (Vestuario 80, ComVis 85, OficinaAuto 77, Officeimpresso 80, Repair 81) vs 5 ERPs verticais de referência mundo 2026
> **Mandato Wagner W27**: features REAIS, não dimensões abstratas. Foco em grade tam×cor, fitting room, NFe vertical, importer Firebird, shop floor.
>
> ⚠️ **Notas baseline (80/85/77/80/81)** vêm da rubrica scoped interna `vertical_client_facing.yaml` (ADR 0160) — saturação Wave 25 contra critérios oimpresso, **NÃO** Capterra real do mercado. Capterra real do oimpresso = N/A (sem usuários públicos).

---

## Fase 1 — PESQUISA: os 5 melhores de ERP vertical 2026

| Player | Categoria | Mecanismo concreto | Por que é referência |
|---|---|---|---|
| **Shopify Plus + Fulfil** (ecossistema) | Apparel/DTC global | Multi-store/multi-market nativo; checkout extensibility + Shopify Functions; Fulfil é único ERP **SOC 2 Type II compliant** purpose-built pra Shopify Plus DTC com data encryption at rest + multi-DC US (GCP). 6+ storefronts internacionais com "Global Merchandising": lança nos EUA, testa, rola pra EU/APAC ajustando pricing local. App Store com **virtual fitting room nativo** (mercado $8.27B→$30.41B até 2034, CAGR 17.7%) — Fitnonce extrai 20 medições do corpo via foto, AI sugere tamanho/estilo, reduz return rate ~25%. | Plataforma com maior maturidade global em apparel DTC; certificação SOC 2 Type II é o teto de confiabilidade enterprise pra retail. |
| **Bling ERP** (Locaweb/LWSA) | Multi-vertical BR PME | 300.000+ usuários ativos (B3 listed). Tier "Titânio" permite multi-CNPJ (mesma raiz CNPJ — matriz+filiais) com gestão de estoque integrada. Verticais cobertas: vendas online (multi-channel + automação NF-e/NFCe), loja física (PDV+estoque), serviços (NFSe), pequena indústria (ordem produção). Em abril/2026 começou a contar importação de notas via API nos planos. Integração marketplace dominante. | Maior base instalada BR PME — define o "esperado mínimo" do mercado horizontal raso. Lock-in de marketplace + financeiro nativo. |
| **Linx Microvix Moda** | Retail moda BR (acessórios, ótica, cosmético, franquias) | 100% cloud. **Grade nativa** tam×cor×modelo×coleção como cidadão de primeira classe — cadastro base se replica em N SKUs; pesquisa por grade na UI; reposição inteligente por grade. PDV front-of-store + estoque + fiscal + e-commerce integrados. Vertical específica: gestão coleção, replenishment automatizado, comissão escalonada vendedor, troca/devolução CDC integrada. | Referência BR vertical moda há 15+ anos — o que Linx Microvix tem como básico é o piso pra qualquer ERP vestuário levado a sério no Brasil. |
| **printIQ** | Print MIS global (offset, digital, wide format, label) | Cloud MIS modular com **Gang Module** (otimização wide-format — agrupa N jobs no mesmo substrato pra maximizar uso material); **IQconnect-PrePress** automatiza handoff pro prepress; integração Impostrip pra imposição; **IQconnect-Automate** automatiza quoting→submission→payment→prepress→proofing→production→finishing→delivery. Capacity planning + shop floor data capture nativos. 12 Connect Modules em 4 categorias (API, Automate, Pre-Press, VDP). | Referência global em print MIS — features `gang run optimization` e `shop floor data capture real-time` são padrão-ouro vertical impressão. |
| **Tekmetric + Mitchell 1 + ARI** (ecossistema auto repair) | Auto repair USA | VIN decoder nativo com dados completos (make/model/year/trim/engine); OBD scanner integration; checklist inspection com fotos/AI/anotações; histórico veicular por placa (upsell predictivo); 95% dos usuários classificam billing/invoice como crítico — solução padrão tem time estimates, online parts ordering, inventory, payments, scheduling, VIN+plate scanning, maintenance reminders. Tendência 2026: AR glasses pra técnico + predictive maintenance via ML. | Padrão de mercado USA — VIN decoder + OBD + checklist visual com foto = baseline do que qualquer ERP automotivo precisa ter em 2026. |

**Observação chave Fase 1**: 3 tendências 2026 atravessam todas verticais:
1. **Agentic AI** ajusta produção autonomamente, re-ordena material quando estoque cai abaixo de threshold
2. **Shop floor data capture real-time** (apparel + print) — não é mais opcional
3. **Audit append-only** + multi-DC + certificações (SOC 2 Type II) são premissa pra entrar em conta enterprise

---

## Fase 2 — COMPARA com bucket vertical_client_facing oimpresso

### Quadro consolidado por dimensão concreta

| Dimensão concreta | Estado-da-arte 2026 | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Grade tam×cor first-class** | Linx Microvix: cadastro base se replica N SKUs automaticamente; pesquisa por grade; reposição inteligente | `App\Variation + VariationTemplate` (núcleo UPos) — Vestuario US-VEST-001 valida 15+ SKUs/peça em ROTA LIVRE prod 2+ anos. Cadastro funciona; **falta** reposição inteligente, pesquisa rápida por grade na UI, "atributo estação" first-class (US-VEST-029 — backlog) | **curta** — tem o core, falta UX polish + estação |
| **Virtual fitting room / size recommendation** | Shopify App Store: Fitnonce, Camweara, Style.me — 20 medições via foto, AI sugere, return -25% | **zero** no oimpresso. Vestuario é PDV físico balcão + estoque, não tem ecommerce visual | **longa** — mas é apropriado: ROTA LIVRE não tem ecommerce; sinal qualificado (ADR 0105) não pede |
| **Multi-tenant Tier 0 (isolation por design)** | Shopify Plus: multi-store/multi-market; Fulfil: SOC 2 Type II. Bling: multi-CNPJ só no Titânio (limitado a mesma raiz). Linx Microvix: por contrato | `business_id` global scope obrigatório, Eloquent scope + Pest cross-tenant biz=1 vs biz=99 (ADR 0093 IRREVOGÁVEL). Hook bloqueia `withoutGlobalScopes` sem comentário. Cofre Tier 0 que **supera Bling** (que exige mesma raiz CNPJ pra multi) | **oimpresso lidera** |
| **NFe-de-boleto-pago automática** | Bling: parcial (boleto+NFe separados); printIQ: N/A; Linx: NFe sim mas não acoplado a boleto pago | **US-RB-044 ADR 0089** — único do mercado. Boleto Asaas/Inter pago → trigger NFe emit automático | **oimpresso lidera (exclusivo)** |
| **FSM canon multi-stage com audit append-only** | printIQ: workflow automation parcial; Bling: state machine raso; Shopify: Flow + custom apps; Linx: state per módulo legacy | **ADR 0143 LIVE prod biz=1 desde 2026-05-12** — 13 stages (Repair) + 11 stages (Sells) × actions × roles per-business. `GuardsFsmTransitions` trait bloqueia UPDATE direto. `sale_stage_history` append-only + Spatie LogsActivity duplo audit | **oimpresso lidera** |
| **WhatsApp aprovação cliente token+PIN** | Auto repair USA: SMS/email padrão; Bling parcial; Linx parcial; Shopify: app store apenas | **US-REP-003 + US-OFICINA-006** — token UUID + PIN com signed URL portal público sem login. Em prod Repair, V0 OficinaAuto | **oimpresso lidera no BR** (WhatsApp Cloud API direto, ADR 0096) |
| **Cálculo m² granular + apontamento drift** | printIQ: estimating + shop floor data capture é padrão-ouro; Mubisys/Zênite/Calcgraf BR: cálculo só, sem apontamento drift | **US-COMVIS-001 + US-COMVIS-004** — `OrcamentoCalculator` com fórmulas por produto; `ApontamentoTracker` com drift (`m2_produzido` vs `m2_orcado`) | **paridade BR**, **gap vs printIQ global** (faltam: gang module, capacity planning, IQconnect-Automate) |
| **VIN decoder + OBD + checklist foto** | Tekmetric, Mitchell 1, ARI: padrão USA — VIN api decode + OBD + checklist + AI | OficinaAuto V0: CRUD Vehicle (placa/chassis/renavam) mas **sem VIN decoder API**, **sem OBD**, **sem checklist visual entrada com foto** (US-OFICINA-008 backlog 24h) | **longa** — mas V0 com sinal qualificado (Martinho) ainda não validou demanda |
| **Devolução/troca CDC + crédito loja** | Linx Microvix: nativo; Bling: parcial; Shopify: nativo (Returns API) | **falta** — US-VEST-021 backlog 16h. ROTA LIVRE opera manual hoje | **média** |
| **Comissão escalonada vendedor/técnico** | Linx Microvix (vestuário), Tekmetric (auto), Cellity (assistência) | **falta** — US-VEST-022 + US-REP-007 + US-OFICINA-010 backlog | **média** |
| **Etiqueta térmica TAM-COR-COLEÇÃO** | Linx Microvix, ProMoz: padrão vestuário BR | **falta** — US-VEST-020 backlog 12h | **curta** (12h IA-pair) |
| **Importer Firebird → Laravel** (migração Delphi legacy) | nenhum concorrente tem (problema interno oimpresso, 6-7 saudáveis OfficeImpresso) | **falta** — Officeimpresso G1 `LicencaImporter` + G4 onboarding wizard backlog. Schema legado documentado em `OFFICEIMPRESSO-FIREBIRD-SCHEMA.md` | **curta** — único barrier pra ativar pipeline ComVis com 6-7 prospects pagantes |
| **Catálogo peças com integração fornecedor (just-in-time)** | Tekmetric, Mitchell 1 (auto), Smart OS (assistência) | **falta** — US-OFICINA-009 + US-REP-008 backlog 32h cada | **longa** |
| **Dashboard KPIs operacionais (lead time, MTBF, OEE)** | printIQ (BI integrado), Linx Microvix, Smart OS | **falta** — US-COMVIS-009 (OEE plotter) + US-REP-005 (lead time/MTBF) backlog 24h cada | **média** |
| **App mobile técnico (foto + checklist + assinatura)** | Tekmetric, Mitchell 1, Mecanizou | **falta** — US-REP-006 + US-OFICINA-008 backlog 40h+24h | **longa** (mobile é PWA mínimo aceitável; nativo é 80h+) |
| **Agentic AI (re-order automático, ajuste produção)** | tendência 2026 cross-vertical — Datatex apparel, printIQ exploration | Jana IA conversacional (ADR 0035-0053) tem memória persistente mas **não age autonomamente** ainda — só responde. Agentic ainda V2 | **média-longa** — base IA existe, falta agency formal |
| **Escala (clientes pagantes)** | Bling: 300k; Shopify Plus: 50k+ brands enterprise; Linx Microvix: 70k+ lojas | ROTA LIVRE = 1 cliente prod + 6-7 candidatos OfficeImpresso saudáveis + Martinho discovery | **muito longa** — mas é estratégia (sinal qualificado ADR 0105), não falha |

### Onde oimpresso bate ou supera o mercado

1. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) > Bling (limitado a mesma raiz CNPJ no plano Titânio)
2. **NFe-de-boleto-pago automática** (US-RB-044) — **exclusivo** no comparativo
3. **FSM canon tabular** (ADR 0143 LIVE prod) — audit append-only com `GuardsFsmTransitions` trait + duplo audit (sale_stage_history + LogsActivity) — supera state machine raso de Bling/Linx legacy
4. **WhatsApp aprovação token+PIN** (Repair em prod, OficinaAuto V0) — supera SMS/email padrão USA
5. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 + Pest 4 — vs Mubisys Delphi, Zênite jQuery, Linx Microvix legacy
6. **Customizações preservadas com ADR** (format_date shift +3h ADR 0066) — concorrente "atualiza e quebra"
7. **Governança formal** Constituição v2 + 160+ ADRs + skills Tier A/B/C — nenhum concorrente BR PME tem isso (provável zero competitors com ADRs Nygard append-only)

### Onde oimpresso fica atrás (real)

1. **Escala** — 1 cliente prod vs 300k Bling. Não é falha (estratégia ADR 0105), mas é o maior gap comercial
2. **Vertical features visíveis no mercado**: virtual fitting room, VIN/OBD, gang run, Agentic AI autônoma — nada disso entrega "primeiro contato" de venda contra Shopify/Tekmetric/printIQ
3. **Certificação SOC 2 Type II** (Fulfil tem; oimpresso não) — barrier pra enterprise USA, irrelevante pra PME BR
4. **App Store / ecossistema** — Shopify tem milhares; oimpresso é monolito modular fechado
5. **Bridge legacy → moderno** (Officeimpresso G1 LicencaImporter): tem o problema, **não tem a solução** ainda — 6-7 saudáveis travam migração ComVis

---

## Fase 3 — AVALIA: 5 features P0 que ERP vertical 2026 PRECISA ter e oimpresso ainda não tem

| Gap | Impacto comercial | Esforço (IA-pair, ADR 0106 10x) | Pré-req? |
|---|---|---|---|
| **G1. LicencaImporter idempotente Firebird→Laravel** (Officeimpresso G1) | **ALTO** — destrava pipeline 6-7 saudáveis ComVis pagantes (R$ 350-1000/mês × 6 = R$ 25-72k/ano ARR adicional). Cada saudável que migra = US-COMVIS validado em prod | 12h IA-pair (×2 margem = 24h) | nenhum bloqueante; charter ComVis + schema multi-vertical já entregue Sprint 1-2 |
| **G2. Etiqueta térmica TAM-COR-COLEÇÃO** (Vestuario US-VEST-020) | **ALTO** — paridade Linx Microvix/ProMoz no PDV; +15% velocidade balcão em pico (sazonal verão/inverno ROTA LIVRE); destrava demo pra próximo prospect vestuário | 12h IA-pair (×2 = 24h) | nenhum bloqueante; printer driver térmico padrão BR documentado |
| **G3. Checklist visual veículo entrada com foto + assinatura** (OficinaAuto US-OFICINA-008) | **ALTO** — proteção legal vs dispute cliente (foto chassis/lateral antes/depois). Requisito implícito Martinho Caçambas. Paridade Tekmetric/Auto Manager | 24h IA-pair (×2 = 48h) | depende sinal qualificado Martinho confirmar piloto (ADR 0105) |
| **G4. Dashboard KPIs operacionais** (Repair US-REP-005: lead time + reincidência; ComVis US-COMVIS-009: OEE plotter; Vestuario: top-sellers/giro) | **MÉDIO-ALTO** — paridade Smart OS, printIQ. Cada vertical tem 1 KPI crítico que vendedor adora demonstrar. **Por que MÉDIO em vez de ALTO**: ROTA LIVRE não pediu; é feature de venda, não retenção | 24h × 3 verticais = 72h IA-pair (×2 = 144h) | depende sinal cliente OU decisão Wagner "feature comercial" |
| **G5. App PWA técnico (foto + checklist + assinatura cliente offline)** (Repair US-REP-006 + OficinaAuto reusa) | **ALTO** quando 2º vertical OS ativar; **MÉDIO** hoje (só Repair biz=1 + Martinho discovery) | 40h IA-pair PWA (×2 = 80h) — nativo ficaria 160h+ | depende G3 (checklist UI) + base FSM canon (já em prod) |

### Recomendação concreta — Wave 28 candidata

**Comece por G1 (LicencaImporter Firebird→Laravel)** — alto-impacto-baixo-esforço, único gap **sem pré-req bloqueante**, único gap onde **oimpresso é único com a solução** (G2-G5 são paridade; G1 é vantagem competitiva única).

**Por que G1 ganha de G2 mesmo G2 sendo paridade Linx**:
- G2 (etiqueta) só serve clientes vestuário **futuros** (ROTA LIVRE já opera 2+ anos sem)
- G1 destrava **6-7 prospects já saudáveis** que pagam pelo Delphi hoje. Cada migração = piloto ComVis validado + ARR garantido + redução risco "vamos abandonar oimpresso e migrar pra Mubisys"
- G1 é **moat competitivo único** (nenhum concorrente migra Firebird→Laravel); G2-G5 só fecha paridade
- G1 esforço 12h IA-pair = ROI por hora **15-20x** maior

**Próxima ação concreta hoje:**
1. Abrir [`memory/requisitos/Officeimpresso/SPEC.md`](../requisitos/Officeimpresso/SPEC.md) + [`OFFICEIMPRESSO-FIREBIRD-SCHEMA.md`](../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
2. Criar US-OFFI-G1 detalhada: schema mapping Firebird tables → Laravel migrations + comando artisan `licenca:importer-firebird {host} {db} --dry-run` idempotente (cursor offset igual `ParseLicencaLogCommand`) + Pest cross-tenant (biz=1 import não vaza pra biz=99)
3. Identificar **1 saudável** (Vargas? Extreme?) pra rodar dry-run real — converter ADR 0105 "wish" em "sinal qualificado"
4. Validar com Wagner: G1 entra Wave 28 como prioridade única, ou paralelizar com G2 (`coordenador-paralelo` em 2 waves isoladas — `Modules/Officeimpresso/` vs `Modules/Vestuario/`)?

---

## Sources Fase 1

- [Shopify Plus Features & Benefits 2026 — Elogic Commerce](https://elogic.co/blog/the-guide-to-shopify-plus-benefits-and-core-features/)
- [Best ERP for Shopify & DTC Brands — Fulfil (SOC 2 Type II)](https://www.fulfil.io/)
- [Virtual Fitting Rooms: A Retailer's Guide for 2026 — Shopify](https://www.shopify.com/enterprise/blog/virtual-fitting-rooms)
- [Bling ERP: Review Completo 2026 — Analister](https://analister.com/ferramentas/bling)
- [Locaweb adquire Bling — Startups](https://startups.com.br/negocios/locaweb-adquire-bling/)
- [Linx Microvix Moda e Acessórios](https://www.linx.com.br/moda-e-acessorios/)
- [Linx Microvix Grade — Cadastro de Produtos](https://share.linx.com.br/display/SHOPLINXMICRPUB/Grade+-+Cadastro+de+Produtos)
- [printIQ — Print MIS Software](https://printiq.com/)
- [printIQ Gang Module](https://printiq.com/modules/gang-module/)
- [printIQ IQconnect-Automate](https://printiq.com/iqconnect-automate/)
- [Best Auto Repair Software with VIN Decoder 2026 — GetApp](https://www.getapp.com/retail-consumer-services-software/auto-repair/f/vin-decoder/)
- [Mitchell 1® — Auto Repair Software](https://mitchell1.com/)
- [Olist Tiny ERP](https://tiny.com.br/)
- [11 Best Fashion & Apparel ERP Software Systems for 2026](https://www.appintent.com/software/ERP/fashion-industry/)
- [Print Industry ERP Software: 15 Best Systems 2026](https://www.appintent.com/software/ERP/printing-industry/)
