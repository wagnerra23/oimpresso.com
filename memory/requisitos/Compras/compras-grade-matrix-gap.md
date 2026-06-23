---
tela: GradeMatrixInput (bloco "Adicionar item à compra" — NÃO é tela completa)
prototipo: prototipo-ui/prototipos/compras-grade-matrix/ (page.jsx · page.css · NOTES.md)
tela_viva_alvo_no_prompt: resources/js/Pages/Compras/Index.tsx
tela_viva_real_equivalente: resources/js/Pages/Compras/components/GradeMatrixInput.tsx (componente órfão) + resources/js/Pages/Purchase/Create.tsx (form real, ainda linha-a-linha)
paridade_atual: "componente 80% · integração viva 0%"
gerado_em: 2026-06-23
governanca: READ-ONLY map (Fase 1 aplicar-prototipo). Nenhum código tocado. Charter /compras v2 declara GradeMatrixInput inline COMO ANTI-HOOK no cockpit (vive em Purchase/Create ou futuro Compras/Create).
related_adrs: [104, 107, 114, 93, 149]
related_us: [US-COM-005]
---

# GAP-SPEC — GradeMatrixInput (compra matricial tam × cor)

## Correção de alvo (importante)

O prompt aponta `Pages/Compras/Index.tsx` como tela viva. **O protótipo NÃO corresponde a essa tela.**
O protótipo `compras-grade-matrix` é o **bloco "Adicionar item à compra"** (uma grade tam×cor que vive DENTRO de um form de criar/editar compra) — não é a tela de listagem. O NOTES.md diz textualmente: _"Não é tela completa de Compras — é só o BLOCO 'Adicionar item à compra'"_.

`Compras/Index.tsx` é um **cockpit de LISTAGEM** (KPIs + tabela paginada + drawer). Comparar a grade contra ela daria 0% por serem artefatos diferentes — seria falso-gap. Por isso o alvo real do protótipo é:

1. **`Pages/Compras/components/GradeMatrixInput.tsx`** — já existe (F3 traduzido), mas é **órfão** (importado por NENHUMA tela).
2. **`Pages/Purchase/Create.tsx`** — form de compra real e vivo, que ainda faz entrada **linha-a-linha** (1 `variation_id` por linha), o paradigma que o protótipo quer substituir.

## Estado consolidado

| Camada | Estado |
|---|---|
| Protótipo F1 (`page.jsx`) | Pronto. Aprovado no gate F1.5 (Wagner 2026-05-21). |
| Componente vivo (`GradeMatrixInput.tsx`) | Existe, headless, ~80% do protótipo. **Não está plugado em lugar nenhum.** |
| Integração no form de compra | **Inexistente.** `Purchase/Create.tsx` usa entrada linha-a-linha legada. |
| Backend (grade de variações product→matriz) | **Inexistente.** Componente exige `cellVariationMap` (tam×cor → variation_id) que nenhum endpoint produz hoje. |

---

## Tabela de gaps por PARTE

| Parte | O que mudou/falta | Por quê | Esforço | Risco | Ação |
|---|---|---|---|---|---|
| **Header / seleção de modelo** | Protótipo tem `<select>` de modelo + custo unitário + botões "Limpar / Adicionar (badge un · R$)". Componente vivo **não tem** seletor de modelo nem campo custo nem botões — é só a tabela. Esperado: esses controles vêm do CALLER (form), não do componente. | Componente foi feito headless de propósito (props `rows/cols/cellVariationMap/unitCost/onChange/onCancel`). O caller (`Purchase/Create.tsx`) é quem deveria montar seletor de produto-pai + custo + botão adicionar. Hoje NÃO existe caller. | M | backend (precisa endpoint que dado um product retorne sizes/colors/variationMap) | Definir o caller (Purchase/Create OU Compras/Create) e construir os controles ao redor da grade. |
| **Grade / matriz (núcleo)** | Paridade alta. Componente replica: linhas=tam, colunas=cor, Σ linha, Σ coluna (tfoot), grand total, células input. **Difere visualmente**: protótipo usa tokens `--gmi-*` (paper bege ERP, swatch HEX da cor, célula verde quando >0); componente usa Tailwind `stone-*` puro, **sem swatch HEX** e **sem highlight verde de célula preenchida**. | Tradução F3 simplificou a paleta pro design-system Tailwind do app (decisão válida), mas perdeu 2 affordances de UX do protótipo: o swatch de cor (ajuda Larissa a identificar cor visualmente) e o feedback verde "essa célula tem valor". | P | visual | Decidir se reintroduz swatch HEX + highlight verde (`primary`/`emerald`) — barato, alto valor pra Larissa não-técnica. |
| **Células / edição inline** | Paridade alta. Ambos: input numérico por célula, navegação teclado Tab/Shift+Tab/Enter/setas, validação 0..9999. **Diferenças**: (a) protótipo `Esc` = LIMPA a grade inteira; componente `Esc` = chama `onCancel` (semântica diferente). (b) componente trava célula sem `variationId` (`!hasVariation` → disabled cinza) — protótipo não tem esse conceito (mock sempre completo). (c) componente faz autofocus 1ª célula vazia; protótipo foca (0,0). | A semântica de `Esc` divergiu na tradução — é uma decisão a confirmar com Wagner/Larissa (limpar vs cancelar). O lock de célula sem SKU é melhoria do componente (real-world: nem toda combinação tam×cor existe). | P | visual + UX (confirmar Esc) | Confirmar comportamento de Esc no F2. Manter lock de célula (melhoria boa). |
| **Quick-fill (2× clique col-head)** | Protótipo: duplo-clique no header da coluna abre `prompt()` e preenche a coluna toda. Componente vivo **não tem** quick-fill. | Feature catalogada como "V1.1 Cin7" no NOTES.md; não foi traduzida no scaffold. Perda de produtividade pra Larissa (preencher coluna inteira de uma cor). | P-M | visual (sem `prompt()`; usar input/popover) | Backlog V1.1 — implementar com UI própria (não `prompt()` nativo). |
| **Totais on-the-fly** | Paridade total. Ambos `useMemo` recalcula Σ linha + Σ col + grand. Componente mostra **valor R$** no grand só se `unitCost>0` (igual protótipo). | — | — | — | Nada a fazer. |
| **Empty state (single)** | Protótipo: se modelo single (sem variação), renderiza input gigante "Quantidade". Componente: se `rows.length===0 \|\| cols.length===0`, mostra texto "não tem variações, use quantidade simples no form acima" (**não** renderiza input gigante). | Tradução delegou o caso single ao caller (o form já tem campo qty simples), o que é mais limpo arquiteturalmente. Diferença intencional, aceitável. | — | — | Aceitar divergência (componente delega ao form). |
| **Footer / barra de atalhos** | Paridade boa. Ambos têm barra de `<kbd>`. Protótipo lista 6 atalhos (inclui Esc=limpar, 2×clique=preencher coluna); componente lista 4 (Tab/Enter/Esc/setas) — coerente com features que tem. Protótipo tem ainda **batch hint** ("N linhas adicionadas") + `<details>` debug payload; componente não (é stateless, delega ao caller). | Batch/preview é responsabilidade do caller (form acumula linhas e mostra a lista). Divergência intencional. | — | — | Caller mostra o batch acumulado. |
| **INTEGRAÇÃO (o gap real)** | **`GradeMatrixInput.tsx` não é importado por nenhuma tela.** O form vivo `Purchase/Create.tsx` adiciona itens **linha-a-linha** (`adicionarLinhaVazia` → 1 `variation_id` por linha). A grade matricial nunca substituiu esse fluxo. | Wave 4.5 (US-COM-005) entregou só o scaffold do componente. F3 de integração + F4 (Pest) + F5 (deploy/smoke) **não foram feitos**. Sem caller + sem backend, o protótipo não tem efeito no produto vivo. | **G** | **backend (alto)** + governança | Decidir caller (charter C1 manda Purchase/Create), criar endpoint de grade de variações, plugar o componente, acumular em `purchases[]`. |
| **BACKEND — grade de variações** | Não existe endpoint que, dado um product-pai vestuário, retorne a matriz {sizes[], colors[], cellVariationMap: "tam__cor"→variation_id, unitCost}. `Purchase/Create.tsx` só tem `variation_id` solto por linha. | UltimatePOS modela variação como `Variation` (filho de `Product`/`ProductVariation`). A grade tam×cor precisa **derivar 2 eixos** das variações filhas — o backend hoje devolve lista flat, não matriz. NOTES.md afirma "backend pronto: app/Variation.php + purchase_lines.variation_id", mas isso cobre só o SAVE (linha tem variation_id); **não cobre o READ matricial** que alimenta `cellVariationMap`. | **G** | **backend + Tier 0** (queries scoped `business_id`; ADR 0093) | Especificar e construir endpoint `GET grade de variações do produto` com isolamento multi-tenant. Validar se o modelo de dados Larissa (biz=4) tem 2 eixos consistentes (atributo Tamanho + atributo Cor). |
| **GOVERNANÇA** | Charter `/compras` v2 lista **GradeMatrixInput inline no cockpit como ANTI-HOOK** (drift). O destino canônico é `Purchase/Create.tsx` (C1) ou um futuro `Compras/Create.tsx` vertical-específico (que também é anti-hook até review trigger da ADR C1 ativar). | Aplicar a grade na tela ERRADA (Index cockpit) viola a charter. Criar `Compras/Create.tsx` exige acionar review trigger #1 da ADR `compras-purchase-convergencia-c1` antes. | — | governança (bloqueante) | Antes de qualquer F3 de integração: decidir caller via ADR/charter. NÃO plugar no cockpit Index. |

---

## Ordem sugerida (se Wagner aprovar evolução)

1. **Decisão de governança (caller)** — Purchase/Create.tsx (default C1) vs novo Compras/Create.tsx (exige review trigger ADR C1). Bloqueante. Esforço P (decisão).
2. **Backend — endpoint de grade de variações** (matriz tam×cor → variation_id, scoped business_id). Bloqueante pro resto. Esforço G, risco backend+Tier0.
3. **Integração F3** — plugar `GradeMatrixInput` no caller, acumular linhas em `purchases[]`, custo + botão adicionar. Esforço M-G.
4. **Paridade visual barata** — reintroduzir swatch HEX da cor + highlight verde de célula preenchida. Esforço P, alto valor Larissa.
5. **Confirmar semântica Esc** (limpar grade vs cancelar) no F2 com Wagner/Larissa. Esforço P.
6. **Quick-fill coluna** (sem `prompt()` nativo) — V1.1. Esforço P-M.
7. **F4 Pest** (unit teclado/Σ + feature purchase.store com payload grade multi-tenant biz=4) + **F5** deploy/smoke real. Esforço M.

---

## Paridade % e veredito

- **Componente isolado vs protótipo:** ~80% (núcleo grade/teclado/totais traduzido; faltam swatch, highlight verde, quick-fill; Esc com semântica diferente).
- **Produto vivo (entregue ao usuário) vs protótipo:** ~0% — o componente é órfão, o form real ainda é linha-a-linha, e não há backend matricial.

**VEREDITO: LONGE (greenfield de integração).** O protótipo foi traduzido em um componente de boa qualidade, mas a entrega real (Wave 4.5 US-COM-005) parou no scaffold: falta caller, falta backend matricial, falta deploy. **Não é um gap visual** — é um gap de **integração + backend**. O caminho NÃO é "aplicar protótipo na tela viva Index" (isso é anti-hook na charter); é executar F3→F5 da US-COM-005 com decisão de governança do caller primeiro.

**Bloqueantes duros antes de qualquer código:** (1) decidir caller via charter/ADR C1; (2) especificar endpoint de grade de variações com isolamento Tier 0. Ambos exigem aprovação Wagner — não são auto-aplicáveis nesta fase.
