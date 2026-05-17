---
title: Estado-da-arte tela de venda — foco RESPONSIVIDADE
date: 2026-05-17
agent: tela-venda-arte
escopo: /sells/create (form longo) — NÃO POS rápido
related_adrs: [0104, 0107, 0110, 0093, 0106]
target_audience: Wagner
duplicado_de_round1: false  # round1 W31 Create.review.md cobre defer/types/biz; este foca SÓ em responsivo
---

# Estado-da-arte tela de venda — RESPONSIVIDADE (oimpresso vs líderes 2026)

> Foco cirúrgico: **comportamento em ≤768 mobile / 768-1023 tablet / 1024-1279 notebook 14"-17" antigo / 1280+ Larissa-baseline**.
> Parque BR real: ~15% mobile + ~30% notebook/monitor antigo 1024-1365 + ~55% 1366-1920.
> Hoje `/sells/create` está desenhada **xl-only** (1280+) e degrada feio abaixo.

---

## 1. PESQUISA — Como os melhores fazem (10 players, foco responsivo)

### Tabela referências

| Player | Público | Mecanismo responsivo principal | Nota responsivo /10 | Fonte |
|---|---|---|---|---|
| **Shopify POS + Polaris** | Global PME-mid | `Table` Polaris **auto-converte pra list/cards** em mobile; breakpoints `phone/tablet/largeTablet`; app nativo iOS/Android pra POS handheld | **10** | [polaris-react.shopify.com/tokens/breakpoints](https://polaris-react.shopify.com/tokens/breakpoints) · [shopify.dev/docs/api/pos-ui-extensions](https://shopify.dev/docs/api/pos-ui-extensions/latest/polaris-web-components) |
| **Square POS app** | Global PME | Bottom-nav fixo + dual-screen Register (cliente vê); redesign 2024 com tabs na base; web admin responsive fluid | **9** | [squareup.com/the-bottom-line/inside-square/square-pos-redesign](https://squareup.com/us/en/the-bottom-line/inside-square/square-pos-redesign) · [squareup.com/hardware/handheld](https://squareup.com/us/en/hardware/handheld) |
| **Toast POS Handheld (Go 3)** | Restaurante US | Tablet 14" countertop + handheld 6" IP65 — UI **dual mode** (lista densa countertop, cards 1-col handheld); switch Wi-Fi↔4G transparente | **9** | [pos.toasttab.com/hardware/toast-go](https://pos.toasttab.com/hardware/toast-go) |
| **Lightspeed Retail X-Series** | Global retail mid | iPad-first ("sell screen" feita pra iPad portrait/landscape); web fallback responsive | **8** | [retail-support.lightspeedhq.com/.../Sell-screen](https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534062778651-Using-the-Retail-POS-Sell-screen) |
| **Linx Microvix Venda Fácil** | BR vestuário (concorrente direto Larissa) | URL dedicada mobile `vendafacil.microvix.com.br` + app PWA fullscreen; PDV em SmartPOS handheld + tablet/celular pra eliminar fila/balcão | **8** | [share.linx.com.br/.../Venda+Fácil+Mobile](https://share.linx.com.br/pages/viewpage.action?pageId=168647622) · [linx.com.br/moda-e-acessorios](https://www.linx.com.br/moda-e-acessorios/) |
| **Omie app** | BR PME | App nativo Android/iOS gratuito; aprova pagamento + pedido + dashboards no celular; web responsive separado | **7** | [omie.com.br/imprensa/omie-expande-software-erp-para-o-mobile](https://www.omie.com.br/imprensa/omie-expande-software-erp-para-o-mobile/) |
| **Vendizap** | BR mobile-first (WhatsApp) | **Nasceu mobile**; PDV no celular sem PC; ValidaPix automático; integra Insta/WhatsApp; 4k+ lojas | **9 (mobile) / 5 (desktop)** | [play.google.com/.../vendizap](https://play.google.com/store/apps/details?id=br.com.vendizap&hl=en_US) · [kyte.com.br/vender/sistema-pdv](https://www.kyte.com.br/vender/sistema-pdv) |
| **Bling ERP** | BR PME #1 (300k usuários) | Web responsive + apps Velis/parceiros pra vendedor de campo; PDV no celular | **6** | [bling.com.br](https://www.bling.com.br/) · [velis.com.br/aplicativo-vendas-bling](https://velis.com.br/aplicativo-vendas-bling/) |
| **Tiny ERP (Olist)** | BR PME e-commerce | Web responsive + app Olist; foco e-commerce; menos POS-feel | **6** | [tiny.com.br](https://tiny.com.br/) |
| **Conta Azul** | BR PME contábil | Web responsive + mobile app; foco financeiro, não POS | **5** | [contaazul.com](https://site-prod.contaazul.com/integracoes/) |

### Top-3 referência responsivo (ranking)

1. **Shopify Polaris** — gold standard. Table auto-converte. Mobile-first nativo. Breakpoints documentados publicamente.
2. **Toast Handheld Go 3** — dual-mode UI (countertop denso vs handheld 1-col cards) é o pattern que falta no oimpresso.
3. **Square POS redesign 2024** — bottom-nav fixo + tabs na base — pattern certo pra mobile/tablet vertical.

### Parágrafos curtos por player

**Shopify Polaris** define breakpoints `phone (0) / tablet (768) / largeTablet (1024)` ([fonte](https://polaris-react.shopify.com/tokens/breakpoints)). O `<Table>` Polaris detecta viewport e **transforma cada linha numa "key-value card stack"** abaixo de 768 — isso resolve o problema #1 do oimpresso (tabela de produtos 5 cols esmagada). Em 2026 a guidance Shopify subiu pra **"content breakpoints"** (medir onde o conteúdo quebra, não fixar device) ([fonte](https://www.rapiddoctools.com/blog/modern-responsive-breakpoints-2026-guide)).

**Square POS** redesenhou em 2024 com **navbar fixo na base** em vez de topo — observação que importa pra mobile (polegar alcança botões sem reach) ([fonte](https://squareup.com/us/en/the-bottom-line/inside-square/square-pos-redesign)). Square Register tem dual-screen (operador + cliente). Square Handheld é a referência de POS mobile cabendo num smartphone.

**Toast Go 3** é o caso mais inspirador: mesmo software roda em **3 form factors** — Flex 14" countertop, Go 3 handheld 6" IP65, e Flex 8" guest-facing — com **mesma identidade visual mas hierarquia diferente** ([fonte](https://pos.toasttab.com/hardware/toast-go)). Lição direta: Larissa pode ter UI densa no 1280 e a mesma codebase render UI 1-coluna no celular do vendedor — sem fork de tela.

**Lightspeed X-Series** assume iPad como primeiro alvo (portrait 768 e landscape 1024). A "Sell screen" é optimizada pra touch + scan barcode + drag-drop. Web é fallback. Conclusão: o tablet vendedor de campo é caso de uso real validado em mid-market global.

**Linx Microvix Venda Fácil** é o mais relevante pra benchmark BR vestuário (concorrente direto Larissa): tem **subdomínio `vendafacil.microvix.com.br` dedicado mobile** + instruções pra adicionar PWA fullscreen no Android/iOS ([fonte](https://share.linx.com.br/pages/viewpage.action?pageId=168647622)). PDV em SmartPOS handheld é vendido como diferencial pra "eliminar filas em loja física" ([fonte](https://www.linx.com.br/moda-e-acessorios/)). **Larissa hoje não tem isso** — se ela contratar funcionária pra atender no balcão e outra pra cadastrar lá no fundo via celular, ela não consegue.

**Vendizap** é caso extremo: **nasceu sendo mobile-only**. 4.000+ lojas BR rodam só no celular ([fonte Play Store](https://play.google.com/store/apps/details?id=br.com.vendizap&hl=en_US)). Validação clara: existe mercado grande pra ERP/PDV mobile-primeiro no BR, e o oimpresso desktop-only **deixa esse segmento na mesa**.

**Bling/Omie/Conta Azul** atendem web responsive bem mas dependem de parceiros (Velis pra Bling) pra app de venda em campo. Oimpresso pode fazer **mesma estratégia** sem app nativo — só PWA responsive bem feita já cobre vendedor de campo.

---

## 2. COMPARA — 15 dimensões, oimpresso atual vs líderes (foco responsivo)

### Tabela canônica (peso responsividade ALTO)

| # | Dimensão | Líder (estado-da-arte) | oimpresso `/sells/create` Inertia (hoje) | Distância | Nota /10 |
|---|---|---|---|---|---|
| **1** | **#responsiveness (DESTAQUE)** | Polaris auto-table→cards <768; Toast dual-mode countertop/handheld | `container max-w-7xl px-8` (1408px tradicional), tabela produtos 5-col `overflow-x-auto` quebra em 1024-, sem layout mobile dedicado, KPIs `grid-cols-2 md:grid-cols-4` OK só sm+ | **LONGA** | **3** |
| 2 | Velocidade fluxo (cliques) | Lightspeed sell screen: 4 cliques walk-in+3items+pay | oimpresso 1280: ~6 cliques (bom); 1024: scroll horizontal força cliques extras; mobile: form vertical longo, sem progresso visual | curta no 1280, média no resto | 6 |
| 3 | Busca produto | Polaris autocomplete + scanner inline + voice | `ProductSearchAutocomplete` debounce min query OK; **sem leitura de código de barras camera** mobile | média | 6 |
| 4 | Busca + cadastro cliente inline | Shopify cria contact dentro do fluxo sem perder contexto | `CustomerSearchAutocomplete` + postMessage `contact_created` da aba nova — funcional, mas abre **nova aba** (bad mobile UX, perde contexto no Android Chrome) | média | 5 |
| 5 | Atalhos teclado | Toast countertop full hotkeys F1-F12 | Ctrl+Enter salva, Esc blur, `/` foca busca — bom desktop, **inútil em mobile** (sem teclado físico) | curta desktop, n/a mobile | 7 |
| 6 | Layout/hierarquia visual | Polaris tokens consistentes + dark/light | shadcn/ui + Tailwind 4 + scroll-spy pills — qualidade alta no 1280, **pills fazem overflow horizontal sem indicador** quando faltam ≥3 (mobile) | curta no 1280, média mobile | 7 |
| **7** | **Mobile/touch (CRITICO)** | Toast handheld 6": cards 1-col, touch targets 48dp+, bottom-nav | Inputs `h-8` (32px) = **abaixo do mínimo 44pt iOS / 48dp Android**; sem bottom sheet pagamento; sticky footer `flex justify-between` esmaga botões em 360px (3 botões + texto helper) | LONGA | **2** |
| 8 | Múltiplos pagamentos split | Square split inline + Apple/Google Pay 1-tap | `PaymentRow` `md:grid-cols-4` — em 1024- vira 2-col OK; em mobile vira 1-col verticalíssimo + cartão tem 6 campos extras | curta 1280, média mobile | 6 |
| 9 | Estoque real-time inline | Lightspeed badge estoque visível por item | Não exibe estoque inline na tabela produtos (`p.name`, `p.sku`, quantidade, preço, desconto, subtotal) | média | 5 |
| 10 | NFe inline (BR) | n/a global; Bling/Omie/Microvix emitem inline | NFe via fluxo separado (não inline tela create) — emissão automática por listener (ADR Sells) | curta | 7 |
| 11 | Descontos/promoções | Square cupom + regra | Desconto inline % ou fixo + per-item; max % validado backend; **sem cupom/regra** | média | 6 |
| 12 | Offline/resiliência | Lightspeed/Square offline-first com sync | Auto-save draft localStorage debounced 500ms + recover OK; **sem offline-first real** (precisa internet pra ProductSearch) | média | 5 |
| 13 | Histórico/auditoria | Polaris timeline drawer | Audit FSM separado (drawer SaleSheet em `Index`, não em `Create`) | curta | 7 |
| 14 | Customização per-business | Shopify themes | Custom flags POS settings + price group por location | curta | 7 |
| 15 | UX feedback | Polaris empty states + loading | EmptyState shared + FieldError inline + `<details>` auto-open quando erro escondido (US-SELL-010 — bom) | curta | 8 |
| 16 | **Customer-facing display** (bônus 2026) | Square Register dual-screen + Toast Flex 8" guest screen | **Ausente** — não tem segunda tela cliente | longa | 2 |

> Nota dim #1 e #7 são **as duas notas que puxam pra baixo**. Outras 14 estão entre 5-8 (não-críticas).

### Mapa de breakpoints recomendado (componentes × viewport)

| Componente | <640 mobile | 640-767 sm | 768-1023 md (tablet) | 1024-1279 lg (notebook antigo) | 1280+ xl (Larissa baseline) |
|---|---|---|---|---|---|
| `container` | `px-3` | `px-4` | `px-6` | `px-6 max-w-5xl` | `px-8 max-w-7xl` (hoje) |
| Header h1 + pills | h1 20px + pills horizontal-scroll com **fade gradient** edges | h1 22px + pills wrap | h1 24px + pills wrap | hoje | hoje |
| KPIs grid | **1-col stack** OU **2-col compacto altura ≤80px** | hoje (2-col) | `md:grid-cols-4` (hoje) | hoje | hoje |
| Dados venda (4 fields) | 1-col stack | 1-col | `md:grid-cols-2` (hoje) | `lg:grid-cols-4` (hoje) | hoje |
| Produtos tabela | **Cards 1-col** (item = card com nome + SKU + qtd stepper + preço + subtotal + trash) | cards | tabela 5-col compacta | tabela hoje | tabela hoje |
| PaymentRow | **Bottom sheet drawer** (chama "Adicionar pagamento" abre sheet); ou cards 1-col | cards 1-col | `md:grid-cols-2` | `lg:grid-cols-4` (hoje) | hoje |
| Sticky footer | **2-row** (texto helper em row separada acima dos botões); botão `Salvar` **full-width** primário; Cancelar como icon button | 2-row | 1-row (hoje) | hoje | hoje |
| `<details>` Mais opções | colapsado por padrão **sempre** mobile (ignora localStorage open) | hoje | hoje | hoje | hoje |
| Touch targets | **44pt+ iOS / 48dp Android** (inputs h-11 = 44px) | h-10 | h-9 | h-8 (hoje) | hoje |

---

## 3. AVALIA — Gaps responsivos rankeados

### Top 5 gaps (impacto × esforço, recalibrado IA-pair ADR 0106)

| # | Gap | Impacto | Esforço IA-pair | Pré-req? | Risco |
|---|---|---|---|---|---|
| **P0-1** | **Tabela produtos não vira cards em mobile** (`overflow-x-auto` força scroll horizontal — Larissa atende celular não vê coluna preço sem deslizar) | ALTO — 99% volume é Sells; mobile vendedor campo é hipótese MAS notebook 14" 1366 já sofre hoje | ~3h (criar `ProductLineCard` 1-col + Tailwind `md:hidden` table swap; reuse PaymentRow pattern) + 1h Pest viewport | Nenhum | baixo |
| **P0-2** | **Touch targets abaixo do mínimo** (`h-8` = 32px em todos Input/Select da tabela produtos + PaymentRow — viola Apple HIG 44pt e Material 48dp; Larissa cutuca celular e erra clique) | ALTO — qualquer uso mobile/tablet (Larissa, vendedor campo hipotético, Wagner no celular) | ~1.5h (token CSS `--input-touch-h: 44px` + conditional `md:h-8 h-11` em ~12 lugares) | Nenhum | baixíssimo |
| **P0-3** | **Footer sticky esmaga em <640px** (3 botões + helper text em `justify-between` = 2 botões cortados em iPhone SE 320-375px; texto helper sobrepõe Cancelar) | MÉDIO-ALTO — todo dispositivo <640px (≈10% parque BR PME) | ~2h (Footer 2-row mobile: helper acima full-width, botões row abaixo; CTA primário full-width, secundários icon-only) | Nenhum | baixo |
| **P1-1** | **Pills overflow sem indicador** (5 pills na nav scroll-spy — em 360px wrap pra 3 linhas; em 640-767px wrap pra 2 linhas — fica feio e sem indicador visual de scroll) | MÉDIO — pills são `flex-wrap` mas perdem identidade compacta | ~2h (substituir `flex-wrap` por `overflow-x-auto` + `scroll-snap-x` + fade gradient L/R via mask-image) | Nenhum | baixo |
| **P1-2** | **PaymentRow 6 campos extras cartão = avalanche em mobile** (Cartão expandido tem nº + nome + tx + tipo + mês + ano + cvv = 7 inputs verticais; UX caótico no celular sem coletar nada de útil) | MÉDIO — Larissa não cobra cartão pela tela (passa direto Stone); mas hipótese vendedor campo coleta cartão | ~3h (esconder bloco cartão atrás de `<details>` "Detalhes do cartão" colapsado; ou bottom-sheet drawer pra mobile usando `Components/ui/sheet.tsx` já existente) | Nenhum | baixo |
| **P2-1** | **Pills + sticky header somam 96-120px em mobile** (header sticky topo + container px-3 com pills = scroll fica com muito chrome; em 568px altura iPhone, sobra ~440px pra form) | BAIXO-MÉDIO — solução: pills auto-collapse em mobile (vira drop menu "Ir pra...") | ~2h (em <768 trocar `<nav pills>` por `<Select>` "Pular pra seção" + ainda scroll-spy mantém active state) | Nenhum | baixo |
| **P2-2** | **KPIs gigantes (`p-6` `text-4xl`) ocupam 1/3 viewport mobile** (4 KPIs em 2x2 grid = ~320px altura) | BAIXO — KPIs são úteis MAS prioridade na primeira dobra deveria ser ProductSearch | ~1.5h (em mobile colapsar KPIs em barra horizontal compacta sticky abaixo do header: "5 itens · R$ [redacted Tier 0] · Falta R$ [redacted Tier 0]") | Nenhum | baixo |
| **P3-1** | **`<details>` "Mais opções" abre por localStorage** (em mobile o usuário não quer 10 campos extras nunca) | BAIXO | ~30min (em <md ignorar localStorage e forçar closed) | Nenhum | baixíssimo |
| **P3-2** | **Sem viewport meta safe-area-inset-bottom no sticky footer** (iPhone home indicator sobrepõe botões em PWA) | BAIXO (não temos PWA install hoje) | ~30min (`pb-[env(safe-area-inset-bottom)]` no footer) | Nenhum | baixíssimo |

### Categorização

- **P0** (alto impacto + baixo esforço, sem pré-req) — fazer cycle atual:
  - **P0-1** tabela→cards mobile (~3h)
  - **P0-2** touch targets 44px (~1.5h)
  - **P0-3** footer sticky 2-row mobile (~2h)
  - **Total P0: ~7h IA-pair = ~1 dia útil** com Pest cobrindo 3 viewports (375/768/1280)

- **P1** — próximo cycle:
  - **P1-1** pills horizontal-scroll com fade
  - **P1-2** PaymentRow cartão como bottom sheet

- **P2** — backlog (esperar sinal qualificado — ADR 0105):
  - **P2-1** pills→Select em mobile
  - **P2-2** KPIs colapsam em barra compacta mobile

- **P3** — descartar ou virar ADR feature-wish ([ADR 0105](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):
  - **P3-1** force-close details mobile (correção trivial)
  - **P3-2** safe-area-inset (faz junto quando tiver PWA install)

---

## 4. NOTA + RECOMENDAÇÃO

### Cálculo ponderado (pesos especiais responsividade)

Pesos:
- Dim #1 (#responsiveness) e #7 (mobile/touch) — peso **4** (P0)
- Dim 2-6 + 8 + 16 (velocidade, busca, atalhos, layout, pagto, customer-display) — peso **2**
- Dim 9-15 — peso **1**

```
oimpresso /sells/create Inertia (Sells/Create.tsx 1409 LOC):
  Σ(peso×nota) = 4×3 + 4×2 + 2×(6+6+5+7+7+6+2) + 1×(5+7+6+5+7+7+8)
              = 12 + 8 + 2×39 + 1×45
              = 12 + 8 + 78 + 45
              = 143

  Σ(pesos) = 4+4 + 7×2 + 7×1 = 8 + 14 + 7 = 29

  Nota = 143 / 29 × 10 = 49.3 / 100

Líder responsivo (Shopify POS + Polaris referência):
  Estimativa todas dim ≥ 8, dim #1 e #7 = 10:
  Σ = 4×10 + 4×10 + 7×2×8.5 + 7×1×8 = 40+40+119+56 = 255
  Nota = 255/29 × 10 = 87.9 / 100

Concorrente BR médio (Bling/Omie/Microvix, ponderado responsivo):
  ~62 / 100
```

```
╔══════════════════════════════════════════════════════════════╗
║  NOTA OIMPRESSO Inertia /sells/create:        49/100         ║
║  NOTA REFERÊNCIA LÍDER (Shopify+Toast+Square): 88/100        ║
║  NOTA CONCORRENTES BR PME (Bling/Omie/Microvix): ~62/100     ║
║                                                              ║
║  Gap: -39 pts vs líder, -13 pts vs concorrentes BR           ║
║  Causa principal: desenhada xl-only — quebra em 1024 e <640  ║
╚══════════════════════════════════════════════════════════════╝
```

> Onde oimpresso **bate** o mercado mesmo na responsividade: scroll-spy pills + IntersectionObserver (pattern moderno bonito em desktop, ninguém entre concorrentes BR tem), `<details>` auto-open quando erro escondido (US-SELL-010 — UX raro), auto-save draft localStorage com biz+user namespace (Tier 0 disciplinado). Mas isso vale **só pra desktop**.

### 3 Screenshots-referência (descrição textual pra Wagner aprovar antes do prototipo-ui F1.5)

1. **Shopify POS app — line item card mobile** (iOS App Store screenshots Shopify POS): cada item de venda é um card 1-col com foto thumb + nome em negrito + qtd stepper +/- + preço à direita + swipe-to-delete. Tudo cabe em 375px. URL: [apps.apple.com/us/app/shopify-point-of-sale](https://apps.apple.com/us/app/shopify-point-of-sale/id371294472).

2. **Toast Go 3 handheld checkout flow** (Toast site hero video — 6" handheld em mão de garçom): bottom sheet drawer pra payment com Apple Pay/cartão/cash em 3 botões grandes 60px+; KPI total venda topo sticky em barra compacta única linha. URL: [pos.toasttab.com/hardware/toast-go](https://pos.toasttab.com/hardware/toast-go).

3. **Linx Venda Fácil mobile PWA** (Linx Share manual): tela cheia sem chrome browser; campos cliente/produto/pagamento como steps separados com botão "Próximo" full-width sticky bottom; concorrente direto vestuário BR. URL: [share.linx.com.br/.../Venda+Fácil+Mobile](https://share.linx.com.br/pages/viewpage.action?pageId=168647622).

### Recomendação ADR design system

**Sim — vale criar um.** Esqueleto sugerido:

```
ADR XXXX — Breakpoints canônicos oimpresso + regra mobile-first
---------------------------------------------------------------
Status: proposed
Contexto: tela /sells/create entrega NOTA 49/100 em responsividade,
          parque BR PME tem ~45% dispositivos abaixo de xl (1280),
          ausência de regra explícita causa diversos componentes
          desenhados xl-only sem aviso.

Decisão:
  1. Breakpoints canon = Tailwind defaults (sm 640, md 768, lg 1024, xl 1280, 2xl 1536).
     Justificativa: Toast/Polaris/Square usam ranges similares.
  2. Componentes em Pages/ DEVEM funcionar em 375px (iPhone SE baseline).
     Quebra silenciosa abaixo de 375 ou acima de 2560 ok.
  3. Tabelas com >3 colunas DEVEM ter fallback card 1-col em <768 (P0-1 pattern).
  4. Inputs/Selects em página primária (Sells/Repair/Pos) DEVEM ter
     altura 44px mobile / 36px desktop (P0-2 pattern).
  5. Sticky footer DEVE ter modo 2-row mobile (helper acima, ações abaixo).
  6. Charter `<Tela>.charter.md` DEVE declarar viewport target mínimo
     ("Cabe em 1280 sem scroll horizontal" → adicionar "Funciona em 375 sem scroll horizontal").
  7. CI: skill futuro `responsive-conformance-test` valida via Playwright headless
     que tela carrega sem horizontal-scroll em 375/768/1024/1280.

Consequências:
  - Pest 5+ Playwright opcional adiciona ~30s CI
  - Charters atualizados retroativamente nas top-5 telas (Sells/Create, Pos/Create,
    Repair/Index, Project/Board, Inbox/Index)
  - Skill Tier B `mobile-first-canon` auto-triggera em Edit de Pages/**/*.tsx
```

### Recomendação imediata (próxima ação hoje)

**Comece por P0-1 (tabela produtos → cards mobile)** — alto-impacto-baixo-esforço, sem pré-req, valida pattern reusável pra PaymentRow e qualquer outra tabela do projeto.

**Ação hoje executável (concreta, ~3h IA-pair):**

1. Criar `resources/js/Pages/Sells/_components/ProductLineCard.tsx` — card 1-col com:
   - Nome + SKU em header
   - Grid 2-col: Qtd (stepper +/- 44px height) + Preço unit
   - Linha: Desconto + Subtotal à direita
   - Botão remove icon-button 44×44px no canto
2. Em `Create.tsx` linha 851-955, wrap tabela atual em `<div className="hidden md:block">` e adicionar `<div className="md:hidden space-y-3">{products.map(p => <ProductLineCard />)}</div>` acima.
3. Pest viewport test `SellsCreateMobileTest.php` cobrindo 375/768/1280 (Playwright headless ou inertia render snapshot).
4. Atualizar `Create.charter.md` adicionando UX target: **"Funciona em 375px (iPhone SE) sem scroll horizontal nas seções primárias (Dados, Produtos, Pagamento, Resumo)"**.
5. PR conventional commits: `feat(sells): tabela produtos vira cards 1-col em mobile [W+C]` com Refs: P0-1 RESPONSIVIDADE.

Não fazer ainda: P0-2 e P0-3 — fila imediata depois do merge de P0-1, mesmo cycle. Total ~7h.

---

## Fontes externas (todas validáveis pelo Wagner)

- [Shopify Polaris breakpoints](https://polaris-react.shopify.com/tokens/breakpoints)
- [Shopify POS UI extensions](https://shopify.dev/docs/api/pos-ui-extensions/latest/polaris-web-components)
- [Square POS redesign 2024](https://squareup.com/us/en/the-bottom-line/inside-square/square-pos-redesign)
- [Square Handheld](https://squareup.com/us/en/hardware/handheld)
- [Toast Go 3 handheld](https://pos.toasttab.com/hardware/toast-go)
- [Lightspeed X-Series Sell screen](https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534062778651-Using-the-Retail-POS-Sell-screen)
- [Linx Microvix Venda Fácil Mobile](https://share.linx.com.br/pages/viewpage.action?pageId=168647622)
- [Linx Moda e Acessórios (vestuário)](https://www.linx.com.br/moda-e-acessorios/)
- [Omie app mobile](https://www.omie.com.br/imprensa/omie-expande-software-erp-para-o-mobile/)
- [Vendizap Play Store](https://play.google.com/store/apps/details?id=br.com.vendizap&hl=en_US)
- [Bling ERP](https://www.bling.com.br/)
- [Mobile checkout UX 2026 (Corefy)](https://corefy.com/blog/mobile-checkout-ui)
- [Mobile-first UX 2026 (UXCam)](https://uxcam.com/blog/mobile-ux/)
- [Modern responsive breakpoints 2026 (RapidDocTools)](https://www.rapiddoctools.com/blog/modern-responsive-breakpoints-2026-guide)

---

**Append-only. Não duplicar findings W31 round 1** (Create.review.md cobre defer/types/biz; este doc cobre só responsividade).
