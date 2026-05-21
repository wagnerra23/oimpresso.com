# CAPTERRA-DESIGN-FICHA — Compras (UX/UI)

> **Cruzamento gerado:** 2026-05-21
> **Skill aplicada:** `design-arte` (input pra CAPTERRA-DESIGN-INVENTARIO.md futuro)
> **Alvo:** protótipo canon Cowork `public/cowork-preview/erp-shell-v2/compras-page.{jsx,css}` + standalone `Compras.html` — F1 pré-MWART (módulo `Modules/Compras` AINDA NÃO EXISTE)
> **Persona:** Larissa @ ROTA LIVRE biz=4 (vestuário Termas do Gravatal/SC, 1280px, balcão + telefone, **não-técnica**, 99% volume oimpresso, decora horários e fluxos)
> **Charter:** ❌ ausente — protótipo Cowork é o único artefato design canon até aqui
> **Visual-comparison prévio:** ❌ ausente (módulo não migrado; legacy é UltimatePOS `app/Http/Controllers/PurchaseController.php` + 14 Blade views em `resources/views/purchase/*`)
> **RUNBOOK:** ❌ ausente (gerar quando entrar em MWART F2)
> **SPEC:** ❌ ausente (criar junto com charter quando aprovado)
> **BRIEFING:** ❌ ausente

> ⚠️ **Nota mãe:** este é o **1º artefato canônico de UX do módulo Compras**. Diferente da ficha Cliente (que comparava Inertia W1-B3 já existente vs Blade legacy), aqui comparamos o **protótipo Cowork F1 pré-implementação** vs Blade legacy `purchase/index.blade.php` (10 colunas DataTable Bootstrap, filtros separados em form, modais jQuery, sem KPIs, sem drawer, sem FSM visual) vs **mercado SOTA 2026** (Shopify / Lightspeed / Cin7 / Zoho / Bling / Tiny / Omie / Conta Azul + Linear/Notion pra atalhos). Decisão: aprovar protótipo F1 → screenshot Wagner (F1.5) → entrar MWART F2.

> 🚨 **Premissa Tier 0 imutável:** protótipo Cowork canon vive em `public/cowork-preview/erp-shell-v2/` (NÃO em `_BACKUP-NAO-USAR/` — Wagner ordem 2026-05-20 movendo legados pra quarentena). 3 arquivos canon: `compras-page.jsx` (434 linhas), `compras-page.css` (182 linhas), `Compras.html` (679 linhas, preview standalone idêntico). Loop ADR 0114 funcional pra este módulo desde F1.

---

## 1. Players UX avaliados (referência 2026)

### 1.1 Compras / Purchase Order — leaders globais

| # | Player | Tipo | Site | Padrão UX característico |
|---|---|---|---|---|
| 1 | **Shopify (Stocky → nativo)** | E-commerce + POS — varejo PME (~ROTA LIVRE vertical) | help.shopify.com | **PO list + receive-partial canon** — UI extension POS pra recebimento, bulk actions em line items, "received vs. not received" linha-a-linha, integração inventory direta. Stocky descontinuado 31/08/2026, features migradas pro admin nativo |
| 2 | **Lightspeed Retail (R-Series + X-Series)** | POS multi-loja retail | retail-support.lightspeedhq.com | **Shipment History** com partial shipments expansíveis + **modal "check in items" autosave progress** — Larissa-friendly (não perde dados se telefone tocar). X-Series expande linha do PO pra mostrar partial deliveries |
| 3 | **Cin7 Core (Cin7 + DEAR)** | Mid-market inventory + multi-channel | cin7.com | **PO workflow drag-stage** (Draft → Ordered → Receiving → Received → Costed → Invoiced) com 6 stages canônicos, locking automático ao avançar, audit log por field |
| 4 | **Zoho Inventory** | PME global | zoho.com/inventory | **Purchase Order Workflow** linear (Draft → Open → Partially Received → Received → Billed → Closed) — status pill colorida + bulk receive, integrado a vendor-portal pra confirmação |
| 5 | **Linear** (cross-ref) | SaaS B2B issue tracker | linear.app | **Cmd+K canon + Esc tudo** + slash menu — referência pra atalhos densos no drawer; Notion idem (`/` global, Cmd+P abre página) |

### 1.2 Concorrentes BR (Capterra-driven baseline)

| # | Player | Padrão UX característico |
|---|---|---|
| 6 | **Bling** | Estoque > Notas de Entrada > **Mais ações > Importar XML NF-e** — vincula itens NF-e com PO existente automaticamente em 1 passo. **Agente NF-e** consulta SEFAZ últimos 3 meses por CNPJ e importa em background. UI Bootstrap legacy, 300k+ users |
| 7 | **Tiny ERP** | Suprimentos > Notas de Entrada > Importar XML — **matching automático** por (fornecedor + código fabricante + descrição exata) → fallback EAN → manual. Vínculo PO mostrado com marker visual na listagem |
| 8 | **Omie** | Compras > Importação NF-e — **NF-e Agent** com certificado digital faz polling SEFAZ automático; recebimento + accounts payable atrelados (3 passos em 1 wizard); link XML→PO bidirecional |
| 9 | **Conta Azul Pro** | Mais simples que Omie — perfil serviço + pequeno comércio; recebimento NF-e + lançamento contábil em modal único; foco em "Larissa-grade" UX |

### 1.3 Pré-requisitos PT-BR/Brasil específicos (não-negociáveis pra cliente real)

- **Importar XML NF-e** como ação primária no header (Bling/Tiny/Omie canon) — protótipo Cowork tem ✅ botão `↓ Importar XML` no head
- **Chave de acesso 44 dígitos** visível + parseável + colável (Bling/Omie) — protótipo tem ✅ exibida em "Documentos" + estado vazio "Cole a chave de 44 dígitos"
- **Manifestação destinatário** SEFAZ (todos BR) — protótipo tem ✅ card "✓ Confirmação da operação"
- **DANFE PDF** download (todos BR) — protótipo tem ✅ botão
- **FSM compras 5-7 stages** (Cin7/Zoho canon) — protótipo tem ✅ 6 stages (Rascunho → Pedido → Trânsito → Recebido → Conferido → Pago)

---

## 2. Tabela de comparação granular — capacidades 32 linhas (a peça central)

Legenda: ✅ tem completo · 🟡 parcial / divergente · ❌ ausente · ⚪ N/A nesse cenário

| # | Capacidade / Feature | Hoje **Blade legacy** `purchase.index.blade.php` | **Protótipo Cowork** `compras-page.jsx` (F1) | **Best-of-class 2026** | Compras BR baseline (Bling/Omie) |
|---|---|:-:|:-:|:-:|:-:|
| C-01 | Listagem PO paginada | ✅ DataTables jQuery ajax_view | ✅ tabela 8 colunas, mock 7 PO, hover+sel | ✅ Shopify add hundreds line items | ✅ |
| C-02 | Filtro por estágio (tabs) | 🟡 dropdown form Bootstrap | ✅ tabs Todas/A pagar/Rascunhos/Em trânsito/Cancelados c/ count | ✅ Zoho status filter | ✅ Bling |
| C-03 | Busca global (NF-e/fornecedor/ref/chave) | 🟡 DataTables search column | ✅ input head c/ kbd `/` indicado | ✅ Linear Cmd+K | 🟡 Bling buscar PO sim |
| C-04 | KPIs/cards header (a pagar / em trânsito / volume / fornecedores) | ❌ ausente | ✅ 4 KPI cards com cores semânticas (warn/ok) + sublinha contexto | ✅ HubSpot Highlights / Shopify Insights | 🟡 dashboard separado |
| C-05 | Filtros pílula (fornecedor / local / período / pagamento) | 🟡 form 5 selects + daterangepicker | ✅ 4 filter-pills + 1 ON state, "filtros" label uppercase | ✅ Attio filter chips | 🟡 Bling form sim |
| C-06 | Status semântico FSM com cor (6 stages) | ❌ texto plano (paid/due/partial/overdue) | ✅ pill colorida por stage (line-2/info/warn/ok/dark-ok/err) + dot 5px | ✅ Cin7 6 stages + Zoho 6 stages | 🟡 status texto + cor |
| C-07 | Drawer lateral 480px detalhe (row-driven) | ❌ ausente (legacy navega pra `show.blade`) | ✅ `aside.drawer` 480px (540px @1500px), `<Sheet>`-ready | ✅ Linear/Attio/Shopify drawer canon 2026 | ❌ Bling navega pra página |
| C-08 | FSM stepper inline no drawer (6 estágios visuais) | ❌ ausente | ✅ `.fsm-track` flex 6 steps c/ done/now/pending + ícones | ✅ Cin7 progress + Eleken stepper pattern | ❌ |
| C-09 | Tabs internas no drawer (Resumo/Itens/Documentos/Pagamentos/Histórico) | 🟡 tabs em `show.blade` | ✅ 5 tabs com count contextual | ✅ HubSpot record tabs | 🟡 Omie sim |
| C-10 | Importar XML NF-e como ação primária | 🟡 endpoint existe em outro menu | ✅ botão head `↓ Importar XML` + atalho `I` + estado vazio "Cole chave 44 dígitos" | ✅ Shopify CSV import + receive | ✅ canon Bling/Tiny/Omie |
| C-11 | Chave de acesso 44 dígitos exibida + parseável | ❌ ausente da listagem | ✅ coluna "NF-e ✓ XML" + xml-badge dashed no drawer | ✅ não-aplicável global, BR-only | ✅ |
| C-12 | Manifestação destinatário SEFAZ | ❌ ausente UI (endpoint legacy via fiscal) | ✅ card verde "✓ Confirmação da operação" + protocolo | ⚪ BR-only | ✅ Bling auto |
| C-13 | DANFE PDF download | 🟡 fiscal module separado | ✅ botão sm no drawer | ⚪ BR-only | ✅ |
| C-14 | Timeline atividades unificada (sistema + humano) | ❌ ausente | ✅ `.tl` cronológica com dots semantic (ok/warn/now) + autor/data | ✅ HubSpot middle column | ❌ Omie audit log separado |
| C-15 | Itens detalhados (qty/custo/total/venda/margem) | 🟡 `show.blade` parcial | ✅ tabela items 6 cols c/ lote + margem % | ✅ Shopify line items bulk | 🟡 Bling sim |
| C-16 | Recebimento partial (qty recebida ≠ qty pedida) | 🟡 endpoint legacy partial | ❌ **ausente do protótipo** (só mostra "Marcar recebida" inteira) | ✅ Lightspeed canon + Shopify "received vs not received" | 🟡 Bling sim |
| C-17 | Pagamentos múltiplos (PIX + boleto) | ✅ legacy `payment_modal` | ✅ pay-row PIX paid + BL due c/ ícones coloridos | ✅ Stripe payment intents | ✅ |
| C-18 | Footer sticky com total + atalhos visíveis | ❌ ausente | ✅ `.ft` c/ N compras + total + a pagar + atalhos `/`/`N`/`I`/`↑↓`/`Esc` | ✅ Linear footer + Notion shortcut bar | ❌ |
| C-19 | Atalhos teclado (`/` busca, `N` nova, `I` importar XML, `↑↓` navegar, `Esc` fechar) | ❌ ausente | 🟡 **declarados visualmente no footer mas NÃO IMPLEMENTADOS** (só visual hints, sem handlers React) | ✅ Linear "Esc tudo" + `/` global | ❌ |
| C-20 | Empty state em "Nenhuma NF-e vinculada" | ❌ ausente | ✅ `⊠` + título PT-BR + CTA "↓ Importar XML" | ✅ Pipedrive onboarding empty | ❌ Bling vazio genérico |
| C-21 | Bulk actions (selecionar massa: cancelar / exportar / marcar pago) | ❌ ausente | ❌ **ausente do protótipo** | ✅ Shopify 6 ações + "Select all results" | 🟡 Bling export sim |
| C-22 | Loading skeleton em tabela inicial | ❌ spinner DataTables | ❌ **ausente do protótipo** (mock estático sem skeleton shape) | ✅ Linear skeleton inteligente | 🟡 Bling spinner |
| C-23 | Localização (location_id ROTA LIVRE única) | ✅ select form | 🟡 pill "Local: Matriz" hardcoded mock, sem dropdown | ✅ Shopify multi-location filter | ✅ |
| C-24 | Multi-tenant `business_id` isolation | ✅ UPOS global scope | ⚪ N/A (protótipo é mock front) | ✅ Shopify workspace | ✅ |
| C-25 | A11y WCAG aria-labels + focus trap drawer + Esc | 🟡 parcial (jQuery legacy) | 🟡 **`<button className="x" onClick={close}>` sem aria-label**, sem focus trap declarado, sem keydown Esc handler | ✅ Shopify Polaris AA | ❌ Bling não-conforme |
| C-26 | Microcopy PT-BR completo | ✅ lang files BR | ✅ PT-BR consistente ("Mercadoria recebida · conferindo NF-e", "aguardando entrega", "Manifestada em") | ⚪ EN nativo SOTA | ✅ |
| C-27 | Responsive 1280px (Larissa monitor) | 🟡 legacy quebra com 21 col antes do fix `/sells` | ✅ `grid-template-columns:minmax(580px,1fr) 480px` cabe em 1280px sem scroll horizontal; expande pra 540px @1500px+ | ✅ Linear adaptativo | 🟡 Bling quebra mobile |
| C-28 | Pre-fill cross-page (de Estoque → Nova compra) | ❌ ausente | ❌ ausente | ✅ HubSpot cross-page quick-create | ❌ |
| C-29 | Comando contextual no footer drawer (Marcar recebida / Conferir itens / Pagar agora) | ❌ ausente (action menu dropdown) | ✅ botão condicional por stage + "Executar →" primary | ✅ Cin7 stage-action linking | 🟡 Bling action menu |
| C-30 | Sub-total + desconto + frete + total + pago + a pagar (breakdown) | 🟡 `show.blade` tabela | ✅ tabela financeira c/ tabular-nums semantic colors | ✅ Stripe invoice breakdown | ✅ |
| C-31 | Tabular-nums em colunas numéricas | ❌ texto regular | ✅ `font-variant-numeric:tabular-nums` + `var(--cmp-mono)` em todas colunas .num | ✅ Linear/Stripe canon | 🟡 Bling não-consistente |
| C-32 | Autosave draft (telefone toca no meio do PO) | ❌ ausente | ❌ **ausente do protótipo** (forms não modelados) | ✅ Lightspeed modal autosave | ❌ |

**Síntese:** Protótipo Cowork F1 **bate Blade legacy em 22 de 32 capacidades** e **pareia best-of-class em 14 de 32** (C-01..C-09, C-15, C-17, C-18, C-26, C-27, C-30, C-31). Lacunas reais:
- **C-16 (partial receive)** — Lightspeed/Shopify canon; **única lacuna funcional crítica** vs SOTA
- **C-19 (atalhos só visuais, sem handlers)** — risco gerar expectativa visual frustrada
- **C-22 (loading skeleton)** — perceived perf Larissa SC conexão lenta
- **C-21 (bulk actions)** — protótipo não modela; Non-Goal aceitável W1
- **C-25 (a11y focus trap + Esc handler)** — gap WCAG dever
- **C-32 (autosave)** — gap Larissa-conhecido (`cliente-rotalivre.md` "Larissa atende telefone no meio")

---

## 3. Dimensões UX 15 pontos — tabela comparativa (estado protótipo Cowork F1)

Pesos canon: P0=3 (hierarquia/densidade/navegação) · P1=2 (DS/microcopy/empty/loading/error) · P2=1 (atalhos/mobile/a11y/feedback/forms/dataviz) · P3=1 (onboarding).

| ID | Dimensão | Peso | Shopify/Lightspeed | Bling/Omie | **Protótipo Cowork compras-page** | Nota /10 |
|---|---|:-:|:-:|:-:|:-:|:-:|
| **D-01 (P0)** | Hierarquia visual | 3 | ✅ admin canon | 🟡 título solto | ✅ crumbs uppercase + h1 16px + count pill + sp + search + 2 CTAs (sec + primary); KPIs hierarquia clara | **9** |
| **D-02 (P0)** | Densidade informacional | 3 | ✅ retail dense | 🟡 Bling excesso col | ✅ tabela 8 col + drawer 480px + footer atalhos — densidade Linear-class sem poluição | **9** |
| **D-03 (P0)** | Navegação primária | 3 | ✅ sidebar + breadcrumb | 🟡 sidebar genérico | 🟡 protótipo é embed em ERP-shell-v2 (sidebar global existe); crumbs `ERP · Operação · Compras` ✅ mas drawer não tem back-nav explícito | **7** |
| **D-04 (P1)** | Sistema de design | 2 | ✅ Polaris certificado | 🟡 Bootstrap legacy | ✅ tokens `--cmp-*` escopados em `.compras-root` (paleta paper-warm + accent-blue + semantic ok/warn/err/info) — coerente com erp-shell-v2 | **9** |
| **D-05 (P1)** | Microcopy PT-BR | 2 | ⚪ EN nativo | ✅ PT-BR | ✅ PT-BR humano ("aguardando entrega", "Mercadoria recebida · conferindo NF-e", "Reposição lona 380gr · pedido P-882"); zero jargon técnico | **10** |
| **D-06 (P1)** | Empty states | 2 | ✅ ícone+CTA | ❌ vazio genérico | ✅ "Nenhuma NF-e vinculada" + ícone `⊠` + CTA "↓ Importar XML"; tab Pagamentos "Sem pagamentos lançados"; Itens "Itens detalhados não disponíveis" | **9** |
| **D-07 (P1)** | Loading + skeleton | 2 | ✅ Shopify skeleton | 🟡 spinner | ❌ **ausente** — protótipo é mock estático, sem skeleton shape nem loading state modelado | **3** |
| **D-08 (P1)** | Error UX | 2 | ✅ inline + recovery | 🟡 toast | ❌ **ausente** — protótipo não modela erro XML inválido, SEFAZ down, fornecedor não-encontrado, partial-receive divergente | **2** |
| **D-09 (P2)** | Atalhos teclado | 1 | ✅ Cmd+K + Esc + `/` | ❌ ausente | 🟡 **declarados no footer (`/`/`N`/`I`/`↑↓`/`Esc`) mas SEM handlers React** — risco gerar expectativa frustrada quando virar Inertia | **4** |
| **D-10 (P2)** | Mobile/touch 1280px | 1 | ✅ responsive | 🟡 Bling quebra | ✅ `grid-template-columns:minmax(580px,1fr) 480px` = 1060px conteúdo + 480px drawer = **1540px IDEAL**, mas at 1280px com drawer **stretches** OK porque min é 580; sem drawer = 100% width OK. KPI `repeat(4,1fr)` cabe | **7** |
| **D-11 (P2)** | A11y WCAG 2.1 AA | 1 | ✅ Polaris AA | 🟡 contraste OK | 🟡 cores ok (paper + ink contraste 4.5:1+); **botão `x` fechar drawer sem `aria-label`**, sem focus trap, sem `Esc` handler, sem `role="dialog"` no drawer, search input sem `<label>` (só placeholder) | **5** |
| **D-12 (P2)** | Feedback ações | 1 | ✅ otimistic | 🟡 toast tardio | ❌ **ausente** — clicar "Marcar recebida" no protótipo não faz nada; sem toast pós-save, sem otimistic, sem undo | **2** |
| **D-13 (P2)** | Formulários | 1 | ✅ inline + autosave | ❌ sem autosave | ❌ **ausente** — protótipo não modela Create/Edit (sem wizard nova compra, sem upload XML real, sem form validation) | **2** |
| **D-14 (P2)** | Dataviz (KPIs/cards) | 1 | ✅ rich | 🟡 separado | ✅ 4 KPIs tabular-nums semantic color + sublinha contexto ("próx. venc. 09/05", "+12,4% vs. abr/26") — Linear-class | **9** |
| **D-15 (P3)** | Onboarding | 1 | ✅ tooltips | ❌ vídeo home | ❌ ausente — sem tooltips contextuais, sem tour, sem hint primeira-vez "Cole XML aqui" | **3** |

---

## 4. Cálculo da nota ponderada

### 4.1 Cálculo agregado (peso × nota / Σ pesos)

```
P0 (3 dimensões × peso 3):
  D-01 (9×3) + D-02 (9×3) + D-03 (7×3) = 27+27+21 = 75

P1 (5 dimensões × peso 2):
  D-04 (9×2) + D-05 (10×2) + D-06 (9×2) + D-07 (3×2) + D-08 (2×2) = 18+20+18+6+4 = 66

P2-P3 (7 dimensões × peso 1):
  D-09 (4) + D-10 (7) + D-11 (5) + D-12 (2) + D-13 (2) + D-14 (9) + D-15 (3) = 32

Total: 75 + 66 + 32 = 173
Σ pesos: (3×3) + (5×2) + (7×1) = 9 + 10 + 7 = 26

nota_agregada = 173 / 26 × 10 = 66.5 → 67
```

### 4.2 Comparativos honestos

| Alvo | Nota | Observação |
|---|:-:|---|
| **Protótipo Cowork F1 (atual)** | **67/100** | Bom F1 visual — hierarquia/DS/microcopy/dataviz pareando SOTA; mas Loading/Error/Feedback/Forms/Autosave = vazio (esperado pra mock estático) |
| **Blade legacy `purchase/index.blade.php`** | **42/100** | DataTables 10 col + dropdown filter + sem KPIs + sem drawer + sem FSM visual + sem atalhos + jQuery modais — baseline UPOS 2018 |
| **Referência TOP (Shopify/Lightspeed/Cin7)** | **92/100** | Receive-partial canon + bulk + autosave + a11y AA + skeleton inteligente |
| **Referência BR direta (Bling/Omie compras)** | **62/100** | XML NF-e import canon + matching automático SEFAZ; UI Bootstrap legacy, sem drawer pattern, sem atalhos |
| **Referência BR moderno (Conta Azul Pro)** | **70/100** | Wizards limpos + Larissa-friendly mas sem FSM visual nem command palette |

```
═════════════════════════════════════════════════════════════════════════
NOTA OIMPRESSO COMPRAS PROTÓTIPO COWORK F1:          67/100
NOTA OIMPRESSO COMPRAS BLADE LEGACY (comparativo):   42/100
NOTA REFERÊNCIA TOP (Shopify/Lightspeed 2026):       92/100
NOTA REFERÊNCIA BR direta (Bling/Omie):              62/100
NOTA REFERÊNCIA BR moderno (Conta Azul Pro):         70/100

Gap pro topo:        -25 pts. Causa: protótipo é F1 estático (mock React IIFE); Loading/Error/Feedback/Forms/Autosave = vazio. Esperado pré-MWART.
Gap pro BR direto:   +5 pts. Já bate Bling/Omie em hierarquia/DS/drawer/FSM; perde só em a11y + Receive-partial.
Distância Blade:     +25 pts. Migrar pra Inertia (depois de fechar gaps) ganha 25 pts vs ficar em jQuery 2018.
═════════════════════════════════════════════════════════════════════════
```

**Leitura honesta (sem inflar):**
- **67 é nota de F1 saudável**, não de produção. Mock React estático sem backend = Loading/Error/Feedback/Forms/Autosave todos sem nota possível. Quando MWART F2+F3+F4 entregarem React real, espera-se subir pra ~80 (com gaps fechados) ou ~88 (com partial-receive + a11y + autosave).
- **Já supera Bling/Omie direto em UX visual** (drawer + FSM + KPIs + dataviz semantic) — mas perde em automação SEFAZ (Agente NF-e auto-polling) e matching XML→PO inteligente (Tiny canon). Isso é gap de **integração**, não de **design** — fora do escopo desta ficha.
- **Os 3 P0 (D-01..D-03, peso 3) somam 25 pts**, já pareando SOTA em hierarquia+densidade (9+9) — protótipo Cowork acertou estrutura macro.
- **Onde perde:** D-07 Loading + D-08 Error + D-12 Feedback + D-13 Forms = todos `2-3/10`. Esperado: mock visual estático não modela estados dinâmicos. Decisão Wagner: aceitar como F1 limitation OU exigir que protótipo modele states antes de avançar pra MWART.

---

## 5. Top 7 gaps priorizados (impacto × esforço × sinal Larissa)

Esforço em **H IA-pair fator 10x ADR 0106** (1H IA-pair ≈ 10H humano pré-IA).

| # | Gap | Onde fechar | Impacto Larissa | Esforço (H IA-pair) | Prioridade | Sinal cliente? (ADR 0105) |
|---|---|---|---|:-:|:-:|---|
| **G-CMP-01** | A11y drawer: `aria-label` no `<button .x>`, `role="dialog"`, `aria-modal="true"`, focus trap, `Esc` keydown handler global, `<label>` no search | protótipo Cowork (CSS+JSX) ANTES de F2 | dever WCAG 2.1 AA, **não wishlist** | XS (≤0.2H) | **P0** | dever WCAG, executável já |
| **G-CMP-02** | Atalhos `/` `N` `I` `↑↓` `Esc` IMPLEMENTAR (não só visual no footer) — Mousetrap ou `useKeyboardShortcut` hook | protótipo F1 + MWART F2 React | ⚪ Larissa não-power-user; **MAS** declarar visual no footer e não funcionar = quebra confiança imediata | S (~0.3H IA-pair) | **P0** | dever de consistência |
| **G-CMP-03** | Loading skeleton shape em tabela inicial (50 linhas pulsantes) + drawer skeleton ao trocar PO selecionado | MWART F2 React real | perceived perf Larissa SC conexão lenta | S (~0.2H IA-pair) | **P1** | inferido `cliente-rotalivre.md` (1280px + interior SC) |
| **G-CMP-04** | Recebimento partial (qty recebida ≠ qty pedida) — modal "check in items" autosave Lightspeed canon | MWART F2+F3 React + backend | ✅ vestuário recebe parcial real (lona faltou 20m², ilhós veio dobrado) | M (~1H IA-pair frontend + L backend Modules/Compras) | **P1** | sinal médio (uso real PME varejo) |
| **G-CMP-05** | Autosave draft "Nova compra" `localStorage.oimpresso.compras.draft.{biz}.{user}` debounced 500ms — Larissa atende telefone no meio | MWART F2 React (Create.tsx) | ✅ **sinal forte** `cliente-rotalivre.md`: "Larissa atende telefone, decora estado" | S (~0.2H IA-pair) | **P1** | sinal qualificado documentado |
| **G-CMP-06** | Error UX: XML inválido / SEFAZ down / fornecedor não-encontrado / chave-44-dígitos malformada — inline + recovery (não toast genérico) | MWART F2+F3 | ✅ Larissa SC tem internet instável; SEFAZ cai semanal | M (~0.8H IA-pair) | **P2** | inferido (sinal SEFAZ down é universal BR) |
| **G-CMP-07** | Matching automático XML→PO + fornecedor (Tiny canon: (CNPJ + cod_fabricante + descrição) → fallback EAN → manual) | backend Modules/Compras | ✅ acelera 10x vs digitar manual; Bling/Tiny/Omie têm | L (~2H IA-pair backend) | **P2** | sinal médio (aderência BR) |

**Resumo capa:**
- **P0 executável ANTES de F1.5 screenshot Wagner (≤0.5H IA-pair total):** G-CMP-01 + G-CMP-02 → mexer protótipo Cowork direto antes de aprovar
- **P1 fechar ANTES de canary biz=1 em produção (≤1.5H total):** G-CMP-03 + G-CMP-04 + G-CMP-05
- **P2 próxima onda:** G-CMP-06 + G-CMP-07
- **Não-listado / Non-Goal aceitável W1:** bulk actions (C-21), command-K global (Linear/Notion canon), onboarding tooltips, lead-scoring de fornecedor — sem sinal Larissa direto

---

## 6. Decisão / Recomendação final

### Wagner pergunta: "Protótipo Cowork F1 está pronto pra F1.5 (screenshot aprovado) → MWART F2?"

### Resposta direta: **🟡 PODE COM GATE — fechar G-CMP-01 + G-CMP-02 ANTES**

**Justificativa em 6 bullets honestos:**

1. **Nota 67/100 é F1 saudável**, supera Blade legacy 42/100 em 25 pts e Bling/Omie BR direto em 5 pts. Estrutura macro (P0 = 25 pts em 27 possíveis) já é SOTA-grade. Hierarquia + densidade + DS + microcopy + dataviz = nada pra refator.

2. **Gap pro topo (-25 pts) é predominantemente "estados dinâmicos não modelados em mock estático"** (D-07 Loading + D-08 Error + D-12 Feedback + D-13 Forms = 8/40). Isso é **limitação intencional de F1**, não bug de design. Fechar exige React real (MWART F2+F3) e backend (Modules/Compras AINDA NÃO EXISTE).

3. **2 gaps são executáveis HOJE no protótipo Cowork antes de F1.5** (≤0.5H IA-pair total): G-CMP-01 (a11y drawer básico) + G-CMP-02 (atalhos visuais → handlers reais). **Declarar atalhos no footer e não funcionar = quebra confiança Larissa imediata** — se vai aparecer visual, tem que funcionar.

4. **3 gaps são executáveis ANTES de canary biz=1 produção** (≤1.5H IA-pair total): G-CMP-03 (skeleton), G-CMP-04 (partial-receive — sinal real PME varejo), G-CMP-05 (autosave draft — **sinal forte documentado** em `cliente-rotalivre.md`).

5. **Protótipo Cowork CSS escopado em `.compras-root` é tecnicamente bem-feito** (tokens `--cmp-*`, paleta paper-warm coerente com erp-shell-v2, tabular-nums, drawer responsive grid). Migração pra Tailwind 4 + shadcn no MWART F3 é direta (mapear tokens 1:1).

6. **Blade legacy `purchase/index.blade.php` é UPOS 2018 puro** — DataTables jQuery + Bootstrap form filtros + modais legacy + zero KPIs. Migrar pra Cowork-derived Inertia é **upgrade líquido de 25 pts UX** + ganha multi-tenant Tier 0 + ganha pattern unificado com Cliente W1-B3.

### O que liberar AGORA (com gates fechados)

- ✅ Após G-CMP-01 + G-CMP-02 fechados → **screenshot Wagner F1.5** (gate ADR 0107) — aprovar visual antes de qualquer linha de React Inertia
- ✅ Criar `memory/requisitos/Compras/{README.md, CHARTER.md, RUNBOOK.md, SPEC.md, BRIEFING.md}` paralelo ao Cliente (ARCHITECTURE+GLOSSARY opcional pra W1)
- ✅ Charter declarar **Non-Goal explícito:** bulk actions, command-K global, onboarding tour, lead-scoring fornecedor — pra não virar "esqueceram"

### O que segurar (NÃO ligar antes de fechar gates)

- 🟡 MWART F2 React Inertia — só após F1.5 aprovado + G-CMP-01/02 fechados no protótipo
- 🟡 Canary biz=1 — só após G-CMP-03/04/05 fechados (skeleton + partial-receive + autosave)
- 🟡 Larissa biz=4 — só após canary 7d biz=1 verde (ADR 0101 — NUNCA biz=4 em smoke)

### O que resolver ANTES de qualquer cliente ver (gates Tier 0 imutáveis)

1. **Charter `Index.charter.md`** declarado com Non-Goals + Anti-hooks (paralelo Cliente W1-B3) — Wagner aprova explicitamente bulk/command-K/onboarding como Non-Goal antes de Felipe/Eliana implementarem React
2. **Pest cross-tenant biz=1 vs biz=99 verde em CI** — Tier 0 IRREVOGÁVEL ADR 0093 + ADR 0101
3. **PII redaction** — CNPJ fornecedor + chave NF-e 44 dígitos podem ser PII regulatório; checar com Eliana[E] antes de logar em Sentry/CloudWatch
4. **`format_date +3h` ADR 0066** — datas exibidas em "Mercadoria recebida · 08/05/26 11:42" precisam respeitar shift legacy preservado pra Larissa (não "corrigir" automaticamente)

### Próxima ação executável (uma só, hoje)

**Fechar G-CMP-01 (a11y drawer) + G-CMP-02 (atalhos com handlers) direto no `compras-page.jsx` ANTES de mostrar screenshot pro Wagner.**

Comando concreto:
```
1. Editar D:\oimpresso.com\public\cowork-preview\erp-shell-v2\compras-page.jsx:
   - Adicionar useEffect com keydown handler global: Esc fecha drawer, "/" foca search, "N" simula nova compra, "I" simula importar XML, ↑↓ navega rows
   - <button className="x" aria-label="Fechar detalhes da compra" onClick={close}>
   - <aside className="drawer" role="dialog" aria-modal="true" aria-labelledby="drw-title">
   - <h2 id="drw-title">{s.name}</h2>
   - <input ref={searchRef} aria-label="Buscar compras por NF-e, fornecedor, referência ou chave de acesso" placeholder="...">
   - Focus trap no drawer (useEffect que prende Tab dentro do aside enquanto aberto)
2. Reabrir Compras.html no browser, validar visualmente
3. Wagner abre tela, tira screenshot, aprova F1.5
4. Criar Modules/Compras + charter + RUNBOOK, entrar MWART F2
```

---

## 7. Restrições Tier 0 que o redesign respeita

- **Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):** quando virar Inertia, `Modules\Compras\Models\Purchase::where('business_id', ...)` global scope obrigatório; autosave draft (G-CMP-05) precisa chave `{biz}.{user}` — vazar draft de compra entre tenants seria vazamento de fornecedor + preço de custo (PII comercial)
- **MWART canon ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)):** módulo ainda em F1 (protótipo Cowork). Próximas fases: F1.5 (screenshot Wagner), F2 (Inertia React + Pest), F3 (visual gate), F4 (canary biz=1), F5 (rollout biz=4 + RedirectLegacy)
- **Visual gate F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)):** F1.5 PENDENTE Wagner — bloqueia início formal MWART
- **Loop Cowork ↔ Claude Code ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) + [`prototipo-ui/PROTOCOL.md`](../../../prototipo-ui/PROTOCOL.md)):** loop funcional — protótipo canon vive em `public/cowork-preview/erp-shell-v2/` (Wagner ordem 2026-05-20 movendo legados pra `_BACKUP-NAO-USAR/`)
- **Charter > Spec (Constituição v2):** charter Compras AINDA NÃO EXISTE — criar com Non-Goals explícitos ANTES de implementar React (evita scope creep tipo "ah, vamos colocar command-K também")
- **Cliente como sinal ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):** G-CMP-05 (autosave) tem sinal forte. G-CMP-04 (partial-receive) tem sinal médio-alto (PME varejo). Bulk actions + command-K + onboarding tour **sem sinal Larissa direto** → ADR feature-wish, não US ativa
- **biz=1 em smoke ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)):** todos Pest novos de Modules/Compras rodam biz=1 ou biz=99. NUNCA biz=4 (Larissa real)
- **ADR 0066 `format_date +3h`:** datas exibidas no drawer ("Mercadoria recebida · 08/05/26 11:42") respeitam shift legacy preservado pra Larissa — NÃO "corrigir" sem comunicar
- **ADR 0110 Cockpit Pattern V2 (canon list-detail):** protótipo Cowork já segue padrão list + drawer 480px right canon — coerente com Cliente W1-B3

---

## 8. O que NÃO mudar (pontos OK do protótipo F1)

| Item | Por quê manter |
|---|---|
| Drawer 480px right ao invés de modal full-screen | Linear/Attio/Shopify canon 2026; preserva contexto da lista; coerente com Cliente W1-B3 e Cockpit Pattern V2 (ADR 0110) |
| 4 KPI cards header (a pagar / em trânsito / volume / fornecedores) com cores semânticas + sublinha contexto | HubSpot Highlights pattern; Linear-class dataviz; Larissa olha em ≤2 segundos pra saber prioridade do dia |
| Status pill semântico FSM 6 stages com dot 5px + cor + uppercase | Cin7/Zoho canon; visual instantâneo pra Larissa não-técnica |
| `↓ Importar XML` como botão secundário no head (não escondido em menu) | Bling/Tiny/Omie canon BR; ação primária do dia-a-dia compras BR |
| FSM stepper inline no drawer com done/now/pending visual | Eleken stepper pattern + Cin7 progress; Larissa entende "estou aqui" sem ler |
| Footer sticky com atalhos visíveis (`/`/`N`/`I`/`↑↓`/`Esc`) | Linear/Notion canon; ensina atalhos passivamente — **MAS exige G-CMP-02 implementar handlers** |
| Tokens `--cmp-*` escopados em `.compras-root` (não vaza pro shell) | CSS isolation pattern; permite migrar gradual pra Tailwind 4 sem quebrar shell |
| Paleta paper-warm (#f6f4ef + #fbf9f3) coerente com erp-shell-v2 | Coerência visual cross-módulo; Larissa não tem fadiga visual cross-tela |
| Tabular-nums + mono em todas colunas .num + KPIs | Linear/Stripe canon; alinhamento perfeito de R$ + qty |
| Drawer responsive `minmax(580px,1fr) 480px` + expansão @1500px | Cabe em 1280px Larissa sem horizontal scroll; expande em monitor maior |
| Microcopy PT-BR humano ("Mercadoria recebida · conferindo NF-e", "aguardando entrega") | Zero jargon técnico; Larissa lê e entende sem treinamento |
| Mock data biz=4 ROTA LIVRE (lonas, vinis, tintas, ilhós) | Aderência semântica imediata com persona real — vale ouro pra screenshot review Wagner |
| Estados condicionais no drw-foot (Marcar recebida / Conferir itens / Pagar agora) por stage | Cin7 stage-action linking; reduz cognitive load — só mostra ação do estágio atual |

---

## 9. Referências externas (Fase 2)

- [Shopify — Receiving and Processing Inventory from Purchase Orders](https://help.shopify.com/en/manual/products/inventory/purchase-orders/receiving-inventory)
- [Shopify — Creating and Managing Purchase Orders](https://help.shopify.com/en/manual/products/inventory/purchase-orders/creating-purchase-orders)
- [Shopify POS — Receiving Purchase Orders (Stocky)](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/inventory-management/stocky/pos-inventory-management/receiving-purchase-orders)
- [Lightspeed Retail R-Series — Receiving items in purchase orders](https://retail-support.lightspeedhq.com/hc/en-us/articles/8153229791131-Receiving-items-in-purchase-orders)
- [Lightspeed Retail X-Series — Receiving purchase orders](https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534168876187-Receiving-purchase-orders)
- [Zoho Inventory — Purchase Orders Overview](https://www.zoho.com/us/inventory/help/purchase-orders/purchase-orders-overview.html)
- [Zoho Inventory — Purchase Order Workflow](https://www.zoho.com/us/inventory/kb/purchase-order/po-flow.html)
- [Cin7 vs Zoho Inventory comparison 2026](https://www.softwaresuggest.com/compare/zoho-inventory-management-vs-cin7)
- [Bling — Importar XML de NF-e de Entrada](https://ajuda.bling.com.br/hc/pt-br/articles/360036460513-Como-importar-o-XML-de-nota-de-entrada)
- [Bling — Vincular itens NF-e com Pedidos de Compra](https://ajuda.bling.com.br/hc/pt-br/articles/21830391097367-Como-vincular-os-itens-da-Nota-Fiscal-de-Entrada-com-Pedidos-de-Compra-na-Importa%C3%A7%C3%A3o-do-XML)
- [Bling — Automatize importação e manifestação NF-e](https://blog.bling.com.br/automatize-a-importacao-e-manifestacao-de-notas-fiscais-recebidas/)
- [Tiny ERP — Suprimentos (Hall de Ajuda)](https://www.tiny.com.br/ajuda/hall-suprimentos)
- [Tiny ERP — Importar XML NFe e vincular produtos](https://marketfacil.com.br/contabilidade/importar-xml-nfe-tiny-erp/)
- [Omie — Importando NF-e de Fornecedor manualmente](https://ajuda.omie.com.br/pt-BR/articles/1387978-importando-uma-nf-e-de-compra)
- [Omie — NF-e Agent automático SEFAZ](https://ajuda.omie.com.br/pt-BR/articles/1350609-importando-a-nf-e-de-fornecedor-automaticamente)
- [Omie — Recebimento da NF-e de Fornecedor](https://ajuda.omie.com.br/pt-BR/articles/1419039-recebimento-da-nf-e-de-fornecedor)
- [Eleken — 32 Stepper UI Examples](https://www.eleken.co/blog-posts/stepper-ui-examples)
- [SaaS UI Workflow Patterns curated gist (mpaiva)](https://gist.github.com/mpaiva-cc/d4ef3a652872cb5a91aa529db98d62dd)
- [B2B SaaS UX Design 2026 Challenges & Patterns](https://www.onething.design/post/b2b-saas-ux-design)
- [AI-Native SaaS and ERP UX 2026 — 6 patterns](https://www.technology.org/2026/04/28/the-new-ux-of-ai-native-saas-and-erp-six-design-patterns-were-shipping-in-2026/)
- [Notion — Keyboard Shortcuts Cheat Sheet 2026](https://shortcut-tools.com/en/shortcuts/notion/)
- [Mobbin — Command Palette UI Design best practices](https://mobbin.com/glossary/command-palette)

---

**Última atualização:** 2026-05-21
**Aprovado por:** — (pendente Wagner)
**Status:** análise estratégica F1 concluída; aguarda decisão Wagner sobre fechar G-CMP-01+02 + F1.5 screenshot
