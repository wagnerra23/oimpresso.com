---
sessao: "00"
titulo: SINCRONIZAR Vendas (menu venda-*) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-29
base: wagnerra23/oimpresso.com@main a71c2f2d052f (lida 2026-09-29 18:43 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/
---

# SINCRONIZAR Vendas — playbook

> **Absorve, não duplica:** `cowork-inbox/venda-menu/PEDIDO-CL-venda-menu.md` (22/08, tree `6a8e45998ee5`) + os 7 charters/casos desta pasta. O PEDIDO propôs 7 PRs; **nenhum saiu em 38 dias** (medido abaixo). Este índice os transforma em threads com prova.

## 1 · LEVANTAR — 4 denominadores, medidos em `a71c2f2d052f`

**D4 (`Inertia::render` nos controllers):** `Sells/Index` (SellController:661) · `Sells/Caixa/Index` (:832) · `Sells/Create` (:1011 e SellPosController:283) · `Sells/Show` (:2587) · `Sells/Edit` (:3035) · `Sells/Drafts` (:3092) · `Sells/Quotations` (:3146) · `Sells/Subscriptions` (SellPosController:2519) · `Sells/CreateV3` (SellsV3Controller:44). **Nenhum** `render('SellReturn|Discount|Import|SalesOrder|Shipment|Pos…')` — as 7 telas do PEDIDO seguem Blade.

| rota do protótipo (`app.jsx`) | Page viva no `main` | estado | thread |
|---|---|---|---|
| `venda-todas` · `vendas` | `Sells/Index.tsx` (78 KB) + charter/casos | 🔵 produção React | 00 (puxar) |
| `venda-nova` · `-rascunho` · `-cotacao` · `venda-pdv` | `Sells/Create.tsx` (88 KB) · `CreateV3.tsx` (53 KB) | 🔵 | 00 |
| `venda-rascunhos` | `Sells/Drafts.tsx` | 🔵 | 00 |
| `venda-cotacoes` | `Sells/Quotations.tsx` | 🔵 | 00 |
| `venda-assinaturas` | `Sells/Subscriptions.tsx` | 🔵 | 00 |
| `venda-caixa` | `Sells/Caixa/Index.tsx` (14 KB) — declara em tela o pendente "Onda 6+1" | 🔵 parcial | 00 + 07 |
| `venda-pos` | — (Blade, `SellPosController@index`) | legado | 01 |
| `venda-remessas` | — (Blade, `SellController@shipments`) | legado | 02 |
| `venda-devolucoes` · `venda-devolver` | — (Blade, `SellReturnController`) | legado | 03 |
| `venda-descontos` | — (Blade, `DiscountController`) | legado | 04 |
| `venda-importar` | — (Blade, import sales) | legado | 05 |
| `venda-pedidos` | — (Blade, `SalesOrderController`, condicional `enable_sales_order`) | legado | 06 |

Não medido neste turno: o frescor fino das 9 telas 🔵 (se o vivo está à frente do protótipo em campo/copy) — é a thread 00.

## 2 · Threads — ordem · dono · prefixo · dependência

| # | thread | dono | prefixo | depende | vaga |
|---|---|---|---|---|---|
| 00 | PUXAR as 9 telas vivas → protótipo (diff 2 sentidos) | [CC] | `venda-*.jsx` do build | — | 1 |
| A1–A2 | ALVO em lote das 7 telas legadas (A1: pos · remessas · devolução · descontos · A2: importação · pedido · caixa) | [CL] | `governance/design/targets/vendas--*` | — | 1 |
| 01 | Lista de POS → `Sells/Pos/Index` | [CL] | `SellPosController@index` · `Pages/Sells/Pos/` | A1 | 2 |
| 02 | Remessas → `Sells/Shipments/Index` (modal de status vira drawer PT-02) | [CL] | `SellController@shipments` · `Pages/Sells/Shipments/` | A1 | 2 |
| 03 | Devolução → `SellReturn/{Index,Add}` | [CL] | `SellReturnController` · `Pages/SellReturn/` | A1 | 2 |
| 04 | Descontos → `Discount/Index` + FormRequest (A2) + permissão `discount.access` (A1) | [CL] | `DiscountController` · `Pages/Discount/` | A1 · D1 | 2 |
| 05 | Importação → `ImportSales/{Index,Preview}` | [CL] | controller de import · `Pages/ImportSales/` | A2 · D2 · D3 | 3 |
| 06 | Pedido de venda → `SalesOrder/Index` | [CL] | `SalesOrderController` · `Pages/SalesOrder/` | A2 | 3 |
| 07 | Caixa (turno): movimentos + conferência física | [CL] | `SellController@inertiaCaixa` · `Pages/Sells/Caixa/` | A2 · 00 | 3 |

Régua de tamanho (a mesma do Ponto §2-ter): tela Blade → Page nova sai em **1 PR ≤ 300 linhas** se a lista for simples (01 · 02 · 06); **2 PRs** (backend+payload → tela) se tiver fluxo (03 add · 04 FormRequest · 05 preview · 07 conferência). Âncora de implementação: irmã golden `Sells/Drafts.tsx` (lista) e `Sells/Show.tsx` (detalhe).

## 3 · Abertura de thread
Use `_SESSAO-FRIA.md`. `/onda venda-menu --thread NN`. Leia a ficha `01-telas-legadas.md` §NN + o charter/casos desta pasta da tela.

## 4 · VERIFICAR
Thread `feito` = `_saida-NN.md` **e** provas verdes lendo o `main`. Nada é "igual ao design" antes do T7.

## 6 · RESÍDUO — fila de decisão [W]
1. **D1** `discount.access` separa ver × editar? (hoje quem entra, exclui)
2. **D2** Importação grande vai para fila? (hoje `max_execution_time=0` no request)
3. **D3** Reverter lote apaga vendas — mantém hard delete ou passa a cancelar?

## 7 · Fonte da máquina
```json
{
  "modulo": "venda-menu",
  "sha": "a71c2f2d052f",
  "gerado": "2026-09-29",
  "absorve": [
    "prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/PEDIDO-CL-venda-menu.md"
  ],
  "variaveis": {
    "PAGES": "resources/js/Pages",
    "CTRL": "app/Http/Controllers",
    "ALVOS": "governance/design/targets"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "discount.access separa ver × editar?",
      "respondida": true,
      "destrava": [
        "04"
      ],
      "resposta": "sim: discount.access separa ver × editar",
      "fonte": "[CC] por delegação de [W] 2026-10-05 (\"o resto pode ser medido, escolha\")"
    },
    {
      "id": "D2",
      "pergunta": "Importação grande vai para fila?",
      "respondida": true,
      "destrava": [
        "05"
      ],
      "resposta": "sim: acima de 500 linhas vai pra fila, abaixo roda na hora",
      "fonte": "[CC] por delegação de [W] 2026-10-05 (\"o resto pode ser medido, escolha\")"
    },
    {
      "id": "D3",
      "pergunta": "Reverter lote de importação: hard delete ou cancelar?",
      "respondida": true,
      "destrava": [
        "05"
      ],
      "resposta": "cancelar (mantém a trilha e o vínculo fiscal), nunca apagar de vez",
      "fonte": "[CC] por delegação de [W] 2026-10-05 (\"o resto pode ser medido, escolha\")"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 9 telas vivas de Vendas → protótipo",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "01-telas-legadas.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/venda-*.jsx"
      ],
      "nao_toca": [
        "${PAGES}/Sells/"
      ],
      "provas": [],
      "nota_provas": "read-only + build: prova = _saida-00.md com o diff nos dois sentidos por tela"
    },
    {
      "id": "A1",
      "titulo": "ALVO lote 1: vendas--pos · --remessas · --devolucao · --descontos",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-telas-legadas.md",
      "prefixo": [
        "${ALVOS}/vendas--pos--index.*",
        "${ALVOS}/vendas--remessas--index.*",
        "${ALVOS}/vendas--devolucao--index.*",
        "${ALVOS}/vendas--descontos--index.*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/vendas--pos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "A2",
      "titulo": "ALVO lote 2: vendas--importacao · --pedidos · --caixa",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-telas-legadas.md",
      "prefixo": [
        "${ALVOS}/vendas--importacao--index.*",
        "${ALVOS}/vendas--pedidos--index.*",
        "${ALVOS}/vendas--caixa--index.*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/vendas--caixa--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Lista de POS → Sells/Pos/Index",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${CTRL}/SellPosController.php (@index)",
        "${PAGES}/Sells/Pos/"
      ],
      "nao_toca": [
        "${PAGES}/Sells/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/SellPosController.php",
          "padrao": "Inertia::render('Sells/Pos/Index'"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Remessas → Sells/Shipments/Index",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${CTRL}/SellController.php (@shipments)",
        "${PAGES}/Sells/Shipments/"
      ],
      "nao_toca": [
        "${PAGES}/Sells/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/SellController.php",
          "padrao": "Inertia::render('Sells/Shipments/Index'"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Devolução → SellReturn/{Index,Add}",
      "dono": "CL",
      "vaga": 2,
      "prs": 2,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${CTRL}/SellReturnController.php",
        "${PAGES}/SellReturn/"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/SellReturnController.php",
          "padrao": "Inertia::render('SellReturn/"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Descontos → Discount/Index + FormRequest + discount.access",
      "dono": "CL",
      "vaga": 2,
      "prs": 2,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A1"
      ],
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "${CTRL}/DiscountController.php",
        "app/Http/Requests/",
        "${PAGES}/Discount/"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/DiscountController.php",
          "padrao": "Inertia::render('Discount/"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Importação → ImportSales/{Index,Preview}",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A2"
      ],
      "depende_decisoes": [
        "D2",
        "D3"
      ],
      "prefixo": [
        "${CTRL}/ImportSalesController.php",
        "${PAGES}/ImportSales/"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/ImportSalesController.php",
          "padrao": "Inertia::render('ImportSales/"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Pedido de venda → SalesOrder/Index",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A2"
      ],
      "prefixo": [
        "${CTRL}/SalesOrderController.php",
        "${PAGES}/SalesOrder/"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "contem",
          "path": "${CTRL}/SalesOrderController.php",
          "padrao": "Inertia::render('SalesOrder/"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Caixa (turno): movimentos + conferência física",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "01-telas-legadas.md",
      "depende_threads": [
        "A2",
        "00"
      ],
      "prefixo": [
        "${CTRL}/SellController.php (@inertiaCaixa, rota /vendas/caixa web.php:794)",
        "${PAGES}/Sells/Caixa/"
      ],
      "nao_toca": [],
      "provas": [],
      "nota_provas": "_saida-07 com o pendente 'Onda 6+1' que Sells/Caixa/Index.tsx declara em tela, antes e depois"
    }
  ]
}
```
