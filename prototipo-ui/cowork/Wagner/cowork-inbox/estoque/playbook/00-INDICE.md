---
sessao: "00"
titulo: Estoque (Movimentações) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main aacb74f4df18 (lida 2026-10-05 20:58 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/estoque/playbook/
---

# Estoque (Movimentações) — playbook

> Sem roteiro anterior. Protótipo: `estoque` + `est-*` (`window.EstoquePage`). O histórico de estoque do produto (`Produto/StockHistory`) é do roteiro `produto`.

## 1 · LEVANTAR — medido em `aacb74f4df18`

| Page | trio | contrato |
|---|---|---|
| `StockAdjustment/Index` · `Create` | charter (1,1 KB cada) · **sem casos** | — |
| `StockTransfer/Index` · `Create` | charter (1,2 KB cada) · **sem casos** | — |

Os charters têm ~1 KB — provavelmente esqueleto. Não li os controllers neste turno.

## 2 · Decisões
Nenhuma.

## 3 · Threads

```json
{
  "modulo": "Estoque (Movimentações)",
  "sha": "aacb74f4df18",
  "gerado": "2026-10-05",
  "decisoes": [],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 4 Pages vivas → estoque-page (uma rota est-* por Page)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-puxar-vivo.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/estoque-page.jsx"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [],
      "nota_provas": "_saida-00 com mapa rota↔Page e divergências"
    },
    {
      "id": "01",
      "titulo": "Charters completos + casos das 4 Pages",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-trio.md",
      "prefixo": [
        "resources/js/Pages/StockAdjustment/",
        "resources/js/Pages/StockTransfer/",
        "tests/"
      ],
      "nao_toca": [
        "resources/js/Pages/StockAdjustment/Index.tsx",
        "resources/js/Pages/StockTransfer/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/StockAdjustment/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/StockAdjustment/Create.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/StockTransfer/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/StockTransfer/Create.casos.md"
        }
      ]
    },
    {
      "id": "A1",
      "titulo": "ALVO estoque--ajustes--index · estoque--transferencias--index",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1-alvos.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "governance/design/targets/estoque--*"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/estoque--ajustes--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/estoque--transferencias--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Contratos estoque-ajustes · estoque-transferencias (derivados do alvo)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "02-contratos.md",
      "depende_threads": [
        "A1",
        "01"
      ],
      "prefixo": [
        "governance/design/contracts/estoque-ajustes.contract.json",
        "governance/design/contracts/estoque-transferencias.contract.json"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/estoque-ajustes.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/estoque-transferencias.contract.json"
        }
      ]
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- Create (ajuste/transferência) fica sem alvo próprio até a 00 decidir se vira drawer.
