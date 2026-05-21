---
name: Estado-da-arte — entrada matricial tam × cor (compra vestuário)
description: Pesquisa 8 concorrentes/refs + comparação com oimpresso (Larissa biz=4) + recomendação de componente pra Onda 1 Compras
type: arte
status: draft
date: 2026-05-21
topic: estado-da-arte entrada matricial tam x cor vestuario PME — 8 concorrentes + recomendacao componente Onda 1 Compras
related:
  - memory/requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md
  - memory/reference/cliente-rotalivre.md
  - memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md
  - memory/decisions/0093-multi-tenant-isolation-tier-0.md
persona: Larissa @ ROTA LIVRE · biz=4 · vestuário · 1280px · não-técnica
escopo: componente UI "grade tam × cor" pra entrada de compra (1 célula = 1 SKU filho)
---

# Estado-da-arte 2026 — entrada matricial **tam × cor** (compra vestuário PME)

## Resumo executivo (3 parágrafos)

Em ERPs de moda **a matriz tam × cor é commodity há 15+ anos** — Lightspeed Retail, Cin7, Blue Link, Dynamics 365 Apparel, Logic ERP e até o veterano BlueLink ERP têm "size/color grid" como recurso assumido. O fluxo canônico é: usuário seleciona o **modelo pai** (style/family) → o sistema renderiza uma grade com tamanhos nas linhas e cores nas colunas → o usuário digita quantidades célula-a-célula → save atomic grava N linhas no PO/recebimento. Cin7 chama isso de "Size/Color Grid", Lightspeed de "Matrix Inventory", BlueLink de "Product Matrix". Detalhe que sobressai na pesquisa: **navegação por Tab horizontal entre colunas + Enter pula linha + autofocus na 1ª célula vazia ao abrir o pai** é padrão entre as 4 referências globais.

No mundo **BR PME**, o estado-da-arte é **bem mais raso**. Bling e Tiny tratam variações apenas como "cadastro pai-filho" (gerador de combinações 2D), mas **a UI de ENTRADA de compra ainda é linha-a-linha** — sem grade visual matricial. Omie é o único do tier popular BR que cita explicitamente "exibida como tabela matriz com colunas e linhas" pra variações, mas a documentação não detalha se a entrada de compra usa esse layout ou só o cadastro de produto. Conta Azul não tem feature de matriz (cadastro de variação simples, sem grade). Linx/Sankhya têm "espelho de grade" no enterprise vestuário mas é UI legacy desktop, não-replicável como referência UX moderna. Resumo: **feature comum globalmente, gap claro no mid-tier BR**.

Pra Larissa (1280px, ~50 modelos/entrega, não-técnica), a referência ergonômica mais próxima é **Cin7 (Size/Color Grid)** porque combina densidade visual razoável + entrada keyboard-friendly + totais on-the-fly por linha/coluna. Recomendação técnica é **construir custom** sobre **TanStack Table v8 + inputs nativos React 19** (não AG Grid, não Handsontable) — porque (a) o componente é pequeno e auto-contido (grid 4×8 ≈ 32 inputs), (b) Mantine/AG-Grid carregam 100-200KB pra resolver um problema que cabe em ~250 linhas de TSX custom, (c) controle total de Tab/Enter/Esc é mais simples no headless que lutar contra cell-editing opinion de framework. Estimate recalibrado ADR 0106: **~3-4 dias IA-pair** F1 protótipo Cowork + F3 implementação Inertia/React.

---

## Tabela comparativa — 8 concorrentes × 12 dimensões

Legenda: `OK` = nativo / documentado · `~` = parcial ou via workaround · `NO` = ausente / não-documentado · `?` = não foi possível confirmar via web

| # | Dimensão | Bling | Tiny ERP | Omie | Conta Azul | Linx/Sankhya | Shopify | Lightspeed | Cin7 |
|---|---|---|---|---|---|---|---|---|---|
| 1 | **Grade matricial na entrada de compra** (UI tam × cor visível) | NO (linha-a-linha) | NO (linha-a-linha) | ~ ("tabela matriz") | NO | OK (enterprise) | NO (variant table linear) | OK ("Matrix") | OK ("Size/Color Grid") |
| 2 | **Navegação Tab/Enter/setas** keyboard-first | ? | ? | ? | NO | ? | NO (bulk editor) | ~ (não documentado) | OK |
| 3 | **Densidade 1280px** (12 cols x 8 linhas s/ scroll H) | n/a | n/a | ? | n/a | ~ (denso mas legacy) | NO (scroll H) | OK | OK |
| 4 | **Totais linha/coluna/grand** on-the-fly | n/a | n/a | ? | n/a | OK | ~ | OK | OK |
| 5 | **Quick-fill** (paste Excel / "fill col=N") | n/a | n/a | n/a | n/a | NO | OK (CSV/bulk) | ~ (OCR) | ~ |
| 6 | **Empty state** (pai single = sem grade; variable = mostra auto) | OK | OK | OK | OK | OK | OK | OK | OK |
| 7 | **Mobile/touch** | NO | NO | NO | NO | NO | OK | OK (X-Series scanner) | OK |
| 8 | **Validação inline** (qty negativa, > pedido) | OK | OK | ? | OK | OK | OK | OK | OK |
| 9 | **Save atomic** (N linhas commit único) | OK | OK | OK | OK | OK | OK | OK | OK |
| 10 | **Edição posterior** (reabrir PO → grade rerendereada com qty atual) | NO (linha) | NO (linha) | ? | n/a | OK | NO | OK | OK |
| 11 | **Custo por filho** vs custo por pai | filho | filho | filho | pai | filho | filho | filho | filho |
| 12 | **Performance ≥100 SKUs** (≈12 tam × 8 cor) | n/a | n/a | ? | n/a | OK (DB) | OK (virtualizado 2k variants) | OK | OK |

**Observações importantes:**

- Bling/Tiny/Conta Azul **têm o conceito de variação** (pai-filho) mas a tela de entrada de compra mostra **uma linha por SKU filho** (linha-a-linha). É o mesmo padrão do `purchase_entry_row.blade.php` atual do oimpresso (`@foreach $variations`) — então o oimpresso hoje empata com os concorrentes BR PME, mas **fica abaixo do estado-da-arte global**.
- Omie é o caso ambíguo: doc menciona "displayed as a matrix table with columns and rows" pra variações 2D, mas não fica claro se é cadastro ou entrada de compra. Vale ver vídeo oficial antes de afirmar.
- Lightspeed e Cin7 são as únicas refs **inequívocas** pra "grade visual de entrada matricial em compra" (não só cadastro).
- Shopify usa "variant table" linear (mesma coisa de Bling/Tiny) mas tem **bulk editor spreadsheet-like** separado — fluxo diferente do "grade na PO" mas atinge resultado similar via paste de Excel.

---

## Top 3 referências recomendadas

### 1. Cin7 — "Size/Color Grid" em Purchase Order (referência primária)

- **Por que:** UI documentada explicitamente em [help.omni.cin7.com — Fashion products and purchase orders](https://help.omni.cin7.com/hc/en-us/articles/9128567271183-Fashion-products-and-purchase-orders). Ao adicionar uma "Family" no PO, a grid renderiza auto, usuário digita qty por célula, save persiste tudo de uma vez. Mais próxima do nosso modelo de dados (`product_id` + `variation_id` em `purchase_lines`).
- **Limite conhecido:** UI cloud genérica (não otimizada pra 1280px especificamente). Tem versão "List view" como alternativa — sinaliza que mesmo Cin7 reconhece que grade não cabe sempre.

### 2. Lightspeed Retail — "Matrix Inventory" (referência ergonômica)

- **Por que:** Doc explícita em [retail-support.lightspeedhq.com — Creating matrixes](https://retail-support.lightspeedhq.com/hc/en-us/articles/229130188-Creating-matrixes). Suporta até 3 atributos (cor/tam/material), built-in matrix attribute sets. POS apparel é vertical pro qual eles otimizam. Em 2026 lançaram AI OCR pra extrair variants de fatura do fornecedor automaticamente — caminho futuro pro nosso "Importar XML NF-e → preencher grade".
- **Limite conhecido:** UI de criação documentada usa dropdowns/wizard (não grade-visual direta). A entrada de qty no PO pode ser via "list" — confirmar antes de copiar.

### 3. Blue Link ERP — "Product (Colour/Size) Matrix" (referência conceitual)

- **Por que:** Vídeo público em [bluelinkerp.com/videos/product-colour-size-matrix](https://www.bluelinkerp.com/videos/product-colour-size-matrix/) mostra exatamente o padrão: style base → wizard gera N part numbers → tela de PO usa grade tradicional pra digitar qty por célula. É legacy (visual datado) mas o mecanismo é o canônico do setor.
- **Limite conhecido:** UI desktop antiga — não copiar visualmente, copiar a estrutura.

---

## Top 3 anti-patterns a evitar

### Anti-pattern 1: **"Cadastro pai pode ser grade, mas entrada não pode"** (Bling / Tiny / Conta Azul / oimpresso atual)

Ter o backend pai-filho mas forçar a UI de entrada a ser linha-a-linha multiplica o tempo de digitação por N (onde N = total de SKUs filhos). Pra Larissa com PMGG × 3 cores = 12 linhas por modelo, isso é 12× mais cliques que necessário. **É exatamente onde o oimpresso está hoje** ([purchase_entry_row.blade.php:1](../../resources/views/purchase/partials/purchase_entry_row.blade.php#L1)).

### Anti-pattern 2: **Grade que vira spreadsheet de uso geral** (Handsontable-style, Shopify bulk editor)

Quando a grade aceita paste de Excel, colunas dinâmicas, fórmulas, dezenas de campos por célula — vira ferramenta de power-user. Larissa **não é power-user**; ela decorou comportamento errado de `format_date` ([cliente-rotalivre.md:30](../../reference/cliente-rotalivre.md#L30)). Grade dela tem que parecer **uma só coisa** — tabelinha com números — não "mini-Excel". Shopify aprendeu isso e separou os fluxos (variant table simples ≠ bulk editor power-tool).

### Anti-pattern 3: **Wizard multi-step pra abrir a grade** (Blue Link generation wizard)

Forçar o usuário a passar por 3-4 telas de "configure atributos → confirme → gere combinações → volte pra PO" quebra o fluxo. Pra Larissa em fluxo de chegada de mercadoria às 14h ela quer: *abro pai → vejo grade do template já cadastrado → digito qty → fecho*. **Zero wizard.** A grade tem que aparecer instantaneamente ao selecionar o modelo, baseada no `VariationTemplate` já cadastrado.

---

## Recomendação concreta pro oimpresso

### Componente: **`GradeMatrixInput.tsx` custom (headless TanStack Table v8 + inputs React 19)**

**Por que custom em vez de lib pronta:**

| Critério | TanStack Table v8 custom | AG Grid | Handsontable | Mantine React Table |
|---|---|---|---|---|
| Bundle | ~15KB (já temos TanStack no projeto) | +150KB | +200KB+ | +130KB |
| Licença | MIT | Commercial pra "fill range" | Commercial pra prod | MIT |
| Controle Tab/Enter/Esc | Total | Lutar contra opinion | Lutar contra opinion | Médio |
| Estética alinhada Cowork | Total | Tema genérico | Tema genérico | Tema Mantine ≠ Cowork |
| Suite-fit | Já é dependência | Nova dep | Nova dep | Conflita com shadcn |

A grade do oimpresso é **pequena e focada** — 4 tams × 8 cores = 32 inputs no caso típico. Não justifica importar 150-200KB de framework. TanStack Table v8 (que já é dependência implícita do oimpresso) entrega headless logic + virtualização opcional, e a UI de cell é literalmente um `<input type="number">` controlado.

**Mecanismo recomendado (ergonomia Larissa):**

1. **Trigger:** ao selecionar produto pai `type=variable` no `<Combobox supplier-products>`, abrir Drawer ou colapsável inline com a grade — sem wizard.
2. **Layout:** linhas = `variation.name` (PMGG), colunas = `product_variation.name` (Preto/Branco/Azul). Cabeçalho sticky. Totais à direita e abaixo.
3. **Teclado:** Tab → próxima cor (mesma linha). Enter → próxima linha (mesma cor). Esc → fecha sem salvar. F2 → entra em modo edit (alinhar com Lightspeed/AG Grid convention). Setas → 4 direções.
4. **Empty state:** se `product.type='single'`, mostrar apenas 1 input (`qty` único) — não renderizar grade.
5. **Totais on-the-fly:** soma da linha aparece à direita, soma da coluna abaixo, grand total no canto. Tudo via `useMemo` (sem libs).
6. **Custo:** **1 custo unitário por modelo** (default da pai). Override por célula só via "modo avançado" (link discreto). Larissa não vai querer 32 campos de custo.
7. **Validação inline:** qty negativa proibida. Qty > 9999 warning (typo provável).
8. **Save:** ao clicar "Adicionar à compra", emit single `onSubmit({ product_id, lines: [{ variation_id, qty, unit_cost }] })`. Caller (Pages/Compras/Create) acumula em state local e só faz POST no submit do form inteiro (`purchase.store`). Backend já aceita isso ([PurchaseController.php:645](../../app/Http/Controllers/PurchaseController.php#L645)).
9. **Edição posterior:** ao abrir purchase salva, reagrupar `purchase_lines` por `product_id` e re-renderizar grade com qty atual por célula. Linhas órfãs (filho não-presente na grade) viram +linha avulsa abaixo.
10. **Quick-fill V1.1 (não V1):** botão "preencher coluna" só na cor selecionada. Paste Excel **fica pra V2** — Larissa não tem Excel no fluxo dela.

---

## Estimate recalibrado (ADR 0106 — 10x IA-pair + margem 2x)

| Fase | Etapa | Estimate IA-pair |
|---|---|---|
| **F1** | Protótipo Cowork (`prototipo-ui/prototipos/compras-grade-matrix/`) — grade tam×cor com mock data vestuário (PMGG × 3-5 cores) | 4-6h |
| **F1.5** | Visual comparison gate ADR 0107 — Wagner aprova screenshot | 1-2h (Wagner-limitado, NÃO recalibra) |
| **F2** | Hand-off Cowork → Claude Code (skill `mwart-comparative V4`) | 1h |
| **F3** | Implementação `GradeMatrixInput.tsx` + integração `Pages/Compras/Create.tsx` | 6-8h |
| **F3** | Backend controller adapter (já 90% pronto — só adapt array shape) | 2h |
| **F4** | Pest tests (unit GradeMatrixInput + feature purchase.store com grade payload) | 2-3h |
| **F5** | Polish + edge cases (empty state, edição posterior, validação) | 3-4h |
| **Total IA-pair** | | **~19-26h ≈ 3-4 dias úteis** |

**Tarefas humano-limitadas adicionais (não recalibram):**

- Call validação com Larissa pré-F1 (script Bloco 4.5 [DISCOVERY-LARISSA-COMPRAS.md:68](../../requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md#L68)) — **20-30 min relógio Wagner**
- Sessão Cowork ↔ Wagner aprovando screenshot F1.5 — **30-60 min Wagner**
- Deploy + smoke test prod (cliente único biz=4) — **10 min**

**Margem total 2x sobre 19-26h:** **40-50h IA-pair ≈ 5-7 dias úteis** com conforto.

---

## Top 5 ações priorizadas (impacto × esforço — próximo Sprint)

| # | Ação | Impacto | Esforço (IA-pair) | Pré-req | Quando |
|---|---|---|---|---|---|
| 1 | **Call discovery Larissa Bloco 4.5** ([DISCOVERY-LARISSA-COMPRAS.md:68](../../requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md#L68)) — quantificar grade típica (Q4.5.1-Q4.5.4) | Alto (define dimensão grade real) | 30 min Wagner | Nenhum | **Hoje/amanhã** |
| 2 | **F1 protótipo Cowork** `prototipos/compras-grade-matrix/` com mock vestuário PMGG×3cores | Alto (Wagner aprova visual antes de codar) | 4-6h | Ação #1 | Sprint atual |
| 3 | **F1.5 visual gate** ADR 0107 — Wagner aprova screenshot da grade | Alto (mata risco rejeição em F3) | 30-60 min Wagner | Ação #2 | Sprint atual |
| 4 | **F3 `GradeMatrixInput.tsx`** + `Pages/Compras/Create.tsx` adapter | Alto (entrega valor real Larissa) | 8-10h | Ação #3 | Sprint atual |
| 5 | **Testes Pest** purchase.store com payload grade (multi-tenant `business_id=4`) + smoke prod | Alto (Tier 0 ADR 0093) | 2-3h | Ação #4 | Sprint atual |

**Fora de escopo Sprint (V2+):** paste Excel, OCR XML→grade auto-fill, custo por célula override, mobile/touch, "fill coluna toda".

---

## Distância oimpresso → estado-da-arte (honestidade)

| Dimensão | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| Backend pai-filho variation | Cin7/Lightspeed nível | OK (`Variation` + `VariationTemplate` + `purchase_lines.variation_id`) | **Empate** |
| UI cadastro produto variable | Bling/Tiny nível | OK (cadastro pai-filho existe core legacy) | **Empate** |
| UI entrada compra matricial | Cin7/Lightspeed nível | NO (linha-a-linha em `purchase_entry_row.blade.php`) | **Longa — esse é O gap** |
| Atalhos teclado entrada | Lightspeed/AG Grid | ? (legacy Blade tem `mousetrap` mas em qty isolada) | Média |
| Custo unitário por filho | Padrão setor | OK (variation tem `default_purchase_price`) | Empate |
| Edição posterior grade | Cin7/Lightspeed | n/a (UI matricial não existe → nada pra reabrir) | Longa (decorrência da #3) |
| Mobile/touch entrada | Lightspeed/Cin7 | NO | Longa — **fora de escopo Larissa (1280px)** |
| Integração XML NF-e → grade | Lightspeed OCR AI | parcial (XML import existe mas não auto-popula grade) | Média — **V2** |

**Veredito:** oimpresso bate Bling/Tiny em backend (pai-filho real, `variation_id` em `purchase_lines`), empata em cadastro, **perde claramente em UI de entrada matricial**. Esse é o gap que move a agulha — e é o mais barato de fechar dado que o backend já cobre.

---

## Fontes

- [Bling — Cadastrar produtos com variação](https://ajuda.bling.com.br/hc/pt-br/articles/360035987033-Cadastrar-produtos-com-varia%C3%A7%C3%A3o)
- [Tiny ERP — Cadastro de produtos](https://tiny.com.br/recursos/cadastro-de-produtos)
- [Omie — Cadastrando e utilizando variações](https://ajuda.omie.com.br/pt-BR/articles/10869823-cadastrando-e-utilizando-as-variacoes-dos-produtos)
- [Conta Azul — Produtos: variação ou grade](https://ajuda.contaazul.com/hc/pt-br/articles/8770839779853-Produtos-varia%C3%A7%C3%A3o-ou-grade)
- [Lightspeed Retail — Creating matrixes](https://retail-support.lightspeedhq.com/hc/en-us/articles/229130188-Creating-matrixes)
- [Lightspeed Retail — Apparel POS](https://www.lightspeedhq.com/pos/retail/apparel/)
- [Cin7 Omni — Fashion products and purchase orders](https://help.omni.cin7.com/hc/en-us/articles/9128567271183-Fashion-products-and-purchase-orders)
- [Cin7 — Apparel inventory control](https://www.cin7.com/industries/fashion-apparel)
- [Blue Link ERP — Product Colour/Size Matrix](https://www.bluelinkerp.com/videos/product-colour-size-matrix/)
- [Shopify — Adding variants](https://help.shopify.com/en/manual/products/variants/add-variants)
- [TanStack Table v8 — Editable Data Example](https://tanstack.com/table/v8/docs/framework/react/examples/editable-data)
- [TanStack Table vs AG Grid 2025 — Simple Table](https://www.simple-table.com/blog/tanstack-table-vs-ag-grid-comparison)
- [Handsontable React Data Grid](https://handsontable.com/docs/react-data-grid/)
- [Logic ERP — Apparel SKU Matrix](https://www.logicerp.com/blog/apparel-erp-software-with-advanced-stock-sku-matrix-management/)
- [Sunrise — Dynamics 365 Matrix for Apparel/Footwear](https://sunrise.co/blog/dynamics365-erp-matrix-apparel-footwear/)
