---
sessao: "00"
titulo: SINCRONIZAR Produto — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 4fa39eb8f007 (lida 2026-09-30 18:45 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/
---

# SINCRONIZAR Produto — playbook

> **Absorve, não duplica:** `cowork-inbox/produto/PEDIDO-CL-produto-trio.md` (F1 lido do espelho, sem sha) + `cowork-inbox/produto-telas-novas/PEDIDO-CL-produto-telas-novas.md` (tree `4235f8df6838`, 8 PRs) e os 3 charters/casos daquela pasta. Os dois pedidos citavam `prototipo-ui/contrato/` e `Pages/Product/` — **nenhum dos dois existe**: contratos moram em `governance/design/contracts/`, a Page real é `Pages/Produto/`.

## 1 · LEVANTAR — medido em `4fa39eb8f007`

**D4 (`Inertia::render`):** `ProductController` → `Produto/Index` (:350) · `Create` (:605) · `Show` (:854) · `Edit` (:969) · `SellingPrices` (:2150) · `BulkEdit` (:2555) · `StockHistory` (:2911); `ProdutoUnificadoController:180` → `Produto/Unificado/Index`. As 8 têm trio (`.tsx` + charter + casos) no `main`.

| rota do protótipo (`app.jsx`) | Page viva | estado | thread |
|---|---|---|---|
| `produtos` · `prod-lista` | `Produto/Unificado/Index.tsx` (73 KB) + `Produto/Index.tsx` | 🔵 produção React | 00 |
| `prod-novo` | `Produto/Create.tsx` · `Edit.tsx` | 🔵 | 00 |
| `prod-historico` | `Produto/StockHistory.tsx` | 🔵 | 00 |
| `prod-precos` | `Produto/SellingPrices.tsx` | 🔵 | 00 |
| `prod-massa` | `Produto/BulkEdit.tsx` | 🔵 | 00 |
| `prod-estoque` | sem Page (relatório em `ReportController`) | não medido | fora — é de Estoque |
| `prod-analises` | sem Page, sem blade | proposta F1 | D5 |
| cadastros de apoio (`produto-cadastros.jsx`) | Blade: `units` :613 · `brands` :609 · `taxonomies` :718 · `variation-templates` :720 · `selling-price-group` :1036 · `warranties` :1121 | 🟠 desenvolver | 02 · 03 |
| etiquetas (`produto-acoes.jsx`) | Blade: `/labels/show` :896 | 🟠 | 04 |
| importações | Blade: `/import-products` :994 · `/import-opening-stock` :1012 | 🟠 | 05 |
| atualizar preço | Blade: `update-product-price` :1032 | 🟠 | 06 |

**Achado A-P1 — aberto em 3 dos 6, não em 6.** ❌ **Retratação (revisão 2026-09-30 19:05, `89f32db43080`):** o pedido absorvido e a 1ª versão deste índice diziam 6 controllers sem permissão; minha busca inicial filtrava só nomes de permissão novos e não viu as que já existem. Lido no `main`: `SellingPriceGroupController` checa `product.create`/`product.update` (:40, :75, :90, :138, :160, :197, :229, :403) · `ImportProductsController` checa `product.create` (:53, :80) · `ImportOpeningStockController` checa `product.opening_stock` (:40, :72). **Abertos de fato (arquivo lido inteiro):** `VariationTemplateController` e `WarrantyController` (zero `can(`, só `business_id` da sessão) e `LabelsController` (zero `can(`; e `preview()` faz `Barcode::find($barcode_setting)` sem escopo de negócio). Se importação deve exigir mais que `product.create`, é D2, não A-P1.

**Alvos:** `governance/design/targets/` não tem nenhum `produto--*` ⇒ toda thread de tela depende de A1/A2.
**Contratos:** nenhum `*produto*` em `governance/design/contracts/`.

## 2 · Decisões

| id | pergunta | destrava |
|---|---|---|
| D1 | Criar as 3 permissões que faltam? Nomes propostos: `variation.*` · `warranty.*` · `print_labels.access` | 01 |
| D2 | Quem recebe o quê (Balcão = `*.view` + `print_labels.access`; Gerente = tudo menos `*.delete`) — e importação/preço em lote continuam só com `product.create`, ou ganham permissão de administrador? | 01 |
| D3 | Categoria (`taxonomies`, genérica por `category_type`): Page parametrizada ou uma por domínio? | 02 |
| D4 | Modelo de folha de etiqueta real (Pimaco? medidas) | 04 |
| D5 | Análises (reposição, margem, curva ABC, sem giro, duplicatas): entra no Produto, vai pro BI, ou sai? | — (sem thread até decidir) |
| D6 | Sugestão de compra: gera rascunho em Compras ou só exporta lista? | — |
| D7 | Rótulos reais dos campos personalizados 1–7 do piloto | 00 |

## 3 · Threads

```json
{
  "modulo": "Produto",
  "sha": "89f32db43080",
  "gerado": "2026-09-30",
  "absorve": [
    "prototipo-ui/cowork/Wagner/cowork-inbox/produto/PEDIDO-CL-produto-trio.md",
    "prototipo-ui/cowork/Wagner/cowork-inbox/produto-telas-novas/PEDIDO-CL-produto-telas-novas.md"
  ],
  "variaveis": {
    "PAGES": "resources/js/Pages",
    "CTRL": "app/Http/Controllers",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Criar as 3 permissões que faltam (variation, warranty, print_labels) e com que nomes?",
      "respondida": false,
      "destrava": [
        "01"
      ]
    },
    {
      "id": "D2",
      "pergunta": "Distribuição das permissões por papel (Balcão, Gerente, Admin)",
      "respondida": false,
      "destrava": [
        "01"
      ]
    },
    {
      "id": "D3",
      "pergunta": "Categoria: Page parametrizada ou uma por domínio?",
      "respondida": false,
      "destrava": [
        "02"
      ]
    },
    {
      "id": "D4",
      "pergunta": "Modelo real de folha de etiqueta",
      "respondida": false,
      "destrava": [
        "04"
      ]
    },
    {
      "id": "D5",
      "pergunta": "Análises de produto: entra, vai pro BI ou sai?",
      "respondida": false,
      "destrava": []
    },
    {
      "id": "D6",
      "pergunta": "Sugestão de compra: rascunho em Compras ou exportar lista?",
      "respondida": false,
      "destrava": []
    },
    {
      "id": "D7",
      "pergunta": "Rótulos dos campos personalizados 1–7 do piloto",
      "respondida": false,
      "destrava": [
        "00"
      ]
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 8 Pages vivas de Produto → protótipo",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "01-puxar-vivo.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/produto*.jsx",
        "prototipo-ui/cowork/Wagner/produtos-page.jsx"
      ],
      "nao_toca": [
        "${PAGES}/Produto/"
      ],
      "provas": [],
      "nota_provas": "read-only no main + build aqui: prova = _saida-00.md com o diff nos dois sentidos por tela"
    },
    {
      "id": "A1",
      "titulo": "ALVO produto--unificado--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "02-alvos.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "${ALVOS}/produto--unificado--index.*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/produto--unificado--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "A2",
      "titulo": "ALVO lote: produto--cadastros · --etiquetas · --importacao · --atualizar-preco",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "02-alvos.md",
      "prefixo": [
        "${ALVOS}/produto--cadastros--index.*",
        "${ALVOS}/produto--etiquetas--index.*",
        "${ALVOS}/produto--importacao--index.*",
        "${ALVOS}/produto--atualizar-preco--index.*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/produto--cadastros--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/produto--etiquetas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/produto--importacao--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/produto--atualizar-preco--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Permissões nos 3 controllers abertos (A-P1) — backend, sem UI",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "03-permissoes.md",
      "depende_decisoes": [
        "D1",
        "D2"
      ],
      "prefixo": [
        "${CTRL}/VariationTemplateController.php",
        "${CTRL}/WarrantyController.php",
        "${CTRL}/LabelsController.php",
        "database/seeders/",
        "tests/Feature/Produto/"
      ],
      "nao_toca": [
        "${PAGES}/",
        "routes/web.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/VariationTemplateController.php",
          "padrao": "->can('"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/WarrantyController.php",
          "padrao": "->can('"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/LabelsController.php",
          "padrao": "->can('"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Contratos das 4 telas novas (derivados do protótipo, nunca do .tsx)",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "04-contratos.md",
      "depende_threads": [
        "A2"
      ],
      "prefixo": [
        "${CONTRATOS}/produto-cadastros.contract.json",
        "${CONTRATOS}/produto-etiquetas.contract.json",
        "${CONTRATOS}/produto-importacao.contract.json",
        "${CONTRATOS}/produto-atualizar-preco.contract.json"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/produto-cadastros.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/produto-etiquetas.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/produto-importacao.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/produto-atualizar-preco.contract.json"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Produto/Cadastros — abas Unidades · Marcas · Categorias (permissões já existem)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "05-cadastros.md",
      "depende_threads": [
        "07"
      ],
      "depende_decisoes": [
        "D3"
      ],
      "prefixo": [
        "${PAGES}/Produto/Cadastros/",
        "${CTRL}/UnitController.php",
        "${CTRL}/BrandController.php",
        "${CTRL}/TaxonomyController.php"
      ],
      "nao_toca": [
        "${PAGES}/Produto/Unificado/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Produto/Cadastros/Index.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Produto/Cadastros/Index.charter.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Produto/Cadastros/Index.casos.md"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/UnitController.php",
          "padrao": "Inertia::render('Produto/Cadastros/Index'"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Produto/Cadastros — abas Variações · Grupos de preço · Garantias",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-cadastros.md",
      "depende_threads": [
        "01",
        "02"
      ],
      "prefixo": [
        "${PAGES}/Produto/Cadastros/",
        "${CTRL}/VariationTemplateController.php",
        "${CTRL}/SellingPriceGroupController.php",
        "${CTRL}/WarrantyController.php"
      ],
      "nao_toca": [
        "${PAGES}/Produto/Unificado/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/VariationTemplateController.php",
          "padrao": "Inertia::render('Produto/Cadastros/Index'"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/WarrantyController.php",
          "padrao": "Inertia::render('Produto/Cadastros/Index'"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Produto/Etiquetas (LabelsController → Inertia; /labels/preview segue render final)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "06-etiquetas.md",
      "depende_threads": [
        "01",
        "07"
      ],
      "depende_decisoes": [
        "D4"
      ],
      "prefixo": [
        "${PAGES}/Produto/Etiquetas/",
        "${CTRL}/LabelsController.php"
      ],
      "nao_toca": [
        "${CTRL}/BarcodeController.php"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Produto/Etiquetas/Index.tsx"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/LabelsController.php",
          "padrao": "Inertia::render('Produto/Etiquetas/Index'"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Produto/Importacao — produtos + estoque inicial, com conferência por linha",
      "dono": "CL",
      "vaga": 2,
      "prs": 2,
      "arquivo": "07-importacao.md",
      "depende_threads": [
        "01",
        "07"
      ],
      "prefixo": [
        "${PAGES}/Produto/Importacao/",
        "${CTRL}/ImportProductsController.php",
        "${CTRL}/ImportOpeningStockController.php"
      ],
      "nao_toca": [
        "${CTRL}/ProductController.php"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Produto/Importacao/Index.tsx"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/ImportProductsController.php",
          "padrao": "Inertia::render('Produto/Importacao/Index'"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/ImportOpeningStockController.php",
          "padrao": "Inertia::render('Produto/Importacao/Index'"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Produto/AtualizarPreco — exportar → devolver planilha (update-product-price)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "08-atualizar-preco.md",
      "depende_threads": [
        "01",
        "07"
      ],
      "prefixo": [
        "${PAGES}/Produto/AtualizarPreco/",
        "${CTRL}/SellingPriceGroupController.php"
      ],
      "nao_toca": [
        "${PAGES}/Produto/SellingPrices.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Produto/AtualizarPreco/Index.tsx"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/SellingPriceGroupController.php",
          "padrao": "Inertia::render('Produto/AtualizarPreco/Index'"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Menu Produtos: 5 itens novos filtrados por permissão; 6 antigos viram deep-link de aba",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "09-menu.md",
      "depende_threads": [
        "02",
        "03",
        "04",
        "05",
        "06"
      ],
      "prefixo": [
        "app/Http/Middleware/AdminSidebarMenu.php"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [],
      "nota_provas": "o caminho do menu não foi medido neste turno — fixar no _saida-08.md"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- `prod-estoque` (relatório de estoque) é de Estoque/Relatórios, não de Produto — fica fora até alguém ser dono.
- `prod-analises` não tem thread: depende de D5. Sem decisão, não vira pedido.
- `produto-preco-especial/produto-preco-especial.html` é um `.html` solto aqui no projeto, fora do host único. Sem rota no `app.jsx`; vai ser tratado do lado do Cowork, não pelo Code.
- Thread 08: o arquivo do menu (`AdminSidebarMenu.php`) é suposição — não li no turno.
