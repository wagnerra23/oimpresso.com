---
id: requisitos-vestuario-sdd-tela-etiqueta-tag-v1-0
slug: vestuario-sdd
title: "SDD — Etiqueta TAG térmica (domínio Vestuario)"
type: sdd
module: Vestuario
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-07-28"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CAPTERRA-FICHA.md
  - RUNBOOK-etiqueta-tag.md
  - SUPERFICIE.md
  - OBSERVABILITY.md
  - PII-LGPD.md
related_adrs:
  - 0066-format-date-shift-3h-preservado-legacy-clientes
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0180-sidebar-v3-5-grupos-ghosts-header
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0351-sdd-from-source
related_us:
  - US-VEST-001
  - US-VEST-005
  - US-VEST-020
  - US-VEST-021
---

# SDD — Software Design Document · Etiqueta TAG térmica (domínio Vestuario)

> **Escopo:** a família **etiquetagem** do vertical Vestuario — a tela
> `Vestuario/Etiquetas/Index` (`/vestuario/etiquetas`, US-VEST-020) **e** a tela legada
> `/labels/show` do núcleo UltimatePOS, que **continua viva e é a que a operadora usa hoje**.
> O SDD é do **MÓDULO/família**, nunca da tela ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.1) —
> as demais US-VEST-* ganham `§5.3 F<n>` e `§6 CU-VEST-NN` nesta mesma numeração quando forem
> documentadas.
>
> ⚠️ **Este módulo descreve sistema com DINHEIRO REAL.** ROTA LIVRE (`business_id=4`, Larissa,
> Termas do Gravatal/SC) roda vestuário em produção há 2+ anos e concentra **~99% do volume de
> vendas** do oimpresso novo. Nada aqui muda comportamento: este documento **descreve e contrata**.
>
> **Triangulação — 3 fontes de 4** (a 4ª declarada ausente, §0.1).

---

## 0. Base empírica

<!-- curado: foto que envelhece -->

### 0.1 As fontes que sustentam este SDD

| # | Fonte | Resolvida em | Estado |
|---|---|---|---|
| 1 | **Documentação canon** | [`SPEC.md`](SPEC.md) §US-VEST-020 · [`RUNBOOK-etiqueta-tag.md`](RUNBOOK-etiqueta-tag.md) · [`Index.charter.md`](../../../resources/js/Pages/Vestuario/Etiquetas/Index.charter.md) · [`BRIEFING.md`](BRIEFING.md) · [`Modules/Vestuario/SCOPE.md`](../../../Modules/Vestuario/SCOPE.md) | ✅ |
| 2 | **React/Laravel atual** | `Vestuario/Etiquetas/Index.tsx` → `EtiquetaTagController` → `EtiquetaTagService` → `VestuarioSettingsResolver` → `vestuario_settings` | ✅ |
| 3 | **Blade legada (a que o OPERADOR abre)** | `/labels/show` → `LabelsController@show` → `resources/views/labels/show.blade.php` (+ 3 partials + `public/js/labels.js`) | ✅ |
| 4 | **Delphi / Office Comercial** | — | ❌ **ausente** |

**Fonte 4 — gap declarado, não inventado.** `find memory -iname "*ANTI-REGRESSAO*"` devolve **2
arquivos, ambos do Produto** (`ANTI-REGRESSAO-cadastro-produto-legacy.md` e
`…-variacao-legacy.md`). O Vestuario **não tem** destilado do Office Comercial. Consequência
honesta: o **contrato de paridade deste domínio é mais fraco** — ele se apoia só na Blade viva
(fonte 3), que é do UltimatePOS, não do Delphi WR. Se um dia o manual WR Comercial descrever
etiquetagem de vestuário, os `[reg]` do §6 devem ser reconferidos contra ele.

**A armadilha da Blade homônima — resolvida, não assumida.** O `LabelsController` tem três
métodos (`show`, `addProductRow`, `preview`) e três views. A que a operadora abre é
`labels/show.blade.php`, e isso foi **verificado pelos dois caminhos de entrada da UI**, não pelo
nome do arquivo:

- **sidebar** — `App\Http\Middleware\AdminSidebarMenu` monta a entry `barcode.print_labels`
  apontando para `action([LabelsController::class, 'show'])`, sob a permissão `product.view`;
- **ação de lista** — `Compras/components/AcoesDropdown.tsx` e `Purchase/Index.tsx` abrem
  `/labels/show?purchase_id={id}` em aba nova ("Imprimir etiquetas" da compra).

`preview` não é tela: é o endpoint que o `labels.js` chama por AJAX e que faz `print_r` + `exit`
(ver §5.4). `addProductRow` devolve `<tr>` por AJAX.

### 0.2 O que o benchmark expôs

A [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) e o [`SPEC §4`](SPEC.md) registram o gap que originou a
US-VEST-020: os verticais de moda BR (**Linx Microvix**, **ProMoz**, **Mubisys**) imprimem
etiqueta com **TAM-COR-COLEÇÃO legível por humano** + código de barras + QR, enquanto a etiqueta
padrão UltimatePOS traz só SKU + nome + preço — e o balcão perde 5–10s por peça lendo barcode
pequeno. Foi classificado **P0** e é a origem da tela nova.

> ⚠️ **Traduzir premissa, não copiar solução** ([proibicoes §5](../../proibicoes.md) 2026-07-16):
> a premissa importada é *"a arara/o balcão precisa ler tamanho e cor sem bipar"*. Ela **vale
> aqui** porque o vestuário do piloto tem matriz tam×cor real (US-VEST-001/005), 15 variações por
> peça. O que **não** foi importado é o modelo de dados do concorrente.

### 0.3 A dobra que decide tudo neste domínio: a tela nova NÃO substituiu a antiga

O [`RUNBOOK-etiqueta-tag.md`](RUNBOOK-etiqueta-tag.md) §"Override `mwart-comparative` justificado"
diz textualmente: *"Tela nova standalone (**NÃO migração Blade existente**)"*. E o aviso de cutover
(§F5) promete ao cliente: *"Mantemos a etiqueta antiga em **Produtos → Imprimir Etiqueta** se
preferir."*

**Portanto as duas coexistem** — e isso muda a leitura do §5.4: as diferenças entre `/labels/show`
e `/vestuario/etiquetas` **não são regressões de migração**; são o recorte deliberado de uma tela
nova. Chamá-las de regressão seria inventar um anti-padrão que ninguém decidiu. O que elas **são**:
o **inventário do que um futuro cutover não pode perder em silêncio** — que é exatamente o vetor da
[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (MWART).

---

## 1. Visão geral

<!-- derivado: re-rodável do fonte -->

**O domínio.** Transformar uma peça de vestuário (produto + variação tam×cor) em **etiqueta física**
que (a) a leitora do balcão consiga bipar e (b) a cliente consiga ler sem bipar.

**A família tem 2 telas vivas, com donos e públicos diferentes:**

| Tela | Rota | Dono | Saída | Público |
|---|---|---|---|---|
| **Etiquetas TAG** (React) | `GET /vestuario/etiquetas` | `Modules/Vestuario` | **ZPL** (Argox/Zebra/Elgin) ou **PDF A4** grid 4×8 | vertical moda — TAG de peça com TAM/COR/COLEÇÃO |
| **Imprimir etiquetas** (Blade) | `GET /labels/show` | núcleo UltimatePOS | HTML → `window.print()` | qualquer vertical — gôndola/prateleira, folha de adesivos |

**Estado de adoção (recibo datado).** O [`BRIEFING.md`](BRIEFING.md) (atualizado 2026-07-18) declara
US-VEST-020 como *"código landed — cutover/validação pendente"*: o código existe com Pest, mas
**ROTA LIVRE ainda não cortou** para a tela nova. Logo, em 2026-07-28, a etiqueta que a Larissa
imprime de fato ainda é a da Blade. Este é um **fato datado**; para reconferir, veja o
`BRIEFING.md` (dono do estado de adoção) — não replique o número aqui.

**Ponto de entrada.** `Modules\Vestuario\Http\Controllers\DataController::modifyAdminMenu()` injeta
a entry "Vestuário" no grupo `vender` da sidebar ([ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)),
com um único ghost `Etiquetas`. O comentário do próprio método explicita a política: *"Demais
US-VEST-* do backlog entram como ghosts quando ganharem rota real — não declaradas aqui pra não
criar link morto."*

---

## 2. Público-alvo e personas

<!-- curado: foto que envelhece -->

| Persona | Quem é | O que precisa da etiqueta |
|---|---|---|
| **Larissa** (`larissa-04`, `Admin#4`) | dona/operadora da ROTA LIVRE, biz=4, monitor **1280px** | etiquetar a arara nova depois de receber a compra; ler TAM/COR sem bipar |
| **`rota.vendas-04`** (`Vendas#4`) | auxiliar de balcão | bipar a peça no POS (US-VEST-002) — consome o EAN-13 que esta tela imprime |
| **Wagner** (biz=1) | dono/superadmin | smoke, validação e configuração (`vestuario_settings`) |

**Régua de UI:** 1280px. A tela usa `grid-cols-12` numa linha por item — o charter fixa
*"Cabe em 1280px (ROTA LIVRE — monitor da Larissa)"* como `ux_target`.

**Sensibilidade de data ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)):**
o shift `+3h` do `format_date` é **preservado de propósito** para clientes legados. Nenhum CU deste
SDD toca data de negócio; o único carimbo temporal é o do cabeçalho do PDF, que usa
`now()->setTimezone('America/Sao_Paulo')` direto (não passa por `format_date`). Se um CU futuro
imprimir data na etiqueta, **o shift não é bug** — é contrato.

---

## 3. Governança aplicável (o Tier 0 que morde AQUI)

<!-- derivado: re-rodável do fonte -->

| Regra | Como aparece neste domínio | Onde |
|---|---|---|
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `VestuarioSetting` tem global scope `business_id` + `UNIQUE(business_id)`; o Controller deriva o biz da sessão web; a config de um business **nunca** resolve para outro | `VestuarioSetting::booted` · `EtiquetaTagController::businessIdFromSession` |
| **`withoutGlobalScopes` só com comentário** | `VestuarioSettingsResolver::loadSettings` usa `withoutGlobalScopes(['business_id'])` **com** o comentário `// SUPERADMIN: resolver pode rodar fora sessão (jobs/CLI)` e **re-aplica** o filtro no `where('business_id', $businessId)` logo abaixo | `VestuarioSettingsResolver::loadSettings` |
| **Teste biz=1, NUNCA biz=4** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) | todo teste do domínio usa biz=1 (Wagner) e biz=99 (adversário). biz=4 é PRODUÇÃO | `Modules/Vestuario/Tests/Feature/*` |
| **REGRA MESTRE valor/estoque** ([proibicoes](../../proibicoes.md)) | a geração de etiqueta é **read-only** — não move estoque nem grava preço. É o `CU-VEST-07`, marcado `[V0]` justamente para que qualquer mudança futura passe pela dupla-confirmação | §6 `CU-VEST-07` |
| **PII / LGPD** | a etiqueta carrega **produto**, nunca cliente. Sem CPF/CNPJ/nome de pessoa no ZPL, no PDF ou no log | [`PII-LGPD.md`](PII-LGPD.md) · `Log::info('vestuario.etiqueta.*')` loga só biz/contagem/bytes |
| **MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) | a tela tem RUNBOOK (F1) — mas ele **não estava declarado no charter**, e por isso ficava invisível para o hook e para o mapa (§9 D-3) | `RUNBOOK-etiqueta-tag.md` |
| **Habilitar módulo por business é UI, nunca código** | `superadmin_package()` expõe `vestuario_module`; `modifyAdminMenu()` consulta `hasThePermissionInSubscription` — **zero hardcode de `business_id`** | `DataController` |

---

## 4. Design system aplicável

<!-- derivado: re-rodável do fonte -->

- **Shell:** `AppShellV2` + `PageHeader` compartilhado (`EtiquetasIndex.layout`).
- **Primitivos:** só `@/Components/ui` (`Card`, `Badge`, `Button`, `Input`, `Label`) — zero
  hand-roll, zero `<select>` nativo (a tela não tem select).
- **Padrão de tela:** o charter declara `related_prototype: n/a (herda PT-01 Lista; segue o Padrão
  de Tela)` — não há protótipo Cowork, logo **não há e não haverá `proto-baseline`** para esta
  tela (é o caso "não-ancorável" descrito na [lápide §5 2026-07-17](../../proibicoes.md), não um gap).
- **Tokens de status:** `bg-success-soft`/`text-success-fg` no badge de QR ativo;
  `destructive-soft`/`destructive-fg` no bloco de erro.
- **Ícones:** `lucide-react` (`Printer`, `FileText`, `QrCode`, `Plus`, `Trash2`).

---

## 5. Arquitetura

<!-- derivado: re-rodável do fonte -->

### 5.1 Visão em camadas

```
Sidebar (DataController::modifyAdminMenu — grupo 'vender', ghost 'Etiquetas')
   │
   ▼
GET  /vestuario/etiquetas         → EtiquetaTagController@index
                                     └─ EtiquetaTagService::getPublicConfig(bizId)
                                          └─ VestuarioSettingsResolver::forBusiness(bizId)
                                               └─ VestuarioSetting (tabela vestuario_settings)
                                     └─ Inertia::render('Vestuario/Etiquetas/Index', {config})

POST /vestuario/etiquetas/lote/zpl → EtiquetaTagController@storeZpl
                                     └─ validate() → expandItems(items, copies)
                                     └─ EtiquetaTagService::gerarLote()
                                          └─ gerarEtiqueta() ×N → buildZpl()
                                     └─ response(text/plain, attachment .zpl)

POST /vestuario/etiquetas/lote/pdf → EtiquetaTagController@storePdf
                                     └─ validate() → expandItems(items, copies)
                                     └─ EtiquetaTagService::gerarEtiqueta() ×N  (só o 'meta')
                                     └─ Pdf::loadView('vestuario::etiquetas.pdf')
                                          └─ Milon\Barcode DNS1D (EAN-13) + DNS2D (QR), PNG base64
                                     └─ $pdf->download(.pdf)
```

Stack de middleware das 3 rotas (canônica UltimatePOS, declarada em `Modules/Vestuario/Routes/web.php`):
`['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin']`.

**Trilho legado, para comparação (fonte 3):**

```
Sidebar (AdminSidebarMenu, perm product.view) ─┐
Compras/Purchase "Imprimir etiquetas"  ────────┤
                                               ▼
GET /labels/show[?purchase_id|?product_id]  → LabelsController@show
    └─ TransactionUtil::getPurchaseProducts  (pré-carrega os itens da COMPRA)
    └─ ProductUtil::getDetailsFromProduct
    └─ SellingPriceGroup (grupos de preço ativos do business)
    └─ Barcode (folha de etiquetas configurável — tabela `barcodes`)
GET /labels/add-product-row  → linha nova por AJAX (busca por NOME)
GET /labels/preview          → ProductUtil::getVariationGroupPrice → HTML + window.print()
```

### 5.2 Modelo de dados (núcleo)

| Tabela | Papel neste domínio | `business_id` |
|---|---|---|
| `vestuario_settings` | **única tabela que este domínio possui.** `settings` JSON, chaves `etiqueta.{width_dots,height_dots,dpi,margin_dots,qr_enabled,qr_data_template}` | `unsignedInteger` **UNIQUE** + índice `idx_vestuario_settings_business`; global scope no Model |
| `products` / `variations` | **lidos por ninguém neste fluxo hoje** — o `product_id`/`variation_id` do payload é só carimbo no SKU derivado (§5.4 D-1) | — |
| `barcodes` (núcleo) | folha de etiquetas do trilho **legado** (`stickers_in_one_sheet`, `paper_width/height`, `is_continuous`) | `business_id` ou `NULL` (templates globais) |
| `selling_price_groups` (núcleo) | grupo de preço do trilho **legado** | `business_id` |

**Onde o EAN-13 mora:** em lugar nenhum. Ele é **derivado em runtime** do SKU
(`generateEan13FromSku`) ou informado pelo operador. Não há coluna de GTIN neste fluxo — ver
§5.4 D-2.

### 5.3 Fluxos críticos

**F1 — Abrir a tela** (`EtiquetaTagController@index`)
1. `authorizeAccess($request,'view')` — exige usuário autenticado (`abort(401)` se `null`);
   a checagem de permissão **só emite `Log::warning`**, não bloqueia (§5.4 D-4 / §9 D-1).
2. `businessIdFromSession()` = `session('user.business_id') ?? session('business.id')`.
3. `EtiquetaTagService::getPublicConfig($bizId)` → `resolveConfig()`:
   - sem resolver injetado **ou** `businessId === null` → `defaultConfig()` (400×240 @203dpi, margem 10, QR off);
   - com resolver → `getInt`/`getBool` com **clamps** (`width/height` 100–2000, `dpi` 100–600,
     `margin` 0–100). Valor fora da faixa **cai no default**, não estoura.
4. `getPublicConfig` **omite `qr_data_template`** de propósito (pode conter URL custom do cliente).
5. `Inertia::render('Vestuario/Etiquetas/Index', ['config' => …])`.

**F2 — Gerar lote ZPL** (`EtiquetaTagController@storeZpl`)
1. `authorizeAccess(…,'create')`.
2. `validate()`: `items` obrigatório, 1–**500**; `copies` 1–**100**; `ean13` `size:13`;
   `preco` `numeric|min:0|max:99999`; strings com `max` por campo.
3. `expandItems($items, $copies)` — **multiplica**: N itens × C cópias = N×C entradas planas.
4. `EtiquetaTagService::gerarLote()` → para cada item `gerarEtiqueta()`:
   - defaults por campo (`tamanho='U'`, `cor='-'`, `sku=sprintf('%06d%05d', productId, variationId)`);
   - EAN-13: `normalizeEan13()` se veio, senão `generateEan13FromSku()`;
   - `truncate()`: nome 30, cor 20, coleção 25 (mb-safe, sufixo `…`);
   - `buildZpl()`: `^XA … ^XZ`, `^PW`/`^LL` da config, `^CI28` (UTF-8), `^BEN` (EAN-13),
     `^BQN` só se `qr_enabled`, `^FD SKU` no rodapé;
   - `Log::info('vestuario.etiqueta.gerada', …)` **por etiqueta**.
5. `Log::info('vestuario.etiqueta.lote.zpl', {business_id, items_count, bytes})`.
6. Resposta `text/plain; charset=utf-8` + `Content-Disposition: attachment` — o browser baixa
   `etiquetas-YmdHis.zpl`, que o operador envia por TCP/USB à impressora.

**F3 — Gerar PDF A4** (`EtiquetaTagController@storePdf`)
1. Mesma validação e mesma expansão de F2.
2. Para cada item chama `gerarEtiqueta()` e **descarta o ZPL**, usando só `meta` + `ean13` + `sku`
   (assim o PDF herda exatamente o mesmo EAN-13 e a mesma truncagem do ZPL — é a garantia de que
   os dois caminhos não divergem).
3. `Pdf::loadView('vestuario::etiquetas.pdf')` A4 retrato → grid `array_chunk($etiquetas, 4)`,
   célula 25% × 30mm, **32 por folha**; EAN-13 e QR viram PNG **inline base64** (`Milon\Barcode`) —
   zero chamada de rede no render.
4. `$pdf->download('etiquetas-YmdHis.pdf')`.

**F4 — Resolver a configuração por business** (`VestuarioSettingsResolver`)
1. `forBusiness($id)` **clona** o resolver (não muta o singleton) — seguro em job/CLI.
2. `loadSettings()` = `Cache::remember("vestuario.settings.{$biz}", 300s, …)`.
3. A query usa `withoutGlobalScopes(['business_id'])` **e** re-aplica `where('business_id', $biz)`:
   o escopo é explícito, não implícito na sessão.
4. `try/catch (\Throwable) → []` — **tabela ausente devolve defaults em vez de estourar**. É o que
   permite a lane sqlite rodar os testes de lógica sem migrar o schema.

**F5 — Ponto de entrada e visibilidade** (`DataController`)
1. superadmin → `isModuleInstalled('Vestuario')`; demais → `hasThePermissionInSubscription($biz,
   'vestuario_module', 'superadmin_package')`.
2. E ainda: `superadmin` **ou** `vestuario.access` **ou** `vestuario.etiqueta.view`.
3. Só então `Menu::modify` injeta a entry (`order(35)`, grupo `vender`, atalho `G V`).

**F6 — Trilho legado `/labels/show`** (fonte 3, para o contrato de paridade do §5.4)
1. `show()` pré-carrega os produtos quando vem `?purchase_id=` (itens da compra) ou `?product_id=`.
2. Sem parâmetro, a tela abre **vazia** e o operador **busca produto por NOME**
   (`#search_product_for_label` → `/labels/add-product-row`).
3. Escolhe grupo de preço (`SellingPriceGroup`), liga/desliga **cada campo** impresso (nome,
   variações, preço, nome do negócio, data de embalagem, lote, validade + **tamanho de fonte** de
   cada) e escolhe a folha (`Barcode`).
4. `preview()` recalcula o preço pelo grupo (`ProductUtil::getVariationGroupPrice`), pagina por
   `stickers_in_one_sheet` e devolve HTML + `window.print()`.

### 5.4 Onde os dois mundos ainda não se conversam

> **Leia com o §0.3:** as duas telas **coexistem por decisão registrada**. As linhas abaixo são o
> **inventário do que um cutover não pode perder em silêncio** — não são regressões consumadas.

| # | Capacidade | `/labels/show` (Blade) | `/vestuario/etiquetas` (React) | Leitura |
|---|---|---|---|---|
| **D-1** | **Achar a peça** | busca por **nome**, com autocomplete | operador **digita o `product_id` numérico** à mão | ⛔ o maior buraco de usabilidade da tela nova. A Larissa não sabe IDs |
| **D-2** | **Ler os dados da peça** | resolve nome/variação/preço **do banco** | **nada é lido do banco** — nome, TAM, COR, coleção, preço e SKU são digitados | consequência de D-1; o EAN-13 sai de um SKU digitado, não do `sub_sku` real da variação |
| **D-3** | **Pré-carga pela compra** | `?purchase_id=` traz os itens recebidos | ausente | é o fluxo real da loja: recebeu a compra → etiqueta a arara |
| **D-4** | **Preço** | vem do **grupo de preço**, com opção inc./exc. de imposto | digitado à mão | `[V0]` — divergência que toca VALOR (§6 `CU-VEST-07` item 2) |
| **D-5** | **O que sai impresso** | ~8 toggles + tamanho de fonte por campo | layout **fixo** | escolha consciente (etiqueta TAG tem 1 formato) |
| **D-6** | **Folha / mídia** | tabela `barcodes` (folha, contínuo, margens, adesivos/folha) | `vestuario_settings.etiqueta.*` (dots/dpi) + PDF **4×8 fixo** | **dois vocabulários para o mesmo assunto** — dívida real |
| **D-7** | **Lote / validade / embalagem** | condicional aos flags do business | ausente | não se aplica a vestuário; entra só se algum cliente pedir ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |
| **D-8** | **Nome do negócio** | opcional na etiqueta | ausente | |
| **D-9** | **Pré-visualizar** | preview HTML antes de imprimir | **baixa o arquivo direto** | ⚠️ o charter promete *"Preview/edição de itens antes de imprimir (evita desperdício)"* — há **edição**, não há **preview** (§9 D-2) |
| **D-10** | **TAM/COR/COLEÇÃO destacados** | só como texto da variação | **campos dedicados no layout** | ✅ React à frente — é a razão de existir da US-VEST-020 |
| **D-11** | **QR Code** | ausente | opcional por business | ✅ React à frente |
| **D-12** | **ZPL térmico** | ausente (HTML → `window.print()`) | ✅ nativo Argox/Zebra/Elgin | ✅ React à frente |

**Dívida do próprio trilho legado (achada na leitura da fonte 3, fora do escopo deste chip):**
`LabelsController::preview()` faz `print_r($output)` + `exit` no meio do controller e devolve
**200 com corpo vazio** quando cai no `catch` (a variável `$output` é atribuída e nunca retornada).
Reportado no session log; **não corrigido aqui** — é arquivo do núcleo, fora da área do chip.

---

## 6. Casos de uso

<!-- derivado: re-rodável do fonte -->

**Marcadores:** `[must]`/`[should]` prioridade · `[T0]` invariante multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) ·
`[V0]` REGRA MESTRE valor/estoque · `[reg]` paridade com o legado.

**Estado:** `✅` provado por teste verde que o cita · `🟡` parcial · `🔴` falso/quebrado ·
`⬜` **não-verificado** (nenhum teste o cita) · `🧪` teste existe e cita, veredito pendente da lane.

> Nenhum `✅` aparece abaixo: **este PR não executou teste algum** (CT 100/CI —
> [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)). Status vem da lane.

### 6.1 Casos de uso — etiquetagem

#### CU-VEST-01 — Abrir a tela com a configuração do próprio business `[must]` `[T0]` 🧪

*Dado* um usuário autenticado de um business com o módulo Vestuário habilitado; *quando* ele abre
`/vestuario/etiquetas`; *então* a tela renderiza com a configuração de etiqueta **daquele** business.

1. `[must]` a rota devolve o componente `Vestuario/Etiquetas/Index` com a prop `config`.
2. `[must]` `config` expõe exatamente `{width_dots, height_dots, dpi, margin_dots, qr_enabled}` e
   **não** expõe `qr_data_template` (pode conter URL custom do cliente).
3. `[T0]` a config resolvida é a do business da sessão; um business **sem** linha em
   `vestuario_settings` recebe os **defaults**, nunca a configuração de outro.
4. `[must]` valor fora da faixa (`width` > 2000, `dpi` < 100…) **cai no default** em vez de gerar
   ZPL inválido.
5. `[must]` sem sessão autenticada a rota não renderiza (redireciona/401).

*Fontes:* SPEC US-VEST-020 (*"Configurável por business"*) · RUNBOOK §Settings configurable ·
charter §Goals · `EtiquetaTagController@index`.

#### CU-VEST-02 — Gerar o lote ZPL que a impressora térmica aceita `[must]` 🧪

*Dado* uma lista de itens montada na tela; *quando* o operador pede o ZPL; *então* recebe um
arquivo com **uma etiqueta bem-formada por item**, na ordem submetida.

1. `[must]` N itens produzem N etiquetas — um par `^XA`/`^XZ` cada.
2. `[must]` cada etiqueta carrega nome, `TAM:`, `COR:`, coleção, **preço formatado em pt-BR**
   (vírgula decimal, ponto de milhar), código de barras (`^BEN`) e o SKU.
3. `[must]` lote **vazio é rejeitado** (`InvalidArgumentException`) — não gera arquivo em branco
   que a impressora consumiria como papel.
4. `[must]` `copies = C` multiplica: N itens × C cópias = **N×C** etiquetas. ⬜ *(sem teste — ver
   `[BACKLOG]` do `casos.md`: a expansão vive em método privado do Controller e exige request
   autenticada, que a lane sqlite não sobe)*
5. `[must]` o arquivo desce como anexo `text/plain` — o navegador **não** tenta renderizar ZPL.

*Fontes:* SPEC US-VEST-020 (*"Geração lote: selecionar produto + variação → imprime N etiquetas"*) ·
RUNBOOK §Acceptance criteria · `EtiquetaTagService::gerarLote`.

#### CU-VEST-03 — Código de barras que a leitora do balcão aceita `[must]` 🧪

*Dado* uma peça com ou sem GTIN próprio; *quando* a etiqueta é gerada; *então* o EAN-13 impresso é
**sempre válido pelo dígito verificador GS1** — senão a leitora do POS (US-VEST-002) recusa e a
peça não vende.

1. `[must]` todo EAN-13 emitido tem 13 dígitos e check digit GS1 mod-10 correto.
2. `[must]` EAN-13 informado pelo operador com **12** dígitos ganha o check; com **13** é
   validado e **rejeitado** se o check estiver errado (não se imprime barcode falso).
3. `[must]` SKU **sem nenhum dígito** ainda produz EAN-13 válido (fallback determinístico por CRC32).
4. `[should]` o EAN derivado usa prefixo **789** (GS1 Brasil) — uso interno de loja; cliente com
   GTIN oficial informa o próprio (§9 R-1).

*Fontes:* SPEC US-VEST-020 (*"código barras"*) · RUNBOOK §Acceptance criteria ·
`EtiquetaTagService::generateEan13` / `normalizeEan13` / `generateEan13FromSku`.

#### CU-VEST-04 — Etiqueta cabe fisicamente na mídia de 50×30mm `[must]` 🧪

*Dado* um produto de nome longo e acentuado ("Camiseta Básica Algodão Pima Coleção Verão 2026");
*quando* a etiqueta é gerada; *então* o texto **não estoura** a etiqueta física e sai legível na
térmica.

1. `[must]` nome > 30 chars é truncado com reticência; cor > 20 e coleção > 25 idem — a etiqueta
   nunca recebe o texto inteiro.
2. `[must]` o ZPL declara `^CI28` (UTF-8): "Verão"/"Básica" saem com acento na térmica em vez de
   virar lixo.
3. `[must]` `^PW`/`^LL` seguem a config do business (default `^PW400`/`^LL240` = 50×30mm @203dpi).
4. `[should]` truncagem é **mb-safe** — não parte caractere multibyte ao meio.

*Fontes:* RUNBOOK §"Layout ZPL térmico 50×30mm @203dpi (Argox/Zebra)" · SPEC US-VEST-020
(*"Layout etiqueta térmica … com campos"*) · `EtiquetaTagService::truncate` / `buildZpl`.

#### CU-VEST-05 — QR Code opcional, ligado por business `[should]` `[T0]` 🧪

*Dado* um cliente que quer QR de consulta na etiqueta; *quando* liga `etiqueta.qr_enabled`;
*então* o ZPL passa a conter o QR — e **só o dele**.

1. `[should]` com `qr_enabled = true`, o ZPL contém `^BQN` e o payload `^FDLA,…`.
2. `[must]` o **default é sem QR** — quem não configurou nada não ganha QR e não perde espaço na
   etiqueta.
3. `[T0]` `qr_enabled` de um business **não vaza** para outro.

*Fontes:* RUNBOOK §"QR Code opcional no ZPL (instrução `^BQ`)" · SPEC US-VEST-020 (*"QR pra
consulta estoque"*) · charter §Goals · `EtiquetaTagService::buildZpl`.

#### CU-VEST-06 — Fallback PDF quando não há impressora térmica `[must]` 🧪

*Dado* uma loja sem Argox/Zebra; *quando* o operador escolhe PDF; *então* recebe uma folha A4 com
as **mesmas** etiquetas que sairiam na térmica.

1. `[must]` N itens viram N etiquetas no documento, em grid **4×8 (32 por folha)**.
2. `[must]` o PDF carrega os mesmos campos do ZPL (nome, TAM, COR, coleção, preço pt-BR, EAN-13, SKU).
3. `[must]` EAN-13 e QR são **imagem inline base64** — o PDF renderiza sem rede.
4. `[must]` o PDF herda o EAN-13 e a truncagem calculados pelo **mesmo** `gerarEtiqueta()` do ZPL —
   os dois caminhos não podem divergir.

*Fontes:* SPEC US-VEST-020 (*"Test Pest: gera PDF com 10 etiquetas, valida campos presentes"*) ·
RUNBOOK §"PDF fallback (DomPDF + milon/barcode)" · `EtiquetaTagController@storePdf` ·
`vestuario::etiquetas.pdf`.

#### CU-VEST-07 — Gerar etiqueta NÃO altera valor nem estoque `[must]` `[V0]` `[reg]` 🧪

*Dado* qualquer lote; *quando* o operador gera ZPL ou PDF; *então* **nada é gravado**: etiqueta é
saída, não movimento.

1. `[must]` `[V0]` a geração é **read-only** — nenhum `INSERT`/`UPDATE`/`DELETE` é emitido.
2. `[must]` `[V0]` o preço impresso é **o que o operador informou**; a tela não lê nem grava
   `default_sell_price`/`sell_price_inc_tax` da variação.
3. `[reg]` isso **diverge conscientemente** do legado (§5.4 D-4), que resolve preço por
   `price_group_id`. A divergência é o **motivo** de este CU ser `[V0]`: no dia em que a tela nova
   passar a ler preço do banco, a mudança entra pela REGRA MESTRE (dupla-confirmação +
   antes→depois), não de carona.
4. `[must]` `GET /vestuario/etiquetas` não grava nada (anti-hook do charter).

*Fontes:* charter §Non-Goals (*"NÃO altera preço/estoque do item"*) + §Anti-hooks (*"NÃO altera
estoque/valor do produto ao gerar etiqueta"*, *"NÃO grava nada em GET"*) ·
[proibicoes §REGRA MESTRE](../../proibicoes.md) · `EtiquetaTagController` (nenhuma escrita).

#### CU-VEST-08 — Acesso é por business habilitado + permissão `[must]` `[T0]` 🟡

*Dado* um business sem o módulo no pacote; *quando* o usuário navega; *então* não há entry de
sidebar nem acesso.

1. `[must]` `[T0]` a entry só aparece com `vestuario_module` no pacote do business
   (`hasThePermissionInSubscription`) — **zero hardcode de `business_id`**.
2. `[must]` e ainda exige `superadmin` **ou** `vestuario.access` **ou** `vestuario.etiqueta.view`.
3. `[must]` as 3 rotas exigem sessão autenticada (`abort(401)` / redirect).
4. 🔴 **os endpoints POST NÃO bloqueiam por permissão.** `authorizeAccess()` faz
   `if (! $user->can($perm))` e apenas `Log::warning('vestuario.etiqueta.permission_check_missing')`
   — segue o fluxo. O código declara a razão (*"Permission pode não estar seedada ainda (Sprint 1)
   … Sprint 3 vira hard-block"*), mas **o charter e o RUNBOOK anunciam as perms sem essa ressalva**
   (§9 D-1). *Reconciliado neste PR do lado do charter; ligar o hard-block é decisão de [W].*

*Fontes:* `DataController::superadmin_package` / `user_permissions` / `modifyAdminMenu` ·
RUNBOOK §Rotas (coluna Permission) · charter §nota de backend ·
[proibicoes §Multi-tenant Tier 0](../../proibicoes.md) (3 camadas de habilitação).

### 6.2 Casos de uso do domínio ainda sem tela (fora deste chip)

`CU-VEST-09+` ficam reservados para **US-VEST-021** (devolução CDC / crédito-ficha). O código já
existe (`DevolucaoService`, `vestuario_devolucoes`, `vestuario_creditos_cliente`) e é `[V0]`
(reintegra estoque), mas **não tem tela nem rota HTTP** — logo não tem charter nem `casos.md`, e
documentá-lo aqui sem contrato de tela seria abrir CU órfão. Ver §9 R-3.

### 6.3 Fora de escopo deste SDD

US-VEST-001..009 (capacidades servidas pelo **núcleo** UltimatePOS: variação, POS, estoque, compra,
AR/AP, invoice schemes) e US-VEST-022..030 (backlog sem código). Elas ganham CU quando o módulo as
encapsular — hoje seriam CU sem dono neste domínio.

### 6.4 Non-Goals — **só [W] preenche**

O agente é **proibido de inferir** Non-Goal ([proibicoes §5](../../proibicoes.md) 2026-07-16). Os
que existem hoje estão em [`Index.charter.md`](../../../resources/js/Pages/Vestuario/Etiquetas/Index.charter.md)
§Non-Goals e §Anti-hooks, e o próprio charter está `status: draft` com a pendência aberta:

> *"[ ] Wagner aprova Non-Goals + Anti-hooks"*

Enquanto essa caixa não for marcada, os Non-Goals da tela são **propostos, não ratificados** — e é
por isso que o `CU-VEST-07` cita o charter como fonte de contrato mas **não** o trata como lei
fechada. **Pendente de [W].**

---

## 7. Requisitos não-funcionais

<!-- derivado: re-rodável do fonte -->

| NFR | Alvo | Origem | Como se mede |
|---|---|---|---|
| Latência da tela | **p95 < 1500ms** | charter §UX targets | 1 leitura cacheada (5min) + render Inertia; sem prop cara → **não precisa de `Inertia::defer`** |
| Largura | **cabe em 1280px** | charter §UX targets · perfil da Larissa | `grid-cols-12` numa linha por item |
| Teto de lote | 500 itens × 100 cópias | `EtiquetaTagController::storeZpl` validate | risco: **50.000** etiquetas num POST (§9 R-2) |
| Render offline | PDF sem chamada de rede | `pdf.blade.php` (PNG base64) | |
| Observabilidade | `vestuario.etiqueta.gerada` (por etiqueta) · `.lote.zpl` · `.lote.pdf` · `.permission_check_missing` | `Log::info`/`Log::warning` · [`OBSERVABILITY.md`](OBSERVABILITY.md) | ⚠️ log **por etiqueta** num lote de 50k = 50k linhas (§9 R-2) |
| Tracing | `vestuario.settings.get` via `OtelHelper::spanBiz` | `VestuarioSettingsResolver` | |
| PII | zero dado de pessoa na etiqueta/log | [`PII-LGPD.md`](PII-LGPD.md) | log carrega biz/contagem/bytes |
| Cache | settings 5min por business | `VestuarioSettingsResolver::CACHE_TTL` | invalidado em `set()`/`refresh()` |

---

## 8. Estratégia de qualidade e rollout

<!-- derivado: re-rodável do fonte -->

**Onde o contrato vive:** [`Index.casos.md`](../../../resources/js/Pages/Vestuario/Etiquetas/Index.casos.md)
(UC derivados do §6 deste SDD, **nunca** do `.tsx`).

**Lane e força do veredito — medido em [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json), não deduzido:**

| Gate | Roda o quê | Força |
|---|---|---|
| `Modules Pest` → job `Pest Vestuario` (`.github/workflows/modules-pest.yml`, matrix) | `vendor/bin/pest Modules/Vestuario/Tests` — **diretório inteiro**, não allowlist | **ADVISORY** — reprova visível, **não bloqueia merge** |
| `Casos-coverage · ratchet (trio + rastreabilidade)` | G-1 trio · G-2 UC↔teste · G-5/G-7 metadata e status | **REQUIRED** |
| `anchor-lint ADR 0273` · `anchor entry/covers gate` | US↔código↔teste | **REQUIRED** |
| full suite / nightly CT 100 | `phpunit.xml` inclui `./Modules/Vestuario/Tests/Feature` | — |

> A lane compartilha o workflow com **Arquivos, ComunicacaoVisual, Fiscal, NfeBrasil e Repair**.
> Como ela roda o **diretório**, um arquivo de teste novo entra sozinho: **nenhuma edição de YAML
> foi feita neste chip** (e o chip está proibido de fazê-la).

**Restrição da lane que muda o desenho do teste:** o job roda **sqlite `:memory:` sem migrar**
(o cabeçalho do workflow explica: as migrations UltimatePOS são MySQL-only). Logo:

- teste que depende de tabela **pula** — e teste que pula **não prova nada** (é o verde por
  não-execução da [lápide §5 2026-07-24](../../proibicoes.md));
- por isso os UC novos deste chip foram desenhados **pure-logic**, com **controle-positivo** e
  **guarda anti-vácuo** explícitos: o teste prova que a operação aconteceu antes de afirmar o que
  ela não fez.

**Rollout (MWART F5).** Cutover para ROTA LIVRE **ainda não ocorreu** (§1). O RUNBOOK já traz o
texto de aviso ao cliente e mantém a tela antiga disponível — coexistência, não corte.

---

## 9. Riscos e dívidas conhecidas

<!-- curado: foto que envelhece -->

| # | Risco / dívida | Evidência | Gravidade |
|---|---|---|---|
| **D-1** | **Charter e RUNBOOK anunciam permissão que o código não aplica.** O RUNBOOK tabela `Permission: vestuario.etiqueta.view/create` e o charter repete; `authorizeAccess()` só loga | `EtiquetaTagController::authorizeAccess` | 🔴 alta — artefato afirmando enforcement que não existe ([proibicoes §5](../../proibicoes.md) 2026-07-16). Reconciliado no charter neste PR; **ligar o hard-block é decisão de [W]** |
| **D-2** | **Promessa de charter não cumprida:** §UX targets promete *"Preview/edição de itens antes de imprimir (evita desperdício de etiqueta)"*. Há **edição**; **não há preview** — o clique baixa o arquivo | `Index.tsx::submit` · §5.4 D-9 | 🟡 **divergência aberta, registrada nos dois lados, não resolvida** — podar a promessa ou construir o preview é decisão de produto ([W]) |
| **D-3** | **RUNBOOK invisível para as máquinas.** `RUNBOOK-etiqueta-tag.md` existe, mas o nome não casa a convenção `RUNBOOK-<tela-kebab>.md` (`etiquetas`/`index`) e o charter não declarava `related_runbook` → `screen:files` acusava `RUNBOOK ✗ ausente` e o hook MWART bloquearia editar o `.tsx` | `npm run screen:files -- Vestuario/Etiquetas/Index` | ✅ **corrigido neste PR** (declaração no charter, sem renomear o arquivo — renomear quebraria backlinks) |
| **D-4** | **Operador precisa saber o `product_id` numérico** (§5.4 D-1/D-2) | `Index.tsx` campo "Produto ID" | 🔴 alta para adoção — é a razão mais provável de a tela não ter sido cortada ainda |
| **R-1** | EAN-13 derivado usa prefixo **789** (GS1 Brasil) sem registro GS1 | `generateEan13FromSku` docblock (assume e declara: uso interno de loja) | 🟡 aceito conscientemente; vira risco se a peça for revendida |
| **R-2** | **Teto de 500 × 100 = 50.000 etiquetas por POST**, com `Log::info` **por etiqueta** e PDF de ~1.560 folhas | `storeZpl`/`storePdf` validate + `gerarEtiqueta` | 🟡 sem timeout/limite de bytes declarado; nenhum incidente registrado |
| **R-3** | **US-VEST-021 (devolução) tem código `[V0]` sem tela, sem rota e sem contrato de UC** — e **convive com um homônimo do núcleo** (`App\Services\DevolucaoService`, consumido por `App\Http\Controllers\DevolucaoController`) | `tests/Feature/Estoque/EstoqueDevolucaoVendaTest.php` documenta o par como *"caminho PARALELO"* | 🟡 fora deste chip; **precisa de chip próprio** (§6.2) |
| **R-4** | **SPEC declarava `status: todo`** para US-VEST-020/021 enquanto o código, os testes e o BRIEFING diziam entregue | `requisitos-status.mjs Vestuario` listava as duas como backlog | ✅ **US-VEST-020 reconciliada neste PR**; US-VEST-021 fica para o chip dela |
| **R-5** | **Dois vocabulários de mídia de etiqueta** no mesmo produto: `barcodes` (núcleo) × `vestuario_settings.etiqueta.*` (vertical) | §5.4 D-6 | 🟡 dívida de domínio; unificar é decisão de [W] |

---

## 10. Roadmap de evolução

<!-- curado: foto que envelhece — [W] prioriza -->

| Trilha | Próximo passo | Destrava | Fonte |
|---|---|---|---|
| **Adoção** | fechar **D-4** (buscar peça por nome/SKU em vez de `product_id`) | o cutover da ROTA LIVRE — sem isso a tela não é usável pela Larissa | §5.4 D-1/D-2 |
| **Adoção** | pré-carga por `?purchase_id=` (paridade D-3) | etiquetar a arara logo após receber a compra | §5.4 D-3 |
| **Governança** | [W] aprova Non-Goals/Anti-hooks → charter `draft → live` | Pest GUARD dos Non-Goals | charter §Pendências |
| **Segurança** | ligar o hard-block de `vestuario.etiqueta.*` (D-1) | fecha a lacuna entre doc e código | §6 `CU-VEST-08` item 4 |
| **Produto** | decidir **D-2** (preview × podar a promessa) | — | §9 D-2 |
| **Domínio** | chip próprio de **US-VEST-021** (tela + charter + casos + CU-VEST-09+) | §6.2 | §9 R-3 |
| **Backlog** | US-VEST-029 (estação) → 023 (liquidação) → 022 (comissão) | roadmap do [`BRIEFING.md`](BRIEFING.md) | SPEC §8 |

---

## 11. Referências

- [`SPEC.md`](SPEC.md) — US-VEST-001..030
- [`BRIEFING.md`](BRIEFING.md) — estado consolidado (dono do estado de adoção)
- [`RUNBOOK-etiqueta-tag.md`](RUNBOOK-etiqueta-tag.md) — MWART F1..F5 da tela
- [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) — benchmark vs Linx/ProMoz/Mubisys
- [`SUPERFICIE.md`](SUPERFICIE.md) · [`OBSERVABILITY.md`](OBSERVABILITY.md) · [`PII-LGPD.md`](PII-LGPD.md)
- [`Index.charter.md`](../../../resources/js/Pages/Vestuario/Etiquetas/Index.charter.md) (lei) ·
  [`Index.casos.md`](../../../resources/js/Pages/Vestuario/Etiquetas/Index.casos.md) (contrato)
- [`Modules/Vestuario/SCOPE.md`](../../../Modules/Vestuario/SCOPE.md)
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) (mãe do vertical) ·
  [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) ·
  [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) ·
  [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) ·
  [0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) ·
  [0351](../../decisions/0351-sdd-from-source.md)
- [passo-5-sdd-por-modulo.md](../_Governanca/programa-ondas/passo-5-sdd-por-modulo.md) — o programa

---

## Changelog

| Versão | Data | O quê |
|---|---|---|
| 1.0.0 | 2026-07-28 | Nascimento. Chip da Onda 4 do passo 5, agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md). Triangulação de 3 fontes (fonte 4 Delphi **ausente**, §0.1). §5.3 F1–F6 · §6 CU-VEST-01..08 · §5.4 com 12 linhas de paridade Blade×React lidas como **coexistência**, não regressão (§0.3). |
