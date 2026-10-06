---
sessao: "00"
titulo: Cobrança Recorrente — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main aacb74f4df18 (lida 2026-10-05 20:58 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/recorrente/playbook/
---

# Cobrança Recorrente — playbook

> Sem roteiro anterior. Protótipo: `recurring` + `rb-*` (`window.CobrancaRecorrentePage`). Placa "hero" invertida no tema escuro já corrigida no protótipo (05/10) — produção na thread 03 do roteiro `tema-escuro`.

## 1 · LEVANTAR — medido em `aacb74f4df18`

| Page | trio | contrato |
|---|---|---|
| `RecurringBilling/Index` (75 KB) | ✅ | — |
| `RecurringBilling/Planos/Index` · `Create` · `Edit` | ✅ ✅ ✅ | — |
| `RecurringBilling/Faturas/Index` | ✅ | — |
| `RecurringBilling/Configuracoes/Index` | ✅ | — |

Trio completo nas 6. Falta medir e contratar.

## 2 · Decisões
Nenhuma.

## 3 · Threads

```json
{
  "modulo": "Cobrança Recorrente",
  "sha": "aacb74f4df18",
  "gerado": "2026-10-05",
  "decisoes": [],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 6 Pages vivas → cobranca-recorrente-page",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-puxar-vivo.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [],
      "nota_provas": "_saida-00 com mapa rota↔Page"
    },
    {
      "id": "A1",
      "titulo": "ALVO 4 Index: assinaturas · planos · faturas · configuracoes",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1-alvos.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "governance/design/targets/recorrente--*"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/recorrente--assinaturas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/recorrente--planos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/recorrente--faturas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/recorrente--configuracoes--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Contratos dos 4 Index",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "01-contratos.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "governance/design/contracts/recorrente-*.contract.json"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/recorrente-assinaturas.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/recorrente-planos.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/recorrente-faturas.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/recorrente-configuracoes.contract.json"
        }
      ]
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- Relação com Superadmin › Assinatura do negócio (`MinhaAssinatura`) — são coisas diferentes (cliente cobrando o cliente dele × o negócio pagando o oimpresso).
